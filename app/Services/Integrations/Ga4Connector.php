<?php

namespace App\Services\Integrations;

use App\Services\Credentials\CredentialService;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class Ga4Connector
{
    public function __construct(private readonly CredentialService $credentials) {}

    public function test(string $storeId = 'default-store'): IntegrationTestResult
    {
        $accessToken = $this->accessToken($storeId);
        $propertyId = $this->credentials->require('GA4_PROPERTY_ID', $storeId);
        $response = Http::withToken($accessToken)->asJson()->timeout(20)->post(
            "https://analyticsdata.googleapis.com/v1beta/properties/{$propertyId}:runReport",
            ['dateRanges' => [['startDate' => '7daysAgo', 'endDate' => 'yesterday']], 'metrics' => [['name' => 'sessions']], 'limit' => 1],
        );

        return new IntegrationTestResult($response->successful(), 'ga4', $response->successful() ? 'GA4 property query succeeded.' : 'GA4 property query failed.', [
            'http_status' => $response->status(),
            'row_count' => $response->json('rowCount', 0),
        ]);
    }

    /** @return array{summary: array<string, float|int>, daily: array<int, array<string, mixed>>} */
    public function overview(string $startDate, string $endDate, string $storeId = 'default-store'): array
    {
        $propertyId = $this->credentials->require('GA4_PROPERTY_ID', $storeId);
        $response = Http::withToken($this->accessToken($storeId))->asJson()->timeout(20)->post(
            "https://analyticsdata.googleapis.com/v1beta/properties/{$propertyId}:runReport",
            ['dateRanges' => [['startDate' => $startDate, 'endDate' => $endDate]], 'dimensions' => [['name' => 'date']], 'metrics' => [['name' => 'sessions'], ['name' => 'activeUsers'], ['name' => 'totalRevenue']], 'metricAggregations' => ['TOTAL'], 'orderBys' => [['dimension' => ['dimensionName' => 'date']]], 'limit' => 500],
        )->throw();
        $daily = [];
        foreach ($response->json('rows', []) as $row) {
            $daily[] = ['date' => $row['dimensionValues'][0]['value'] ?? '', 'sessions' => (int) ($row['metricValues'][0]['value'] ?? 0), 'activeUsers' => (int) ($row['metricValues'][1]['value'] ?? 0), 'revenue' => (float) ($row['metricValues'][2]['value'] ?? 0)];
        }
        $totals = $response->json('totals.0.metricValues', []);

        return ['summary' => ['sessions' => (int) ($totals[0]['value'] ?? 0), 'activeUsers' => (int) ($totals[1]['value'] ?? 0), 'revenue' => (float) ($totals[2]['value'] ?? 0)], 'daily' => $daily];
    }

    private function accessToken(string $storeId): string
    {
        $account = json_decode($this->credentials->require('GA4_SERVICE_ACCOUNT_JSON', $storeId), true, flags: JSON_THROW_ON_ERROR);
        foreach (['client_email', 'private_key', 'token_uri'] as $key) {
            if (empty($account[$key])) {
                throw new RuntimeException("GA4 service account is missing {$key}.");
            }
        }

        $assertion = $this->jwt($account);
        $tokenResponse = Http::asForm()->timeout(15)->retry(2, 300)->post($account['token_uri'], [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $assertion,
        ]);
        $accessToken = $tokenResponse->json('access_token');
        if (! $tokenResponse->successful() || ! is_string($accessToken)) {
            $tokenResponse->throw();
        }

        return $accessToken;
    }

    /** @param array<string, string> $account */
    private function jwt(array $account): string
    {
        $now = time();
        $encode = static fn (array $value): string => rtrim(strtr(base64_encode(json_encode($value, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
        $unsigned = $encode(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$encode([
            'iss' => $account['client_email'], 'sub' => $account['client_email'],
            'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
            'aud' => $account['token_uri'], 'iat' => $now, 'exp' => $now + 3600,
        ]);
        if (! openssl_sign($unsigned, $signature, $account['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Unable to sign GA4 service-account JWT.');
        }

        return $unsigned.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }
}
