<?php

namespace App\Http\Controllers;

use App\Services\DataSync\SeoSnapshotService;
use App\Services\Integrations\Ga4Connector;
use App\Services\Integrations\GscConnector;
use App\Services\Stores\StoreContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SeoController extends Controller
{
    public function index(SeoSnapshotService $snapshots, StoreContext $storeContext): Response
    {
        $snapshot = $snapshots->get($storeContext->id());

        return Inertia::render('Seo', [
            'initialData' => $snapshot['overview'] ?? null,
            'initialRange' => $snapshot['range'] ?? null,
            'goals' => $snapshot['goals'] ?? null,
            'dataSync' => $snapshots->status($storeContext->id()),
        ]);
    }

    public function refresh(Request $request, SeoSnapshotService $snapshots, StoreContext $storeContext): RedirectResponse
    {
        $data = $request->validate([
            'startDate' => ['nullable', 'date'],
            'endDate' => ['nullable', 'date', 'after_or_equal:startDate'],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);
        $snapshots->sync($data['startDate'] ?? null, $data['endDate'] ?? null, $data['month'] ?? null, $storeContext->id());

        return back()->with('success', 'SEO、GSC 与 GA4 数据已更新到数据库。');
    }

    public function overview(Request $request, GscConnector $gsc, Ga4Connector $ga4, StoreContext $storeContext): JsonResponse
    {
        $data = $request->validate(['startDate' => ['required', 'date'], 'endDate' => ['required', 'date', 'after_or_equal:startDate']]);
        $result = ['range' => $data, 'gsc' => null, 'ga4' => null, 'errors' => []];
        try {
            $result['gsc'] = $gsc->overview($data['startDate'], $data['endDate'], $storeContext->id());
        } catch (Throwable $exception) {
            $result['errors']['gsc'] = $exception->getMessage();
        }
        try {
            $result['ga4'] = $ga4->overview($data['startDate'], $data['endDate'], $storeContext->id());
        } catch (Throwable $exception) {
            $result['errors']['ga4'] = $exception->getMessage();
        }

        return response()->json($result, $result['gsc'] || $result['ga4'] ? 200 : 502);
    }
}
