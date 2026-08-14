<?php

namespace App\Http\Controllers;

use App\Services\Stores\StoreContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(StoreContext $storeContext): Response
    {
        $storeId = $storeContext->id();
        return Inertia::render('Dashboard', [
            'metrics' => [
                ['label' => '业务数据表', 'value' => count(Schema::getTables()), 'hint' => 'MySQL 8.4'],
                ['label' => '页面权限', 'value' => DB::table('Permission')->count(), 'hint' => 'RBAC 已同步'],
                ['label' => '当前店铺模块', 'value' => DB::table('StorePage')->where('storeId', $storeId)->where('enabled', true)->count(), 'hint' => $storeContext->store()?->name ?? '当前店铺'],
                ['label' => '店铺成员', 'value' => DB::table('StoreMember')->where('storeId', $storeId)->count(), 'hint' => '已授权访问'],
            ],
            'systems' => [
                ['name' => 'MySQL', 'status' => '正常', 'detail' => '主业务数据库'],
                ['name' => 'MySQL 缓存', 'status' => '正常', 'detail' => 'Session / Cache / Queue'],
                ['name' => 'Laravel Queue', 'status' => '正常', 'detail' => '异步任务'],
                ['name' => 'Scheduler', 'status' => '正常', 'detail' => '定时同步'],
            ],
            'amazon' => [
                'sales' => (float) DB::table('AmazonOrder')->where('storeId', $storeId)->sum('itemPrice'),
                'orders' => DB::table('AmazonOrder')->where('storeId', $storeId)->distinct()->count('amazonOrderId'),
                'adSpend' => (float) DB::table('AmazonSpAd')->where('storeId', $storeId)->sum('spend') + (float) DB::table('AmazonSbAd')->where('storeId', $storeId)->sum('spend'),
            ],
        ]);
    }
}
