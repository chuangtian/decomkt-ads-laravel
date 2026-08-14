<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportLegacyConfigBundleCommand extends Command
{
    protected $signature = 'legacy:import-config-bundle {file : Decrypted CSV bundle} {--store=default-store}';

    protected $description = 'Import legacy system and store configuration and encrypt all values with Laravel Crypt';

    public function handle(): int
    {
        $handle = fopen((string) $this->argument('file'), 'rb');
        if ($handle === false) {
            throw new RuntimeException('Cannot open configuration bundle.');
        }

        $header = fgetcsv($handle, escape: '');
        if ($header !== ['scope', 'key', 'value', 'encrypted']) {
            fclose($handle);
            throw new RuntimeException('Expected CSV columns: scope,key,value,encrypted');
        }

        $counts = ['store' => 0, 'system' => 0];
        DB::transaction(function () use ($handle, &$counts): void {
            while (($row = fgetcsv($handle, escape: '')) !== false) {
                if (count($row) !== 4 || ! isset($counts[$row[0]]) || $row[1] === '') {
                    continue;
                }

                $payload = [
                    'value' => Crypt::encryptString($row[2]),
                    'encrypted' => true,
                    'updatedAt' => now(),
                ];

                if ($row[0] === 'store') {
                    DB::table('StoreConfig')->updateOrInsert(
                        ['storeId' => (string) $this->option('store'), 'key' => $row[1]],
                        $payload,
                    );
                } else {
                    DB::table('SystemConfig')->updateOrInsert(['key' => $row[1]], $payload);
                }
                $counts[$row[0]]++;
            }
        });
        fclose($handle);

        $this->info("Imported {$counts['store']} store and {$counts['system']} system configuration entries.");

        return self::SUCCESS;
    }
}
