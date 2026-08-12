<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsurePageAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $employee = DB::table('Employee')->where('email', $request->user()?->email)->first();
        abort_unless($employee && $employee->status === 'ACTIVE', 403, '账号已停用或未建立员工档案');
        if ($employee->isDefaultAdmin) {
            return $next($request);
        }

        $path = '/'.trim($request->path(), '/');
        $path = $path === '/' ? '/' : $path;
        if (str_starts_with($path, '/account')) {
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
            str_starts_with($path, '/employees/') => '/employees',
            str_starts_with($path, '/roles/') => '/roles',
            str_starts_with($path, '/stores/') => '/stores',
            str_starts_with($path, '/permissions/') => '/permissions',
            $path === '/configuration/system' || str_starts_with($path, '/configuration/system/') => '/settings',
            $path === '/configuration/store' || str_starts_with($path, '/configuration/store/') => '/store-settings',
            $path === '/student-discounts' => '/ecommerce/student-discounts',
            str_starts_with($path, '/reputation/items') => '/reputation/overview',
            default => $path,
        };

        $allowed = DB::table('Permission')
            ->leftJoin('RolePermission', 'RolePermission.permissionId', '=', 'Permission.id')
            ->leftJoin('EmployeeRole', 'EmployeeRole.roleId', '=', 'RolePermission.roleId')
            ->leftJoin('EmployeePermission', 'EmployeePermission.permissionId', '=', 'Permission.id')
            ->where(function ($query) use ($employee) {
                $query->where('EmployeeRole.employeeId', $employee->id)->orWhere('EmployeePermission.employeeId', $employee->id);
            })->pluck('Permission.description')->filter()->contains($permissionPath);

        abort_unless($allowed, 403, '没有访问此页面的权限');

        return $next($request);
    }
}
