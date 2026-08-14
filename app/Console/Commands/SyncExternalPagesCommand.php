<?php

namespace App\Console\Commands;

use App\Http\Controllers\BrandController;
use App\Http\Controllers\ModuleController;
use App\Services\Stores\StoreContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class SyncExternalPagesCommand extends Command
{
    protected $signature = 'data:sync-external-pages {--only=}';
    protected $description = 'Synchronize every external business page into MySQL snapshots';

    public function handle(ModuleController $modules, BrandController $brand, StoreContext $storeContext): int
    {
        $paths = $this->option('only') ? [(string) $this->option('only')] : ['/workspace/brand', ...ModuleController::externalPaths()];
        $failed = false;
        foreach (DB::table('Store')->where('status', 'ACTIVE')->pluck('id') as $storeId) {
            $storeContext->setForBackgroundJob($storeId);
            foreach ($paths as $path) {
                if (! $storeContext->isEnabled($path, $storeId)) continue;
                try {
                    $result = $path === '/workspace/brand' ? $brand->sync() : $modules->syncExternalPage($path);
                    $this->info("{$storeId} {$path} synchronized (".count($result['rows'] ?? $result).' records).');
                } catch (Throwable $exception) {
                    $failed = true; $this->error("{$storeId} {$path}: ".$exception->getMessage());
                }
            }
        }
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
