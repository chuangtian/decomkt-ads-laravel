<?php

namespace App\Console\Commands;

use App\Models\Domain\ShopifyInstallation;
use App\Services\Shopify\ShopifyAdminService;
use App\Services\Shopify\ShopifyApiException;
use App\Services\Shopify\ShopifyTokenService;
use Illuminate\Console\Command;
use Throwable;

class SyncShopifyInstallationsCommand extends Command
{
    protected $signature = 'shopify:sync-installations {--shop=}';

    protected $description = 'Refresh Shopify access tokens and reconcile installation status into MySQL';

    public function handle(ShopifyAdminService $admin, ShopifyTokenService $tokens): int
    {
        $query = ShopifyInstallation::query()->where('status', 'INSTALLED');
        if ($shop = $this->option('shop')) {
            $query->where('shopDomain', $shop);
        }

        $failed = false;
        foreach ($query->cursor() as $installation) {
            try {
                $installation = $tokens->refreshIfNeeded($installation);
                $data = $admin->graphql($installation, <<<'GRAPHQL'
query InstallationHealth { shop { id name myshopifyDomain } }
GRAPHQL);
                $installation->forceFill(['lastSeenAt' => now(), 'updatedAt' => now()])->save();
                $this->info($installation->shopDomain.': installed ('.data_get($data, 'shop.name', 'Shopify store').')');
            } catch (ShopifyApiException $exception) {
                if (in_array($exception->status, [401, 403], true)) {
                    $installation->forceFill(['status' => 'REAUTH_REQUIRED', 'updatedAt' => now()])->save();
                }
                $failed = true;
                $this->error($installation->shopDomain.': '.$exception->getMessage());
            } catch (Throwable $exception) {
                $failed = true;
                $this->error($installation->shopDomain.': '.$exception->getMessage());
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
