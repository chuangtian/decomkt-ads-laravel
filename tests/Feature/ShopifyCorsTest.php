<?php

namespace Tests\Feature;

use Tests\TestCase;

class ShopifyCorsTest extends TestCase
{
    public function test_public_storefront_errors_remain_readable_across_origins(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://example-store.myshopify.com',
            'Accept' => 'application/json',
        ])->getJson('/api/v1/public/student-discounts?shop=not-a-shop');

        $response->assertUnprocessable()
            ->assertHeader('Access-Control-Allow-Origin', 'https://example-store.myshopify.com')
            ->assertJsonPath('message', 'Enter a valid Shopify store domain.');
    }

    public function test_shopify_app_home_extension_can_preflight_the_backend_api(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://extensions.shopifycdn.com',
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'authorization,content-type',
        ])->options('/api/v1/shopify/student-discounts');

        $response->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'https://extensions.shopifycdn.com');

        $allowedHeaders = strtolower((string) $response->headers->get('Access-Control-Allow-Headers'));
        $this->assertStringContainsString('authorization', $allowedHeaders);
        $this->assertStringContainsString('content-type', $allowedHeaders);
    }

    public function test_untrusted_origins_are_not_granted_shopify_api_cors_access(): void
    {
        $response = $this->withHeaders([
            'Origin' => 'https://attacker.example',
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'authorization',
        ])->options('/api/v1/shopify/student-discounts');

        $response->assertHeader('Access-Control-Allow-Origin', 'https://extensions.shopifycdn.com');
        $this->assertNotSame(
            'https://attacker.example',
            $response->headers->get('Access-Control-Allow-Origin'),
        );
    }
}
