<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $store = DB::table('Store')->where('id', 'default-store')->first(['id', 'name', 'slug']);
        if (! $store || $store->slug !== 'default') {
            return;
        }

        $slug = Str::slug($store->name) ?: 'default-store';
        if (DB::table('Store')->where('id', '!=', $store->id)->where('slug', $slug)->exists()) {
            $slug .= '-store';
        }

        DB::table('Store')->where('id', $store->id)->update([
            'slug' => $slug,
            'updatedAt' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('Store')->where('id', 'default-store')->update([
            'slug' => 'default',
            'updatedAt' => now(),
        ]);
    }
};
