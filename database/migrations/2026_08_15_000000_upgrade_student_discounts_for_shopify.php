<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('StudentDiscountCampaign', function (Blueprint $table): void {
            $table->string('discountTarget')->default('ALL_PRODUCTS')->after('discountValue');
            $table->json('discountProductIds')->nullable()->after('discountTarget');
            $table->json('discountCollectionIds')->nullable()->after('discountProductIds');
            $table->unsignedInteger('usageLimit')->default(1)->after('validityDays');
        });

        Schema::table('StudentDiscountClaim', function (Blueprint $table): void {
            $table->dropUnique(['campaignId', 'emailNormalized']);
            $table->index(['campaignId', 'emailNormalized'], 'student_claim_campaign_email_history');
            $table->string('code')->nullable()->change();
            $table->string('emailDeliveryStatus')->nullable()->after('emailSentAt');
            $table->string('reviewSource')->nullable()->after('reviewedBy');
            $table->string('aiProvider')->nullable()->after('reviewSource');
            $table->string('aiModel')->nullable()->after('aiProvider');
            $table->decimal('aiConfidence', 5, 4)->nullable()->after('aiModel');
            $table->text('aiReason')->nullable()->after('aiConfidence');
        });

        Schema::create('ShopifyInstallation', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('storeId')->unique();
            $table->string('shopDomain')->unique();
            $table->string('clientId');
            $table->string('status')->default('INSTALLED');
            $table->text('scopes')->nullable();
            $table->text('accessToken')->nullable();
            $table->text('refreshToken')->nullable();
            $table->dateTime('accessTokenExpiresAt')->nullable();
            $table->dateTime('refreshTokenExpiresAt')->nullable();
            $table->dateTime('installedAt')->nullable();
            $table->dateTime('uninstalledAt')->nullable();
            $table->dateTime('lastSeenAt')->nullable();
            $table->dateTime('createdAt')->useCurrent();
            $table->dateTime('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ShopifyInstallation');

        Schema::table('StudentDiscountClaim', function (Blueprint $table): void {
            $table->dropIndex('student_claim_campaign_email_history');
            $table->dropColumn([
                'emailDeliveryStatus', 'reviewSource', 'aiProvider', 'aiModel',
                'aiConfidence', 'aiReason',
            ]);
            $table->unique(['campaignId', 'emailNormalized']);
        });

        Schema::table('StudentDiscountCampaign', function (Blueprint $table): void {
            $table->dropColumn(['discountTarget', 'discountProductIds', 'discountCollectionIds', 'usageLimit']);
        });
    }
};
