<?php

namespace App\Http\Controllers;

use App\Models\Domain\StudentDiscountCampaign;
use App\Models\Domain\StudentDiscountClaim;
use App\Services\Credentials\CredentialService;
use App\Services\Stores\StoreContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentDiscountController extends Controller
{
    private const MAIL_KEYS = [
        'MAIL_HOST', 'MAIL_PORT', 'MAIL_SCHEME', 'MAIL_USERNAME', 'MAIL_PASSWORD',
        'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME',
    ];

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
