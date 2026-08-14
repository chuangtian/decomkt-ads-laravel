<?php

namespace App\Http\Controllers;

use App\Services\DataSync\DesignDataSyncService;
use App\Services\Stores\StoreContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DesignController extends Controller
{
    public function __invoke(Request $request, DesignDataSyncService $sync, StoreContext $storeContext): Response
    {
        $storeId = $storeContext->id();
        $rows = null;
        $getRows = function () use (&$rows, $sync, $storeId) {
            return $rows ??= collect($sync->rows($storeId))->map(fn (array $row) => $this->normalize($row))->values();
        };

        return Inertia::render('Module', [
            'module' => collect(config('decomkt.pages'))->firstWhere('path', '/ecommerce/design'),
            'store' => $storeContext->store(),
            'designPage' => true,
            'summary' => function () use ($getRows) {
                $items = $getRows();
                $scores = $items->where('completed', true)->pluck('score');

                return [
                    'total' => $items->count(),
                    'inProgress' => $items->filter(fn ($row) => str_contains($row['status'], '进行中'))->count(),
                    'completed' => $items->where('completed', true)->count(),
                    'delayed' => $items->where('delayed', true)->count(),
                    'quantity' => $items->sum('quantity'),
                    'averageScore' => round((float) ($scores->avg() ?? 0), 1),
                ];
            },
            'designers' => fn () => $getRows()->groupBy('designer')->map(fn ($items, $name) => [
                'name' => $name ?: '未分配', 'tasks' => $items->count(), 'quantity' => $items->sum('quantity'), 'completed' => $items->where('completed', true)->count(),
            ])->sortByDesc('tasks')->values(),
            'types' => fn () => $getRows()->groupBy('type')->map(fn ($items, $name) => ['name' => $name ?: '其他', 'count' => $items->count()])->sortByDesc('count')->values(),
            'activeTasks' => fn () => $getRows()->filter(fn ($row) => str_contains($row['status'], '进行中'))->take(12)->values(),
            'recordPage' => function () use ($getRows, $request) {
                if ($request->query('detail') !== 'records') {
                    return null;
                }
                $search = mb_strtolower(trim((string) $request->query('search', '')));
                $items = $getRows()->filter(fn ($row) => $search === '' || str_contains(mb_strtolower(implode(' ', array_map(fn ($value) => is_scalar($value) ? (string) $value : '', $row))), $search))->values();
                $page = max(1, $request->integer('page', 1));
                $perPage = 30;
                $total = $items->count();

                return [
                    'data' => $items->slice(($page - 1) * $perPage, $perPage)->values(),
                    'current_page' => $page,
                    'last_page' => max(1, (int) ceil($total / $perPage)),
                    'per_page' => $perPage,
                    'total' => $total,
                    'from' => $total ? (($page - 1) * $perPage) + 1 : null,
                    'to' => $total ? min($page * $perPage, $total) : null,
                ];
            },
            'dataSync' => fn () => $sync->status($storeId),
        ]);
    }

    private function normalize(array $row): array
    {
        $text = fn ($value) => $this->text($value);
        $status = $text($row['当前状态'] ?? '');
        $delayDays = (int) ($row['延迟天数'] ?? 0);
        $delayFlag = $text($row['是否延期'] ?? '');

        return [
            'id' => $row['record_id'] ?? uniqid(), 'number' => $text($row['编号'] ?? ''), 'brand' => $text($row['品牌名称'] ?? ''),
            'type' => $text($row['需求类型'] ?? ''), 'department' => $text($row['提报部门'] ?? ''), 'reporter' => $text($row['提报人'] ?? ''),
            'designer' => $text($row['设计师'] ?? ''), 'priority' => $text($row['优先级'] ?? ''), 'description' => $text($row['需求描述'] ?? ''),
            'quantity' => (int) ($row['数量'] ?? 0), 'status' => $status, 'completed' => str_contains($status, '完成'),
            'delayed' => $delayDays > 0 || str_contains($delayFlag, '🔴'), 'delayDays' => $delayDays, 'changes' => (int) ($row['更改次数'] ?? 0),
            'score' => (float) ($row['综合得分'] ?? 0), 'submitDate' => $this->date($row['提报日期'] ?? null),
            'plannedDate' => $this->date($row['计划交付日期'] ?? null), 'actualDate' => $this->date($row['实际交付日期'] ?? null),
        ];
    }

    private function text(mixed $value): string
    {
        if (is_array($value) && array_is_list($value)) {
            return trim(collect($value)->map(fn ($item) => $this->text($item))->filter()->join(''));
        }
        if (is_array($value)) {
            return (string) ($value['name'] ?? $value['text'] ?? $value['value'] ?? '');
        }

        return trim((string) ($value ?? ''));
    }

    private function date(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }
        if (is_numeric($value)) {
            return Carbon::createFromTimestampMs((int) $value, 'Asia/Shanghai')->format('Y-m-d');
        }

        return substr((string) $value, 0, 10);
    }
}
