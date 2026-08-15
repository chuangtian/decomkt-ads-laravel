<?php

namespace App\Http\Controllers;

use App\Models\Domain\ShopifyInstallation;
use App\Models\Domain\StudentDiscountCampaign;
use App\Models\Domain\StudentDiscountClaim;
use App\Services\Credentials\CredentialService;
use App\Services\Shopify\ShopifyAdminService;
use App\Services\Stores\StoreContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class StudentDiscountController extends Controller
{
    private const MAIL_KEYS = [
        'MAIL_HOST', 'MAIL_PORT', 'MAIL_SCHEME', 'MAIL_USERNAME', 'MAIL_PASSWORD',
        'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME',
    ];

    private const BRAND_KEY_MAP = [
        'brandName' => 'STUDENT_DISCOUNT_BRAND_NAME',
        'shopUrl' => 'STUDENT_DISCOUNT_SHOP_URL',
        'supportUrl' => 'STUDENT_DISCOUNT_SUPPORT_URL',
        'instagramUrl' => 'STUDENT_DISCOUNT_INSTAGRAM_URL',
        'facebookUrl' => 'STUDENT_DISCOUNT_FACEBOOK_URL',
        'tiktokUrl' => 'STUDENT_DISCOUNT_TIKTOK_URL',
        'youtubeUrl' => 'STUDENT_DISCOUNT_YOUTUBE_URL',
    ];

    private const LOGO_URL_KEY = 'STUDENT_DISCOUNT_LOGO_URL';

    private const LOGO_PATH_KEY = 'STUDENT_DISCOUNT_LOGO_PATH';

    public function update(Request $request, StoreContext $storeContext): RedirectResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean']]);
        StudentDiscountCampaign::query()->where('storeId', $storeContext->id())->update(['enabled' => $data['enabled'], 'updatedAt' => now()]);

        return back()->with('success', $data['enabled'] ? '学生优惠已启用' : '学生优惠已停用');
    }

    public function settings(Request $request, StoreContext $storeContext): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'codePrefix' => ['required', 'string', 'max:24', 'regex:/^[A-Za-z0-9-]+$/'],
            'discountType' => ['required', Rule::in(['PERCENTAGE', 'FIXED_AMOUNT'])],
            'discountValue' => ['required', 'numeric', 'gt:0', 'max:999999'],
            'currencyCode' => ['required_if:discountType,FIXED_AMOUNT', 'string', 'size:3'],
            'discountTarget' => ['required', Rule::in(['ALL_PRODUCTS', 'PRODUCTS', 'COLLECTIONS'])],
            'discountProductIds' => ['nullable', 'array', 'max:100'],
            'discountProductIds.*' => ['string', 'max:255'],
            'discountCollectionIds' => ['nullable', 'array', 'max:100'],
            'discountCollectionIds.*' => ['string', 'max:255'],
            'usageLimit' => ['required', 'integer', 'min:1', 'max:1000000'],
            'combinesWithProduct' => ['required', 'boolean'],
            'combinesWithOrder' => ['required', 'boolean'],
            'combinesWithShipping' => ['required', 'boolean'],
        ]);
        if ($data['discountType'] === 'PERCENTAGE' && (float) $data['discountValue'] > 100) {
            return back()->withErrors(['discountValue' => '百分比折扣不能超过 100%。']);
        }
        if ($data['discountTarget'] === 'PRODUCTS' && empty($data['discountProductIds'])) {
            return back()->withErrors(['discountProductIds' => '选择“指定产品”时至少需要一个 Shopify 产品 ID。']);
        }
        if ($data['discountTarget'] === 'COLLECTIONS' && empty($data['discountCollectionIds'])) {
            return back()->withErrors(['discountCollectionIds' => '选择“指定产品系列”时至少需要一个 Shopify 产品系列 ID。']);
        }

        $campaign = StudentDiscountCampaign::query()->where('storeId', $storeContext->id())->firstOrFail();
        $campaign->forceFill([
            ...$data,
            'codePrefix' => strtoupper(trim($data['codePrefix'], '-')),
            'currencyCode' => strtoupper($data['currencyCode']),
            'discountProductIds' => $data['discountTarget'] === 'PRODUCTS' ? ($data['discountProductIds'] ?? []) : [],
            'discountCollectionIds' => $data['discountTarget'] === 'COLLECTIONS' ? ($data['discountCollectionIds'] ?? []) : [],
            'updatedAt' => now(),
        ])->save();

        return back()->with('success', '折扣设置已保存');
    }

    public function smtp(Request $request, StoreContext $storeContext, CredentialService $credentials): RedirectResponse
    {
        $data = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'scheme' => ['required', Rule::in(['smtp', 'smtps'])],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:1000'],
            'fromAddress' => ['required', 'email:rfc', 'max:255'],
            'fromName' => ['required', 'string', 'max:120'],
        ]);
        $storeId = $storeContext->id();
        $values = [
            'MAIL_HOST' => $data['host'],
            'MAIL_PORT' => (string) $data['port'],
            'MAIL_SCHEME' => $data['scheme'],
            'MAIL_USERNAME' => $data['username'],
            'MAIL_FROM_ADDRESS' => $data['fromAddress'],
            'MAIL_FROM_NAME' => $data['fromName'],
        ];
        if (filled($data['password'] ?? null)) {
            $values['MAIL_PASSWORD'] = $data['password'];
        }

        DB::transaction(function () use ($values, $storeId): void {
            foreach ($values as $key => $value) {
                DB::table('StoreConfig')->updateOrInsert(
                    ['storeId' => $storeId, 'key' => $key],
                    ['value' => Crypt::encryptString($value), 'encrypted' => true, 'updatedAt' => now()],
                );
            }
        });
        foreach (array_keys($values) as $key) {
            $credentials->forget($key, $storeId);
        }

        return back()->with('success', '当前店铺的邮件配置已加密保存');
    }

    public function resetSmtp(StoreContext $storeContext, CredentialService $credentials): RedirectResponse
    {
        $storeId = $storeContext->id();
        DB::table('StoreConfig')->where('storeId', $storeId)->whereIn('key', self::MAIL_KEYS)->delete();
        foreach (self::MAIL_KEYS as $key) {
            $credentials->forget($key, $storeId);
        }

        return back()->with('success', '已恢复使用系统默认邮件配置');
    }

    public function emailBranding(Request $request, StoreContext $storeContext, CredentialService $credentials): RedirectResponse
    {
        $data = $request->validate([
            'brandName' => ['nullable', 'string', 'max:120'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'shopUrl' => ['nullable', 'url:http,https', 'max:2048'],
            'supportUrl' => ['nullable', 'string', 'max:2048', 'regex:/^(?:https?:\/\/|mailto:)[^\s]+$/i'],
            'instagramUrl' => ['nullable', 'url:http,https', 'max:2048'],
            'facebookUrl' => ['nullable', 'url:http,https', 'max:2048'],
            'tiktokUrl' => ['nullable', 'url:http,https', 'max:2048'],
            'youtubeUrl' => ['nullable', 'url:http,https', 'max:2048'],
        ]);
        $storeId = $storeContext->id();
        $oldLogoPath = $credentials->get(self::LOGO_PATH_KEY, $storeId);
        $newLogoPath = null;

        if ($request->hasFile('logo')) {
            $newLogoPath = $request->file('logo')->storeAs(
                "student-discount-branding/{$storeId}",
                Str::uuid().'.'.$request->file('logo')->extension(),
                'public',
            );
        }

        DB::transaction(function () use ($data, $newLogoPath, $storeId): void {
            foreach (self::BRAND_KEY_MAP as $field => $key) {
                $value = trim((string) ($data[$field] ?? ''));
                if ($value === '') {
                    DB::table('StoreConfig')->where(['storeId' => $storeId, 'key' => $key])->delete();

                    continue;
                }

                DB::table('StoreConfig')->updateOrInsert(
                    ['storeId' => $storeId, 'key' => $key],
                    ['value' => Crypt::encryptString($value), 'encrypted' => true, 'updatedAt' => now()],
                );
            }

            if ($newLogoPath) {
                $logoUrl = Storage::disk('public')->url($newLogoPath);
                foreach ([self::LOGO_PATH_KEY => $newLogoPath, self::LOGO_URL_KEY => $logoUrl] as $key => $value) {
                    DB::table('StoreConfig')->updateOrInsert(
                        ['storeId' => $storeId, 'key' => $key],
                        ['value' => Crypt::encryptString($value), 'encrypted' => true, 'updatedAt' => now()],
                    );
                }
            }
        });

        foreach ([...array_values(self::BRAND_KEY_MAP), self::LOGO_PATH_KEY, self::LOGO_URL_KEY] as $key) {
            $credentials->forget($key, $storeId);
        }
        if ($newLogoPath && $oldLogoPath && $oldLogoPath !== $newLogoPath) {
            Storage::disk('public')->delete($oldLogoPath);
        }

        return back()->with('success', '当前店铺的邮件品牌与链接已保存');
    }

    public function removeEmailLogo(StoreContext $storeContext, CredentialService $credentials): RedirectResponse
    {
        $storeId = $storeContext->id();
        $logoPath = $credentials->get(self::LOGO_PATH_KEY, $storeId);
        DB::table('StoreConfig')->where('storeId', $storeId)->whereIn('key', [self::LOGO_PATH_KEY, self::LOGO_URL_KEY])->delete();
        $credentials->forget(self::LOGO_PATH_KEY, $storeId);
        $credentials->forget(self::LOGO_URL_KEY, $storeId);
        if ($logoPath) {
            Storage::disk('public')->delete($logoPath);
        }

        return back()->with('success', '邮件 Logo 已删除，可以重新上传');
    }

    public function purgeClaims(Request $request, StoreContext $storeContext, ShopifyAdminService $shopify): RedirectResponse
    {
        $request->validate([
            'confirmation' => ['required', Rule::in(['DELETE'])],
        ]);
        $storeId = $storeContext->id();
        $claims = StudentDiscountClaim::query()
            ->where('storeId', $storeId)
            ->get(['id', 'shopifyDiscountId', 'evidencePath']);
        if ($claims->isEmpty()) {
            return back()->with('success', '当前店铺没有申请数据需要删除');
        }

        $discountIds = $claims->pluck('shopifyDiscountId')->filter()->unique()->values();
        $installation = ShopifyInstallation::query()->where('storeId', $storeId)->first();
        $deletedDiscounts = 0;
        if ($discountIds->isNotEmpty()) {
            if (! $installation || $installation->status !== 'INSTALLED') {
                return back()->withErrors([
                    'purgeClaims' => '当前店铺未连接 Shopify，无法确认线上优惠码已删除。本地申请数据未做修改。',
                ]);
            }

            foreach ($discountIds as $discountId) {
                try {
                    $shopify->deleteDiscount($installation, (string) $discountId);
                    StudentDiscountClaim::query()
                        ->where('storeId', $storeId)
                        ->where('shopifyDiscountId', $discountId)
                        ->update(['shopifyDiscountId' => null, 'updatedAt' => now()]);
                    $deletedDiscounts++;
                } catch (Throwable $exception) {
                    Log::warning('Student discount purge stopped after a Shopify deletion failure.', [
                        'storeId' => $storeId,
                        'discountId' => $discountId,
                        'deletedDiscounts' => $deletedDiscounts,
                        'exception' => $exception,
                    ]);

                    return back()->withErrors([
                        'purgeClaims' => "Shopify 优惠码删除失败，清空已停止。已删除 {$deletedDiscounts} 个优惠码，本地申请记录仍保留，可稍后重试。",
                    ]);
                }
            }
        }

        $claimIds = $claims->pluck('id')->all();
        $evidencePaths = $claims->pluck('evidencePath')->filter()->all();
        DB::transaction(fn () => StudentDiscountClaim::query()
            ->where('storeId', $storeId)
            ->whereIn('id', $claimIds)
            ->delete());
        if ($evidencePaths !== []) {
            Storage::disk('local')->delete($evidencePaths);
        }

        return back()->with('success', "已删除当前店铺 {$claims->count()} 条申请数据及 {$deletedDiscounts} 个 Shopify 优惠码");
    }

    public function evidence(StudentDiscountClaim $claim, StoreContext $storeContext): StreamedResponse
    {
        abort_unless($claim->storeId === $storeContext->id() && filled($claim->evidencePath), 404);
        abort_unless(Storage::disk('local')->exists($claim->evidencePath), 404);

        return Storage::disk('local')->response($claim->evidencePath, basename($claim->evidencePath), [
            'Cache-Control' => 'private, no-store',
            'Content-Disposition' => 'inline',
        ]);
    }
}
