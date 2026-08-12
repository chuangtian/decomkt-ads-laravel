<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class ConfigurationController extends Controller
{
    private const SYSTEM_KEYS = ['OPENAI_API_KEY', 'OPENAI_MODEL', 'CODEX_API_KEY', 'GEMINI_API_KEY', 'ANTHROPIC_API_KEY', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'FEISHU_APP_ID', 'FEISHU_APP_SECRET'];

    private const STORE_KEYS = ['FB_ACCESS_TOKEN', 'SHOPIFY_ACCESS_TOKEN', 'SHOPIFY_STORE_DOMAIN', 'TK_ACCESS_TOKEN', 'TK_ADVERTISER_IDS', 'GOOGLE_ADS_CLIENT_ID', 'GOOGLE_ADS_CLIENT_SECRET', 'GOOGLE_ADS_REFRESH_TOKEN', 'GOOGLE_ADS_DEVELOPER_TOKEN', 'GOOGLE_ADS_CUSTOMER_ID', 'BING_ADS_CLIENT_ID', 'BING_ADS_CLIENT_SECRET', 'BING_ADS_REFRESH_TOKEN', 'BING_ADS_DEVELOPER_TOKEN', 'BING_ADS_ACCOUNT_ID', 'CRITEO_API_KEY', 'CRITEO_CLIENT_SECRET', 'FEISHU_APP_ID', 'FEISHU_APP_SECRET', 'YOUTUBE_CLIENT_ID', 'YOUTUBE_CLIENT_SECRET', 'GSC_SITE_URL', 'GA4_PROPERTY_ID'];

    public function store(Request $request, string $scope): RedirectResponse
    {
        abort_unless(in_array($scope, ['system', 'store'], true), 404);
        $data = $request->validate(['key' => ['required', 'string'], 'value' => ['required', 'string', 'max:10000']]);
        $allowed = $scope === 'system' ? self::SYSTEM_KEYS : self::STORE_KEYS;
        abort_unless(in_array($data['key'], $allowed, true), 422, '不支持的配置项');
        $payload = ['value' => Crypt::encryptString($data['value']), 'encrypted' => true, 'updatedAt' => now()];
        if ($scope === 'system') {
            DB::table('SystemConfig')->updateOrInsert(['key' => $data['key']], $payload);
        } else {
            DB::table('StoreConfig')->updateOrInsert(['storeId' => 'default-store', 'key' => $data['key']], $payload);
        }

        return back()->with('success', '配置已加密保存');
    }

    public function destroy(Request $request, string $scope, string $key): RedirectResponse
    {
        abort_unless(in_array($scope, ['system', 'store'], true), 404);
        $allowed = $scope === 'system' ? self::SYSTEM_KEYS : self::STORE_KEYS;
        abort_unless(in_array($key, $allowed, true), 422, '不支持的配置项');
        if ($scope === 'system') {
            DB::table('SystemConfig')->where('key', $key)->delete();
        } else {
            DB::table('StoreConfig')->where('storeId', 'default-store')->where('key', $key)->delete();
        }

        return back()->with('success', '配置已清空');
    }
}
