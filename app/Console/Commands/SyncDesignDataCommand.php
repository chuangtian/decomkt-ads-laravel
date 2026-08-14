<?php

namespace App\Console\Commands;

use App\Services\DataSync\DesignDataSyncService;
use App\Services\Stores\StoreContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class SyncDesignDataCommand extends Command
{
    protected $signature = 'data:sync-design';

    protected $description = 'Synchronize Feishu design records into the MySQL snapshot table';

    public function handle(DesignDataSyncService $sync, StoreContext $storeContext): int
    {
        $failed = false;
        foreach (DB::table('Store')->where('status', 'ACTIVE')->pluck('id') as $storeId) {
            if (! $storeContext->isEnabled('/ecommerce/design', $storeId)) {
                continue;
            }
            try {
                $records = $sync->sync($storeId);
                $this->info("{$storeId}: design synchronized (".count($records).' records).');
            } catch (Throwable $exception) {
                $failed = true;
                $this->error("{$storeId}: {$exception->getMessage()}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
