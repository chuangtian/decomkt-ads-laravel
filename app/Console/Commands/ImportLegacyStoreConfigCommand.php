<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportLegacyStoreConfigCommand extends Command
{
    protected $signature = 'legacy:import-store-config {file : CSV exported from the legacy StoreConfig table} {--store=default-store}';

    protected $description = 'Import legacy store configuration and re-encrypt every value with Laravel Crypt';

    public function handle(): int
    {
        $path = (string) $this->argument('file');
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Cannot open {$path}");
        }

        $header = fgetcsv($handle, escape: '');
        if ($header !== ['key', 'value']) {
            fclose($handle);
            throw new RuntimeException('Expected CSV columns: key,value');
        }

        $count = 0;
        DB::transaction(function () use ($handle, &$count): void {
            while (($row = fgetcsv($handle, escape: '')) !== false) {
                if (count($row) !== 2 || $row[0] === '') {
                    continue;
                }

                DB::table('StoreConfig')->updateOrInsert(
                    ['storeId' => (string) $this->option('store'), 'key' => $row[0]],
                    ['value' => Crypt::encryptString($row[1]), 'encrypted' => true, 'updatedAt' => now()],
                );
                $count++;
            }
        });
        fclose($handle);

        $this->info("Imported and encrypted {$count} store configuration entries.");

        return self::SUCCESS;
    }
}
