<?php

namespace Tests\Unit;

use App\Services\StudentDiscounts\StudentDiscountMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentDiscountMailServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_email_uses_compact_store_specific_branding_and_real_social_icons(): void
    {
        config()->set('app.url', 'https://admin.example');
        $this->insertStore('store-a', 'Store A');
        foreach ([
            'STUDENT_DISCOUNT_BRAND_NAME' => 'Macfox',
            'STUDENT_DISCOUNT_LOGO_URL' => 'https://cdn.example/macfox.png',
            'STUDENT_DISCOUNT_SHOP_URL' => 'https://macfox.example/',
            'STUDENT_DISCOUNT_SUPPORT_URL' => 'mailto:support@macfox.example',
            'STUDENT_DISCOUNT_INSTAGRAM_URL' => 'https://instagram.com/macfox',
            'STUDENT_DISCOUNT_FACEBOOK_URL' => 'https://facebook.com/macfox',
            'STUDENT_DISCOUNT_TIKTOK_URL' => 'https://tiktok.com/@macfox',
            'STUDENT_DISCOUNT_YOUTUBE_URL' => 'https://youtube.com/@macfox',
        ] as $key => $value) {
            DB::table('StoreConfig')->insert([
                'storeId' => 'store-a',
                'key' => $key,
                'value' => Crypt::encryptString($value),
                'encrypted' => true,
            ]);
        }

        $html = app(StudentDiscountMailService::class)->approvedHtml('store-a', 'TEST-CODE');

        $this->assertStringContainsString('font-size:28px', $html);
        $this->assertStringContainsString('font-size:16px', $html);
        $this->assertStringContainsString('https://cdn.example/macfox.png', $html);
        $this->assertStringContainsString('https://macfox.example/', $html);
        $this->assertStringContainsString('Contact our support team', $html);
        foreach (['instagram', 'facebook', 'tiktok', 'youtube'] as $network) {
            $this->assertStringContainsString("https://admin.example/email-icons/{$network}.png", $html);
        }
    }

    public function test_unconfigured_store_hides_all_optional_email_blocks_without_system_fallback(): void
    {
        $this->insertStore('store-b', 'Store B');
        DB::table('SystemConfig')->insert([
            'key' => 'STUDENT_DISCOUNT_LOGO_URL',
            'value' => Crypt::encryptString('https://system.example/logo.png'),
            'encrypted' => true,
        ]);

        $html = app(StudentDiscountMailService::class)->approvedHtml('store-b', 'STORE-B-CODE');

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('SHOP NOW', $html);
        $this->assertStringNotContainsString('Contact our support team', $html);
        $this->assertStringNotContainsString('/email-icons/', $html);
        $this->assertStringNotContainsString('https://system.example/logo.png', $html);
        $this->assertStringContainsString('next Store B ride', $html);
    }

    private function insertStore(string $id, string $name): void
    {
        DB::table('Store')->insert([
            'id' => $id,
            'slug' => $id,
            'name' => $name,
            'timezone' => 'America/Los_Angeles',
            'status' => 'ACTIVE',
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);
    }
}
