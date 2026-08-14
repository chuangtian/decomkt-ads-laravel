<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        $roles = DB::table('Role')->orderBy('name')->get(['id', 'name', 'key']);
        $superAdminRoleId = (string) ($roles->where('key', 'super-admin')->pluck('id')->first() ?: 'system-super-admin');
        $stores = DB::table('Store')->where('status', 'ACTIVE')->orderBy('name')->get(['id', 'name']);
        $employees = DB::table('Employee')->orderByDesc('isDefaultAdmin')->orderBy('name')->get();
        $employeeIds = $employees->pluck('id');
        $roleAssignments = DB::table('EmployeeRole')
            ->join('Role', 'Role.id', '=', 'EmployeeRole.roleId')
            ->whereIn('EmployeeRole.employeeId', $employeeIds)
            ->get(['EmployeeRole.employeeId', 'EmployeeRole.roleId', 'Role.name'])
            ->groupBy('employeeId');
        $storeAssignments = DB::table('StoreMember')
            ->join('Store', 'Store.id', '=', 'StoreMember.storeId')
            ->whereIn('StoreMember.employeeId', $employeeIds)
            ->get(['StoreMember.employeeId', 'StoreMember.storeId', 'Store.name'])
            ->groupBy('employeeId');

        $employees->transform(function ($employee) use ($roleAssignments, $storeAssignments, $stores, $superAdminRoleId) {
            $employee->isDefaultAdmin = (bool) $employee->isDefaultAdmin;
            $employee->hasGlobalAccess = $employee->isDefaultAdmin;

            if ($employee->isDefaultAdmin) {
                $employee->roles = collect(['超级管理员（全部权限）']);
                $employee->roleIds = collect([$superAdminRoleId]);
                $employee->stores = $stores->pluck('name');
                $employee->storeIds = $stores->pluck('id');

                return $employee;
            }

            $assignedRoles = $roleAssignments->get($employee->id, collect());
            $assignedStores = $storeAssignments->get($employee->id, collect());
            $employee->roles = $assignedRoles->pluck('name');
            $employee->roleIds = $assignedRoles->pluck('roleId');
            $employee->stores = $assignedStores->pluck('name');
            $employee->storeIds = $assignedStores->pluck('storeId');

            return $employee;
        });

        return Inertia::render('Employees', [
            'employees' => $employees,
            'roles' => $roles,
            'stores' => $stores,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:Employee,email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'jobTitle' => ['nullable', 'string', 'max:120'],
            'roleId' => ['nullable', 'exists:Role,id'],
            'storeIds' => ['nullable', 'array'],
            'storeIds.*' => ['string', 'exists:Store,id'],
        ]);
        $id = null;
        DB::transaction(function () use ($data, &$id) {
            $user = User::query()->create(['name' => $data['name'], 'email' => strtolower($data['email']), 'password' => $data['password']]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $id = DB::table('Employee')->insertGetId([
                'username' => strtolower($data['email']), 'passwordHash' => Hash::make($data['password']),
                'name' => $data['name'], 'email' => strtolower($data['email']), 'emailVerified' => true,
                'jobTitle' => $data['jobTitle'] ?? null, 'status' => 'ACTIVE', 'isDefaultAdmin' => false,
                'createdAt' => now(), 'updatedAt' => now(),
            ]);
            if (! empty($data['roleId'])) {
                DB::table('EmployeeRole')->insert(['employeeId' => $id, 'roleId' => $data['roleId'], 'assignedAt' => now()]);
            }
            $storeIds = array_values(array_unique($data['storeIds'] ?? ['default-store']));
            foreach ($storeIds as $storeId) {
                DB::table('StoreMember')->insertOrIgnore(['storeId' => $storeId, 'employeeId' => $id, 'role' => 'MEMBER', 'assignedAt' => now()]);
            }
        });

        return back()->with('success', '人员已创建');
    }

    public function update(Request $request, int $employee): RedirectResponse
    {
        $isDefaultAdmin = (bool) DB::table('Employee')->where('id', $employee)->value('isDefaultAdmin');
        abort_if($isDefaultAdmin && $request->input('status') === 'DISABLED', 422, '默认管理员不能停用');
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'jobTitle' => ['nullable', 'string', 'max:120'],
            'status' => ['sometimes', 'in:ACTIVE,DISABLED'],
            'roleIds' => ['sometimes', 'array'],
            'roleIds.*' => ['string', 'exists:Role,id'],
            'storeIds' => ['sometimes', 'array', 'min:1'],
            'storeIds.*' => ['string', 'exists:Store,id'],
        ]);

        DB::transaction(function () use ($data, $employee, $isDefaultAdmin) {
            $attributes = collect($data)->only(['name', 'jobTitle', 'status'])->all();
            if ($attributes !== []) {
                DB::table('Employee')->where('id', $employee)->update([...$attributes, 'updatedAt' => now()]);
            }
            if (! $isDefaultAdmin && array_key_exists('roleIds', $data)) {
                DB::table('EmployeeRole')->where('employeeId', $employee)->delete();
                foreach (array_unique($data['roleIds']) as $roleId) {
                    DB::table('EmployeeRole')->insert(['employeeId' => $employee, 'roleId' => $roleId, 'assignedAt' => now()]);
                }
            }
            if (! $isDefaultAdmin && array_key_exists('storeIds', $data)) {
                DB::table('StoreMember')->where('employeeId', $employee)->delete();
                foreach (array_unique($data['storeIds']) as $storeId) {
                    DB::table('StoreMember')->insert(['storeId' => $storeId, 'employeeId' => $employee, 'role' => 'MEMBER', 'assignedAt' => now()]);
                }
            }
        });
        if (isset($data['name'])) {
            User::query()->where('email', DB::table('Employee')->where('id', $employee)->value('email'))->update(['name' => $data['name']]);
        }

        return back()->with('success', '人员信息已更新');
    }

    public function resetPassword(Request $request, int $employee): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'string', 'min:8']]);
        DB::table('Employee')->where('id', $employee)->update(['passwordHash' => Hash::make($data['password']), 'updatedAt' => now()]);
        User::query()->where('email', DB::table('Employee')->where('id', $employee)->value('email'))->update(['password' => Hash::make($data['password'])]);

        return back()->with('success', '密码已重置');
    }

    public function destroy(int $employee): RedirectResponse
    {
        abort_if((bool) DB::table('Employee')->where('id', $employee)->value('isDefaultAdmin'), 422, '默认管理员不能删除');
        DB::transaction(function () use ($employee) {
            $email = DB::table('Employee')->where('id', $employee)->value('email');
            DB::table('EmployeeRole')->where('employeeId', $employee)->delete();
            DB::table('EmployeePermission')->where('employeeId', $employee)->delete();
            DB::table('StoreMember')->where('employeeId', $employee)->delete();
            DB::table('Employee')->where('id', $employee)->delete();
            User::query()->where('email', $email)->delete();
        });

        return back()->with('success', '人员已删除');
    }
}
