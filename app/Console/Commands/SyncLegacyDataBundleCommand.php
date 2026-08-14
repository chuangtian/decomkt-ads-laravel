<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SyncLegacyDataBundleCommand extends Command
{
    protected $signature = 'legacy:sync-data-bundle
        {file : JSON Lines export from the legacy PostgreSQL database}
        {--source-store= : Legacy default Store id to remap}
        {--target-store=default-store : Laravel default Store id}';

    protected $description = 'Replace Laravel domain-table rows with a legacy PostgreSQL snapshot';

    /** @var list<string> */
    private const PROTECTED_TABLES = [
        'StoreConfig',
        'SystemConfig',
        'cache',
        'cache_locks',
        'failed_jobs',
        'job_batches',
        'jobs',
        'migrations',
        'passkeys',
        'password_reset_tokens',
        'sessions',
        'users',
    ];

    public function handle(): int
    {
        $path = (string) $this->argument('file');
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Cannot open {$path}");
        }

        $grouped = [];
        try {
            foreach ($this->records($handle) as $record) {
                $table = $record['table'] ?? null;
                $row = $record['row'] ?? null;
                if (! is_string($table) || ! is_array($row)) {
                    continue;
                }
                if (in_array($table, self::PROTECTED_TABLES, true) || ! Schema::hasTable($table)) {
                    continue;
                }

                $grouped[$table][] = $this->prepareRow($table, $row);
            }
        } finally {
            fclose($handle);
        }

        ksort($grouped);
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            DB::transaction(function () use ($grouped): void {
                foreach (array_keys($grouped) as $table) {
                    DB::table($table)->delete();
                }

                foreach ($grouped as $table => $rows) {
                    foreach (array_chunk($rows, 200) as $chunk) {
                        if ($chunk !== []) {
                            DB::table($table)->insert($chunk);
                        }
                    }
                    $this->line("{$table}: ".count($rows));
                }
            });
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->info('Legacy domain data synchronized successfully.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function prepareRow(string $table, array $row): array
    {
        $columns = array_flip(Schema::getColumnListing($table));
        $sourceStore = (string) ($this->option('source-store') ?: '');
        $targetStore = (string) $this->option('target-store');
        $payload = [];

        foreach ($row as $column => $value) {
            if (! isset($columns[$column])) {
                continue;
            }

            if ($sourceStore !== '' && $value === $sourceStore && ($column === 'storeId' || ($table === 'Store' && $column === 'id'))) {
                $value = $targetStore;
            }

            if (is_array($value)) {
                $value = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            }

            if (is_string($value) && $this->isDateColumn($table, $column) && $value !== '') {
                $value = Carbon::parse($value)->format('Y-m-d H:i:s');
            }

            $payload[$column] = $value;
        }

        return $payload;
    }

    private function isDateColumn(string $table, string $column): bool
    {
        return in_array(Schema::getColumnType($table, $column), ['date', 'datetime', 'timestamp'], true);
    }

    /**
     * @param  resource  $handle
     * @return \Generator<int, array<string, mixed>>
     */
    private function records($handle): \Generator
    {
        $buffer = '';
        $depth = 0;
        $inString = false;
        $escaped = false;

        while (! feof($handle)) {
            $chunk = fread($handle, 65536);
            if ($chunk === false) {
                throw new RuntimeException('Cannot read data bundle.');
            }

            for ($index = 0, $length = strlen($chunk); $index < $length; $index++) {
                $character = $chunk[$index];
                if ($depth === 0 && $character !== '{') {
                    continue;
                }

                if ($inString && ord($character) < 32) {
                    $buffer .= match ($character) {
                        "\n" => '\\n',
                        "\r" => '\\r',
                        "\t" => '\\t',
                        "\x08" => '\\b',
                        "\x0c" => '\\f',
                        default => sprintf('\\u%04x', ord($character)),
                    };

                    continue;
                }

                $buffer .= $character;
                if ($inString) {
                    if ($escaped) {
                        $escaped = false;
                    } elseif ($character === '\\') {
                        $escaped = true;
                    } elseif ($character === '"') {
                        $inString = false;
                    }
                } elseif ($character === '"') {
                    $inString = true;
                } elseif ($character === '{') {
                    $depth++;
                } elseif ($character === '}') {
                    $depth--;
                    if ($depth === 0) {
                        yield json_decode($buffer, true, flags: JSON_THROW_ON_ERROR);
                        $buffer = '';
                    }
                }
            }
        }

        if ($depth !== 0 || $inString || trim($buffer) !== '') {
            throw new RuntimeException('The data bundle ended with an incomplete JSON record.');
        }
    }
}
