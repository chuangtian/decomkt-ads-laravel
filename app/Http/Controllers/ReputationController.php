<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReputationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'entity' => ['required', 'in:review,reddit,risk,resource'], 'platform' => ['nullable', 'string', 'max:80'],
            'title' => ['nullable', 'string', 'max:255'], 'content' => ['required', 'string', 'max:10000'],
            'star' => ['nullable', 'integer', 'between:1,5'], 'sentiment' => ['nullable', 'in:POSITIVE,NEUTRAL,NEGATIVE'],
            'level' => ['nullable', 'in:LOW,MEDIUM,HIGH,CRITICAL'], 'priority' => ['nullable', 'in:LOW,MEDIUM,HIGH,URGENT'],
        ]);
        $id = (string) Str::uuid();
        match ($data['entity']) {
            'review' => DB::table('ReputationReview')->insert(['id' => $id, 'storeId' => 'default-store', 'platform' => $data['platform'] ?? 'WEBSITE', 'content' => $data['content'], 'star' => $data['star'] ?? 5, 'sentiment' => $data['sentiment'] ?? 'POSITIVE', 'replied' => false, 'source' => 'manual', 'createdAt' => now(), 'updatedAt' => now()]),
            'reddit' => DB::table('RedditPost')->insert(['id' => $id, 'storeId' => 'default-store', 'title' => $data['title'] ?? 'Reddit 帖子', 'body' => $data['content'], 'upvotes' => 0, 'views' => 0, 'commentCount' => 0, 'sentiment' => $data['sentiment'] ?? 'NEUTRAL', 'replied' => false, 'source' => 'manual', 'createdAt' => now(), 'updatedAt' => now()]),
            'risk' => DB::table('ReputationRisk')->insert(['id' => $id, 'storeId' => 'default-store', 'description' => $data['content'], 'level' => $data['level'] ?? 'MEDIUM', 'platform' => $data['platform'] ?? '综合', 'status' => 'OPEN', 'source' => 'manual', 'createdAt' => now(), 'updatedAt' => now()]),
            'resource' => DB::table('ReputationResource')->insert(['id' => $id, 'storeId' => 'default-store', 'description' => $data['content'], 'type' => $data['platform'] ?? 'OTHER', 'priority' => $data['priority'] ?? 'MEDIUM', 'createdAt' => now(), 'updatedAt' => now()]),
        };

        return back()->with('success', '记录已添加');
    }

    public function update(Request $request, string $entity, string $id): RedirectResponse
    {
        $table = ['review' => 'ReputationReview', 'reddit' => 'RedditPost', 'risk' => 'ReputationRisk'][$entity] ?? null;
        abort_unless($table, 404);
        $data = $request->validate(['replied' => ['sometimes', 'boolean'], 'replyNote' => ['nullable', 'string'], 'status' => ['sometimes', 'string', 'max:30']]);
        DB::table($table)->where('id', $id)->update([...$data, 'updatedAt' => now()]);

        return back()->with('success', '记录已更新');
    }

    public function destroy(string $entity, string $id): RedirectResponse
    {
        $table = ['review' => 'ReputationReview', 'reddit' => 'RedditPost', 'risk' => 'ReputationRisk', 'resource' => 'ReputationResource'][$entity] ?? null;
        abort_unless($table, 404);
        DB::table($table)->where('id', $id)->delete();

        return back()->with('success', '记录已删除');
    }

    public function analyze(): RedirectResponse
    {
        $week = (int) now()->isoWeek();
        $negativeReviews = DB::table('ReputationReview')
            ->where('storeId', 'default-store')
            ->where(fn ($query) => $query->where('sentiment', 'NEGATIVE')->orWhere('star', '<=', 2))
            ->select('platform', DB::raw('COUNT(*) as total'))
            ->groupBy('platform')
            ->get();
        $negativeReddit = DB::table('RedditPost')->where('storeId', 'default-store')->where('sentiment', 'NEGATIVE')->count();

        DB::transaction(function () use ($week, $negativeReviews, $negativeReddit) {
            DB::table('ReputationRisk')->where('storeId', 'default-store')->where('source', 'auto')->where('week', $week)->delete();
            foreach ($negativeReviews as $item) {
                DB::table('ReputationRisk')->insert([
                    'id' => (string) Str::uuid(), 'storeId' => 'default-store',
                    'description' => "{$item->platform} 平台检测到 {$item->total} 条负面评价，请优先回复并核查集中问题。",
                    'level' => $item->total >= 5 ? 'HIGH' : 'MEDIUM', 'platform' => $item->platform,
                    'status' => 'OPEN', 'suggestion' => '24 小时内完成回复，并将重复问题同步至产品与客服团队。',
                    'week' => $week, 'source' => 'auto', 'createdAt' => now(), 'updatedAt' => now(),
                ]);
            }
            if ($negativeReddit > 0) {
                DB::table('ReputationRisk')->insert([
                    'id' => (string) Str::uuid(), 'storeId' => 'default-store',
                    'description' => "Reddit 检测到 {$negativeReddit} 条负面帖子，需要跟进社区讨论。",
                    'level' => $negativeReddit >= 5 ? 'HIGH' : 'MEDIUM', 'platform' => 'REDDIT',
                    'status' => 'OPEN', 'suggestion' => '核实帖子事实并制定透明、非营销化的社区回复。',
                    'week' => $week, 'source' => 'auto', 'createdAt' => now(), 'updatedAt' => now(),
                ]);
            }
        });

        $count = $negativeReviews->count() + ($negativeReddit > 0 ? 1 : 0);

        return back()->with('success', $count ? "风险分析完成，共生成 {$count} 项风险" : '风险分析完成，当前未发现负面风险');
    }

    public function saveWeeklyReport(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reporter' => ['nullable', 'string', 'max:120'],
            'reportTo' => ['nullable', 'string', 'max:120'],
            'content' => ['required', 'string', 'max:50000'],
        ]);
        $year = (int) now()->isoWeekYear();
        $week = (int) now()->isoWeek();
        $reviews = DB::table('ReputationReview')->where('storeId', 'default-store');
        $tpAvg = (clone $reviews)->where('platform', 'TRUSTPILOT')->avg('star');
        $websiteAvg = (clone $reviews)->where('platform', 'WEBSITE')->avg('star');
        $negativeCount = (clone $reviews)->where(fn ($query) => $query->where('sentiment', 'NEGATIVE')->orWhere('star', '<=', 2))->count();

        DB::table('ReputationWeeklyReport')->updateOrInsert(
            ['storeId' => 'default-store', 'year' => $year, 'week' => $week],
            [
                'id' => DB::table('ReputationWeeklyReport')->where('storeId', 'default-store')->where('year', $year)->where('week', $week)->value('id') ?: (string) Str::uuid(),
                ...$data, 'overallScore' => collect([$tpAvg, $websiteAvg])->filter(fn ($value) => $value !== null)->avg(),
                'tpAvg' => $tpAvg, 'websiteAvg' => $websiteAvg,
                'redditPosts' => DB::table('RedditPost')->where('storeId', 'default-store')->count(),
                'negativeCount' => $negativeCount, 'createdAt' => now(), 'updatedAt' => now(),
            ],
        );

        return back()->with('success', "{$year} 年第 {$week} 周周报已保存");
    }
}
