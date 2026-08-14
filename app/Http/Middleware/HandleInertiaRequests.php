<?php

namespace App\Http\Middleware;

use App\Services\Stores\StoreContext;
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
    public function share(Request $request, ?StoreContext $storeContext = null): array
    {
        $storeContext ??= app(StoreContext::class);
        $employee = $storeContext->employee();
        $allowedPaths = [];
        if ($employee?->isDefaultAdmin) {
            $allowedPaths = ['*'];
        } elseif ($employee) {
            $allowedPaths = DB::table('Permission')->leftJoin('RolePermission', 'RolePermission.permissionId', '=', 'Permission.id')->leftJoin('EmployeeRole', 'EmployeeRole.roleId', '=', 'RolePermission.roleId')->leftJoin('EmployeePermission', 'EmployeePermission.permissionId', '=', 'Permission.id')->where(fn ($query) => $query->where('EmployeeRole.employeeId', $employee->id)->orWhere('EmployeePermission.employeeId', $employee->id))->pluck('Permission.description')->filter()->unique()->values()->all();
            $membership = $storeContext->membership();
            if ($membership?->role === 'OWNER') {
                $allowedPaths = array_values(array_unique([...$allowedPaths, ...StoreContext::allStorePaths()]));
            } elseif ($membership?->roleId) {
                $storeRolePaths = DB::table('Permission')->join('RolePermission', 'RolePermission.permissionId', '=', 'Permission.id')->where('RolePermission.roleId', $membership->roleId)->pluck('Permission.description')->filter()->all();
                $allowedPaths = array_values(array_unique([...$allowedPaths, ...$storeRolePaths]));
            }
        }

        $canAccess = fn (string $path): bool => in_array('*', $allowedPaths, true) || in_array($path, $allowedPaths, true);
        $enabledPaths = $storeContext->enabledPaths();
        $storeNavigation = collect(config('store_navigation.groups'))->map(function (array $group) use ($canAccess, $enabledPaths, $storeContext): array {
            $group['items'] = collect($group['items'])->filter(fn (array $item) => in_array($item['path'], $enabledPaths, true) && $canAccess($item['path']))
                ->map(fn (array $item) => [...$item, 'href' => $storeContext->url($item['path'])])->values()->all();

            return $group;
        })->values()->all();
        $globalNavigation = collect(config('store_navigation.global_groups'))->map(function (array $group) use ($canAccess): array {
            $group['items'] = collect($group['items'])->filter(fn (array $item) => $canAccess($item['path']))->map(fn (array $item) => [...$item, 'href' => $item['path']])->values()->all();

            return $group;
        })->filter(fn (array $group) => count($group['items']) > 0)->values()->all();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'employee' => $employee,
                'allowedPaths' => $allowedPaths,
                'isSuperAdmin' => (bool) ($employee?->isDefaultAdmin ?? false),
            ],
            'stores' => fn () => $storeContext->availableStores()->values(),
            'currentStore' => fn () => $storeContext->store(),
            'storeNavigation' => $storeNavigation,
            'globalNavigation' => $globalNavigation,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
