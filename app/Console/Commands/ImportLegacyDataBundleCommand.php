<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ImportLegacyDataBundleCommand extends Command
{
    protected $signature = 'legacy:import-data-bundle {file : JSON Lines export}';

    protected $description = 'Import legacy PostgreSQL business rows into matching MySQL tables';

    public function handle(): int
    {
        $handle = fopen((string) $this->argument('file'), 'rb');
        if ($handle === false) {
            throw new RuntimeException('Cannot open data bundle.');
        }

        $counts = [];
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($this->records($handle) as $record) {
                $table = $record['table'] ?? null;
                $row = $record['row'] ?? null;
                if (! is_string($table) || ! is_array($row) || ! Schema::hasTable($table)) {
                    continue;
                }

                $columns = array_flip(Schema::getColumnListing($table));
                $payload = [];
                foreach ($row as $key => $value) {
                    if (! isset($columns[$key])) {
                        continue;
                    }
                    $payload[$key] = is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : $value;
                }
                if ($payload !== []) {
                    DB::table($table)->insertOrIgnore($payload);
                    $counts[$table] = ($counts[$table] ?? 0) + 1;
                }
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            fclose($handle);
        }

        ksort($counts);
        foreach ($counts as $table => $count) {
            $this->line("{$table}: {$count}");
        }

        return self::SUCCESS;
    }

    /**
     * Read concatenated JSON objects and repair legacy exports containing raw
     * line breaks inside JSON strings. No record contents are logged.
     *
     * @param  resource  $handle
     * @return \Generator<int, array<string, mixed>>
     */
    private function records($handle): \Generator
    {
        $buffer = '';
        $depth = 0;
        $inString = false;
        $escaped = false;
        $recordNumber = 0;

        while (! feof($handle)) {
            $chunk = fread($handle, 65536);
            if ($chunk === false) {
                throw new RuntimeException('Cannot read data bundle.');
            }

            $length = strlen($chunk);
            for ($index = 0; $index < $length; $index++) {
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
                        $recordNumber++;
                        try {
                            yield json_decode($buffer, true, flags: JSON_THROW_ON_ERROR);
                        } catch (\JsonException $exception) {
                            $this->warn("Skipped malformed legacy record {$recordNumber} (".strlen($buffer).' bytes).');
                        }
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
