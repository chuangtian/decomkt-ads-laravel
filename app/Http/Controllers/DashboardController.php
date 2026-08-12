<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Dashboard', [
            'metrics' => [
                ['label' => '业务数据表', 'value' => count(Schema::getTables()), 'hint' => 'MySQL 8.4'],
                ['label' => '页面权限', 'value' => DB::table('Permission')->count(), 'hint' => 'RBAC 已同步'],
                ['label' => '店铺', 'value' => DB::table('Store')->where('status', 'ACTIVE')->count(), 'hint' => '多店铺架构'],
                ['label' => '团队成员', 'value' => DB::table('Employee')->where('status', 'ACTIVE')->count(), 'hint' => '可分配角色'],
            ],
            'systems' => [
                ['name' => 'MySQL', 'status' => '正常', 'detail' => '主业务数据库'],
                ['name' => 'Redis', 'status' => '正常', 'detail' => 'Session / Cache / Queue'],
                ['name' => 'Laravel Queue', 'status' => '正常', 'detail' => '异步任务'],
                ['name' => 'Scheduler', 'status' => '正常', 'detail' => '定时同步'],
            ],
        ]);
    }
}
