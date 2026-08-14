<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

class AuditCredentialsCommand extends Command
{
    protected $signature = 'credentials:audit';

    protected $description = 'Verify encrypted configuration without exposing credential values';

    public function handle(): int
    {
        $rows = DB::table('StoreConfig')->select('value')
            ->get()
            ->concat(DB::table('SystemConfig')->select('value')->get());
        $valid = 0;
        $invalid = 0;

        foreach ($rows as $row) {
            try {
                Crypt::decryptString($row->value);
                $valid++;
            } catch (Throwable) {
                $invalid++;
            }
        }

        $this->line(json_encode([
            'configured' => $rows->count(),
            'decrypt_ok' => $valid,
            'decrypt_failed' => $invalid,
        ], JSON_THROW_ON_ERROR));

        return $invalid === 0 ? self::SUCCESS : self::FAILURE;
    }
}
