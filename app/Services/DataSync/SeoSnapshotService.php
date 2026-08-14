<?php

namespace App\Services\DataSync;

use App\Services\Credentials\CredentialService;
use App\Services\Integrations\FeishuConnector;
use App\Services\Integrations\Ga4Connector;
use App\Services\Integrations\GscConnector;
use Carbon\CarbonImmutable;

class SeoSnapshotService
{
    private const TARGETS = [
        'seo_gmv' => ['name' => 'SEO GMV', 'target' => 268483.61, 'unit' => '$', 'source' => '飞书日数据表 API', 'category' => '结果'],
        'industry_clicks' => ['name' => '行业词点击', 'target' => 4318, 'unit' => '', 'source' => '飞书日数据表 API', 'category' => '流量'],
        'blog_clicks' => ['name' => '博客页面点击', 'target' => 19379, 'unit' => '', 'source' => '飞书日数据表 API', 'category' => '流量'],
        'new_blog' => ['name' => '新博客上线数量', 'target' => 15, 'unit' => '篇', 'source' => '新博客推进表', 'category' => '动作'],
        'old_blog' => ['name' => '旧博客/内链/404/重定向优化量', 'target' => 50, 'unit' => '篇', 'source' => '旧博客推进表', 'category' => '动作'],
        'backlinks' => ['name' => '外链合作数（含PR）', 'target' => 10, 'unit' => '个', 'source' => '外链推进表', 'category' => '动作'],
        'ai_automation' => ['name' => 'AI自动化有效流程', 'target' => 2, 'unit' => '个', 'source' => 'AI自动化应用推进表', 'category' => '动作'],
    ];

    public function __construct(
        private readonly CredentialService $credentials,
        private readonly FeishuConnector $feishu,
        private readonly GscConnector $gsc,
        private readonly Ga4Connector $ga4,
        private readonly ExternalSnapshotService $snapshots,
    ) {}

    public function get(string $storeId = 'default-store'): array
    {
        return $this->snapshots->get('page:/organic/seo', [
            'range' => null, 'overview' => ['gsc' => null, 'ga4' => null, 'errors' => []],
            'goals' => null,
        ], $storeId);
    }

    public function status(string $storeId = 'default-store'): ?object
    {
        return $this->snapshots->status('page:/organic/seo', $storeId);
    }

    public function sync(?string $startDate = null, ?string $endDate = null, ?string $month = null, string $storeId = 'default-store'): array
    {
        $today = CarbonImmutable::now('Asia/Shanghai');
        $endDate ??= $today->subDay()->toDateString();
        $startDate ??= CarbonImmutable::parse($endDate)->subDays(7)->toDateString();
        $month ??= $today->format('Y-m');
        $errors = [];
        $gsc = $ga4 = null;

        try {
            $gsc = $this->gsc->overview($startDate, $endDate, $storeId);
        } catch (\Throwable $exception) {
            $errors['gsc'] = $exception->getMessage();
        }
        try {
            $ga4 = $this->ga4->overview($startDate, $endDate, $storeId);
        } catch (\Throwable $exception) {
            $errors['ga4'] = $exception->getMessage();
        }

        $payload = [
            'range' => ['startDate' => $startDate, 'endDate' => $endDate],
            'overview' => ['gsc' => $gsc, 'ga4' => $ga4, 'errors' => $errors],
            'goals' => $this->goals($month, $storeId),
        ];
        $this->snapshots->put('page:/organic/seo', $payload, $storeId);

        return $payload;
    }

