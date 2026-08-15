<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Domain\ShopifyInstallation;
use App\Services\Shopify\ShopifyAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ShopifyWebhookController extends Controller
{
    public function __construct(private readonly ShopifyAuthService $auth) {}

    public function uninstall(Request $request): Response
    {
        abort_unless($this->auth->verifyWebhook($request), 401);
        $webhookId = (string) $request->header('X-Shopify-Webhook-Id');
        if ($webhookId && ! Cache::add('shopify-webhook:'.$webhookId, true, now()->addDays(7))) {
            return response()->noContent();
        }
        $shop = $this->auth->normalizeShop((string) $request->header('X-Shopify-Shop-Domain'));
        ShopifyInstallation::query()->where('shopDomain', $shop)->update([
            'status' => 'UNINSTALLED', 'accessToken' => null, 'uninstalledAt' => now(), 'updatedAt' => now(),
        ]);

        return response()->noContent();
    }
}
