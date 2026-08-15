<?php

namespace App\Http\Controllers;

use App\Models\Domain\ShopifyInstallation;
use App\Models\Domain\StudentDiscountCampaign;
use App\Models\Domain\StudentDiscountClaim;
use App\Services\Credentials\CredentialService;
use App\Services\Stores\StoreContext;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StudentDiscountPluginController extends Controller
{
    public function __construct(private readonly CredentialService $credentials) {}

    public function __invoke(StoreContext $storeContext): Response
    {
        $storeId = $storeContext->id();
        $installation = ShopifyInstallation::query()->where('storeId', $storeId)->first();
        $campaign = StudentDiscountCampaign::query()->where('storeId', $storeId)->first();
        $claims = StudentDiscountClaim::query()
            ->where('storeId', $storeId)
            ->orderByRaw("CASE WHEN status = 'PENDING' THEN 0 ELSE 1 END")
            ->orderByDesc('createdAt')
            ->limit(100)
            ->get();
        $claimQuery = StudentDiscountClaim::query()->where('storeId', $storeId);

        $mailKeys = ['MAIL_HOST', 'MAIL_PORT', 'MAIL_SCHEME', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME'];
        $storeMailKeys = DB::table('StoreConfig')->where('storeId', $storeId)->whereIn('key', $mailKeys)->pluck('key')->all();
        $effectiveMail = $this->credentials->many($mailKeys, $storeId);
        $brandKeys = [
            'STUDENT_DISCOUNT_BRAND_NAME', 'STUDENT_DISCOUNT_LOGO_URL', 'STUDENT_DISCOUNT_SHOP_URL',
            'STUDENT_DISCOUNT_SUPPORT_URL', 'STUDENT_DISCOUNT_INSTAGRAM_URL', 'STUDENT_DISCOUNT_FACEBOOK_URL',
            'STUDENT_DISCOUNT_TIKTOK_URL', 'STUDENT_DISCOUNT_YOUTUBE_URL',
        ];
        $brand = $this->credentials->manyStore($brandKeys, $storeId);

        return Inertia::render('Plugins/StudentDiscount', [
            'store' => $storeContext->store(),
            'installation' => [
                'installed' => $installation !== null && $installation->status === 'INSTALLED',
                'status' => $installation === null ? 'NOT_INSTALLED' : $installation->status,
                'shopDomain' => $installation?->shopDomain,
                'installedAt' => optional($installation?->installedAt)->toIso8601String(),
                'uninstalledAt' => optional($installation?->uninstalledAt)->toIso8601String(),
            ],
            'campaign' => $campaign?->toArray(),
            'claims' => $claims->map(fn (StudentDiscountClaim $claim) => [
                'id' => $claim->id,
                'fullName' => $claim->fullName,
                'email' => $claim->email,
                'verificationMethod' => $claim->verificationMethod,
                'hasEvidence' => filled($claim->evidencePath),
                'code' => $claim->code,
                'status' => $claim->status,
                'aiConfidence' => $claim->aiConfidence,
                'aiReason' => $claim->aiReason,
                'emailDeliveryStatus' => $claim->emailDeliveryStatus,
                'createdAt' => optional($claim->createdAt)->toIso8601String(),
            ]),
            'metrics' => [
                'issued' => (clone $claimQuery)->where('status', 'ISSUED')->count(),
                'failed' => (clone $claimQuery)->whereIn('status', ['FAILED', 'REJECTED'])->count(),
                'pending' => (clone $claimQuery)->where('status', 'PENDING')->count(),
                'total' => (clone $claimQuery)->count(),
            ],
            'smtp' => [
                'source' => $storeMailKeys === [] ? 'system' : 'store',
                'configured' => filled($effectiveMail['MAIL_HOST'] ?? null)
                    && filled($effectiveMail['MAIL_USERNAME'] ?? null)
                    && filled($effectiveMail['MAIL_PASSWORD'] ?? null),
                'host' => $effectiveMail['MAIL_HOST'] ?? '',
                'port' => $effectiveMail['MAIL_PORT'] ?? '465',
                'scheme' => $effectiveMail['MAIL_SCHEME'] ?? 'smtps',
                'username' => $effectiveMail['MAIL_USERNAME'] ?? '',
                'password' => $effectiveMail['MAIL_PASSWORD'] ?? '',
                'fromAddress' => $effectiveMail['MAIL_FROM_ADDRESS'] ?? '',
                'fromName' => $effectiveMail['MAIL_FROM_NAME'] ?? 'Macfox',
                'hasPassword' => filled($effectiveMail['MAIL_PASSWORD'] ?? null),
            ],
            'branding' => [
                'brandName' => $brand['STUDENT_DISCOUNT_BRAND_NAME'] ?? '',
                'logoUrl' => $brand['STUDENT_DISCOUNT_LOGO_URL'] ?? null,
                'shopUrl' => $brand['STUDENT_DISCOUNT_SHOP_URL'] ?? '',
                'supportUrl' => $brand['STUDENT_DISCOUNT_SUPPORT_URL'] ?? '',
                'instagramUrl' => $brand['STUDENT_DISCOUNT_INSTAGRAM_URL'] ?? '',
                'facebookUrl' => $brand['STUDENT_DISCOUNT_FACEBOOK_URL'] ?? '',
                'tiktokUrl' => $brand['STUDENT_DISCOUNT_TIKTOK_URL'] ?? '',
                'youtubeUrl' => $brand['STUDENT_DISCOUNT_YOUTUBE_URL'] ?? '',
            ],
        ]);
    }
}
