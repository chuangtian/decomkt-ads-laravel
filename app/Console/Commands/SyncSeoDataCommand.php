<?php

namespace App\Console\Commands;

use App\Services\DataSync\SeoSnapshotService;
use App\Services\Stores\StoreContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class SyncSeoDataCommand extends Command
{
    protected $signature = 'data:sync-seo';
    protected $description = 'Synchronize SEO goals, GSC and GA4 into the MySQL snapshot';

    public function handle(SeoSnapshotService $service, StoreContext $storeContext): int
    {
        $failed = false;
        foreach (DB::table('Store')->where('status', 'ACTIVE')->pluck('id') as $storeId) {
            if (! $storeContext->isEnabled('/organic/seo', $storeId)) continue;
            try {
                $payload = $service->sync(storeId: $storeId);
                $this->info("{$storeId}: SEO synchronized (".count($payload['goals']['metrics'] ?? []).' goals).');
            } catch (Throwable $exception) {
                $failed = true;
                $this->error("{$storeId}: {$exception->getMessage()}");
            }
        }
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
