<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Domain\StudentDiscountClaim;
use App\Services\Credentials\CredentialService;
use App\Services\StudentDiscounts\StudentDiscountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShopifyStudentDiscountController extends Controller
{
    public function __construct(
        private readonly StudentDiscountService $discounts,
        private readonly CredentialService $credentials,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $installation = $request->attributes->get('shopifyInstallation');
        $section = $request->validate([
            'section' => ['nullable', 'in:reviews,discount-settings,email-settings,analytics,help'],
        ])['section'] ?? 'reviews';
        $payload = [
            'installation' => [
                'shop' => $installation->shopDomain,
                'status' => $installation->status,
            ],
        ];

        if ($section === 'reviews') {
            $payload['claims'] = $this->claims($installation->storeId);
        } elseif ($section === 'discount-settings') {
            $payload['campaign'] = $this->discounts->campaign($installation);
        } elseif ($section === 'email-settings') {
            $payload['smtp'] = $this->smtp($installation->storeId);
        } elseif ($section === 'analytics') {
            $query = StudentDiscountClaim::query()->where('storeId', $installation->storeId);
            $payload['analytics'] = [
                'issued' => (clone $query)->where('status', 'ISSUED')->count(),
                'failed' => (clone $query)->whereIn('status', ['FAILED', 'REJECTED'])->count(),
                'pending' => (clone $query)->where('status', 'PENDING')->count(),
                'total' => (clone $query)->count(),
            ];
        }

        return response()->json($payload);
    }

    /** @return array<int, array<string, mixed>> */
    private function claims(string $storeId): array
    {
        return StudentDiscountClaim::query()->where('storeId', $storeId)
            ->orderByRaw("CASE WHEN status = 'PENDING' THEN 0 ELSE 1 END")
            ->orderByDesc('createdAt')->limit(100)->get()->map(fn ($claim) => [
                'id' => $claim->id, 'email' => $claim->email, 'fullName' => $claim->fullName,
                'verificationMethod' => $claim->verificationMethod, 'code' => $claim->code,
                'status' => $claim->status, 'error' => $claim->error, 'reviewedBy' => $claim->reviewedBy,
                'aiProvider' => $claim->aiProvider, 'aiModel' => $claim->aiModel,
                'aiConfidence' => $claim->aiConfidence, 'aiReason' => $claim->aiReason,
                'createdAt' => optional($claim->createdAt)->toIso8601String(),
                'hasEvidence' => (bool) $claim->evidencePath,
            ])->values()->all();
    }

    /** @return array<string, mixed> */
    private function smtp(string $storeId): array
    {
        $smtpKeys = ['MAIL_HOST', 'MAIL_PORT', 'MAIL_SCHEME', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME'];
        $storeConfigured = DB::table('StoreConfig')->where('storeId', $storeId)->whereIn('key', $smtpKeys)->pluck('key');
        $smtp = $this->credentials->many($smtpKeys, $storeId);

        return [
            'usesStoreOverride' => $storeConfigured->isNotEmpty(),
            'configured' => collect(['MAIL_HOST', 'MAIL_USERNAME', 'MAIL_PASSWORD'])->every(fn ($key) => ! empty($smtp[$key])),
            'configuredKeys' => $storeConfigured->values(),
            'host' => $smtp['MAIL_HOST'] ?? 'smtp.gmail.com',
            'port' => (int) ($smtp['MAIL_PORT'] ?? 465),
            'scheme' => $smtp['MAIL_SCHEME'] ?? 'smtps',
            'username' => $smtp['MAIL_USERNAME'] ?? '',
            'fromAddress' => $smtp['MAIL_FROM_ADDRESS'] ?? '',
            'fromName' => $smtp['MAIL_FROM_NAME'] ?? 'Macfox Student Discount',
            'hasStorePassword' => $storeConfigured->contains('MAIL_PASSWORD'),
        ];
    }

    public function update(Request $request): JsonResponse
    {
        $installation = $request->attributes->get('shopifyInstallation');
        $campaign = $this->discounts->campaign($installation);
        $data = $request->validate([
            'enabled' => ['sometimes', 'boolean'], 'codePrefix' => ['sometimes', 'string', 'regex:/^[A-Za-z0-9-]{1,12}$/'],
            'discountType' => ['sometimes', 'in:PERCENTAGE,FIXED_AMOUNT'], 'discountValue' => ['sometimes', 'numeric', 'gt:0'],
            'discountTarget' => ['sometimes', 'in:ALL_PRODUCTS,PRODUCTS,COLLECTIONS'],
            'discountProductIds' => ['sometimes', 'array', 'max:100'],
            'discountProductIds.*' => ['string', 'regex:/^gid:\/\/shopify\/Product\/\d+$/'],
            'discountCollectionIds' => ['sometimes', 'array', 'max:100'],
            'discountCollectionIds.*' => ['string', 'regex:/^gid:\/\/shopify\/Collection\/\d+$/'],
            'usageLimit' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'combinesWithProduct' => ['sometimes', 'boolean'], 'combinesWithOrder' => ['sometimes', 'boolean'],
            'combinesWithShipping' => ['sometimes', 'boolean'],
        ]);
        $discountType = $data['discountType'] ?? $campaign->discountType;
        $discountValue = (float) ($data['discountValue'] ?? $campaign->discountValue);
        if ($discountType === 'PERCENTAGE' && $discountValue > 100) {
            throw ValidationException::withMessages([
                'discountValue' => '百分比折扣不能超过 100%。',
            ]);
        }
        $target = $data['discountTarget'] ?? $campaign->discountTarget;
        if ($target === 'PRODUCTS' && empty($data['discountProductIds'] ?? $campaign->discountProductIds)) {
            throw ValidationException::withMessages(['discountProductIds' => '请至少选择一个产品。']);
        }
        if ($target === 'COLLECTIONS' && empty($data['discountCollectionIds'] ?? $campaign->discountCollectionIds)) {
            throw ValidationException::withMessages(['discountCollectionIds' => '请至少选择一个产品系列。']);
        }
        $campaign->forceFill($data + ['updatedAt' => now()])->save();

        return response()->json(['ok' => true, 'campaign' => $campaign->fresh()]);
    }

    public function updateSmtp(Request $request): JsonResponse
    {
        $installation = $request->attributes->get('shopifyInstallation');
        $data = $request->validate([
            'host' => ['required', 'string', 'max:255'], 'port' => ['required', 'integer', 'between:1,65535'],
            'scheme' => ['required', 'in:smtp,smtps'], 'username' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:1000'], 'fromAddress' => ['required', 'email', 'max:255'],
            'fromName' => ['required', 'string', 'max:255'],
        ]);
        $hasStorePassword = DB::table('StoreConfig')->where([
            'storeId' => $installation->storeId,
            'key' => 'MAIL_PASSWORD',
        ])->exists();
        if (! $hasStorePassword && empty($data['password'])) {
            return response()->json([
                'message' => '创建店铺专用 SMTP 配置时必须填写密码或应用专用密码。',
            ], 422);
        }
        $values = ['MAIL_HOST' => $data['host'], 'MAIL_PORT' => (string) $data['port'], 'MAIL_SCHEME' => $data['scheme'],
            'MAIL_USERNAME' => $data['username'], 'MAIL_FROM_ADDRESS' => $data['fromAddress'], 'MAIL_FROM_NAME' => $data['fromName']];
        if (! empty($data['password'])) {
            $values['MAIL_PASSWORD'] = $data['password'];
        }
        foreach ($values as $key => $value) {
            DB::table('StoreConfig')->updateOrInsert(['storeId' => $installation->storeId, 'key' => $key], [
                'value' => Crypt::encryptString($value), 'encrypted' => true, 'updatedAt' => now(),
            ]);
            $this->credentials->forget($key, $installation->storeId);
        }

        return response()->json(['ok' => true]);
    }

    public function resetSmtp(Request $request): JsonResponse
    {
        $installation = $request->attributes->get('shopifyInstallation');
        $keys = ['MAIL_HOST', 'MAIL_PORT', 'MAIL_SCHEME', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME'];
        DB::table('StoreConfig')->where('storeId', $installation->storeId)->whereIn('key', $keys)->delete();
        foreach ($keys as $key) {
            $this->credentials->forget($key, $installation->storeId);
        }

        return response()->json(['ok' => true]);
    }

    public function review(Request $request, StudentDiscountClaim $claim): JsonResponse
    {
        $installation = $request->attributes->get('shopifyInstallation');
        $data = $request->validate(['action' => ['required', 'in:APPROVE,REJECT'], 'reason' => ['nullable', 'string', 'max:500']]);
        $session = $request->attributes->get('shopifySession');

        return response()->json(['ok' => true] + $this->discounts->review($installation, $claim, $data['action'], (string) ($session['sub'] ?? 'Shopify admin'), $data['reason'] ?? null));
    }

    public function evidence(Request $request, StudentDiscountClaim $claim): StreamedResponse
    {
        $installation = $request->attributes->get('shopifyInstallation');
        abort_unless($claim->storeId === $installation->storeId && $claim->evidencePath && Storage::disk('local')->exists($claim->evidencePath), 404);

        return Storage::disk('local')->response($claim->evidencePath, null, ['Cache-Control' => 'private, no-store']);
    }
}
