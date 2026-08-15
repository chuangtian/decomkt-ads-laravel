<?php

namespace App\Http\Controllers;

use App\Services\Stores\StoreContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class ConfigurationController extends Controller
{
    private const SYSTEM_KEYS = ['OPENAI_API_KEY', 'OPENAI_MODEL', 'CODEX_API_KEY', 'GEMINI_API_KEY', 'GEMINI_MODEL', 'ANTHROPIC_API_KEY', 'CLAUDE_API_KEY', 'CLAUDE_MODEL', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_SCHEME', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME', 'FEISHU_APP_ID', 'FEISHU_APP_SECRET'];

    private const STORE_KEYS = [
        'FB_ACCESS_TOKEN', 'SHOPIFY_ACCESS_TOKEN', 'SHOPIFY_STORE_DOMAIN', 'TK_ACCESS_TOKEN', 'TK_ADVERTISER_IDS',
        'MAIL_HOST', 'MAIL_PORT', 'MAIL_SCHEME', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME',
        'GOOGLE_ADS_CLIENT_ID', 'GOOGLE_ADS_CLIENT_SECRET', 'GOOGLE_ADS_REFRESH_TOKEN', 'GOOGLE_ADS_DEVELOPER_TOKEN', 'GOOGLE_ADS_CUSTOMER_ID', 'GOOGLE_ADS_LOGIN_CUSTOMER_ID',
        'BING_ADS_CLIENT_ID', 'BING_ADS_CLIENT_SECRET', 'BING_ADS_REFRESH_TOKEN', 'BING_ADS_DEVELOPER_TOKEN', 'BING_ADS_ACCOUNT_ID', 'BING_ADS_CUSTOMER_ID',
        'CRITEO_API_KEY', 'CRITEO_CLIENT_SECRET', 'CRITEO_ADVERTISER_ID', 'YOUTUBE_CLIENT_ID', 'YOUTUBE_CLIENT_SECRET',
        'GSC_SITE_URL', 'GSC_CLIENT_ID', 'GSC_CLIENT_SECRET', 'GSC_REFRESH_TOKEN', 'GA4_PROPERTY_ID', 'GA4_SERVICE_ACCOUNT_JSON',
        'FEISHU_APP_ID', 'FEISHU_APP_SECRET', 'FEISHU_BRAND_WIKI_URL', 'FEISHU_BRAND_SPREADSHEET_TOKEN',
        'FEISHU_CAMPAIGN_APP_TOKEN', 'FEISHU_CAMPAIGN_TABLE_ID', 'FEISHU_CAMPAIGN_VIEW_ID',
        'FEISHU_DESIGN_APP_TOKEN', 'FEISHU_DESIGN_TABLE_ID', 'FEISHU_KOL_APP_TOKEN', 'FEISHU_KOL_TABLE_ID', 'FEISHU_KOL_VIEW_ID',
        'FEISHU_AFFILIATE_APP_TOKEN', 'FEISHU_AFFILIATE_TABLE_ID', 'FEISHU_AFFILIATE_VIEW_ID', 'FEISHU_SEQUENCE_WIKI_NODE',
        'FEISHU_SEO_APP_TOKEN', 'FEISHU_SEO_DAILY_TABLE_ID', 'FEISHU_SEO_WORK_NEW_BLOG_TOKEN', 'FEISHU_SEO_WORK_NEW_BLOG_SHEET',
        'FEISHU_SEO_WORK_OLD_BLOG_TOKEN', 'FEISHU_SEO_WORK_OLD_BLOG_SHEET', 'FEISHU_SEO_WORK_BACKLINKS_TOKEN', 'FEISHU_SEO_WORK_BACKLINKS_SHEET',
        'FEISHU_SEO_WORK_AI_TOKEN', 'FEISHU_SEO_WORK_AI_SHEET', 'SEO_WORK_NEW_BLOG', 'SEO_WORK_OLD_BLOG', 'SEO_WORK_BACKLINKS', 'SEO_WORK_AI_AUTOMATION',
    ];

    public function store(Request $request, string $scope, StoreContext $storeContext): RedirectResponse
    {
        abort_unless(in_array($scope, ['system', 'store'], true), 404);
        $data = $request->validate(['key' => ['required', 'string'], 'value' => ['required', 'string', 'max:10000']]);
        $allowed = $scope === 'system' ? self::SYSTEM_KEYS : self::STORE_KEYS;
        abort_unless(in_array($data['key'], $allowed, true), 422, '不支持的配置项');
        $payload = ['value' => Crypt::encryptString($data['value']), 'encrypted' => true, 'updatedAt' => now()];
        if ($scope === 'system') {
            DB::table('SystemConfig')->updateOrInsert(['key' => $data['key']], $payload);
        } else {
            DB::table('StoreConfig')->updateOrInsert(['storeId' => $storeContext->id(), 'key' => $data['key']], $payload);
        }

        return back()->with('success', '配置已加密保存');
    }

    public function destroy(Request $request, string $scope, string $key, StoreContext $storeContext): RedirectResponse
    {
        abort_unless(in_array($scope, ['system', 'store'], true), 404);
        $allowed = $scope === 'system' ? self::SYSTEM_KEYS : self::STORE_KEYS;
        abort_unless(in_array($key, $allowed, true), 422, '不支持的配置项');
        if ($scope === 'system') {
            DB::table('SystemConfig')->where('key', $key)->delete();
        } else {
            DB::table('StoreConfig')->where('storeId', $storeContext->id())->where('key', $key)->delete();
        }

        return back()->with('success', '配置已清空');
    }
}
