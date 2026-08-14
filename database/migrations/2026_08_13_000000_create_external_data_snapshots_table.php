<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ExternalDataSnapshot', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->longText('payload');
            $table->unsignedInteger('recordCount')->default(0);
            $table->timestamp('syncedAt')->nullable();
            $table->text('lastError')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ExternalDataSnapshot');
    }
};
