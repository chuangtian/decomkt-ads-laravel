<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ImportLegacyCsvBundleCommand extends Command
{
    protected $signature = 'legacy:import-csv-bundle {directory}';

    protected $description = 'Import a directory of PostgreSQL CSV exports into matching MySQL tables';

    public function handle(): int
    {
        $directory = rtrim((string) $this->argument('directory'), DIRECTORY_SEPARATOR);
        $files = glob($directory.DIRECTORY_SEPARATOR.'*.csv') ?: [];
        if ($files === []) {
            throw new RuntimeException('No CSV files found in bundle directory.');
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($files as $file) {
                $table = pathinfo($file, PATHINFO_FILENAME);
                if (! Schema::hasTable($table)) {
                    $this->warn("Skipped missing table: {$table}");
                    continue;
                }

                $handle = fopen($file, 'rb');
                if ($handle === false) {
                    throw new RuntimeException("Cannot open {$table} export.");
                }

                $headers = fgetcsv($handle, escape: '');
                if (! is_array($headers)) {
                    fclose($handle);
                    continue;
                }

                $available = array_flip(Schema::getColumnListing($table));
                $rows = [];
                $count = 0;
                while (($values = fgetcsv($handle, escape: '')) !== false) {
                    if (count($values) !== count($headers)) {
                        throw new RuntimeException("Column mismatch in {$table} row ".($count + 2).'.');
                    }

                    $row = [];
                    foreach (array_combine($headers, $values) as $column => $value) {
                        if (isset($available[$column])) {
                            $row[$column] = $value === 'CODXNULL' ? null : $value;
                        }
                    }
                    $rows[] = $row;
                    $count++;
                    if (count($rows) === 250) {
                        DB::table($table)->insertOrIgnore($rows);
                        $rows = [];
                    }
                }
                fclose($handle);
                if ($rows !== []) {
                    DB::table($table)->insertOrIgnore($rows);
                }
                $this->line("{$table}: {$count}");
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        return self::SUCCESS;
    }
}
