<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('User', function (Blueprint $table) {
            $table->id('id');
            $table->string('email')->unique();
            $table->string('name')->nullable();
        });

        Schema::create('Post', function (Blueprint $table) {
            $table->id('id');
            $table->string('title');
            $table->string('content')->nullable();
            $table->boolean('published')->default(false);
            $table->integer('authorId');
        });

        Schema::create('Employee', function (Blueprint $table) {
            $table->id('id');
            $table->string('username')->unique();
            $table->string('passwordHash');
            $table->string('name');
            $table->string('email')->nullable();
            $table->boolean('emailVerified')->default(false);
            $table->string('emailVerificationToken')->nullable();
            $table->string('mobile')->nullable();
            $table->string('avatarUrl')->nullable();
            $table->string('jobTitle')->nullable();
            $table->string('status');
            $table->boolean('isDefaultAdmin')->default(false);
            $table->dateTime('createdAt')->useCurrent();
            $table->dateTime('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->index(['name']);
            $table->index(['email']);
            $table->index(['status']);
        });

        Schema::create('Store', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('logoUrl')->nullable();
            $table->string('timezone')->default('America/Los_Angeles');
            $table->string('status');
            $table->dateTime('createdAt')->useCurrent();
            $table->dateTime('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->index(['status']);
        });

        Schema::create('StudentDiscountCampaign', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('storeId')->unique();
            $table->boolean('enabled')->default(false);
            $table->boolean('schoolEmailEnabled')->default(true);
            $table->boolean('universityEnabled')->default(true);
            $table->boolean('studentIdEnabled')->default(true);
            $table->string('educationDomains');
            $table->string('title')->default('Student discount');
            $table->text('description');
            $table->string('schoolEmailLabel')->default('School email');
            $table->string('schoolNameLabel')->default('School name');
            $table->text('consentLabel');
            $table->string('buttonLabel')->default('Get my discount');
            $table->string('successTitle')->default('Your student discount is ready');
            $table->text('successMessage');
            $table->text('terms');
            $table->string('discountType');
            $table->decimal('discountValue', 20, 4)->default(10);
            $table->string('currencyCode')->default('USD');
            $table->decimal('minimumSubtotal', 20, 4)->nullable();
            $table->string('codePrefix')->default('STUDENT');
            $table->integer('validityDays')->default(30);
            $table->string('customerTag')->default('student-self-attested');
            $table->string('accentColor')->default('#111111');
            $table->string('backgroundColor')->default('#ffffff');
            $table->string('allowedOrigins');
            $table->boolean('combinesWithProduct')->default(false);
            $table->boolean('combinesWithOrder')->default(false);
            $table->boolean('combinesWithShipping')->default(false);
            $table->dateTime('createdAt')->useCurrent();
            $table->dateTime('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->index(['enabled']);
        });

        Schema::create('StudentDiscountClaim', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('storeId');
            $table->string('campaignId');
            $table->string('email');
            $table->string('emailNormalized');
            $table->string('schoolName');
            $table->string('fullName')->nullable();
            $table->string('verificationMethod')->default('EDUCATION_EMAIL');
            $table->string('evidencePath')->nullable();
            $table->string('code')->unique();
            $table->string('shopifyDiscountId')->nullable();
            $table->string('shopifyCustomerId')->nullable();
            $table->string('ipHash')->nullable();
            $table->string('status');
            $table->text('error')->nullable();
            $table->dateTime('expiresAt')->nullable();
            $table->dateTime('reviewedAt')->nullable();
            $table->string('reviewedBy')->nullable();
            $table->dateTime('emailSentAt')->nullable();
            $table->dateTime('createdAt')->useCurrent();
            $table->dateTime('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['campaignId', 'emailNormalized']);
            $table->index(['storeId', 'createdAt']);
            $table->index(['status']);
        });

        Schema::create('StoreMember', function (Blueprint $table) {
            $table->string('storeId');
            $table->integer('employeeId');
            $table->string('role')->default('MEMBER');
            $table->dateTime('assignedAt')->useCurrent();
            $table->primary(['storeId', 'employeeId']);
            $table->index(['employeeId']);
        });

        Schema::create('StoreConfig', function (Blueprint $table) {
            $table->string('storeId');
            $table->string('key');
            $table->text('value');
            $table->boolean('encrypted')->default(true);
            $table->dateTime('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->primary(['storeId', 'key']);
            $table->index(['storeId']);
        });

        Schema::create('Role', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('key')->unique();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->boolean('isSystem')->default(false);
            $table->dateTime('createdAt')->useCurrent();
            $table->dateTime('updatedAt')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('Permission', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('module');
            $table->string('description')->nullable();
            $table->dateTime('createdAt')->useCurrent();
            $table->dateTime('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->index(['module']);
        });

        Schema::create('EmployeeRole', function (Blueprint $table) {
            $table->integer('employeeId');
            $table->string('roleId');
            $table->dateTime('assignedAt')->useCurrent();
            $table->primary(['employeeId', 'roleId']);
        });

        Schema::create('RolePermission', function (Blueprint $table) {
            $table->string('roleId');
            $table->string('permissionId');
            $table->dateTime('assignedAt')->useCurrent();
            $table->primary(['roleId', 'permissionId']);
        });

        Schema::create('EmployeePermission', function (Blueprint $table) {
            $table->integer('employeeId');
            $table->string('permissionId');
            $table->dateTime('assignedAt')->useCurrent();
            $table->primary(['employeeId', 'permissionId']);
        });

        Schema::create('AiSuggestion', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('storeId')->nullable();
            $table->string('module');
            $table->string('provider');
            $table->string('priority');
            $table->string('text');
            $table->dateTime('createdAt')->useCurrent();
            $table->index(['module', 'provider']);
            $table->index(['module', 'createdAt']);
            $table->index(['storeId']);
        });

        Schema::create('AiPromptTemplate', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('storeId')->nullable();
            $table->string('module');
            $table->string('name');
            $table->text('content');
            $table->boolean('active')->default(false);
            $table->dateTime('createdAt')->useCurrent();
            $table->dateTime('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->index(['module', 'active']);
            $table->index(['storeId']);
        });

        Schema::create('SystemConfig', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value');
            $table->boolean('encrypted')->default(true);
            $table->dateTime('updatedAt')->useCurrent()->useCurrentOnUpdate();
        });

        Schema::create('Task', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('storeId')->nullable();
            $table->string('title');
            $table->string('description')->nullable();
            $table->string('status');
            $table->string('priority');
            $table->string('department')->nullable();
            $table->dateTime('dueDate')->nullable();
            $table->integer('creatorId');
            $table->dateTime('createdAt')->useCurrent();
            $table->dateTime('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->index(['status']);
            $table->index(['creatorId']);
            $table->index(['dueDate']);
            $table->index(['storeId']);
        });

        Schema::create('TaskAssignee', function (Blueprint $table) {
            $table->string('taskId');
            $table->integer('employeeId');
            $table->dateTime('assignedAt')->useCurrent();
            $table->primary(['taskId', 'employeeId']);
            $table->index(['employeeId']);
        });

        Schema::create('Notification', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('type');
            $table->string('title');
            $table->string('content');
            $table->boolean('isRead')->default(false);
            $table->integer('recipientId');
            $table->string('taskId')->nullable();
            $table->dateTime('createdAt')->useCurrent();
            $table->index(['recipientId', 'isRead']);
            $table->index(['recipientId', 'createdAt']);
        });

        Schema::create('AiConversation', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('storeId')->nullable();
            $table->string('title')->default('新对话');
            $table->string('provider')->default('gemini');
            $table->boolean('pinned')->default(false);
            $table->integer('ownerId');
            $table->dateTime('createdAt')->useCurrent();
            $table->dateTime('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->index(['ownerId', 'updatedAt']);
            $table->index(['ownerId', 'pinned']);
            $table->index(['storeId']);
        });

        Schema::create('AiMessage', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('conversationId');
            $table->string('role');
            $table->text('content');
            $table->string('provider')->nullable();
            $table->dateTime('createdAt')->useCurrent();
            $table->index(['conversationId', 'createdAt']);
        });

        Schema::create('AiMemory', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('storeId')->nullable();
            $table->integer('ownerId');
            $table->string('type');
            $table->string('key');
            $table->text('value');
            $table->text('metadata')->nullable();
            $table->dateTime('expiresAt')->nullable();
            $table->dateTime('createdAt')->useCurrent();
            $table->dateTime('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['storeId', 'ownerId', 'key']);
            $table->index(['ownerId', 'type']);
            $table->index(['ownerId', 'updatedAt']);
            $table->index(['storeId']);
        });

        Schema::create('AiQuickPrompt', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('storeId')->nullable();
            $table->integer('ownerId');
            $table->string('text');
            $table->string('category')->nullable();
            $table->double('score')->default(0);
            $table->integer('usedCount')->default(0);
            $table->dateTime('createdAt')->useCurrent();
            $table->dateTime('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->index(['ownerId', 'score']);
            $table->index(['ownerId', 'updatedAt']);
            $table->index(['storeId']);
        });

        Schema::create('SocialWeeklyReport', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('storeId')->nullable();
            $table->string('weekLabel');
            $table->string('startDate');
            $table->string('endDate');
            $table->dateTime('createdAt')->useCurrent();
            $table->dateTime('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['storeId', 'weekLabel']);
            $table->index(['startDate']);
            $table->index(['storeId']);
        });

        Schema::create('SocialWeeklyPost', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('reportId');
            $table->string('postId');
            $table->string('accountId');
            $table->string('accountHandle');
            $table->string('accountName');
            $table->text('description');
            $table->integer('duration')->default(0);
            $table->string('publishedAt');
            $table->string('permalink');
            $table->string('postType');
            $table->integer('views')->default(0);
            $table->integer('likes')->default(0);
            $table->integer('shares')->default(0);
            $table->integer('comments')->default(0);
            $table->integer('saves')->default(0);
            $table->integer('reach')->default(0);
            $table->integer('followers')->default(0);
            $table->dateTime('createdAt')->useCurrent();
            $table->index(['reportId']);
            $table->index(['accountHandle']);
        });

        Schema::create('SocialDailyReview', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('storeId')->nullable();
            $table->dateTime('date');
            $table->text('metrics');
            $table->text('content');
            $table->text('actions');
            $table->boolean('isDraft')->default(false);
            $table->integer('creatorId');
            $table->dateTime('createdAt')->useCurrent();
            $table->dateTime('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['storeId', 'date', 'creatorId']);
            $table->index(['creatorId', 'createdAt']);
            $table->index(['creatorId', 'isDraft']);
            $table->index(['storeId']);
        });

        Schema::create('YouTubeToken', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('storeId')->nullable();
            $table->string('channelId');
            $table->string('channelTitle');
            $table->text('accessToken');
            $table->text('refreshToken');
            $table->dateTime('expiresAt');
            $table->dateTime('createdAt')->useCurrent();
            $table->dateTime('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['storeId', 'channelId']);
            $table->index(['storeId']);
        });

        Schema::create('SocialMediaPost', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('storeId')->nullable();
            $table->string('postId');
            $table->string('accountId');
            $table->string('accountHandle');
            $table->string('accountName');
            $table->text('description');
            $table->integer('duration')->default(0);
            $table->string('publishedAt');
            $table->string('permalink');
            $table->string('postType');
            $table->string('dataNote')->nullable();
            $table->string('dateLabel')->nullable();
            $table->integer('views')->default(0);
            $table->integer('likes')->default(0);
            $table->integer('shares')->default(0);
            $table->integer('comments')->default(0);
            $table->integer('saves')->default(0);
            $table->integer('reach')->nullable();
            $table->integer('followers')->nullable();
            $table->string('platform')->default('instagram');
            $table->string('importBatch')->nullable();
            $table->dateTime('createdAt')->useCurrent();
            $table->unique(['storeId', 'postId', 'platform']);
            $table->index(['platform']);
            $table->index(['publishedAt']);
            $table->index(['accountHandle']);
            $table->index(['storeId']);
        });

        Schema::create('FacebookPost', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('storeId')->nullable();
            $table->string('postId');
            $table->string('pageId');
            $table->string('pageName');
            $table->text('title');
            $table->text('description');
            $table->integer('duration')->default(0);
            $table->string('publishedAt');
            $table->string('permalink');
            $table->string('postType');
            $table->string('dataNote')->nullable();
            $table->string('dateLabel')->nullable();
            $table->integer('views')->default(0);
            $table->integer('reach')->default(0);
            $table->integer('reactions')->default(0);
            $table->integer('comments')->default(0);
            $table->integer('shares')->default(0);
            $table->integer('totalClicks')->default(0);
            $table->integer('otherClicks')->default(0);
            $table->integer('photoClicks')->default(0);
            $table->integer('linkClicks')->default(0);
            $table->integer('negativeFeedback')->default(0);
            $table->string('importBatch')->nullable();
            $table->dateTime('createdAt')->useCurrent();
            $table->unique(['storeId', 'postId']);
            $table->index(['publishedAt']);
            $table->index(['storeId']);
        });

        Schema::create('ReputationReview', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('storeId')->nullable();
            $table->string('platform');
            $table->text('content');
            $table->integer('star');
            $table->integer('week')->nullable();
            $table->dateTime('publishDate')->nullable();
            $table->string('sentiment');
            $table->string('tags')->nullable();
            $table->string('aiTags')->nullable();
            $table->string('sku')->nullable();
            $table->string('orderNumber')->nullable();
            $table->boolean('replied')->default(false);
            $table->text('replyNote')->nullable();
            $table->string('source')->default('manual');
            $table->dateTime('createdAt')->useCurrent();
            $table->dateTime('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->index(['platform', 'star']);
            $table->index(['platform', 'sentiment']);
            $table->index(['platform', 'createdAt']);
            $table->index(['platform', 'replied']);
            $table->index(['week']);
            $table->index(['publishDate']);
            $table->index(['storeId']);
        });

        Schema::create('RedditPost', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('storeId')->nullable();
            $table->string('postUrl')->nullable();
            $table->string('subreddit')->nullable();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->integer('upvotes')->default(0);
            $table->integer('views')->default(0);
            $table->integer('commentCount')->default(0);
            $table->text('commentTexts')->nullable();
            $table->text('rawExport')->nullable();
            $table->dateTime('postDate')->nullable();
            $table->integer('week')->nullable();
            $table->string('sentiment');
            $table->string('tags')->nullable();
            $table->string('topicCategory')->nullable();
            $table->string('actionableOutcome')->nullable();
            $table->boolean('replied')->default(false);
            $table->text('replyNote')->nullable();
            $table->string('source')->default('manual');
            $table->dateTime('createdAt')->useCurrent();
            $table->dateTime('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->index(['subreddit']);
            $table->index(['sentiment']);
            $table->index(['createdAt']);
            $table->index(['week']);
            $table->index(['replied']);
            $table->index(['upvotes']);
            $table->index(['topicCategory']);
            $table->index(['storeId']);
        });

        Schema::create('ReputationRisk', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('storeId')->nullable();
            $table->text('description');
            $table->string('level');
            $table->string('platform');
            $table->string('status');
            $table->text('suggestion')->nullable();
            $table->integer('week')->nullable();
            $table->string('source')->default('manual');
            $table->dateTime('createdAt')->useCurrent();
            $table->dateTime('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->index(['level']);
            $table->index(['status']);
            $table->index(['week']);
            $table->index(['createdAt']);
            $table->index(['storeId']);
        });

        Schema::create('ReputationResource', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('storeId')->nullable();
            $table->text('description');
            $table->string('type');
            $table->string('priority');
            $table->string('assignee')->nullable();
            $table->integer('week')->nullable();
            $table->dateTime('createdAt')->useCurrent();
            $table->dateTime('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->index(['type']);
            $table->index(['priority']);
            $table->index(['week']);
            $table->index(['storeId']);
        });

        Schema::create('ReputationWeeklyReport', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('storeId')->nullable();
            $table->integer('week');
            $table->integer('year');
            $table->string('reporter')->nullable();
            $table->string('reportTo')->nullable();
            $table->text('content');
            $table->double('overallScore')->nullable();
            $table->double('tpAvg')->nullable();
            $table->double('websiteAvg')->nullable();
            $table->integer('redditPosts')->nullable();
            $table->integer('negativeCount')->nullable();
            $table->dateTime('createdAt')->useCurrent();
            $table->dateTime('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['storeId', 'year', 'week']);
            $table->index(['year', 'week']);
            $table->index(['storeId']);
        });

        Schema::create('KolProspect', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('storeId')->nullable();
            $table->string('name');
            $table->string('platform');
            $table->string('handle')->nullable();
            $table->string('followers')->nullable();
            $table->string('niche')->nullable();
            $table->double('avgER')->nullable();
            $table->string('estPrice')->nullable();
            $table->text('reason')->nullable();
            $table->string('source')->default('ai');
            $table->string('status')->default('候选中');
            $table->string('priority')->default('中');
            $table->string('profileUrl')->nullable();
            $table->text('note')->nullable();
            $table->dateTime('createdAt')->useCurrent();
            $table->dateTime('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->index(['status']);
            $table->index(['platform']);
            $table->index(['source']);
            $table->index(['storeId']);
        });

        Schema::create('KolRecommendBatch', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('storeId')->nullable();
            $table->text('context')->nullable();
            $table->string('provider')->default('gemini');
            $table->dateTime('createdAt')->useCurrent();
            $table->index(['createdAt']);
            $table->index(['storeId']);
        });

        Schema::create('KolRecommendItem', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('batchId');
            $table->string('name');
            $table->string('platform');
            $table->string('followers')->nullable();
            $table->string('niche')->nullable();
            $table->double('avgER')->nullable();
            $table->string('estPrice')->nullable();
            $table->text('reason')->nullable();
            $table->string('priority')->default('中');
            $table->string('profileUrl')->nullable();
            $table->integer('matchScore')->nullable();
            $table->string('feedback')->nullable();
            $table->boolean('addedToPool')->default(false);
            $table->dateTime('createdAt')->useCurrent();
            $table->index(['batchId']);
            $table->index(['feedback']);
            $table->index(['name', 'platform']);
        });

        Schema::create('SeoMonthly', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('storeId')->nullable();
            $table->string('month');
            $table->text('data');
            $table->dateTime('syncedAt')->useCurrent();
            $table->dateTime('createdAt')->useCurrent();
            $table->dateTime('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['storeId', 'month']);
            $table->index(['month']);
            $table->index(['storeId']);
        });

        Schema::create('SeoWeekly', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('storeId')->nullable();
            $table->string('startDate');
            $table->string('label')->nullable();
            $table->text('data');
            $table->dateTime('syncedAt')->useCurrent();
            $table->dateTime('createdAt')->useCurrent();
            $table->dateTime('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['storeId', 'startDate']);
            $table->index(['startDate']);
            $table->index(['storeId']);
        });

        Schema::create('SeoDaily', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('storeId')->nullable();
            $table->string('date');
            $table->text('data');
            $table->dateTime('syncedAt')->useCurrent();
            $table->dateTime('createdAt')->useCurrent();
            $table->dateTime('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['storeId', 'date']);
            $table->index(['date']);
            $table->index(['storeId']);
        });

        Schema::create('AmazonOrder', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('storeId')->nullable();
            $table->string('store')->default('MF');
            $table->string('amazonOrderId');
            $table->string('merchantOrderId')->nullable();
            $table->dateTime('purchaseDate');
            $table->dateTime('lastUpdatedDate')->nullable();
            $table->string('orderStatus');
            $table->string('fulfillmentChannel')->nullable();
            $table->string('salesChannel')->nullable();
            $table->text('productName')->nullable();
            $table->string('sku')->nullable();
            $table->string('asin')->nullable();
            $table->string('itemStatus')->nullable();
            $table->integer('quantity')->default(0);
            $table->string('currency')->nullable();
            $table->double('itemPrice')->nullable();
            $table->double('itemTax')->nullable();
            $table->double('shippingPrice')->nullable();
            $table->double('shippingTax')->nullable();
            $table->string('shipCity')->nullable();
            $table->string('shipState')->nullable();
            $table->string('shipPostalCode')->nullable();
            $table->string('shipCountry')->nullable();
            $table->boolean('isBusinessOrder')->default(false);
            $table->string('importBatch')->nullable();
            $table->dateTime('createdAt')->useCurrent();
            $table->index(['amazonOrderId']);
            $table->index(['purchaseDate']);
            $table->index(['orderStatus']);
            $table->index(['sku']);
            $table->index(['asin']);
            $table->index(['store']);
            $table->index(['storeId']);
        });

        Schema::create('AmazonSpAd', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('storeId')->nullable();
            $table->string('store')->default('MF');
            $table->string('date');
            $table->string('portfolioName')->nullable();
            $table->string('currency')->nullable();
            $table->string('campaignName')->nullable();
            $table->string('adGroupName')->nullable();
            $table->string('retailer')->nullable();
            $table->string('country')->nullable();
            $table->string('sku')->nullable();
            $table->string('asin')->nullable();
            $table->integer('impressions')->default(0);
            $table->integer('clicks')->default(0);
            $table->double('ctr')->nullable();
            $table->double('cpc')->nullable();
            $table->double('spend')->default(0);
            $table->double('sales7d')->default(0);
            $table->double('acos')->nullable();
            $table->double('roas')->nullable();
            $table->integer('orders7d')->default(0);
            $table->integer('units7d')->default(0);
            $table->double('conversionRate')->nullable();
            $table->integer('skuUnits7d')->default(0);
            $table->integer('otherSkuUnits7d')->default(0);
            $table->double('skuSales7d')->default(0);
            $table->double('otherSkuSales7d')->default(0);
            $table->string('importBatch')->nullable();
            $table->dateTime('createdAt')->useCurrent();
            $table->index(['date']);
            $table->index(['campaignName']);
            $table->index(['sku']);
            $table->index(['asin']);
            $table->index(['store']);
            $table->index(['storeId']);
        });

        Schema::create('AmazonSbAd', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('storeId')->nullable();
            $table->string('store')->default('MF');
            $table->string('date');
            $table->string('portfolioName')->nullable();
            $table->string('currency')->nullable();
            $table->string('campaignName')->nullable();
            $table->string('adGroupName')->nullable();
            $table->string('targeting')->nullable();
            $table->string('matchType')->nullable();
            $table->string('costType')->nullable();
            $table->integer('impressions')->default(0);
            $table->string('topOfSearchShare')->nullable();
            $table->integer('viewableImpressions')->default(0);
            $table->integer('clicks')->default(0);
            $table->double('ctr')->nullable();
            $table->double('spend')->default(0);
            $table->double('cpc')->nullable();
            $table->double('vcpm')->nullable();
            $table->double('acos')->nullable();
            $table->double('roas')->nullable();
            $table->double('sales14d')->default(0);
            $table->integer('orders14d')->default(0);
            $table->integer('units14d')->default(0);
            $table->double('conversionRate')->nullable();
            $table->double('vtr')->nullable();
            $table->double('vctr')->nullable();
            $table->integer('brandSearches14d')->default(0);
            $table->integer('dpv14d')->default(0);
            $table->integer('newBuyerOrders14d')->default(0);
            $table->string('importBatch')->nullable();
            $table->dateTime('createdAt')->useCurrent();
            $table->index(['date']);
            $table->index(['campaignName']);
            $table->index(['targeting']);
            $table->index(['matchType']);
            $table->index(['store']);
            $table->index(['storeId']);
        });

        Schema::create('AmazonMonitorLink', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('storeId')->nullable();
            $table->string('asin');
            $table->string('productName');
            $table->string('marketplace')->default('US');
            $table->boolean('enabled')->default(true);
            $table->dateTime('createdAt')->useCurrent();
            $table->dateTime('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['storeId', 'asin']);
            $table->index(['enabled']);
            $table->index(['marketplace']);
            $table->index(['storeId']);
        });

        Schema::create('AmazonMonitorSnapshot', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('storeId')->nullable();
            $table->string('linkId');
            $table->string('asin');
            $table->double('price')->nullable();
            $table->double('rating')->nullable();
            $table->integer('reviews')->nullable();
            $table->integer('activeVariations')->nullable();
            $table->string('buyBox')->nullable();
            $table->string('availability')->nullable();
            $table->string('cartStatus')->nullable();
            $table->string('seller')->nullable();
            $table->integer('bsr')->nullable();
            $table->integer('bigCategoryRank')->nullable();
            $table->integer('smallCategoryRank')->nullable();
            $table->string('status')->default('正常');
            $table->string('issues')->nullable();
            $table->dateTime('checkedAt')->useCurrent();
            $table->index(['linkId', 'checkedAt']);
            $table->index(['asin', 'checkedAt']);
            $table->index(['checkedAt']);
            $table->index(['storeId']);
        });

        Schema::create('SeoGaCache', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('storeId')->nullable();
            $table->string('cacheKey');
            $table->string('startDate');
            $table->string('endDate');
            $table->text('filters');
            $table->longText('rows');
            $table->longText('totals');
            $table->longText('trendData');
            $table->integer('rowCount')->default(0);
            $table->dateTime('createdAt')->useCurrent();
            $table->dateTime('updatedAt')->useCurrent()->useCurrentOnUpdate();
            $table->unique(['storeId', 'cacheKey']);
            $table->index(['startDate', 'endDate']);
            $table->index(['createdAt']);
            $table->index(['storeId']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('SeoGaCache');
        Schema::dropIfExists('AmazonMonitorSnapshot');
        Schema::dropIfExists('AmazonMonitorLink');
        Schema::dropIfExists('AmazonSbAd');
        Schema::dropIfExists('AmazonSpAd');
        Schema::dropIfExists('AmazonOrder');
        Schema::dropIfExists('SeoDaily');
        Schema::dropIfExists('SeoWeekly');
        Schema::dropIfExists('SeoMonthly');
        Schema::dropIfExists('KolRecommendItem');
        Schema::dropIfExists('KolRecommendBatch');
        Schema::dropIfExists('KolProspect');
        Schema::dropIfExists('ReputationWeeklyReport');
        Schema::dropIfExists('ReputationResource');
        Schema::dropIfExists('ReputationRisk');
        Schema::dropIfExists('RedditPost');
        Schema::dropIfExists('ReputationReview');
        Schema::dropIfExists('FacebookPost');
        Schema::dropIfExists('SocialMediaPost');
        Schema::dropIfExists('YouTubeToken');
        Schema::dropIfExists('SocialDailyReview');
        Schema::dropIfExists('SocialWeeklyPost');
        Schema::dropIfExists('SocialWeeklyReport');
        Schema::dropIfExists('AiQuickPrompt');
        Schema::dropIfExists('AiMemory');
        Schema::dropIfExists('AiMessage');
        Schema::dropIfExists('AiConversation');
        Schema::dropIfExists('Notification');
        Schema::dropIfExists('TaskAssignee');
        Schema::dropIfExists('Task');
        Schema::dropIfExists('SystemConfig');
        Schema::dropIfExists('AiPromptTemplate');
        Schema::dropIfExists('AiSuggestion');
        Schema::dropIfExists('EmployeePermission');
        Schema::dropIfExists('RolePermission');
        Schema::dropIfExists('EmployeeRole');
        Schema::dropIfExists('Permission');
        Schema::dropIfExists('Role');
        Schema::dropIfExists('StoreConfig');
        Schema::dropIfExists('StoreMember');
        Schema::dropIfExists('StudentDiscountClaim');
        Schema::dropIfExists('StudentDiscountCampaign');
        Schema::dropIfExists('Store');
        Schema::dropIfExists('Employee');
        Schema::dropIfExists('Post');
        Schema::dropIfExists('User');
    }
};
