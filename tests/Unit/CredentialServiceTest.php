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
}
