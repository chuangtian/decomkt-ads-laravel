<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ModuleController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $definition = collect(config('decomkt.pages'))->firstWhere('path', '/'.$request->path());
        abort_unless($definition, 404);

        $configuration = [];
        if ($definition['path'] === '/settings') {
            $configuration = DB::table('SystemConfig')->pluck('key')->mapWithKeys(fn ($key) => [$key => true]);
        }
        if ($definition['path'] === '/store-settings') {
            $configuration = DB::table('StoreConfig')->where('storeId', 'default-store')->pluck('key')->mapWithKeys(fn ($key) => [$key => true]);
        }

        $studentDiscount = null;
        if ($definition['path'] === '/ecommerce/student-discounts') {
            $studentDiscount = DB::table('StudentDiscountCampaign')->where('storeId', 'default-store')->first();
            if ($studentDiscount) {
                $studentDiscount->issued = DB::table('StudentDiscountClaim')->where('storeId', 'default-store')->where('status', 'ISSUED')->count();
                $studentDiscount->failed = DB::table('StudentDiscountClaim')->where('storeId', 'default-store')->where('status', 'FAILED')->count();
                $studentDiscount->total = DB::table('StudentDiscountClaim')->where('storeId', 'default-store')->count();
            }
        }

        $records = [];
        if (str_starts_with($definition['path'], '/reputation/')) {
            $records = match ($definition['path']) {
                '/reputation/reddit' => DB::table('RedditPost')->where('storeId', 'default-store')->latest('createdAt')->limit(50)->get(),
                '/reputation/risk-sync' => ['risks' => DB::table('ReputationRisk')->where('storeId', 'default-store')->latest('createdAt')->get(), 'resources' => DB::table('ReputationResource')->where('storeId', 'default-store')->latest('createdAt')->get()],
                '/reputation/google-reviews' => DB::table('ReputationReview')->where('storeId', 'default-store')->where('platform', 'GOOGLE')->latest('createdAt')->limit(50)->get(),
                '/reputation/trustpilot' => DB::table('ReputationReview')->where('storeId', 'default-store')->where('platform', 'TRUSTPILOT')->latest('createdAt')->limit(50)->get(),
                '/reputation/website-reviews' => DB::table('ReputationReview')->where('storeId', 'default-store')->where('platform', 'WEBSITE')->latest('createdAt')->limit(50)->get(),
                '/reputation/weekly-report' => DB::table('ReputationWeeklyReport')->where('storeId', 'default-store')->orderByDesc('year')->orderByDesc('week')->limit(12)->get(),
                default => [],
            };
        }

        return Inertia::render('Module', [
            'module' => $definition,
            'store' => ['id' => 'default-store', 'name' => '默认店铺', 'timezone' => 'America/Los_Angeles'],
            'configuration' => $configuration,
            'studentDiscount' => $studentDiscount,
            'records' => $records,
        ]);
    }
}
