<?php

namespace App\Http\Controllers;

use App\Services\DataSync\DesignDataSyncService;
use App\Services\Stores\StoreContext;
use Illuminate\Http\RedirectResponse;

class DesignDataController extends Controller
{
    public function refresh(DesignDataSyncService $sync, StoreContext $storeContext): RedirectResponse
    {
        $records = $sync->sync($storeContext->id());

        return back()->with('success', '视觉设计数据已更新，共 '.count($records).' 条。');
    }
}
