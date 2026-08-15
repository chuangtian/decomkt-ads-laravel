<?php

namespace App\Services\Shopify;

use App\Models\Domain\ShopifyInstallation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class ShopifyTokenService
{
    /** @param array<string, mixed> $session */
    public function fromSession(array $session, string $sessionToken): ShopifyInstallation
    {
        $shop = (string) $session['shop'];
        $installation = ShopifyInstallation::query()
            ->where('shopDomain', $shop)
            ->where('clientId', config('shopify.client_id'))
            ->first();

        if ($installation && $installation->status === 'INSTALLED' && $installation->accessToken
            && (! $installation->accessTokenExpiresAt || $installation->accessTokenExpiresAt->isAfter(now()->addMinutes(5)))) {
            return $installation;
        }

        $token = Http::asForm()->acceptJson()->timeout(20)->retry(2, 500)
            ->post("https://{$shop}/admin/oauth/access_token", [
                'client_id' => config('shopify.client_id'),
                'client_secret' => config('shopify.client_secret'),
                'grant_type' => 'urn:ietf:params:oauth:grant-type:token-exchange',
                'subject_token' => $sessionToken,
                'subject_token_type' => 'urn:ietf:params:oauth:token-type:id_token',
                'requested_token_type' => 'urn:shopify:params:oauth:token-type:offline-access-token',
                'expiring' => 1,
            ])->throw()->json();

        $installation = DB::transaction(function () use ($shop, $token, $installation): ShopifyInstallation {
            $baseSlug = Str::slug(Str::before($shop, '.myshopify.com')) ?: 'shopify-store';
            $storeId = $installation?->storeId ?: DB::table('Store')->where('slug', $baseSlug)->value('id');
            $storeId = $storeId ?: (string) Str::uuid();
            $slug = DB::table('Store')->where('id', $storeId)->value('slug') ?: $baseSlug;
            $suffix = 2;
            while (DB::table('Store')->where('slug', $slug)->where('id', '!=', $storeId)->exists()) {
                $slug = $baseSlug.'-'.$suffix++;
            }

            $storeValues = [
                'slug' => $slug,
                'name' => Str::headline(Str::before($shop, '.myshopify.com')),
                'status' => 'ACTIVE',
                'timezone' => 'America/Los_Angeles',
                'updatedAt' => now(),
            ];
            if (DB::table('Store')->where('id', $storeId)->exists()) {
                DB::table('Store')->where('id', $storeId)->update($storeValues);
            } else {
                DB::table('Store')->insert($storeValues + ['id' => $storeId, 'createdAt' => now()]);
            }

            return ShopifyInstallation::query()->updateOrCreate([
                'shopDomain' => $shop,
                'clientId' => config('shopify.client_id'),
            ], [
                'id' => $installation?->id ?: (string) Str::uuid(),
                'storeId' => $storeId,
                'clientId' => config('shopify.client_id'),
                'status' => 'INSTALLED',
                'scopes' => array_values(array_filter(explode(',', (string) ($token['scope'] ?? '')))),
                'accessToken' => $token['access_token'],
                'refreshToken' => $token['refresh_token'] ?? null,
                'accessTokenExpiresAt' => isset($token['expires_in']) ? now()->addSeconds((int) $token['expires_in']) : null,
                'refreshTokenExpiresAt' => isset($token['refresh_token_expires_in']) ? now()->addSeconds((int) $token['refresh_token_expires_in']) : null,
                'installedAt' => $installation?->installedAt ?: now(),
                'uninstalledAt' => null,
                'lastSeenAt' => now(),
                'updatedAt' => now(),
            ]);
        });

        $this->registerUninstallWebhook($installation);

        return $installation;
    }

    public function refreshIfNeeded(ShopifyInstallation $installation): ShopifyInstallation
    {
        if (! $installation->accessTokenExpiresAt || $installation->accessTokenExpiresAt->isAfter(now()->addMinutes(5))) {
            return $installation;
        }
        if (! $installation->refreshToken || ($installation->refreshTokenExpiresAt && $installation->refreshTokenExpiresAt->isPast())) {
            throw new RuntimeException('Shopify authorization expired. Open the app in Shopify Admin to reconnect it.');
        }

        $token = Http::asForm()->acceptJson()->timeout(20)->retry(2, 500)
            ->post("https://{$installation->shopDomain}/admin/oauth/access_token", [
                'client_id' => config('shopify.client_id'),
                'client_secret' => config('shopify.client_secret'),
                'grant_type' => 'refresh_token',
                'refresh_token' => $installation->refreshToken,
            ])->throw()->json();

        $installation->forceFill([
            'accessToken' => $token['access_token'],
            'refreshToken' => $token['refresh_token'],
            'scopes' => array_values(array_filter(explode(',', (string) ($token['scope'] ?? '')))),
            'accessTokenExpiresAt' => now()->addSeconds((int) $token['expires_in']),
            'refreshTokenExpiresAt' => now()->addSeconds((int) $token['refresh_token_expires_in']),
            'updatedAt' => now(),
        ])->save();

        return $installation->fresh();
    }

    public function refresh(ShopifyInstallation $installation): ShopifyInstallation
    {
        if (! $installation->refreshToken || ($installation->refreshTokenExpiresAt && $installation->refreshTokenExpiresAt->isPast())) {
            throw new RuntimeException('Shopify authorization expired. Open the app in Shopify Admin to reconnect it.');
        }

        $installation->forceFill(['accessTokenExpiresAt' => now()->subMinute()]);

        return $this->refreshIfNeeded($installation);
    }

    public function registerUninstallWebhook(ShopifyInstallation $installation): void
    {
        try {
            $response = Http::acceptJson()->withHeader('X-Shopify-Access-Token', (string) $installation->accessToken)
                ->timeout(20)->post(sprintf('https://%s/admin/api/%s/graphql.json', $installation->shopDomain, config('shopify.api_version')), [
                    'query' => <<<'GRAPHQL'
mutation RegisterUninstall($topic: WebhookSubscriptionTopic!, $webhookSubscription: WebhookSubscriptionInput!) {
  webhookSubscriptionCreate(topic: $topic, webhookSubscription: $webhookSubscription) {
    webhookSubscription { id }
    userErrors { message }
  }
}
GRAPHQL,
                    'variables' => [
                        'topic' => 'APP_UNINSTALLED',
                        'webhookSubscription' => ['callbackUrl' => config('shopify.app_url').'/api/v1/webhooks/shopify/app-uninstalled', 'format' => 'JSON'],
                    ],
                ])->throw()->json();
            $rawErrors = data_get($response, 'data.webhookSubscriptionCreate.userErrors', []);
            $errors = [];
            if (is_array($rawErrors)) {
                foreach ($rawErrors as $error) {
                    if (is_array($error) && is_string($error['message'] ?? null) && $error['message'] !== '') {
                        $errors[] = $error['message'];
                    }
                }
            }
            $onlyDuplicates = $errors !== [] && collect($errors)->every(
                fn (string $message): bool => str_contains(strtolower($message), 'already'),
            );
            if ($errors !== [] && ! $onlyDuplicates) {
                throw new RuntimeException(implode('; ', $errors));
            }
        } catch (\Throwable $exception) {
            Log::warning('Shopify uninstall webhook registration failed', ['shop' => $installation->shopDomain, 'error' => $exception->getMessage()]);
        }
    }
}
