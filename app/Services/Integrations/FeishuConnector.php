<?php

namespace App\Services\Integrations;

use App\Services\Credentials\CredentialService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class FeishuConnector
{
    public function __construct(private readonly CredentialService $credentials) {}

    public function test(string $storeId = 'default-store'): IntegrationTestResult
    {
        $response = Http::asJson()->timeout(15)->retry(2, 300)->post(
            'https://open.feishu.cn/open-apis/auth/v3/tenant_access_token/internal',
            [
                'app_id' => $this->credentials->require('FEISHU_APP_ID', $storeId),
                'app_secret' => $this->credentials->require('FEISHU_APP_SECRET', $storeId),
            ],
        );
        $data = $response->json();
        $ok = $response->successful() && ($data['code'] ?? -1) === 0 && ! empty($data['tenant_access_token']);

        return new IntegrationTestResult($ok, 'feishu', $ok ? 'Tenant access token acquired.' : 'Feishu authentication failed.', [
            'http_status' => $response->status(),
            'api_code' => $data['code'] ?? null,
            'expires_in' => $data['expire'] ?? null,
        ]);
    }

    public function tenantToken(string $storeId = 'default-store'): string
    {
        return Cache::remember("feishu:tenant-token:{$storeId}", now()->addMinutes(90), function () use ($storeId): string {
            $response = Http::asJson()->timeout(15)->post(
                'https://open.feishu.cn/open-apis/auth/v3/tenant_access_token/internal',
                ['app_id' => $this->credentials->require('FEISHU_APP_ID', $storeId), 'app_secret' => $this->credentials->require('FEISHU_APP_SECRET', $storeId)],
            )->throw();
            $data = $response->json();
            if (($data['code'] ?? -1) !== 0 || empty($data['tenant_access_token'])) {
                throw new \RuntimeException('Feishu authentication failed.');
            }

            return $data['tenant_access_token'];
        });
    }

    /** @return array<string, mixed> */
    public function get(string $path, string $storeId = 'default-store'): array
    {
        $response = Http::withToken($this->tenantToken($storeId))->timeout(20)->get('https://open.feishu.cn/open-apis'.$path)->throw();
        $data = $response->json();
        if (($data['code'] ?? -1) !== 0) {
            throw new \RuntimeException((string) ($data['msg'] ?? 'Feishu API request failed.'));
        }

        return $data;
    }

    /** @return list<array<string, mixed>> */
    public function bitableRecords(string $appToken, string $tableId, string $viewId = '', string $storeId = 'default-store'): array
    {
        $records = [];
        $pageToken = null;
        do {
            $query = ['page_size' => 500, 'text_field_as_array' => 'true'];
            if ($viewId !== '') $query['view_id'] = $viewId;
            if ($pageToken) $query['page_token'] = $pageToken;
            $data = $this->get('/bitable/v1/apps/'.$appToken.'/tables/'.$tableId.'/records?'.http_build_query($query), $storeId)['data'] ?? [];
            foreach ($data['items'] ?? [] as $item) {
                $records[] = ['record_id' => $item['record_id'], ...$this->flattenFields($item['fields'] ?? [])];
            }
            $pageToken = ($data['has_more'] ?? false) ? ($data['page_token'] ?? null) : null;
        } while ($pageToken);

        return $records;
    }

    /** @return list<array<string, mixed>> */
    public function spreadsheetRowsFromWiki(string $wikiNode, string $sheetTitle, string $storeId = 'default-store'): array
    {
        $node = $this->get('/wiki/v2/spaces/get_node?'.http_build_query(['token' => $wikiNode]), $storeId);
        $spreadsheetToken = $node['data']['node']['obj_token'] ?? null;
        if (! $spreadsheetToken) throw new \RuntimeException('Feishu wiki node has no spreadsheet token.');
        $sheets = $this->get('/sheets/v3/spreadsheets/'.$spreadsheetToken.'/sheets/query', $storeId)['data']['sheets'] ?? [];
        $sheet = collect($sheets)->firstWhere('title', $sheetTitle);
        if (! $sheet) throw new \RuntimeException("Feishu sheet {$sheetTitle} was not found.");
        $range = rawurlencode($sheet['sheet_id'].'!A1:Z200');
        $values = $this->get('/sheets/v2/spreadsheets/'.$spreadsheetToken.'/values/'.$range.'?valueRenderOption=ToString&dateTimeRenderOption=FormattedString', $storeId)['data']['valueRange']['values'] ?? [];
        if (count($values) < 2) return [];
        $headers = array_map(fn ($value) => trim((string) ($value ?? '')), $values[0]);
        $columns = array_keys(array_filter($headers, fn ($value) => $value !== ''));

        return collect(array_slice($values, 1))->filter(fn ($row) => collect($columns)->contains(fn ($column) => isset($row[$column]) && $row[$column] !== ''))->values()->map(function ($row, $index) use ($headers, $columns) {
            $record = ['record_id' => (string) $index];
            foreach ($columns as $column) $record[$headers[$column]] = $row[$column] ?? null;
            return $record;
        })->all();
    }

    /** @return array<int, array<int, mixed>> */
    public function spreadsheetValues(string $spreadsheetToken, string $sheetId, string $storeId = 'default-store'): array
    {
        $range = rawurlencode($sheetId.'!A1:Z2000');

        return $this->get('/sheets/v2/spreadsheets/'.$spreadsheetToken.'/values/'.$range.'?valueRenderOption=UnformattedValue', $storeId)['data']['valueRange']['values'] ?? [];
    }

    /** @param array<string, mixed> $fields @return array<string, mixed> */
    private function flattenFields(array $fields): array
    {
        foreach ($fields as $key => $value) {
            if (is_array($value)) {
                if (array_is_list($value)) {
                    $fields[$key] = array_map(fn ($item) => is_array($item) ? ($item['text'] ?? $item['name'] ?? $item['value'] ?? $item) : $item, $value);
                } else {
                    $fields[$key] = $value['value'] ?? $value['text'] ?? $value['link'] ?? $value;
                }
            }
        }

        return $fields;
    }
}
