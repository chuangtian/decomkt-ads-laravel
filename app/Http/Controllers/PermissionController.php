<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PermissionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Permissions', ['permissions' => DB::table('Permission')->orderBy('module')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $request->validate(['name' => ['required', 'string', 'max:120'], 'module' => ['required', 'string', 'max:120'], 'key' => ['required', 'string', 'max:190', 'unique:Permission,key'], 'description' => ['nullable', 'string', 'max:255']]);
        DB::table('Permission')->insert(['id' => (string) Str::uuid(), ...$d, 'createdAt' => now(), 'updatedAt' => now()]);

        return back()->with('success', '权限已创建');
    }

    public function destroy(string $permission): RedirectResponse
    {
        DB::transaction(function () use ($permission) {
            DB::table('RolePermission')->where('permissionId', $permission)->delete();
            DB::table('EmployeePermission')->where('permissionId', $permission)->delete();
            DB::table('Permission')->where('id', $permission)->delete();
        });

        return back()->with('success', '权限已删除');
    }
}
