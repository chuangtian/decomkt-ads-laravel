<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class StoreController extends Controller
{
    public function index(): Response
    {
        $stores = DB::table('Store')->orderBy('createdAt')->get()->map(function ($store) {
            $store->memberIds = DB::table('StoreMember')->where('storeId', $store->id)->pluck('employeeId');
            $store->memberCount = $store->memberIds->count();

            return $store;
        });

        return Inertia::render('Stores', [
            'stores' => $stores,
            'employees' => DB::table('Employee')->where('status', 'ACTIVE')->orderByDesc('isDefaultAdmin')->orderBy('name')->get(['id', 'name', 'jobTitle', 'isDefaultAdmin']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'slug' => ['nullable', 'alpha_dash', 'max:120', 'unique:Store,slug'], 'timezone' => ['required', 'timezone']]);
        $slug = $data['slug'] ?: Str::slug($data['name']);
        $id = (string) Str::uuid();
        DB::table('Store')->insert(['id' => $id, 'slug' => $slug, 'name' => $data['name'], 'timezone' => $data['timezone'], 'status' => 'ACTIVE', 'createdAt' => now(), 'updatedAt' => now()]);
        $employeeId = DB::table('Employee')->where('email', $request->user()->email)->value('id');
        if ($employeeId) {
            DB::table('StoreMember')->insert(['storeId' => $id, 'employeeId' => $employeeId, 'role' => 'OWNER', 'assignedAt' => now()]);
        }

        return back()->with('success', '店铺已创建');
    }

    public function update(Request $request, string $store): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'timezone' => ['required', 'timezone'], 'status' => ['required', 'in:ACTIVE,DISABLED']]);
        DB::table('Store')->where('id', $store)->update([...$data, 'updatedAt' => now()]);

        return back()->with('success', '店铺已更新');
    }

    public function destroy(string $store): RedirectResponse
    {
        abort_if($store === 'default-store', 422, '默认店铺不能删除');
        DB::transaction(function () use ($store) {
            DB::table('StoreConfig')->where('storeId', $store)->delete();
            DB::table('StoreMember')->where('storeId', $store)->delete();
            DB::table('Store')->where('id', $store)->delete();
        });

        return back()->with('success', '店铺已删除');
    }

    public function syncMembers(Request $request, string $store): RedirectResponse
    {
        $data = $request->validate([
            'employeeIds' => ['required', 'array', 'min:1'],
            'employeeIds.*' => ['integer', 'exists:Employee,id'],
        ]);
        $defaultAdminId = DB::table('Employee')->where('isDefaultAdmin', true)->value('id');
        $employeeIds = array_values(array_unique([...$data['employeeIds'], $defaultAdminId]));

        DB::transaction(function () use ($store, $employeeIds, $defaultAdminId) {
            DB::table('StoreMember')->where('storeId', $store)->delete();
            foreach ($employeeIds as $employeeId) {
                DB::table('StoreMember')->insert([
                    'storeId' => $store,
                    'employeeId' => $employeeId,
                    'role' => $employeeId === $defaultAdminId ? 'OWNER' : 'MEMBER',
                    'assignedAt' => now(),
                ]);
            }
        });

        return back()->with('success', '店铺成员授权已更新');
    }
}
