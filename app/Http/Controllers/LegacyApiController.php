<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LegacyApiController extends Controller
{
    public function __invoke(Request $request, string $path): JsonResponse
    {
        $path = trim($path, '/');

        if ($path === 'auth/check-access') {
            $employee = DB::table('Employee')->where('email', $request->user()?->email)->first();

            return response()->json(['allowed' => (bool) $employee, 'isAdmin' => (bool) ($employee?->isDefaultAdmin ?? false)]);
        }
        if (in_array($path, ['collab/employees', 'admin/employees'], true)) {
            return response()->json(['employees' => DB::table('Employee')->orderBy('name')->get()]);
        }
        if ($path === 'admin/roles') {
            return response()->json(['roles' => DB::table('Role')->orderBy('name')->get()]);
        }
        if ($path === 'admin/permissions') {
            return response()->json(['permissions' => DB::table('Permission')->orderBy('module')->get()]);
        }
        if ($path === 'admin/stores') {
            return response()->json(['stores' => DB::table('Store')->orderBy('name')->get()]);
        }
        if ($path === 'collab/tasks') {
            return response()->json(DB::table('Task')->where('storeId', 'default-store')->orderBy('dueDate')->get());
        }
        if ($path === 'collab/notifications/unread-count') {
            $employeeId = DB::table('Employee')->where('email', $request->user()?->email)->value('id');

            return response()->json(['count' => DB::table('Notification')->where('recipientId', $employeeId)->where('isRead', false)->count()]);
        }
        if ($path === 'collab/notifications') {
            $employeeId = DB::table('Employee')->where('email', $request->user()?->email)->value('id');

            return response()->json(DB::table('Notification')->where('recipientId', $employeeId)->latest('createdAt')->limit(50)->get());
        }
        if ($path === 'ecommerce/student-discounts') {
            return response()->json(['campaign' => DB::table('StudentDiscountCampaign')->where('storeId', 'default-store')->first(), 'claims' => DB::table('StudentDiscountClaim')->where('storeId', 'default-store')->latest('createdAt')->limit(100)->get()]);
        }
        if (str_starts_with($path, 'reputation/reviews')) {
            return response()->json(['reviews' => DB::table('ReputationReview')->where('storeId', 'default-store')->latest('createdAt')->limit(200)->get()]);
        }
        if (str_starts_with($path, 'reputation/reddit')) {
            return response()->json(['posts' => DB::table('RedditPost')->where('storeId', 'default-store')->latest('createdAt')->limit(200)->get()]);
        }
        if ($path === 'reputation/risks') {
            return response()->json(['risks' => DB::table('ReputationRisk')->where('storeId', 'default-store')->latest('createdAt')->get()]);
        }
        if ($path === 'reputation/resources') {
            return response()->json(['resources' => DB::table('ReputationResource')->where('storeId', 'default-store')->latest('createdAt')->get()]);
        }
        if (str_starts_with($path, 'workspace/conversations')) {
            return response()->json(['conversations' => DB::table('AiConversation')->where('storeId', 'default-store')->latest('updatedAt')->get()]);
        }
        if ($path === 'admin/system-config/status') {
            return response()->json(['configured' => DB::table('SystemConfig')->pluck('key'), 'total' => DB::table('SystemConfig')->count()]);
        }
        if ($path === 'store-settings/oauth-status') {
            return response()->json(['configured' => DB::table('StoreConfig')->where('storeId', 'default-store')->pluck('key')]);
        }

        $requirements = [
            'ads/facebook' => ['FB_ACCESS_TOKEN'], 'ads/google' => ['GOOGLE_ADS_CLIENT_ID', 'GOOGLE_ADS_REFRESH_TOKEN', 'GOOGLE_ADS_DEVELOPER_TOKEN', 'GOOGLE_ADS_CUSTOMER_ID'],
            'ads/tiktok' => ['TK_ACCESS_TOKEN', 'TK_ADVERTISER_IDS'], 'ads/bing' => ['BING_ADS_CLIENT_ID', 'BING_ADS_REFRESH_TOKEN', 'BING_ADS_DEVELOPER_TOKEN', 'BING_ADS_ACCOUNT_ID'],
            'ads/criteo' => ['CRITEO_API_KEY', 'CRITEO_CLIENT_SECRET'], 'ecommerce/shopify' => ['SHOPIFY_ACCESS_TOKEN', 'SHOPIFY_STORE_DOMAIN'],
        ];
        $requirement = collect($requirements)->first(fn ($keys, $prefix) => str_starts_with($path, $prefix));
        if ($requirement) {
            $configured = DB::table('StoreConfig')->where('storeId', 'default-store')->whereIn('key', $requirement)->pluck('key')->all();
            $missing = array_values(array_diff($requirement, $configured));

            return response()->json(['configured' => count($missing) === 0, 'missing' => $missing, 'data' => []]);
        }
        if (str_starts_with($path, 'feishu/') || str_starts_with($path, 'amazon/')) {
            $configured = DB::table('StoreConfig')->where('storeId', 'default-store')->whereIn('key', ['FEISHU_APP_ID', 'FEISHU_APP_SECRET'])->count() === 2;

            return response()->json(['configured' => $configured, 'data' => []]);
        }

        return response()->json(['data' => [], 'meta' => ['adapter' => 'laravel', 'path' => $path, 'message' => '接口已迁移，等待对应平台凭证后同步数据']]);
    }

    public function publicStudentDiscount(): JsonResponse
    {
        $campaign = DB::table('StudentDiscountCampaign')->where('storeId', 'default-store')->first();
        if (! $campaign) {
            return response()->json(['enabled' => false], 404);
        }

        return response()->json(['enabled' => (bool) $campaign->enabled, 'title' => $campaign->title, 'description' => $campaign->description, 'discountType' => $campaign->discountType, 'discountValue' => $campaign->discountValue, 'accentColor' => $campaign->accentColor, 'backgroundColor' => $campaign->backgroundColor]);
    }
}
