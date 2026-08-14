<?php

namespace App\Http\Controllers;

use App\Services\Credentials\CredentialService;
use App\Services\DataSync\ExternalSnapshotService;
use App\Services\Integrations\FeishuConnector;
use App\Services\Stores\StoreContext;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    private const SHEETS = [
        ['id' => 'ff4fc0', 'key' => 'overview', 'title' => '总览', 'secrets' => []],
        ['id' => 'IRkYpH', 'key' => 'emails', 'title' => '登录邮箱', 'secrets' => ['密码']],
        ['id' => 'ikuPIv', 'key' => 'seo', 'title' => 'SEO账号密码', 'secrets' => ['密码']],
        ['id' => 'jr5Y7X', 'key' => 'plugins', 'title' => '插件', 'secrets' => ['密码']],
    ];

    public function __construct(private readonly CredentialService $credentials, private readonly FeishuConnector $feishu, private readonly ExternalSnapshotService $snapshots, private readonly StoreContext $storeContext) {}

    public function __invoke(): Response
    {
        $storeId = $this->storeContext->id();
        $wikiUrl = $this->credentials->get('FEISHU_BRAND_WIKI_URL', $storeId) ?? '';
        $sheets = $this->snapshots->get('page:/workspace/brand', [], $storeId);

        return Inertia::render('Brand', ['wikiUrl' => $wikiUrl, 'sheets' => $sheets, 'dataSync' => $this->snapshots->status('page:/workspace/brand', $storeId)]);
    }

    public function sync(): array
    {
        $storeId = $this->storeContext->id();
        $spreadsheet = $this->credentials->require('FEISHU_BRAND_SPREADSHEET_TOKEN', $storeId);
        $sheets = array_map(function (array $config) use ($spreadsheet, $storeId): array {
            $path = '/sheets/v2/spreadsheets/'.$spreadsheet.'/values/'.$config['id'].'!A1:H200?valueRenderOption=ToString';
            $values = $this->feishu->get($path, $storeId)['data']['valueRange']['values'] ?? [];
            $matrix = array_values(array_filter(array_map(fn (array $row) => array_map(fn ($cell) => is_scalar($cell) ? trim((string) $cell) : '', $row), $values), fn (array $row) => collect($row)->contains(fn ($cell) => $cell !== '')));
            if ($matrix === []) {
                return ['key' => $config['key'], 'title' => $config['title'], 'headers' => [], 'rows' => [], 'secretCols' => []];
            }
            $columnCount = max(array_map(function (array $row): int {
                for ($index = count($row) - 1; $index >= 0; $index--) {
                    if ($row[$index] !== '') {
                        return $index + 1;
                    }
                }

                return 0;
            }, $matrix));
            $headers = array_pad(array_slice($matrix[0], 0, $columnCount), $columnCount, '');
            $rows = array_map(fn (array $row) => array_pad(array_slice($row, 0, $columnCount), $columnCount, ''), array_slice($matrix, 1));

            return ['key' => $config['key'], 'title' => $config['title'], 'headers' => $headers, 'rows' => $rows, 'secretCols' => array_values(array_filter(array_map(fn ($name) => array_search($name, $headers, true), $config['secrets']), fn ($index) => $index !== false))];
        }, self::SHEETS);
        $this->snapshots->put('page:/workspace/brand', $sheets, $storeId);

        return $sheets;
    }
}
