<?php

namespace App\Services\Shopify;

use Illuminate\Http\Request;
use RuntimeException;

class ShopifyAuthService
{
    /** @return array<string, mixed> */
    public function verifySessionToken(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new RuntimeException('Invalid Shopify session token.');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $signature = hash_hmac('sha256', $encodedHeader.'.'.$encodedPayload, $this->secret(), true);
        if (! hash_equals($signature, $this->decode($encodedSignature))) {
            throw new RuntimeException('Invalid Shopify session token signature.');
        }

        $header = json_decode($this->decode($encodedHeader), true, flags: JSON_THROW_ON_ERROR);
        $payload = json_decode($this->decode($encodedPayload), true, flags: JSON_THROW_ON_ERROR);
        if (($header['alg'] ?? null) !== 'HS256') {
            throw new RuntimeException('Unsupported Shopify session token algorithm.');
        }

        $now = time();
        $audience = $payload['aud'] ?? null;
        if (! in_array((string) config('shopify.client_id'), is_array($audience) ? $audience : [$audience], true)
            || (int) ($payload['exp'] ?? 0) <= $now
            || (int) ($payload['nbf'] ?? 0) > $now + 10) {
            throw new RuntimeException('Expired or invalid Shopify session token.');
        }

        $destination = $this->normalizeShop((string) ($payload['dest'] ?? ''));
        $issuer = $this->normalizeShop((string) ($payload['iss'] ?? ''));
        if (! $destination || $destination !== $issuer) {
            throw new RuntimeException('Shopify session token store mismatch.');
        }
        $payload['shop'] = $destination;

        return $payload;
    }

    public function verifyWebhook(Request $request): bool
    {
        $provided = (string) $request->header('X-Shopify-Hmac-Sha256');
        $expected = base64_encode(hash_hmac('sha256', $request->getContent(), $this->secret(), true));

        return $provided !== '' && hash_equals($expected, $provided);
    }

    public function normalizeShop(string $value): ?string
    {
        $host = strtolower((string) (parse_url(str_contains($value, '://') ? $value : 'https://'.$value, PHP_URL_HOST) ?: ''));

        return preg_match('/^[a-z0-9][a-z0-9-]*\.myshopify\.com$/', $host) ? $host : null;
    }

    private function secret(): string
    {
        $secret = (string) config('shopify.client_secret');
        if ($secret === '') {
            throw new RuntimeException('SHOPIFY_CLIENT_SECRET is not configured.');
        }

        return $secret;
    }

    private function decode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        if ($decoded === false) {
            throw new RuntimeException('Invalid token encoding.');
        }

        return $decoded;
    }
}
