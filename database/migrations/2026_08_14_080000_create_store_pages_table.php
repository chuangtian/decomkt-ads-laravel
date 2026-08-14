<?php

use App\Services\Stores\StoreContext;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('Employee', 'lastStoreId')) {
            Schema::table('Employee', function (Blueprint $table): void {
                $table->string('lastStoreId')->nullable()->after('isDefaultAdmin')->index();
            });
        }
        if (! Schema::hasColumn('StoreMember', 'roleId')) {
            Schema::table('StoreMember', function (Blueprint $table): void {
                $table->string('roleId')->nullable()->after('role')->index();
            });
        }

        Schema::create('StorePage', function (Blueprint $table): void {
            $table->string('storeId');
            $table->string('path');
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->dateTime('createdAt')->useCurrent();
            $table->dateTime('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->primary(['storeId', 'path']);
            $table->index(['storeId', 'enabled', 'position']);
        });

        $now = now();
        $rows = [];
        foreach (DB::table('Store')->pluck('id') as $storeId) {
            foreach (StoreContext::allStorePaths() as $position => $path) {
                $rows[] = compact('storeId', 'path', 'position') + ['enabled' => true, 'createdAt' => $now, 'updatedAt' => $now];
            }
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('StorePage')->insert($chunk);
        }

        // Legacy imports predated real store context. Assign only unowned rows
        // to the original default store; existing explicit ownership is kept.
        foreach ([
            'AiSuggestion', 'AiMemory', 'AiPromptTemplate', 'AiConversation', 'AiMessage', 'AiQuickPrompt',
            'Task', 'SocialMediaPost', 'ReputationReview', 'RedditPost', 'ReputationRisk',
            'ReputationResource', 'ReputationWeeklyReport', 'KolRecommendBatch', 'KolRecommendItem',
            'AmazonOrder', 'AmazonSpAd', 'AmazonSbAd', 'AmazonMonitorLink', 'AmazonManualEntry',
        ] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'storeId')) {
                DB::table($table)->whereNull('storeId')->update(['storeId' => 'default-store']);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('StorePage');
        if (Schema::hasColumn('StoreMember', 'roleId')) {
            Schema::table('StoreMember', fn (Blueprint $table) => $table->dropColumn('roleId'));
        }
        if (Schema::hasColumn('Employee', 'lastStoreId')) {
            Schema::table('Employee', fn (Blueprint $table) => $table->dropColumn('lastStoreId'));
        }
    }
};
