<?php

namespace App\Services\StudentDiscounts;

use App\Models\Domain\ShopifyInstallation;
use App\Models\Domain\StudentDiscountCampaign;
use App\Models\Domain\StudentDiscountClaim;
use App\Services\Shopify\ShopifyAdminService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class StudentDiscountService
{
    public function __construct(
        private readonly ShopifyAdminService $shopify,
        private readonly StudentDiscountMailService $mail,
        private readonly StudentIdClassifier $classifier,
    ) {}

    public function campaign(ShopifyInstallation $installation): StudentDiscountCampaign
    {
        $origin = 'https://'.$installation->shopDomain;
        $campaign = StudentDiscountCampaign::query()->where('storeId', $installation->storeId)->first();
        if ($campaign) {
            $origins = $this->list($campaign->allowedOrigins);
            if (! in_array($origin, $origins, true)) {
                $origins[] = $origin;
                $campaign->forceFill(['allowedOrigins' => json_encode($origins), 'updatedAt' => now()])->save();
            }

            return $campaign;
        }

        return StudentDiscountCampaign::query()->create([
            'id' => (string) Str::uuid(), 'storeId' => $installation->storeId, 'enabled' => true,
            'educationDomains' => json_encode(['.edu', '.ac.uk', '.edu.au']),
            'description' => 'Verify your student status and get a student discount.',
            'consentLabel' => 'I agree to the Privacy Policy and Terms of Service.',
            'successMessage' => 'Your discount code is ready.',
            'terms' => 'Valid on eligible products only.', 'discountType' => 'PERCENTAGE',
            'allowedOrigins' => json_encode([$origin]), 'createdAt' => now(), 'updatedAt' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    public function publicCampaign(StudentDiscountCampaign $campaign): array
    {
        return collect($campaign->only([
            'title', 'description', 'schoolEmailLabel', 'schoolNameLabel', 'consentLabel', 'buttonLabel',
            'successTitle', 'successMessage', 'terms', 'accentColor', 'backgroundColor', 'schoolEmailEnabled',
            'universityEnabled', 'studentIdEnabled', 'discountType', 'discountValue', 'currencyCode',
        ]))->merge(['educationDomains' => $this->list($campaign->educationDomains)])->all();
    }

    /** @return array<string, mixed> */
    public function educationEmail(ShopifyInstallation $installation, StudentDiscountCampaign $campaign, string $email, string $ipHash): array
    {
        $emailNormalized = strtolower(trim($email));
        if (! $campaign->schoolEmailEnabled || ! $this->isEducationEmail($emailNormalized, $campaign)) {
            return ['ok' => true, 'eligible' => false, 'studentIdAvailable' => (bool) $campaign->studentIdEnabled];
        }
        $existing = StudentDiscountClaim::query()->where('campaignId', $campaign->id)
            ->where('emailNormalized', $emailNormalized)->where('status', 'ISSUED')
            ->where(fn ($query) => $query->whereNull('expiresAt')->orWhere('expiresAt', '>', now()))
            ->latest('createdAt')->first();
        if ($existing?->code) {
            return $this->approvedPayload($existing, true);
        }

        $claim = $this->newClaim($campaign, [
            'email' => $email, 'emailNormalized' => $emailNormalized,
            'schoolName' => Str::after($emailNormalized, '@'), 'verificationMethod' => 'EDUCATION_EMAIL',
            'ipHash' => $ipHash,
        ]);
        $this->issue($installation, $claim, $campaign);

        return $this->approvedPayload($claim->fresh());
    }

    /** @return array<string, mixed> */
    public function studentId(ShopifyInstallation $installation, StudentDiscountCampaign $campaign, string $email, string $fullName, UploadedFile $file, string $ipHash): array
    {
        $emailNormalized = strtolower(trim($email));
        $bytes = file_get_contents($file->getRealPath());
        if ($bytes === false) {
            throw new RuntimeException('The uploaded image could not be read.');
        }
        $path = $file->storeAs('student-discounts/'.$campaign->storeId, Str::uuid().'.'.$file->extension(), 'local');
        if (! $path) {
            throw new RuntimeException('The uploaded image could not be stored.');
        }

        $claim = DB::transaction(function () use ($campaign, $email, $emailNormalized, $fullName, $path, $ipHash) {
            StudentDiscountClaim::query()->where('campaignId', $campaign->id)
                ->where('emailNormalized', $emailNormalized)->where('status', 'PENDING')->whereNull('reviewedAt')
                ->update([
                    'status' => 'REJECTED', 'reviewedAt' => now(),
                    'reviewedBy' => 'System — superseded by a newer submission', 'reviewSource' => 'SYSTEM',
                    'error' => 'Automatically rejected because a newer application was submitted for the same email address.',
                    'updatedAt' => now(),
                ]);

            return $this->newClaim($campaign, [
                'email' => $email, 'emailNormalized' => $emailNormalized, 'fullName' => $fullName,
                'schoolName' => 'Student ID review', 'verificationMethod' => 'STUDENT_ID',
                'evidencePath' => $path, 'ipHash' => $ipHash,
            ]);
        });

        $review = $this->classifier->classify($campaign->storeId, $bytes, (string) $file->getMimeType());
        $claim->forceFill([
            'aiProvider' => $review['provider'], 'aiModel' => $review['model'],
            'aiConfidence' => $review['confidence'], 'aiReason' => $review['reason'], 'updatedAt' => now(),
        ])->save();

        if ($review['isStudentId'] && $review['confidence'] >= 0.8) {
            $claim->forceFill(['reviewedAt' => now(), 'reviewedBy' => 'AI', 'reviewSource' => 'AI'])->save();
            $this->issue($installation, $claim, $campaign);

            return $this->approvedPayload($claim->fresh());
        }

        return ['ok' => true, 'approved' => false, 'pending' => true, 'message' => 'Your application was submitted for manual review.'];
    }

    /** @return array{status: string, emailSent: bool} */
    public function review(ShopifyInstallation $installation, StudentDiscountClaim $claim, string $action, string $reviewer, ?string $reason = null): array
    {
        if ($claim->storeId !== $installation->storeId || $claim->status !== 'PENDING' || $claim->reviewedAt) {
            throw new RuntimeException('This application has already been processed.');
        }
        if ($action === 'REJECT') {
            $claim->forceFill([
                'status' => 'REJECTED', 'reviewedAt' => now(), 'reviewedBy' => $reviewer,
                'reviewSource' => 'SHOPIFY_ADMIN', 'error' => mb_substr($reason ?: 'Student ID review was not approved.', 0, 500),
                'updatedAt' => now(),
            ])->save();
            try {
                $this->mail->sendRejected($claim->storeId, $claim->email);
                $claim->forceFill(['emailDeliveryStatus' => 'SENT', 'emailSentAt' => now()])->save();

                return ['status' => 'REJECTED', 'emailSent' => true];
            } catch (Throwable $exception) {
                $claim->forceFill(['emailDeliveryStatus' => 'FAILED', 'error' => 'Rejection email failed: '.$exception->getMessage()])->save();

                return ['status' => 'REJECTED', 'emailSent' => false];
            }
        }

        $claim->forceFill(['reviewedAt' => now(), 'reviewedBy' => $reviewer, 'reviewSource' => 'SHOPIFY_ADMIN'])->save();
        $this->issue($installation, $claim, StudentDiscountCampaign::findOrFail($claim->campaignId));

        return ['status' => 'ISSUED', 'emailSent' => $claim->fresh()->emailDeliveryStatus === 'SENT'];
    }

    public function issue(ShopifyInstallation $installation, StudentDiscountClaim $claim, StudentDiscountCampaign $campaign): void
    {
        $prefix = trim(strtoupper((string) preg_replace('/[^A-Z0-9-]/', '', (string) $campaign->codePrefix)), '-');
        $code = $claim->code ?: ($prefix ?: 'STUDENT').'-'.strtoupper(Str::random(10));
        $expiresAt = $claim->expiresAt ?: now()->addDays((int) $campaign->validityDays);
        $target = $campaign->discountTarget ?: 'ALL_PRODUCTS';
        $items = match ($target) {
            'PRODUCTS' => ['products' => ['productsToAdd' => $campaign->discountProductIds ?: []]],
            'COLLECTIONS' => ['collections' => ['add' => $campaign->discountCollectionIds ?: []]],
            default => ['all' => true],
        };
        $value = $campaign->discountType === 'FIXED_AMOUNT'
            ? ['discountAmount' => ['amount' => number_format((float) $campaign->discountValue, 2, '.', ''), 'appliesOnEachItem' => false]]
            : ['percentage' => (float) $campaign->discountValue / 100];
        try {
            $input = [
                'title' => 'Student discount — '.$claim->emailNormalized, 'code' => $code,
                'startsAt' => now()->toIso8601String(), 'endsAt' => $expiresAt->toIso8601String(),
                'context' => ['all' => 'ALL'], 'customerGets' => ['value' => $value, 'items' => $items],
                'usageLimit' => max(1, (int) $campaign->usageLimit), 'appliesOncePerCustomer' => false,
                'combinesWith' => ['productDiscounts' => (bool) $campaign->combinesWithProduct, 'orderDiscounts' => (bool) $campaign->combinesWithOrder, 'shippingDiscounts' => (bool) $campaign->combinesWithShipping],
            ];
            if ($campaign->minimumSubtotal !== null) {
                $input['minimumRequirement'] = ['subtotal' => ['greaterThanOrEqualToSubtotal' => number_format((float) $campaign->minimumSubtotal, 2, '.', '')]];
            }
            $shopifyId = $claim->shopifyDiscountId ?: $this->shopify->createBasicDiscount($installation, $input);
            $claim->forceFill(['code' => $code, 'shopifyDiscountId' => $shopifyId, 'status' => 'ISSUED', 'expiresAt' => $expiresAt, 'error' => null, 'updatedAt' => now()])->save();
        } catch (Throwable $exception) {
            $claim->forceFill(['status' => 'FAILED', 'error' => mb_substr($exception->getMessage(), 0, 2000), 'updatedAt' => now()])->save();
            throw $exception;
        }

        try {
            $this->mail->sendApproved($claim->storeId, $claim->email, $code);
            $claim->forceFill(['emailSentAt' => now(), 'emailDeliveryStatus' => 'SENT'])->save();
        } catch (Throwable $exception) {
            $claim->forceFill(['emailDeliveryStatus' => 'FAILED', 'error' => 'Email delivery failed: '.mb_substr($exception->getMessage(), 0, 1900)])->save();
        }
    }

    /** @param array<string, mixed> $attributes */
    private function newClaim(StudentDiscountCampaign $campaign, array $attributes): StudentDiscountClaim
    {
        return StudentDiscountClaim::query()->create($attributes + [
            'id' => (string) Str::uuid(), 'storeId' => $campaign->storeId, 'campaignId' => $campaign->id,
            'status' => 'PENDING', 'expiresAt' => now()->addDays((int) $campaign->validityDays),
            'createdAt' => now(), 'updatedAt' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function approvedPayload(StudentDiscountClaim $claim, bool $existing = false): array
    {
        $sent = $claim->emailDeliveryStatus === 'SENT' || $claim->emailSentAt !== null;

        return ['ok' => true, 'eligible' => true, 'approved' => true, 'sent' => $sent, 'code' => $claim->code, 'existing' => $existing,
            'message' => $sent
                ? 'Your discount code is ready and has been sent to your email address. If you cannot find it, please check your spam or junk folder.'
                : 'Your application was approved, but the email could not be delivered. Use the discount code shown below at checkout.'];
    }

    private function isEducationEmail(string $email, StudentDiscountCampaign $campaign): bool
    {
        $domain = Str::after($email, '@');

        return collect($this->list($campaign->educationDomains))->contains(function ($suffix) use ($domain) {
            $configuredDomain = ltrim(strtolower(trim((string) $suffix)), '@.');

            return $configuredDomain !== ''
                && ($domain === $configuredDomain || str_ends_with($domain, '.'.$configuredDomain));
        });
    }

    /** @return list<string> */
    public function list(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value));
        }
        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? array_values(array_filter($decoded)) : array_values(array_filter(array_map('trim', explode(',', (string) $value))));
    }
}
