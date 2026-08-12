<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $employee = $request->user() ? DB::table('Employee')->where('email', $request->user()->email)->first() : null;
        $allowedPaths = [];
        if ($employee?->isDefaultAdmin) {
            $allowedPaths = ['*'];
        } elseif ($employee) {
            $allowedPaths = DB::table('Permission')->leftJoin('RolePermission', 'RolePermission.permissionId', '=', 'Permission.id')->leftJoin('EmployeeRole', 'EmployeeRole.roleId', '=', 'RolePermission.roleId')->leftJoin('EmployeePermission', 'EmployeePermission.permissionId', '=', 'Permission.id')->where(fn ($query) => $query->where('EmployeeRole.employeeId', $employee->id)->orWhere('EmployeePermission.employeeId', $employee->id))->pluck('Permission.description')->filter()->values()->all();
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'employee' => $employee,
                'allowedPaths' => $allowedPaths,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
