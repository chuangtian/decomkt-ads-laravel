<?php

namespace App\Http\Middleware;

use App\Services\Stores\StoreContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsurePageAccess
{
    public function __construct(private readonly StoreContext $storeContext) {}

    public function handle(Request $request, Closure $next): Response
    {
        $storeContext = $this->storeContext;
        $employee = $storeContext->employee();
        abort_unless($employee && $employee->status === 'ACTIVE', 403, '账号已停用或未建立员工档案');

        $storeSlug = (string) ($request->route('storeSlug') ?? '');
        if ($storeSlug !== '') {
            $storeContext->switchToSlug($storeSlug);
        }

        $path = '/'.trim($request->path(), '/');
        $path = $path === '/' ? '/' : $path;
        if ($storeSlug !== '') {
            $prefix = '/'.$storeSlug;
            $path = substr($path, strlen($prefix)) ?: '/';
        }
        if (str_starts_with($path, '/account') || $path === '/stores/switch') {
            return $next($request);
        }
        if (str_starts_with($path, '/api/')) {
            $segments = explode('/', trim(substr($path, 5), '/'));
            $path = match ($segments[0] ?? '') {
                'ads', 'ecommerce', 'organic', 'reputation', 'workspace' => '/'.implode('/', array_slice($segments, 0, 2)),
                'collab' => ($segments[1] ?? '') === 'employees' ? '/collab/team' : '/collab/kanban',
                'admin' => match ($segments[1] ?? '') {
                    'employees' => '/employees','roles' => '/roles','permissions' => '/permissions','stores' => '/stores',default => '/settings'
                },
                'store-settings' => '/store-settings',
                default => '/',
            };
        }
        $permissionPath = match (true) {
            str_starts_with($path, '/tasks') => '/collab/kanban',
            $path === '/data-sync/refresh' => in_array((string) $request->input('path'), StoreContext::allStorePaths(), true) ? (string) $request->input('path') : '/',
            str_starts_with($path, '/organic/seo/') => '/organic/seo',
            str_starts_with($path, '/ecommerce/design/') => '/ecommerce/design',
            str_starts_with($path, '/employees/') => '/employees',
            str_starts_with($path, '/roles/') => '/roles',
            str_starts_with($path, '/stores/') => '/stores',
            str_starts_with($path, '/permissions/') => '/permissions',
            $path === '/configuration/system' || str_starts_with($path, '/configuration/system/') => '/settings',
            $path === '/configuration/store' || str_starts_with($path, '/configuration/store/') => '/store-settings',
            $path === '/student-discounts' => '/ecommerce/student-discounts',
            str_starts_with($path, '/reputation/items') => '/reputation/overview',
            str_starts_with($path, '/reputation/analyze') => '/reputation/risk-sync',
            str_starts_with($path, '/reputation/weekly-report') => '/reputation/weekly-report',
            default => $path,
        };

        $isStorePath = in_array($permissionPath, StoreContext::allStorePaths(), true);
        if ($isStorePath) {
            abort_unless($storeContext->isEnabled($permissionPath), 404, '当前店铺未启用此模块');
        }

        if ($employee->isDefaultAdmin) {
            return $next($request);
        }

        $membership = $storeContext->membership();
        if ($isStorePath && $membership?->role === 'OWNER') {
            return $next($request);
        }

        $allowed = DB::table('Permission')
            ->leftJoin('RolePermission', 'RolePermission.permissionId', '=', 'Permission.id')
            ->leftJoin('EmployeeRole', 'EmployeeRole.roleId', '=', 'RolePermission.roleId')
            ->leftJoin('EmployeePermission', 'EmployeePermission.permissionId', '=', 'Permission.id')
            ->where(function ($query) use ($employee, $membership, $isStorePath) {
                $query->where('EmployeeRole.employeeId', $employee->id)->orWhere('EmployeePermission.employeeId', $employee->id);
                if ($isStorePath && $membership?->roleId) {
                    $query->orWhere('RolePermission.roleId', $membership->roleId);
                }
            })->pluck('Permission.description')->filter()->contains($permissionPath);

        abort_unless($allowed, 403, '没有访问此页面的权限');

        return $next($request);
    }
}
