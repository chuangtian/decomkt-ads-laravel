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
}