    private function goals(string $month, string $storeId): array
    {
        $records = $this->feishu->bitableRecords(
            $this->credentials->require('FEISHU_SEO_APP_TOKEN', $storeId),
            $this->credentials->require('FEISHU_SEO_DAILY_TABLE_ID', $storeId),
            '',
            $storeId,
        );
        $actual = ['seo_gmv' => 0.0, 'industry_clicks' => 0.0, 'blog_clicks' => 0.0];
        $dataThrough = null;

        foreach ($records as $row) {
            $date = $this->date($row['日期'] ?? null);
            if (! $date || ! str_starts_with($date, $month)) {
                continue;
            }
            $dataThrough = max($dataThrough ?? $date, $date);
            $actual['seo_gmv'] += $this->number($row['SEOGMV'] ?? 0);
            $actual['industry_clicks'] += $this->number($row['点击-行业词'] ?? 0);
            $actual['blog_clicks'] += $this->number($row['点击-博客'] ?? 0);
        }

        $actual += [
            'new_blog' => $this->number($this->credentials->get('SEO_WORK_NEW_BLOG', $storeId) ?? 0),
            'old_blog' => $this->number($this->credentials->get('SEO_WORK_OLD_BLOG', $storeId) ?? 0),
            'backlinks' => $this->number($this->credentials->get('SEO_WORK_BACKLINKS', $storeId) ?? 0),
            'ai_automation' => $this->number($this->credentials->get('SEO_WORK_AI_AUTOMATION', $storeId) ?? 0),
        ];
        foreach ([
            'new_blog' => ['FEISHU_SEO_WORK_NEW_BLOG_TOKEN', 'FEISHU_SEO_WORK_NEW_BLOG_SHEET'],
            'old_blog' => ['FEISHU_SEO_WORK_OLD_BLOG_TOKEN', 'FEISHU_SEO_WORK_OLD_BLOG_SHEET'],
            'backlinks' => ['FEISHU_SEO_WORK_BACKLINKS_TOKEN', 'FEISHU_SEO_WORK_BACKLINKS_SHEET'],
            'ai_automation' => ['FEISHU_SEO_WORK_AI_TOKEN', 'FEISHU_SEO_WORK_AI_SHEET'],
        ] as $metric => [$tokenKey, $sheetKey]) {
            $token = $this->credentials->get($tokenKey, $storeId);
            $sheet = $this->credentials->get($sheetKey, $storeId);
            if (! $token || ! $sheet) {
                continue;
            }
            try {
                $actual[$metric] = max($actual[$metric], $this->countSheet($this->feishu->spreadsheetValues($token, $sheet, $storeId), $metric, $month));
            } catch (\Throwable) { /* Keep the last persisted count when a work sheet is temporarily unavailable. */
            }
        }

        [$year, $monthNumber] = array_map('intval', explode('-', $month));
        $days = CarbonImmutable::create($year, $monthNumber, 1)->daysInMonth;
        $now = CarbonImmutable::now('Asia/Shanghai');
        $pace = $month < $now->format('Y-m') ? 1.0 : min(1, $now->day / $days);
        $dataPace = $dataThrough ? min(1, (int) substr($dataThrough, 8, 2) / $days) : $pace;
        $metrics = [];

        foreach (self::TARGETS as $id => $meta) {
            $current = (float) ($actual[$id] ?? 0);
            $metricPace = in_array($id, ['seo_gmv', 'industry_clicks', 'blog_clicks'], true) ? $dataPace : $pace;
            $forecast = $metricPace > 0 ? $current / $metricPace : 0;
            $rate = $meta['target'] > 0 ? $forecast / $meta['target'] : 0;
            $metrics[] = ['id' => $id, ...$meta, 'current' => $current,
                'completion' => $meta['target'] > 0 ? $current / $meta['target'] : 0,
                'expected' => $meta['target'] * $metricPace, 'forecast' => $forecast,
                'forecast_rate' => $rate, 'status' => $rate >= 1 ? '领先' : ($rate >= .8 ? '可达成' : '高风险')];
        }

        return ['month' => $month, 'dataThrough' => $dataThrough, 'metrics' => $metrics,
            'onTrack' => collect($metrics)->where('forecast_rate', '>=', 1)->count(),
            'atRisk' => collect($metrics)->where('forecast_rate', '<', 1)->count()];
    }

