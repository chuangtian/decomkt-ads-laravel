<?php

namespace Tests\Unit;

use App\Services\Credentials\CredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CredentialServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_credentials_override_system_credentials_and_are_decrypted(): void
    {
        DB::table('SystemConfig')->insert(['key' => 'EXAMPLE_KEY', 'value' => Crypt::encryptString('system'), 'encrypted' => true]);
        DB::table('StoreConfig')->insert(['storeId' => 'store-a', 'key' => 'EXAMPLE_KEY', 'value' => Crypt::encryptString('store'), 'encrypted' => true]);

        $credentials = app(CredentialService::class);

        $this->assertSame('store', $credentials->get('EXAMPLE_KEY', 'store-a'));
        $this->assertSame('system', $credentials->get('EXAMPLE_KEY', 'store-b'));
    }

    public function test_plain_legacy_values_are_supported_during_migration(): void
    {
        DB::table('SystemConfig')->insert(['key' => 'PLAIN_KEY', 'value' => 'plain-value', 'encrypted' => false]);

        $this->assertSame('plain-value', app(CredentialService::class)->get('PLAIN_KEY'));
    }

    public function test_mail_credentials_fall_back_to_environment_backed_configuration(): void
    {
        config()->set('mail.mailers.smtp.host', 'smtp.env.example');
        DB::table('SystemConfig')->insert([
            'key' => 'MAIL_HOST',
            'value' => Crypt::encryptString('smtp.legacy.example'),
            'encrypted' => true,
        ]);

        $this->assertSame(
            'smtp.env.example',
            app(CredentialService::class)->get('MAIL_HOST', 'store-without-mail-settings'),
        );
    }

    public function test_store_mail_credentials_override_environment_backed_configuration(): void
    {
        config()->set('mail.mailers.smtp.password', 'environment-password');
        DB::table('StoreConfig')->insert([
            'storeId' => 'store-a',
            'key' => 'MAIL_PASSWORD',
            'value' => Crypt::encryptString('store-password'),
            'encrypted' => true,
        ]);

        $credentials = app(CredentialService::class);

        $this->assertSame('store-password', $credentials->get('MAIL_PASSWORD', 'store-a'));
        $this->assertSame('environment-password', $credentials->get('MAIL_PASSWORD', 'store-b'));
    }

    public function test_store_only_credentials_never_fall_back_to_system_values(): void
    {
        DB::table('SystemConfig')->insert([
            'key' => 'STUDENT_DISCOUNT_LOGO_URL',
            'value' => Crypt::encryptString('https://system.example/logo.png'),
            'encrypted' => true,
        ]);
        DB::table('StoreConfig')->insert([
            'storeId' => 'store-a',
            'key' => 'STUDENT_DISCOUNT_LOGO_URL',
            'value' => Crypt::encryptString('https://store-a.example/logo.png'),
            'encrypted' => true,
        ]);

        $credentials = app(CredentialService::class);

        $this->assertSame('https://store-a.example/logo.png', $credentials->getStore('STUDENT_DISCOUNT_LOGO_URL', 'store-a'));
        $this->assertNull($credentials->getStore('STUDENT_DISCOUNT_LOGO_URL', 'store-b'));
    }

    public function test_forget_clears_the_store_only_credential_cache(): void
    {
        DB::table('StoreConfig')->insert([
            'storeId' => 'store-a',
            'key' => 'STUDENT_DISCOUNT_BRAND_NAME',
            'value' => Crypt::encryptString('Old name'),
            'encrypted' => true,
        ]);
        $credentials = app(CredentialService::class);
        $this->assertSame('Old name', $credentials->getStore('STUDENT_DISCOUNT_BRAND_NAME', 'store-a'));

        DB::table('StoreConfig')->where([
            'storeId' => 'store-a',
            'key' => 'STUDENT_DISCOUNT_BRAND_NAME',
        ])->update(['value' => Crypt::encryptString('New name')]);

        $credentials->forget('STUDENT_DISCOUNT_BRAND_NAME', 'store-a');

        $this->assertSame('New name', $credentials->getStore('STUDENT_DISCOUNT_BRAND_NAME', 'store-a'));
    }
}
