<?php

namespace App\Http\Controllers;

use App\Services\Credentials\CredentialService;
use App\Services\DataSync\DesignDataSyncService;
use App\Services\DataSync\ExternalSnapshotService;
use App\Services\Integrations\FeishuConnector;
use App\Services\Stores\StoreContext;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class ModuleController extends Controller
{
    private const EXTERNAL_PATHS = ['/ads/campaign','/ads/target','/ecommerce/shopify','/ads/facebook','/ads/google','/ads/tiktok','/ads/bing','/ads/criteo','/organic/kol','/organic/edm','/organic/affiliate'];

    /** @var array<string, array<string, mixed>> */
    private array $analyticsCache = [];

    public function __construct(private readonly CredentialService $credentials, private readonly FeishuConnector $feishu, private readonly DesignDataSyncService $designSync, private readonly ExternalSnapshotService $snapshots, private readonly StoreContext $storeContext) {}

    public function __invoke(Request $request): Response
    {
        $definition = collect(config('decomkt.pages'))->firstWhere('path', '/'.$request->path());
        abort_unless($definition, 404);
        $path = $definition['path'];

        $configuration = [];
        if ($definition['path'] === '/settings') {
            $configuration = DB::table('SystemConfig')->pluck('key')->mapWithKeys(fn ($key) => [$key => true]);
        }
        if ($definition['path'] === '/store-settings') {
            $configuration = DB::table('StoreConfig')->where('storeId', $this->storeId())->pluck('key')->mapWithKeys(fn ($key) => [$key => true]);
        }

        $studentDiscount = null;
        if ($definition['path'] === '/ecommerce/student-discounts') {
            $studentDiscount = DB::table('StudentDiscountCampaign')->where('storeId', $this->storeId())->first();
            if ($studentDiscount) {
                $studentDiscount->issued = DB::table('StudentDiscountClaim')->where('storeId', $this->storeId())->where('status', 'ISSUED')->count();
                $studentDiscount->failed = DB::table('StudentDiscountClaim')->where('storeId', $this->storeId())->where('status', 'FAILED')->count();
                $studentDiscount->total = DB::table('StudentDiscountClaim')->where('storeId', $this->storeId())->count();
            }
        }

        return Inertia::render('Module', [
            'module' => $definition,
            'store' => $this->storeContext->store(),
            'configuration' => $configuration,
            'studentDiscount' => $studentDiscount,
            'records' => fn () => $this->legacyRecords($path),
            'recordPage' => fn () => $this->recordPage($request, $path),
            'socialSummary' => fn () => $path === '/organic/social' ? $this->socialSummary() : null,
            'kolSummary' => fn () => $path === '/organic/kol' ? $this->kolSummary($this->cachedAnalytics($path)['rows'] ?? []) : null,
            'reputationSummary' => fn () => str_starts_with($path, '/reputation/') ? $this->reputationSummary($request, $path) : null,
            'analytics' => fn () => $this->pageAnalytics($path),
            'externalSync' => fn () => in_array($path, self::EXTERNAL_PATHS, true) ? $this->snapshots->status('page:'.$path, $this->storeId()) : null,
            'configuredCredentials' => fn () => array_keys($this->credentials->many($this->credentialKeys($path), $this->storeId())),
        ]);
    }

    /** @return array<string, mixed>|array<int, mixed> */
    private function legacyRecords(string $path): array
    {
        return match ($path) {
            '/reputation/risk-sync' => [
                'risks' => DB::table('ReputationRisk')->where('storeId', $this->storeId())->latest('createdAt')->limit(100)->get(),
                'resources' => DB::table('ReputationResource')->where('storeId', $this->storeId())->latest('createdAt')->limit(100)->get(),
            ],
            '/reputation/weekly-report' => DB::table('ReputationWeeklyReport')->where('storeId', $this->storeId())->orderByDesc('year')->orderByDesc('week')->limit(12)->get()->all(),
            default => [],
        };
    }

    /** @return array<string, mixed>|null */
    private function recordPage(Request $request, string $path): ?array
    {
        $page = max(1, $request->integer('page', 1));

        if ($path === '/organic/social') {
            $query = DB::table('SocialMediaPost')
                ->where('storeId', $this->storeId())
                ->select(['id', 'platform', 'postType', 'description', 'publishedAt', 'views', 'likes', 'comments', 'shares', 'permalink'])
                ->latest('publishedAt');
            $platform = trim((string) $request->query('platform', ''));
            if ($platform !== '' && $platform !== '全部') {
                $query->whereRaw('LOWER(platform) = ?', [mb_strtolower($platform)]);
            }

            return $this->databasePage($query, $page, 40);
        }

        if ($path === '/organic/kol') {
            $rows = $this->cachedAnalytics($path)['rows'] ?? [];

            return $this->arrayPage(is_array($rows) ? $rows : [], $page, 50);
        }

        if ($path === '/reputation/overview') {
            $detail = (string) $request->query('detail', '');
            if ($detail === 'reviews') {
                $query = DB::table('ReputationReview')
                    ->where('storeId', $this->storeId())
                    ->select(['id', 'platform', 'content', 'star', 'publishDate', 'sentiment', 'sku', 'replied'])
                    ->latest('publishDate');
                $this->applyDateRange($query, 'publishDate', $request);

                return $this->databasePage($query, $page, 30);
            }
            if ($detail === 'reddit') {
                $query = DB::table('RedditPost')
                    ->where('storeId', $this->storeId())
                    ->select(['id', 'title', 'body', 'subreddit', 'views', 'upvotes', 'commentCount', 'postDate', 'sentiment'])
                    ->latest('postDate');
                $this->applyDateRange($query, 'postDate', $request);

                return $this->databasePage($query, $page, 30);
            }

            return null;
        }

        if ($path === '/reputation/reddit') {
            if ($request->query('detail') !== 'records') return null;

            return $this->databasePage(
                DB::table('RedditPost')
                    ->where('storeId', $this->storeId())
                    ->select(['id', 'title', 'body', 'subreddit', 'views', 'upvotes', 'commentCount', 'postDate', 'sentiment', 'topicCategory', 'tags'])
                    ->latest('createdAt'),
                $page,
                30,
            );
        }

        $platform = match ($path) {
            '/reputation/google-reviews' => 'GOOGLE',
            '/reputation/trustpilot' => 'TRUSTPILOT',
            '/reputation/website-reviews' => 'WEBSITE',
            default => null,
        };
        if ($platform && $request->query('detail') === 'records') {
            return $this->databasePage(
                DB::table('ReputationReview')
                    ->where('storeId', $this->storeId())
                    ->where('platform', $platform)
                    ->select(['id', 'platform', 'content', 'star', 'publishDate', 'sentiment', 'tags', 'aiTags', 'sku', 'replied'])
                    ->latest('createdAt'),
                $page,
                30,
            );
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function socialSummary(): array
    {
        $totals = DB::table('SocialMediaPost')->where('storeId', $this->storeId())->selectRaw(
            'COUNT(*) posts, COALESCE(SUM(views),0) views, COALESCE(SUM(likes),0) likes, COALESCE(SUM(comments),0) comments, COALESCE(SUM(shares),0) shares'
        )->first();
        $platforms = DB::table('SocialMediaPost')
            ->where('storeId', $this->storeId())
            ->selectRaw('platform name, COUNT(*) posts, COALESCE(SUM(views),0) views, COALESCE(SUM(likes + comments + shares),0) eng')
            ->groupBy('platform')
            ->orderByDesc('posts')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name ?: 'unknown',
                'posts' => (int) $row->posts,
                'views' => (int) $row->views,
                'eng' => (int) $row->eng,
                'rate' => $row->views ? round($row->eng * 100 / $row->views, 2) : 0,
            ])->all();

        return [
            'posts' => (int) ($totals->posts ?? 0),
            'views' => (int) ($totals->views ?? 0),
            'likes' => (int) ($totals->likes ?? 0),
            'comments' => (int) ($totals->comments ?? 0),
            'shares' => (int) ($totals->shares ?? 0),
            'platforms' => $platforms,
        ];
    }

    /** @param array<int, mixed> $rows
     *  @return array<string, mixed>
     */
    private function kolSummary(array $rows): array
    {
        $collection = collect($rows);
        $views = $collection->sum(fn ($row) => $this->number($row['浏览'] ?? 0));
        $likes = $collection->sum(fn ($row) => $this->number($row['赞'] ?? 0));
        $comments = $collection->sum(fn ($row) => $this->number($row['评'] ?? 0));
        $names = $collection->flatMap(function ($row) {
            $value = $row['红人title'] ?? null;
            return is_array($value) ? $value : [$value];
        })->filter()->unique()->count();

        return [
            'total' => $collection->count(),
            'names' => $names,
            'views' => $views,
            'likes' => $likes,
            'comments' => $comments,
            'engagementRate' => $views ? round(($likes + $comments) * 100 / $views, 2) : 0,
            'trend' => $collection->take(20)->values()->all(),
            'top' => $collection->sortByDesc(fn ($row) => $this->number($row['浏览'] ?? 0))->take(10)->values()->all(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function reputationSummary(Request $request, string $path): ?array
    {
        if ($path === '/reputation/overview') {
            $reviews = DB::table('ReputationReview')->where('storeId', $this->storeId());
            $reddit = DB::table('RedditPost')->where('storeId', $this->storeId());
            $this->applyDateRange($reviews, 'publishDate', $request);
            $this->applyDateRange($reddit, 'postDate', $request);
            $reviewStats = $reviews->selectRaw('COUNT(*) total, COALESCE(SUM(star >= 4),0) satisfied, COALESCE(SUM(star <= 2),0) negative, COALESCE(AVG(star),0) average')->first();
            $redditStats = $reddit->selectRaw("COUNT(*) total, COALESCE(SUM(views),0) views, COALESCE(SUM(commentCount),0) comments, COALESCE(SUM(upvotes),0) upvotes, COALESCE(SUM(sentiment = 'NEGATIVE'),0) negative")->first();

            return [
                'range' => ['start' => $this->dateQuery($request, 'start', now()->subDays(6)->toDateString()), 'end' => $this->dateQuery($request, 'end', now()->toDateString())],
                'reviews' => ['total' => (int) $reviewStats->total, 'satisfied' => (int) $reviewStats->satisfied, 'negative' => (int) $reviewStats->negative, 'average' => round((float) $reviewStats->average, 1)],
                'reddit' => ['total' => (int) $redditStats->total, 'views' => (int) $redditStats->views, 'comments' => (int) $redditStats->comments, 'upvotes' => (int) $redditStats->upvotes, 'negative' => (int) $redditStats->negative],
            ];
        }

        if ($path === '/reputation/reddit') {
            $stats = DB::table('RedditPost')->where('storeId', $this->storeId())->selectRaw("COUNT(*) total, COALESCE(SUM(upvotes),0) upvotes, COALESCE(SUM(commentCount),0) comments, COALESCE(SUM(sentiment = 'POSITIVE'),0) positive, COALESCE(SUM(sentiment = 'NEGATIVE'),0) negative")->first();
            $topics = DB::table('RedditPost')->where('storeId', $this->storeId())->selectRaw("COALESCE(NULLIF(topicCategory,''), '其他') name, COUNT(*) count")->groupBy('name')->orderByDesc('count')->limit(8)->get();
            $subreddits = DB::table('RedditPost')->where('storeId', $this->storeId())->selectRaw("COALESCE(NULLIF(subreddit,''), '未知') name, COUNT(*) n, COALESCE(SUM(upvotes),0) u, COALESCE(SUM(commentCount),0) c")->groupBy('name')->orderByDesc('n')->limit(12)->get();
            $negativeItems = DB::table('RedditPost')->where('storeId', $this->storeId())->where('sentiment', 'NEGATIVE')->latest('createdAt')->limit(15)->get(['id', 'title', 'body', 'subreddit', 'upvotes', 'commentCount']);

            return ['kind' => 'reddit', 'total' => (int) $stats->total, 'upvotes' => (int) $stats->upvotes, 'comments' => (int) $stats->comments, 'positive' => (int) $stats->positive, 'negative' => (int) $stats->negative, 'topics' => $topics, 'subreddits' => $subreddits, 'negativeItems' => $negativeItems];
        }

        $platform = match ($path) {
            '/reputation/google-reviews' => 'GOOGLE',
            '/reputation/trustpilot' => 'TRUSTPILOT',
            '/reputation/website-reviews' => 'WEBSITE',
            default => null,
        };
        if (! $platform) return null;

        $base = DB::table('ReputationReview')->where('storeId', $this->storeId())->where('platform', $platform);
        $stats = (clone $base)->selectRaw("COUNT(*) total, COALESCE(AVG(star),0) average, COALESCE(SUM(star >= 4 OR sentiment = 'POSITIVE'),0) positive, COALESCE(SUM(star <= 2 OR sentiment = 'NEGATIVE'),0) negative")->first();
        $stars = (clone $base)->selectRaw('star, COUNT(*) count')->groupBy('star')->pluck('count', 'star');
        $skus = (clone $base)->whereNotNull('sku')->where('sku', '!=', '')->selectRaw('sku name, COUNT(*) count, AVG(star) average')->groupBy('sku')->orderByDesc('count')->limit(8)->get();
        $negativeItems = (clone $base)->where(fn ($query) => $query->where('star', '<=', 2)->orWhere('sentiment', 'NEGATIVE'))->latest('createdAt')->limit(20)->get(['id', 'content', 'star', 'publishDate']);
        $tagCounts = [];
        foreach ((clone $base)->get(['tags', 'aiTags']) as $row) {
            foreach ($this->extractTags($row->aiTags ?: $row->tags) as $tag) {
                $tagCounts[$tag] = ($tagCounts[$tag] ?? 0) + 1;
            }
        }
        arsort($tagCounts);

        return [
            'kind' => 'review',
            'total' => (int) $stats->total,
            'average' => round((float) $stats->average, 1),
            'positive' => (int) $stats->positive,
            'negative' => (int) $stats->negative,
            'stars' => collect([5, 4, 3, 2, 1])->map(fn ($star) => ['star' => $star, 'count' => (int) ($stars[$star] ?? 0)])->all(),
            'skus' => $skus,
            'tags' => collect($tagCounts)->take(10)->map(fn ($count, $name) => ['name' => $name, 'count' => $count])->values()->all(),
            'negativeItems' => $negativeItems,
        ];
    }

    private function applyDateRange($query, string $column, Request $request): void
    {
        $query->whereDate($column, '>=', $this->dateQuery($request, 'start', now()->subDays(6)->toDateString()))
            ->whereDate($column, '<=', $this->dateQuery($request, 'end', now()->toDateString()));
    }

    private function dateQuery(Request $request, string $key, string $fallback): string
    {
        $value = (string) $request->query($key, '');

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : $fallback;
    }

    /** @return list<string> */
    private function extractTags(mixed $value): array
    {
        if (! $value) return [];
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : preg_split('/[,，]/u', $value);
        }

        return collect(is_array($value) ? $value : [])->map(fn ($tag) => trim((string) $tag))->filter()->values()->all();
    }

    /** @return array<string, mixed> */
    private function databasePage($query, int $page, int $perPage): array
    {
        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return $this->pagePayload($paginator);
    }

    /** @param array<int, mixed> $rows
     *  @return array<string, mixed>
     */
    private function arrayPage(array $rows, int $page, int $perPage): array
    {
        $paginator = new LengthAwarePaginator(
            array_slice($rows, ($page - 1) * $perPage, $perPage),
            count($rows),
            $perPage,
            $page,
        );

        return $this->pagePayload($paginator);
    }

    /** @return array<string, mixed> */
    private function pagePayload(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => array_values($paginator->items()),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }

    /** @return array<string, mixed> */
    private function pageAnalytics(string $path): array
    {
        $analytics = $this->cachedAnalytics($path);
        if (in_array($path, ['/organic/kol', '/organic/social'], true)) {
            $analytics['rows'] = [];
        }

        return $analytics;
    }

    /** @return array<string, mixed> */
    private function cachedAnalytics(string $path): array
    {
        return $this->analyticsCache[$path] ??= $this->analytics($path);
    }

    /** @return list<string> */
    private function credentialKeys(string $path): array
    {
        $all = [
            'FB_ACCESS_TOKEN', 'SHOPIFY_ACCESS_TOKEN', 'SHOPIFY_STORE_DOMAIN', 'TK_ACCESS_TOKEN', 'TK_ADVERTISER_IDS',
            'GOOGLE_ADS_CLIENT_ID', 'GOOGLE_ADS_CLIENT_SECRET', 'GOOGLE_ADS_REFRESH_TOKEN', 'GOOGLE_ADS_DEVELOPER_TOKEN', 'GOOGLE_ADS_CUSTOMER_ID',
            'BING_ADS_CLIENT_ID', 'BING_ADS_CLIENT_SECRET', 'BING_ADS_REFRESH_TOKEN', 'BING_ADS_DEVELOPER_TOKEN', 'BING_ADS_ACCOUNT_ID',
            'CRITEO_API_KEY', 'CRITEO_CLIENT_SECRET', 'FEISHU_APP_ID', 'FEISHU_APP_SECRET', 'YOUTUBE_CLIENT_ID', 'YOUTUBE_CLIENT_SECRET',
        ];
        if (in_array($path, ['/settings', '/store-settings'], true)) return $all;

        return match ($path) {
            '/ads/facebook' => ['FB_ACCESS_TOKEN'],
            '/ecommerce/shopify' => ['SHOPIFY_ACCESS_TOKEN', 'SHOPIFY_STORE_DOMAIN'],
            '/ads/tiktok' => ['TK_ACCESS_TOKEN', 'TK_ADVERTISER_IDS'],
            '/ads/google' => ['GOOGLE_ADS_CLIENT_ID', 'GOOGLE_ADS_CLIENT_SECRET', 'GOOGLE_ADS_REFRESH_TOKEN', 'GOOGLE_ADS_DEVELOPER_TOKEN', 'GOOGLE_ADS_CUSTOMER_ID'],
            '/ads/bing' => ['BING_ADS_CLIENT_ID', 'BING_ADS_CLIENT_SECRET', 'BING_ADS_REFRESH_TOKEN', 'BING_ADS_DEVELOPER_TOKEN', 'BING_ADS_ACCOUNT_ID'],
            '/ads/criteo' => ['CRITEO_API_KEY', 'CRITEO_CLIENT_SECRET'],
            '/organic/kol', '/organic/edm', '/organic/affiliate', '/reputation/overview' => ['FEISHU_APP_ID', 'FEISHU_APP_SECRET'],
            default => [],
        };
    }

    /** @return array{metrics: array<int, array{label: string, value: string, detail?: string}>, rows: array<int, mixed>, trend: array<int, float|int>, error?: string} */
    private function analytics(string $path, bool $live = false): array
    {
        $result = ['metrics' => [], 'rows' => [], 'trend' => []];
        if (! $live && in_array($path, self::EXTERNAL_PATHS, true)) {
            return $this->snapshots->get('page:'.$path, [...$result, 'error' => '数据尚未同步，请点击刷新。'], $this->storeId());
        }

        if ($path === '/ecommerce/amazon') {
            $sales = (float) DB::table('AmazonOrder')->where('storeId', $this->storeId())->sum('itemPrice');
            $orders = DB::table('AmazonOrder')->where('storeId', $this->storeId())->distinct()->count('amazonOrderId');
            $spend = (float) DB::table('AmazonSpAd')->where('storeId', $this->storeId())->sum('spend') + (float) DB::table('AmazonSbAd')->where('storeId', $this->storeId())->sum('spend');
            $adSales = (float) DB::table('AmazonSpAd')->where('storeId', $this->storeId())->sum('sales7d') + (float) DB::table('AmazonSbAd')->where('storeId', $this->storeId())->sum('sales14d');
            $days = DB::table('AmazonSpAd')->where('storeId', $this->storeId())->distinct()->count('date');
            $result['metrics'] = [
                ['label' => '销售额', 'value' => '$'.number_format($sales, 2)],
                ['label' => '订单数', 'value' => number_format($orders)],
                ['label' => '广告花费', 'value' => '$'.number_format($spend, 2)],
                ['label' => '广告销售额', 'value' => '$'.number_format($adSales, 2)],
                ['label' => 'ROAS', 'value' => $spend > 0 ? number_format($adSales / $spend, 2).'×' : '—'],
                ['label' => '数据天数', 'value' => $days.' 天'],
            ];
            $result['rows'] = DB::table('AmazonOrder')->where('storeId', $this->storeId())->latest('purchaseDate')->limit(25)->get()->all();
            $result['trend'] = DB::table('AmazonOrder')->where('storeId', $this->storeId())->selectRaw('DATE(purchaseDate) day, SUM(itemPrice) total')->groupByRaw('DATE(purchaseDate)')->orderBy('day')->limit(30)->pluck('total')->map(fn ($value) => (float) $value)->all();
        } elseif ($path === '/ads/campaign') {
            $records = $this->feishu->bitableRecords($this->credentials->require('FEISHU_CAMPAIGN_APP_TOKEN', $this->storeId()), $this->credentials->require('FEISHU_CAMPAIGN_TABLE_ID', $this->storeId()), $this->credentials->get('FEISHU_CAMPAIGN_VIEW_ID', $this->storeId()) ?? '', $this->storeId());
            $gmv = collect($records)->sum(fn ($row) => $this->number($row['销售额'] ?? 0));
            $spend = collect($records)->sum(fn ($row) => $this->number($row['广告花费'] ?? 0));
            $orders = collect($records)->sum(fn ($row) => $this->number($row['订单数'] ?? 0));
            $visits = collect($records)->sum(fn ($row) => $this->number($row['店铺访问'] ?? 0));
            $result['metrics'] = [['label' => '总 GMV', 'value' => '$'.number_format($gmv)], ['label' => '广告花费', 'value' => '$'.number_format($spend)], ['label' => '订单', 'value' => number_format($orders)], ['label' => '整体 ROI', 'value' => $spend ? number_format($gmv / $spend, 2).'×' : '—'], ['label' => '整体 CVR', 'value' => $visits ? number_format($orders * 100 / $visits, 2).'%' : '—']];
            $result['rows'] = $records;
            $result['trend'] = collect($records)->map(fn ($row) => $this->number($row['销售额'] ?? 0))->all();
        } elseif ($path === '/ads/target') {
            $records = $this->feishu->bitableRecords('BSjhbR5umaSyruse4bYcfeE0n5b', 'tbl1NvQtOWmeiicG', 'vewzQLO9MF', $this->storeId());
            $latest = collect($records)->sortByDesc(fn ($row) => $this->number($row['日期'] ?? 0))->first() ?? [];
            $result['metrics'] = [
                ['label' => '当日销售额', 'value' => '$'.number_format($this->number($latest['总销售额'] ?? 0), 2)],
                ['label' => '总花费', 'value' => '$'.number_format($this->number($latest['总花费'] ?? 0), 2)],
                ['label' => '总 ROI', 'value' => number_format($this->number($latest['总ROI'] ?? 0), 2).'×'],
                ['label' => '月累计花费', 'value' => '$'.number_format($this->number($latest['月总花费总和'] ?? 0), 2)],
                ['label' => '月度目标销售额', 'value' => '$'.number_format($this->number($latest['月度目标销售额'] ?? 0), 2)],
            ];
            $result['rows'] = array_slice(array_reverse($records), 0, 30);
            $result['trend'] = collect($records)->map(fn ($row) => $this->number($row['总销售额'] ?? 0))->all();
        } elseif ($path === '/ecommerce/shopify') {
            $domain = $this->credentials->get('SHOPIFY_STORE_DOMAIN', $this->storeId());
            $token = $this->credentials->get('SHOPIFY_ACCESS_TOKEN', $this->storeId());
            if ($domain && $token) {
                try {
                    $orders = (function () use ($domain, $token): array {
                    $response = Http::withHeaders(['X-Shopify-Access-Token' => $token])->timeout(15)->get('https://'.preg_replace('#^https?://#', '', rtrim($domain, '/')).'/admin/api/2025-01/orders.json', [
                        'status' => 'any', 'limit' => 250, 'created_at_min' => now()->subDays(30)->toIso8601String(),
                    ]);
                    $response->throw();

                    return $response->json('orders', []);
                    })();
                    $gmv = collect($orders)->sum(fn (array $order) => (float) ($order['total_price'] ?? 0));
                    $count = count($orders);
                    $result['metrics'] = [
                    ['label' => 'GMV（近30天）', 'value' => '$'.number_format($gmv, 2)],
                    ['label' => '订单数', 'value' => number_format($count)],
                    ['label' => '客单价', 'value' => '$'.number_format($count ? $gmv / $count : 0, 2)],
                    ['label' => '日均 GMV', 'value' => '$'.number_format($gmv / 30, 2)],
                    ];
                    $result['rows'] = array_slice($orders, 0, 25);
                    $result['trend'] = collect($orders)->groupBy(fn (array $order) => substr((string) ($order['created_at'] ?? ''), 0, 10))->map(fn ($group) => (float) collect($group)->sum(fn (array $order) => (float) ($order['total_price'] ?? 0)))->values()->all();
                } catch (\Throwable $exception) {
                    $result['error'] = str_contains($exception->getMessage(), '401')
                        ? 'Shopify 已配置，但访问令牌已失效或无权读取订单，请在 Shopify 后台重新生成 Admin API access token。'
                        : 'Shopify API 暂时无法连接，请稍后重试。';
                }
            }
        } elseif (in_array($path, ['/ads/facebook', '/ads/google', '/ads/tiktok', '/ads/bing', '/ads/criteo'], true)) {
            $result = $this->marketingAnalytics($path);
        } elseif ($path === '/organic/kol') {
            $records = $this->feishuRecords('FEISHU_KOL_APP_TOKEN', 'FEISHU_KOL_TABLE_ID', 'FEISHU_KOL_VIEW_ID');
            $result['metrics'] = [
                ['label' => '红人记录', 'value' => number_format(count($records))],
                ['label' => '本地推荐达人', 'value' => number_format(DB::table('KolRecommendItem')->where('storeId', $this->storeId())->count())],
                ['label' => '推荐批次', 'value' => number_format(DB::table('KolRecommendBatch')->where('storeId', $this->storeId())->count())],
            ];
            $result['rows'] = $records ?: DB::table('KolRecommendItem')->where('storeId', $this->storeId())->latest('createdAt')->limit(30)->get()->all();
        } elseif ($path === '/ecommerce/design') {
            $records = $this->designSync->rows($this->storeId());
            $completed = collect($records)->filter(fn ($row) => str_contains($this->text($row['当前状态'] ?? $row['状态'] ?? ''), '完成'))->count();
            $delayed = collect($records)->filter(fn ($row) => in_array($this->text($row['是否延期'] ?? ''), ['是', 'true', '1'], true))->count();
            $result['metrics'] = [['label' => '设计需求', 'value' => number_format(count($records))], ['label' => '已完成', 'value' => number_format($completed)], ['label' => '延期', 'value' => number_format($delayed)]];
            $result['rows'] = $records;
        } elseif ($path === '/organic/edm') {
            $wikiNode = $this->credentials->get('FEISHU_SEQUENCE_WIKI_NODE', $this->storeId());
            $records = $wikiNode ? $this->feishu->spreadsheetRowsFromWiki($wikiNode, '序列表现', $this->storeId()) : [];
            $revenue = collect($records)->sum(fn ($row) => collect($row)->filter(fn ($value, $key) => preg_match('/营收|收入|revenue/i', (string) $key))->sum(fn ($value) => $this->number($value)));
            $openRates = collect($records)->flatMap(fn ($row) => collect($row)->filter(fn ($value, $key) => preg_match('/打开率|open.*rate/i', (string) $key))->map(fn ($value) => $this->number($value)));
            $clickRates = collect($records)->flatMap(fn ($row) => collect($row)->filter(fn ($value, $key) => preg_match('/点击率|click.*rate/i', (string) $key))->map(fn ($value) => $this->number($value)));
            $result['metrics'] = [
                ['label' => '邮件序列', 'value' => number_format(count($records))],
                ['label' => '平均打开率', 'value' => number_format((float) ($openRates->avg() ?? 0) * (($openRates->max() ?? 0) <= 1 ? 100 : 1), 2).'%'],
                ['label' => '平均点击率', 'value' => number_format((float) ($clickRates->avg() ?? 0) * (($clickRates->max() ?? 0) <= 1 ? 100 : 1), 2).'%'],
                ['label' => '邮件营收', 'value' => '$'.number_format($revenue, 2)],
            ];
            $result['rows'] = $records;
            $result['trend'] = collect($records)->map(fn ($row) => collect($row)->filter(fn ($value, $key) => preg_match('/营收|收入|revenue/i', (string) $key))->sum(fn ($value) => $this->number($value)))->all();
        } elseif ($path === '/organic/affiliate') {
            $records = $this->feishuRecords('FEISHU_AFFILIATE_APP_TOKEN', 'FEISHU_AFFILIATE_TABLE_ID', 'FEISHU_AFFILIATE_VIEW_ID');
            $result['metrics'] = [['label' => '联盟记录', 'value' => number_format(count($records))]];
            $result['rows'] = $records;
        } elseif ($path === '/organic/social') {
            $totals = DB::table('SocialMediaPost')->where('storeId', $this->storeId())->selectRaw('COUNT(*) posts, COALESCE(SUM(views),0) views, COALESCE(SUM(likes),0) likes, COALESCE(SUM(comments),0) comments, COALESCE(SUM(shares),0) shares')->first();
            $result['metrics'] = [
                ['label' => '帖子数', 'value' => number_format($totals->posts)],
                ['label' => '浏览量', 'value' => number_format($totals->views)],
                ['label' => '点赞', 'value' => number_format($totals->likes)],
                ['label' => '评论', 'value' => number_format($totals->comments)],
                ['label' => '分享', 'value' => number_format($totals->shares)],
            ];
            $result['trend'] = DB::table('SocialMediaPost')->where('storeId', $this->storeId())->orderBy('publishedAt')->limit(30)->pluck('views')->map(fn ($value) => (int) $value)->all();
        } elseif ($path === '/organic/kol-legacy') {
            $items = DB::table('KolRecommendItem')->where('storeId', $this->storeId())->count();
            $batches = DB::table('KolRecommendBatch')->where('storeId', $this->storeId())->count();
            $result['metrics'] = [['label' => '推荐批次', 'value' => number_format($batches)], ['label' => '推荐达人', 'value' => number_format($items)]];
            $result['rows'] = DB::table('KolRecommendItem')->where('storeId', $this->storeId())->latest('createdAt')->limit(30)->get()->all();
        } elseif ($path === '/workspace/ai-brain') {
            $result['metrics'] = [
                ['label' => '历史会话', 'value' => number_format(DB::table('AiConversation')->where('storeId', $this->storeId())->count())],
                ['label' => '消息', 'value' => number_format(DB::table('AiMessage')->where('storeId', $this->storeId())->count())],
                ['label' => '快捷提示词', 'value' => number_format(DB::table('AiQuickPrompt')->where('storeId', $this->storeId())->count())],
                ['label' => 'AI 建议', 'value' => number_format(DB::table('AiSuggestion')->where('storeId', $this->storeId())->count())],
            ];
            $result['rows'] = DB::table('AiConversation')->where('storeId', $this->storeId())->latest('updatedAt')->limit(20)->get()->all();
        } elseif (str_starts_with($path, '/reputation/')) {
            $reviews = DB::table('ReputationReview')->where('storeId', $this->storeId());
            if (in_array($path, ['/reputation/google-reviews', '/reputation/trustpilot', '/reputation/website-reviews'], true)) {
                $platform = match ($path) { '/reputation/google-reviews' => 'GOOGLE', '/reputation/trustpilot' => 'TRUSTPILOT', default => 'WEBSITE' };
                $reviews->where('platform', $platform);
            }
            $count = (clone $reviews)->count();
            $average = (float) ((clone $reviews)->avg('star') ?? 0);
            $positive = (clone $reviews)->where('sentiment', 'POSITIVE')->count();
            $negative = (clone $reviews)->where('sentiment', 'NEGATIVE')->count();
            $result['metrics'] = [
                ['label' => '评论总数', 'value' => number_format($count)],
                ['label' => '综合评分', 'value' => number_format($average, 1).'★'],
                ['label' => '正面占比', 'value' => $count ? number_format($positive * 100 / $count, 1).'%' : '0%'],
                ['label' => '负面占比', 'value' => $count ? number_format($negative * 100 / $count, 1).'%' : '0%'],
            ];
            if ($path === '/reputation/reddit') {
                $posts = DB::table('RedditPost')->where('storeId', $this->storeId());
                $result['metrics'] = [['label' => '帖子总数', 'value' => number_format($posts->count())], ['label' => '风险项', 'value' => number_format(DB::table('ReputationRisk')->where('storeId', $this->storeId())->count())]];
                $result['trend'] = $posts->orderBy('createdAt')->limit(30)->pluck('upvotes')->map(fn ($value) => (int) $value)->all();
            }
        }

        if ($live && in_array($path, self::EXTERNAL_PATHS, true)) $this->snapshots->put('page:'.$path, $result, $this->storeId());
        return $result;
    }

    public function syncExternalPage(string $path): array
    {
        abort_unless(in_array($path, self::EXTERNAL_PATHS, true), 404);
        return $this->analytics($path, true);
    }

    public static function externalPaths(): array { return self::EXTERNAL_PATHS; }

    private function number(mixed $value): float
    {
        if (is_array($value)) $value = $value[0] ?? 0;

        return (float) preg_replace('/[^0-9.\-]/', '', (string) $value);
    }

    private function text(mixed $value): string
    {
        if (is_array($value)) $value = $value[0] ?? '';
        if (is_array($value)) $value = $value['text'] ?? $value['name'] ?? $value['value'] ?? '';

        return is_scalar($value) ? (string) $value : '';
    }

    /** @return array<int, array<string, mixed>> */
    private function feishuRecords(string $appKey, string $tableKey, ?string $viewKey = null): array
    {
        $appToken = $this->credentials->get($appKey, $this->storeId());
        $tableId = $this->credentials->get($tableKey, $this->storeId());
        if (! $appToken || ! $tableId) return [];

        return $this->feishu->bitableRecords($appToken, $tableId, $viewKey ? ($this->credentials->get($viewKey, $this->storeId()) ?? '') : '', $this->storeId());
    }

    /** @return array{metrics: array<int, array{label: string, value: string}>, rows: array<int, mixed>, trend: array<int, float|int>, error?: string} */
    private function marketingAnalytics(string $path): array
    {
        $result = ['metrics' => [], 'rows' => [], 'trend' => []];
        $until = now('America/Los_Angeles')->subDay();
        $since = $until->copy()->subDays(6);
        try {
            if ($path === '/ads/facebook') {
                $token = $this->credentials->require('FB_ACCESS_TOKEN', $this->storeId());
                $accounts = Http::timeout(15)->get('https://graph.facebook.com/v21.0/me/adaccounts', ['access_token' => $token, 'fields' => 'id,name', 'limit' => 50])->throw()->json('data', []);
                $daily = [];
                foreach ($accounts as $account) {
                    $rows = Http::timeout(20)->get('https://graph.facebook.com/v21.0/'.$account['id'].'/insights', [
                        'access_token' => $token,
                        'fields' => 'campaign_id,campaign_name,date_start,spend,impressions,clicks,actions,action_values',
                        'time_range' => json_encode(['since' => $since->toDateString(), 'until' => $until->toDateString()]), 'time_increment' => 1, 'level' => 'campaign', 'limit' => 500,
                    ])->throw()->json('data', []);
                    $daily = array_merge($daily, $rows);
                }
                $spend = collect($daily)->sum(fn ($row) => (float) ($row['spend'] ?? 0));
                $revenue = collect($daily)->sum(fn ($row) => (float) (collect($row['action_values'] ?? [])->firstWhere('action_type', 'purchase')['value'] ?? 0));
                $purchases = collect($daily)->sum(fn ($row) => (float) (collect($row['actions'] ?? [])->firstWhere('action_type', 'purchase')['value'] ?? 0));
                $result['metrics'] = [['label' => '广告花费', 'value' => '$'.number_format($spend, 2)], ['label' => '购买价值', 'value' => '$'.number_format($revenue, 2)], ['label' => 'ROAS', 'value' => $spend ? number_format($revenue / $spend, 2).'×' : '0.00×'], ['label' => '转化', 'value' => number_format($purchases)]];
                $result['rows'] = $daily;
                $result['trend'] = collect($daily)->groupBy('date_start')->map(fn ($rows) => (float) collect($rows)->sum(fn ($row) => (float) ($row['spend'] ?? 0)))->values()->all();
            } elseif ($path === '/ads/tiktok') {
                $token = $this->credentials->require('TK_ACCESS_TOKEN', $this->storeId());
                $advertiser = trim(explode(',', $this->credentials->require('TK_ADVERTISER_IDS', $this->storeId()))[0]);
                $response = Http::withHeaders(['Access-Token' => $token])->timeout(20)->get('https://business-api.tiktok.com/open_api/v1.3/report/integrated/get/', [
                    'advertiser_id' => $advertiser, 'report_type' => 'BASIC', 'data_level' => 'AUCTION_CAMPAIGN',
                    'dimensions' => json_encode(['campaign_id','stat_time_day']), 'metrics' => json_encode(['spend','impressions','clicks','conversion','complete_payment','complete_payment_roas']),
                    'start_date' => $since->toDateString(), 'end_date' => $until->toDateString(), 'page' => 1, 'page_size' => 90,
                ])->throw()->json();
                if (($response['code'] ?? -1) !== 0) {
                    throw new \RuntimeException((string) ($response['message'] ?? 'TikTok API error'));
                }
                $daily = $response['data']['list'] ?? [];
                $spend = collect($daily)->sum(fn ($row) => (float) ($row['metrics']['spend'] ?? 0));
                $revenue = collect($daily)->sum(fn ($row) => (float) ($row['metrics']['spend'] ?? 0) * (float) ($row['metrics']['complete_payment_roas'] ?? 0));
                $conversions = collect($daily)->sum(fn ($row) => (float) ($row['metrics']['conversion'] ?? 0));
                $impressions = collect($daily)->sum(fn ($row) => (int) ($row['metrics']['impressions'] ?? 0));
                $result['metrics'] = [['label' => '广告花费', 'value' => '$'.number_format($spend, 2)], ['label' => '转化价值', 'value' => '$'.number_format($revenue, 2)], ['label' => 'ROAS', 'value' => $spend ? number_format($revenue / $spend, 2).'×' : '0.00×'], ['label' => '展示', 'value' => number_format($impressions)], ['label' => '转化', 'value' => number_format($conversions)]];
                $result['rows'] = $daily;
                $result['trend'] = collect($daily)->map(fn ($row) => (float) ($row['metrics']['spend'] ?? 0))->all();
            } elseif ($path === '/ads/google') {
                $credentials = $this->credentials->many(['GOOGLE_ADS_CLIENT_ID','GOOGLE_ADS_CLIENT_SECRET','GOOGLE_ADS_REFRESH_TOKEN','GOOGLE_ADS_DEVELOPER_TOKEN','GOOGLE_ADS_CUSTOMER_ID','GOOGLE_ADS_LOGIN_CUSTOMER_ID'], $this->storeId());
                $oauth = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', ['client_id' => $credentials['GOOGLE_ADS_CLIENT_ID'], 'client_secret' => $credentials['GOOGLE_ADS_CLIENT_SECRET'], 'refresh_token' => $credentials['GOOGLE_ADS_REFRESH_TOKEN'], 'grant_type' => 'refresh_token'])->throw()->json('access_token');
                $customer = str_replace('-', '', $credentials['GOOGLE_ADS_CUSTOMER_ID']);
                $headers = ['Authorization' => 'Bearer '.$oauth, 'developer-token' => $credentials['GOOGLE_ADS_DEVELOPER_TOKEN']];
                if (! empty($credentials['GOOGLE_ADS_LOGIN_CUSTOMER_ID'])) $headers['login-customer-id'] = str_replace('-', '', $credentials['GOOGLE_ADS_LOGIN_CUSTOMER_ID']);
                $query = "SELECT campaign.id, campaign.name, segments.date, metrics.cost_micros, metrics.impressions, metrics.clicks, metrics.conversions, metrics.conversions_value FROM campaign WHERE segments.date BETWEEN '{$since->toDateString()}' AND '{$until->toDateString()}' ORDER BY segments.date ASC";
                $daily = Http::withHeaders($headers)->timeout(25)->post("https://googleads.googleapis.com/v23/customers/{$customer}/googleAds:search", ['query' => $query])->throw()->json('results', []);
                $spend = collect($daily)->sum(fn ($row) => (float) ($row['metrics']['costMicros'] ?? 0) / 1000000);
                $revenue = collect($daily)->sum(fn ($row) => (float) ($row['metrics']['conversionsValue'] ?? 0));
                $clicks = collect($daily)->sum(fn ($row) => (int) ($row['metrics']['clicks'] ?? 0));
                $result['metrics'] = [['label' => '广告花费', 'value' => '$'.number_format($spend, 2)], ['label' => '转化价值', 'value' => '$'.number_format($revenue, 2)], ['label' => 'ROAS', 'value' => $spend ? number_format($revenue / $spend, 2).'×' : '0.00×'], ['label' => '点击', 'value' => number_format($clicks)]];
                $result['rows'] = $daily;
                $result['trend'] = collect($daily)->map(fn ($row) => (float) ($row['metrics']['costMicros'] ?? 0) / 1000000)->all();
            } elseif ($path === '/ads/bing') {
                $result = $this->bingAnalytics($since->toDateString(), $until->toDateString());
            } elseif ($path === '/ads/criteo') {
                $result = $this->criteoAnalytics($since->toDateString(), $until->toDateString());
            } elseif ($path === '/ads/bing-legacy') {
                $credentials = $this->credentials->many(['BING_ADS_CLIENT_ID','BING_ADS_CLIENT_SECRET','BING_ADS_REFRESH_TOKEN'], $this->storeId());
                Http::asForm()->timeout(15)->post('https://login.microsoftonline.com/common/oauth2/v2.0/token', ['client_id' => $credentials['BING_ADS_CLIENT_ID'], 'client_secret' => $credentials['BING_ADS_CLIENT_SECRET'], 'refresh_token' => $credentials['BING_ADS_REFRESH_TOKEN'], 'grant_type' => 'refresh_token', 'scope' => 'https://ads.microsoft.com/msads.manage offline_access'])->throw();
                $result['error'] = 'Bing Ads 授权有效；完整报表由 Microsoft 异步生成，当前尚未完成本地报表缓存。';
            } elseif ($path === '/ads/criteo-legacy') {
                Http::asForm()->timeout(15)->post('https://api.criteo.com/oauth2/token', ['client_id' => $this->credentials->require('CRITEO_API_KEY', $this->storeId()), 'client_secret' => $this->credentials->require('CRITEO_CLIENT_SECRET', $this->storeId()), 'grant_type' => 'client_credentials'])->throw();
                $result['error'] = 'Criteo 授权有效；当前缺少广告主维度报表缓存。';
            }
        } catch (\Throwable $exception) {
            $platform = match ($path) { '/ads/facebook' => 'Meta Ads', '/ads/google' => 'Google Ads', '/ads/tiktok' => 'TikTok Ads', '/ads/bing' => 'Bing Ads', default => 'Criteo' };
            $result['error'] = $platform.' 连接失败：'.(str_contains($exception->getMessage(), '401') ? '访问令牌已失效或权限不足。' : '平台拒绝请求，请更新授权凭证。');
        }

        return $result;
    }

    /** @return array{metrics: array<int, array{label: string, value: string}>, rows: array<int, mixed>, trend: array<int, float|int>} */
    private function criteoAnalytics(string $since, string $until): array
    {
        $token = Http::asForm()->timeout(15)->post('https://api.criteo.com/oauth2/token', [
            'client_id' => $this->credentials->require('CRITEO_API_KEY', $this->storeId()),
            'client_secret' => $this->credentials->require('CRITEO_CLIENT_SECRET', $this->storeId()),
            'grant_type' => 'client_credentials',
        ])->throw()->json('access_token');
        $advertiserIds = array_values(array_filter(array_map('trim', explode(',', (string) $this->credentials->get('CRITEO_ADVERTISER_ID', $this->storeId())))));
        if (! $advertiserIds) {
            $advertiserIds = collect(Http::withToken($token)->timeout(20)->get('https://api.criteo.com/2025-01/advertisers/me')->throw()->json('data', []))->pluck('id')->map(fn ($id) => (string) $id)->all();
        }
        if (! $advertiserIds) throw new \RuntimeException('No Criteo advertiser account is available.');
        $body = Http::withToken($token)->accept('text/csv')->timeout(30)->post('https://api.criteo.com/2025-01/statistics/report', [
            'advertiserIds' => implode(',', $advertiserIds), 'startDate' => $since,
            'endDate' => $until, 'dimensions' => ['Day'],
            'metrics' => ['AdvertiserCost','Displays','Clicks','SalesPc7d','SalesPv24h','RevenueGeneratedPc7d','RevenueGeneratedPv24h'],
            'currency' => 'USD', 'timezone' => 'UTC', 'format' => 'csv',
        ])->throw()->body();
        $lines = preg_split('/\r?\n/', trim($body));
        $separator = str_contains($lines[0] ?? '', ';') ? ';' : ',';
        $headers = array_map(fn ($value) => trim($value, " \t\n\r\0\x0B\""), str_getcsv(array_shift($lines) ?: '', $separator));
        $rows = collect($lines)->filter()->map(function ($line) use ($headers, $separator) {
            $values = array_map(fn ($value) => trim($value, " \t\n\r\0\x0B\""), str_getcsv($line, $separator));
            return array_combine($headers, array_pad($values, count($headers), '')) ?: [];
        })->values();
        $spend = $rows->sum(fn ($row) => $this->number($row['AdvertiserCost'] ?? 0));
        $revenue = $rows->sum(fn ($row) => $this->number($row['RevenueGeneratedPc7d'] ?? 0) + $this->number($row['RevenueGeneratedPv24h'] ?? 0));
        $sales = $rows->sum(fn ($row) => $this->number($row['SalesPc7d'] ?? 0) + $this->number($row['SalesPv24h'] ?? 0));
        $clicks = $rows->sum(fn ($row) => $this->number($row['Clicks'] ?? 0));
        $impressions = $rows->sum(fn ($row) => $this->number($row['Displays'] ?? 0));

        return [
            'metrics' => [['label' => '广告花费', 'value' => '$'.number_format($spend, 2)], ['label' => '收入', 'value' => '$'.number_format($revenue, 2)], ['label' => 'ROAS', 'value' => number_format($spend ? $revenue / $spend : 0, 2).'×'], ['label' => '转化', 'value' => number_format($sales)], ['label' => '点击', 'value' => number_format($clicks)], ['label' => '展示', 'value' => number_format($impressions)]],
            'rows' => $rows->all(),
            'trend' => $rows->map(fn ($row) => $this->number($row['AdvertiserCost'] ?? 0))->all(),
        ];
    }

    private function bingAnalytics(string $since, string $until): array
    {
        $config = $this->credentials->many(['BING_ADS_CLIENT_ID','BING_ADS_CLIENT_SECRET','BING_ADS_REFRESH_TOKEN','BING_ADS_DEVELOPER_TOKEN','BING_ADS_ACCOUNT_ID','BING_ADS_CUSTOMER_ID'], $this->storeId());
        $token = Http::asForm()->timeout(15)->post('https://login.microsoftonline.com/common/oauth2/v2.0/token', ['client_id' => $config['BING_ADS_CLIENT_ID'], 'client_secret' => $config['BING_ADS_CLIENT_SECRET'], 'refresh_token' => $config['BING_ADS_REFRESH_TOKEN'], 'grant_type' => 'refresh_token', 'scope' => 'https://ads.microsoft.com/msads.manage offline_access'])->throw()->json('access_token');
        $headers = ['Authorization' => 'Bearer '.$token, 'DeveloperToken' => $config['BING_ADS_DEVELOPER_TOKEN'], 'CustomerAccountId' => (string) $config['BING_ADS_ACCOUNT_ID'], 'Accept' => 'application/json'];
        if (! empty($config['BING_ADS_CUSTOMER_ID'])) $headers['CustomerId'] = (string) $config['BING_ADS_CUSTOMER_ID'];
        $date = fn (string $value) => ['Day' => (int) substr($value, 8, 2), 'Month' => (int) substr($value, 5, 2), 'Year' => (int) substr($value, 0, 4)];
        $request = ['Type' => 'AccountPerformanceReportRequest', 'ExcludeColumnHeaders' => false, 'ExcludeReportFooter' => true, 'ExcludeReportHeader' => true, 'Format' => 'Csv', 'FormatVersion' => '2.0', 'ReportName' => 'LaravelBingDailyPerf', 'ReturnOnlyCompleteData' => false, 'Aggregation' => 'Daily', 'Columns' => ['TimePeriod','AccountId','Spend','Impressions','Clicks','Ctr','AverageCpc','Conversions','Revenue'], 'Scope' => ['AccountIds' => [(int) $config['BING_ADS_ACCOUNT_ID']]], 'Time' => ['CustomDateRangeStart' => $date($since), 'CustomDateRangeEnd' => $date($until)]];
        $submit = Http::withHeaders($headers)->asJson()->timeout(20)->post('https://reporting.api.bingads.microsoft.com/Reporting/v13/GenerateReport/Submit', ['ReportRequest' => $request])->throw()->json();
        $reportId = $submit['ReportRequestId'] ?? null;
        if (! $reportId) throw new \RuntimeException('Bing report submission did not return a report id.');
        $downloadUrl = null;
        for ($attempt = 0; $attempt < 30; $attempt++) {
            usleep(1000000);
            $status = Http::withHeaders($headers)->asJson()->timeout(15)->post('https://reporting.api.bingads.microsoft.com/Reporting/v13/GenerateReport/Poll', ['ReportRequestId' => $reportId]);
            if (! $status->successful()) continue;
            if ($status->json('ReportRequestStatus.Status') === 'Error') throw new \RuntimeException('Bing report generation failed.');
            if ($status->json('ReportRequestStatus.Status') === 'Success') { $downloadUrl = $status->json('ReportRequestStatus.ReportDownloadUrl'); break; }
        }
        if (! $downloadUrl) throw new \RuntimeException('Bing report generation timed out.');
        $download = Http::timeout(30)->get($downloadUrl)->throw();
        $csv = $download->body();
        if (str_contains(strtolower($download->header('Content-Type')), 'zip') || str_contains(strtolower($downloadUrl), '.zip')) {
            $temporary = tempnam(sys_get_temp_dir(), 'bing-report-'); file_put_contents($temporary, $csv); $zip = new \ZipArchive();
            if ($zip->open($temporary) !== true || $zip->numFiles < 1) { @unlink($temporary); throw new \RuntimeException('Bing returned an empty report archive.'); }
            $csv = (string) $zip->getFromIndex(0); $zip->close(); @unlink($temporary);
        }
        $lines = array_values(array_filter(preg_split('/\r?\n/', $csv), fn ($line) => trim($line) !== ''));
        $headerIndex = collect($lines)->search(fn ($line) => str_contains($line, 'Spend') && str_contains($line, 'Impressions'));
        if ($headerIndex === false) $headerIndex = 0;
        $headersCsv = str_getcsv($lines[$headerIndex] ?? '');
        $rows = collect(array_slice($lines, $headerIndex + 1))->map(fn ($line) => array_combine($headersCsv, array_pad(str_getcsv($line), count($headersCsv), '')) ?: [])->values();
        $spend = $rows->sum(fn ($row) => $this->number($row['Spend'] ?? 0)); $revenue = $rows->sum(fn ($row) => $this->number($row['Revenue'] ?? 0)); $conversions = $rows->sum(fn ($row) => $this->number($row['Conversions'] ?? 0)); $clicks = $rows->sum(fn ($row) => $this->number($row['Clicks'] ?? 0)); $impressions = $rows->sum(fn ($row) => $this->number($row['Impressions'] ?? 0));
        return ['metrics' => [['label' => '广告花费', 'value' => '$'.number_format($spend, 2)], ['label' => '收入', 'value' => '$'.number_format($revenue, 2)], ['label' => 'ROAS', 'value' => number_format($spend ? $revenue / $spend : 0, 2).'×'], ['label' => '转化', 'value' => number_format($conversions)], ['label' => '点击', 'value' => number_format($clicks)], ['label' => '展示', 'value' => number_format($impressions)]], 'rows' => $rows->all(), 'trend' => $rows->map(fn ($row) => $this->number($row['Spend'] ?? 0))->all()];
    }

    private function storeId(): string
    {
        return $this->storeContext->id();
    }
}
