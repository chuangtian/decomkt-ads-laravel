<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $columns = DB::select(<<<'SQL'
            SELECT TABLE_NAME AS table_name, COLUMN_NAME AS column_name, IS_NULLABLE AS is_nullable
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND DATA_TYPE = 'text'
            SQL);

        foreach ($columns as $column) {
            $table = str_replace('`', '``', $column->table_name);
            $name = str_replace('`', '``', $column->column_name);
            $nullable = $column->is_nullable === 'YES' ? 'NULL' : 'NOT NULL';

            DB::statement("ALTER TABLE `{$table}` MODIFY COLUMN `{$name}` LONGTEXT {$nullable}");
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive: imported PostgreSQL text can exceed 64 KiB.
    }
};
