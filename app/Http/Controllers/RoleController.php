<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(): Response
    {
        $roles = DB::table('Role')->orderByDesc('isSystem')->orderBy('createdAt')->get()->map(function ($role) {
            $role->permissionIds = DB::table('RolePermission')->where('roleId', $role->id)->pluck('permissionId');
            $role->employeeCount = DB::table('EmployeeRole')->where('roleId', $role->id)->count();

            return $role;
        });

        return Inertia::render('Roles', ['roles' => $roles, 'permissions' => DB::table('Permission')->orderBy('module')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', 'unique:Role,name'], 'key' => ['required', 'alpha_dash', 'max:100', 'unique:Role,key'], 'description' => ['nullable', 'string', 'max:255'], 'permissionIds' => ['array'], 'permissionIds.*' => ['exists:Permission,id']]);
        $id = (string) Str::uuid();
        DB::transaction(function () use ($data, $id) {
            DB::table('Role')->insert(['id' => $id, 'key' => $data['key'], 'name' => $data['name'], 'description' => $data['description'] ?? null, 'isSystem' => false, 'createdAt' => now(), 'updatedAt' => now()]);
            foreach ($data['permissionIds'] ?? [] as $permissionId) {
                DB::table('RolePermission')->insert(['roleId' => $id, 'permissionId' => $permissionId, 'assignedAt' => now()]);
            }
        });

        return back()->with('success', '角色已创建');
    }

    public function update(Request $request, string $role): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:255'], 'permissionIds' => ['array'], 'permissionIds.*' => ['exists:Permission,id']]);
        DB::transaction(function () use ($data, $role) {
            DB::table('Role')->where('id', $role)->update(['name' => $data['name'], 'description' => $data['description'] ?? null, 'updatedAt' => now()]);
            DB::table('RolePermission')->where('roleId', $role)->delete();
            foreach ($data['permissionIds'] ?? [] as $permissionId) {
                DB::table('RolePermission')->insert(['roleId' => $role, 'permissionId' => $permissionId, 'assignedAt' => now()]);
            }
        });

        return back()->with('success', '角色已更新');
    }

    public function destroy(string $role): RedirectResponse
    {
        abort_if((bool) DB::table('Role')->where('id', $role)->value('isSystem'), 422, '系统角色不能删除');
        DB::transaction(function () use ($role) {
            DB::table('EmployeeRole')->where('roleId', $role)->delete();
            DB::table('RolePermission')->where('roleId', $role)->delete();
            DB::table('Role')->where('id', $role)->delete();
        });

        return back()->with('success', '角色已删除');
    }
}