    private function number(mixed $value): float
    {
        if (is_array($value)) {
            $value = $value[0] ?? 0;
        }

        return (float) preg_replace('/[^0-9.\-]/', '', (string) $value);
    }

    private function date(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value[0] ?? null;
        }
        if (is_numeric($value)) {
            return CarbonImmutable::createFromTimestampMs((int) $value, 'Asia/Shanghai')->toDateString();
        }
        if (is_string($value) && preg_match('/\d{4}[-\/]\d{1,2}[-\/]\d{1,2}/', $value, $match)) {
            return str_replace('/', '-', $match[0]);
        }

        return null;
    }

    /** @param array<int, array<int, mixed>> $rows */
    private function countSheet(array $rows, string $metric, string $month): int
    {
        $headers = match ($metric) {
            'new_blog' => [['实际上线日期'], ['博客URL']],
            'old_blog' => [['实际上线日期'], ['旧博客URL']],
            'backlinks' => [['合作月份']],
            default => [['本月计入'], ['流程名称']],
        };
        $columns = array_map(fn (array $names) => $this->findColumn($rows, $names), $headers);
        if (($columns[0][1] ?? -1) < 0) {
            return 0;
        }
        $start = max(array_column($columns, 0)) + 1;
        $seen = [];
        $count = 0;

        for ($index = $start; $index < count($rows); $index++) {
            $row = $rows[$index] ?? [];
            $primary = $row[$columns[0][1]] ?? null;
            if ($metric === 'ai_automation') {
                if (trim($this->cellText($primary)) !== '计入') {
                    continue;
                }
                $name = trim($this->cellText($row[$columns[1][1]] ?? ''));
                if ($name !== '') {
                    $seen[$name] = true;
                }

                continue;
            }
            if ($metric === 'backlinks') {
                $text = $this->cellText($primary);
                $matches = $this->monthValue($primary) === $month || (int) preg_replace('/\D/', '', $text) === (int) substr($month, 5, 2);
                if ($matches) {
                    $count++;
                }

                continue;
            }
            if ($this->monthValue($primary) !== $month) {
                continue;
            }
            if (($columns[1][1] ?? -1) >= 0 && trim($this->cellText($row[$columns[1][1]] ?? '')) === '') {
                continue;
            }
            $count++;
        }

        return $metric === 'ai_automation' ? count($seen) : $count;
    }

    /** @param array<int, array<int, mixed>> $rows @param array<int, string> $names @return array{int,int} */
    private function findColumn(array $rows, array $names): array
    {
        for ($row = 0; $row < min(5, count($rows)); $row++) {
            foreach ($rows[$row] ?? [] as $column => $value) {
                $text = trim($this->cellText($value));
                if (collect($names)->contains(fn (string $name) => $text === $name || str_contains($text, $name))) {
                    return [$row, $column];
                }
            }
        }

        return [0, -1];
    }

    private function cellText(mixed $value): string
    {
        if (is_array($value)) {
            return collect($value)->map(fn ($item) => is_array($item) ? ($item['text'] ?? '') : $item)->join('');
        }

        return is_scalar($value) ? (string) $value : '';
    }

    private function monthValue(mixed $value): ?string
    {
        if (is_numeric($value)) {
            $number = (float) $value;
            if ($number > 100000000000) {
                return CarbonImmutable::createFromTimestampMs((int) $number)->format('Y-m');
            }
            if ($number > 40000 && $number < 80000) {
                return CarbonImmutable::create(1899, 12, 30)->addDays((int) $number)->format('Y-m');
            }
        }
        if (preg_match('/(\d{4})[-\/](\d{1,2})/', $this->cellText($value), $match)) {
            return $match[1].'-'.str_pad($match[2], 2, '0', STR_PAD_LEFT);
        }

        return null;
    }
}
