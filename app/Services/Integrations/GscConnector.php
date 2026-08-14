<?php

namespace App\Services\Integrations;

use App\Services\Credentials\CredentialService;
use Illuminate\Support\Facades\Http;

class GscConnector
{
    public function __construct(private readonly CredentialService $credentials) {}

    public function test(string $storeId = 'default-store'): IntegrationTestResult
    {
        $accessToken = $this->accessToken($storeId);
        $siteUrl = $this->credentials->require('GSC_SITE_URL', $storeId);
        $response = Http::withToken($accessToken)->timeout(15)->get('https://www.googleapis.com/webmasters/v3/sites');
        $sites = $response->json('siteEntry', []);
        $sites = is_array($sites) ? $sites : [];
        $configuredIdentity = $this->siteIdentity($siteUrl);
        $matched = false;
        foreach ($sites as $site) {
            if (is_array($site) && $this->siteIdentity((string) ($site['siteUrl'] ?? '')) === $configuredIdentity) {
                $matched = true;
                break;
            }
        }

        return new IntegrationTestResult($response->successful() && $matched, 'gsc', $matched ? 'Configured GSC site is accessible.' : 'Configured GSC site was not found.', [
            'http_status' => $response->status(),
            'site_count' => count($sites),
            'configured_site_accessible' => $matched,
        ]);
    }

    /** @return array{summary: array{clicks: float, impressions: float, ctr: float, position: float}, daily: array<int, array<string, mixed>>} */
    public function overview(string $startDate, string $endDate, string $storeId = 'default-store'): array
    {
        $response = Http::withToken($this->accessToken($storeId))->asJson()->timeout(20)->post(
            'https://www.googleapis.com/webmasters/v3/sites/'.urlencode($this->credentials->require('GSC_SITE_URL', $storeId)).'/searchAnalytics/query',
            ['startDate' => $startDate, 'endDate' => $endDate, 'dimensions' => ['date'], 'rowLimit' => 500, 'dataState' => 'all'],
        )->throw();
        $rows = $response->json('rows', []);
        $rows = is_array($rows) ? $rows : [];
        $daily = [];
        $clicks = $impressions = $weightedPosition = 0.0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $rowClicks = (float) ($row['clicks'] ?? 0);
            $rowImpressions = (float) ($row['impressions'] ?? 0);
            $position = (float) ($row['position'] ?? 0);
            $clicks += $rowClicks;
            $impressions += $rowImpressions;
            $weightedPosition += $position * $rowImpressions;
            $daily[] = ['date' => $row['keys'][0] ?? '', 'clicks' => $rowClicks, 'impressions' => $rowImpressions, 'ctr' => (float) ($row['ctr'] ?? 0), 'position' => $position];
        }

        return ['summary' => ['clicks' => $clicks, 'impressions' => $impressions, 'ctr' => $impressions > 0 ? $clicks / $impressions : 0, 'position' => $impressions > 0 ? $weightedPosition / $impressions : 0], 'daily' => $daily];
    }

    private function accessToken(string $storeId): string
    {
        $tokenResponse = Http::asForm()->timeout(15)->retry(2, 300)->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->credentials->require('GSC_REFRESH_TOKEN', $storeId),
            'client_id' => $this->credentials->require('GSC_CLIENT_ID', $storeId),
            'client_secret' => $this->credentials->require('GSC_CLIENT_SECRET', $storeId),
        ]);
        $accessToken = $tokenResponse->json('access_token');
        if (! $tokenResponse->successful() || ! is_string($accessToken)) {
            $tokenResponse->throw();
        }

        return $accessToken;
    }

    private function siteIdentity(string $siteUrl): string
    {
        $siteUrl = strtolower(trim($siteUrl));
        if (str_starts_with($siteUrl, 'sc-domain:')) {
            return rtrim(substr($siteUrl, 10), '/');
        }

        $host = parse_url($siteUrl, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : rtrim($siteUrl, '/');
    }
}
