<?php

namespace App\Http\Controllers;

use App\Services\Stores\StoreContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class StoreController extends Controller
{
    private const RESERVED_SLUGS = [
        'account', 'api', 'collab', 'configuration', 'dashboard', 'data-sync',
        'ecommerce', 'employees', 'home', 'organic', 'permissions', 'profile',
        'reputation', 'roles', 'settings', 'stores', 'student-discounts', 'tasks',
        'workspace',
    ];

    public function index(StoreContext $storeContext): Response
    {
        $stores = DB::table('Store')->orderBy('createdAt')->get()->map(function ($store) {
            $store->memberIds = DB::table('StoreMember')->where('storeId', $store->id)->pluck('employeeId');
            $store->memberRoleIds = DB::table('StoreMember')->where('storeId', $store->id)->pluck('roleId', 'employeeId');
            $store->memberCount = $store->memberIds->count();
            $store->enabledPaths = DB::table('StorePage')->where('storeId', $store->id)->where('enabled', true)->orderBy('position')->pluck('path');
            $store->moduleCount = $store->enabledPaths->count();
            $store->credentialCount = DB::table('StoreConfig')->where('storeId', $store->id)->count();

            return $store;
        });

        return Inertia::render('Stores', [
            'stores' => $stores,
            'employees' => DB::table('Employee')->where('status', 'ACTIVE')->orderByDesc('isDefaultAdmin')->orderBy('name')->get(['id', 'name', 'jobTitle', 'isDefaultAdmin']),
            'roles' => DB::table('Role')->orderByDesc('isSystem')->orderBy('name')->get(['id', 'name']),
            'moduleGroups' => config('store_navigation.groups'),
            'currentStoreId' => $storeContext->id(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'alpha_dash', 'max:120', 'unique:Store,slug', 'not_in:'.implode(',', self::RESERVED_SLUGS)],
            'timezone' => ['required', 'timezone'],
        ]);
        $slug = $data['slug'] ?: Str::slug($data['name']);
        $slug = $slug !== '' ? $slug : 'store-'.Str::lower(Str::random(8));
        if (in_array(Str::lower($slug), self::RESERVED_SLUGS, true)) {
            $slug .= '-store';
        }
        while (DB::table('Store')->where('slug', $slug)->exists()) {
            $slug .= '-'.Str::lower(Str::random(4));
        }
        $id = (string) Str::uuid();
        $employeeId = DB::table('Employee')->where('email', $request->user()->email)->value('id');
        DB::transaction(function () use ($id, $slug, $data, $employeeId): void {
            DB::table('Store')->insert(['id' => $id, 'slug' => $slug, 'name' => $data['name'], 'timezone' => $data['timezone'], 'status' => 'ACTIVE', 'createdAt' => now(), 'updatedAt' => now()]);
            if ($employeeId) {
                DB::table('StoreMember')->insert(['storeId' => $id, 'employeeId' => $employeeId, 'role' => 'OWNER', 'assignedAt' => now()]);
            }
            foreach (StoreContext::allStorePaths() as $position => $path) {
                DB::table('StorePage')->insert(['storeId' => $id, 'path' => $path, 'enabled' => true, 'position' => $position, 'createdAt' => now(), 'updatedAt' => now()]);
            }
        });
        $request->session()->put('current_store_id', $id);
        if ($employeeId) DB::table('Employee')->where('id', $employeeId)->update(['lastStoreId' => $id, 'updatedAt' => now()]);

        return redirect('/stores')->with('success', '店铺已创建并切换为当前店铺');
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
        foreach (['Task', 'SocialMediaPost', 'ReputationReview', 'RedditPost', 'AmazonOrder', 'StudentDiscountClaim'] as $table) {
            abort_if(DB::table($table)->where('storeId', $store)->exists(), 422, '该店铺已有业务数据，请先停用店铺，不允许直接删除');
        }
        DB::transaction(function () use ($store) {
            DB::table('StoreConfig')->where('storeId', $store)->delete();
            DB::table('StoreMember')->where('storeId', $store)->delete();
            DB::table('StorePage')->where('storeId', $store)->delete();
            DB::table('Store')->where('id', $store)->delete();
        });

        return back()->with('success', '店铺已删除');
    }

    public function syncMembers(Request $request, string $store): RedirectResponse
    {
        $data = $request->validate([
            'employeeIds' => ['required', 'array', 'min:1'],
            'employeeIds.*' => ['integer', 'exists:Employee,id'],
            'roleIds' => ['nullable', 'array'],
            'roleIds.*' => ['nullable', 'string', 'exists:Role,id'],
        ]);
        $defaultAdminId = DB::table('Employee')->where('isDefaultAdmin', true)->value('id');
        $employeeIds = array_values(array_unique([...$data['employeeIds'], $defaultAdminId]));

        DB::transaction(function () use ($store, $employeeIds, $defaultAdminId, $data) {
            DB::table('StoreMember')->where('storeId', $store)->delete();
            foreach ($employeeIds as $employeeId) {
                DB::table('StoreMember')->insert([
                    'storeId' => $store,
                    'employeeId' => $employeeId,
                    'role' => $employeeId === $defaultAdminId ? 'OWNER' : 'MEMBER',
                    'roleId' => $employeeId === $defaultAdminId ? null : ($data['roleIds'][$employeeId] ?? null),
                    'assignedAt' => now(),
                ]);
            }
        });

        return back()->with('success', '店铺成员授权已更新');
    }

    public function syncModules(Request $request, string $store): RedirectResponse
    {
        $data = $request->validate([
            'paths' => ['present', 'array'],
            'paths.*' => ['string', 'in:'.implode(',', StoreContext::allStorePaths())],
        ]);
        $selected = array_unique($data['paths']);

        DB::transaction(function () use ($store, $selected): void {
            foreach (StoreContext::allStorePaths() as $position => $path) {
                DB::table('StorePage')->updateOrInsert(
                    ['storeId' => $store, 'path' => $path],
                    ['enabled' => in_array($path, $selected, true), 'position' => $position, 'updatedAt' => now(), 'createdAt' => now()],
                );
            }
        });

        return back()->with('success', '店铺功能模块已更新');
    }

    public function switch(Request $request, StoreContext $storeContext): RedirectResponse
    {
        $data = $request->validate([
            'storeId' => ['required', 'string', 'exists:Store,id'],
            'returnTo' => ['nullable', 'string', 'max:500'],
        ]);
        $storeContext->switchTo($data['storeId']);
        $returnTo = $data['returnTo'] ?? '/';
        if (! str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//')) {
            $returnTo = '/';
        }
        $path = parse_url($returnTo, PHP_URL_PATH) ?: '/';
        $logicalPath = null;
        if (preg_match('#^/stores/[^/]+(?<logical>/.*)?$#', $path, $match)) {
            $logicalPath = ($match['logical'] ?? '') ?: '/';
        } elseif (in_array($path, StoreContext::allStorePaths(), true)) {
            $logicalPath = $path;
        } elseif (preg_match('#^/[^/]+(?<logical>/.*)?$#', $path, $match)) {
            $candidate = ($match['logical'] ?? '') ?: '/';
            if ($candidate === '/' || in_array($candidate, StoreContext::allStorePaths(), true)) {
                $logicalPath = $candidate;
            }
        }
        if ($logicalPath !== null) {
            if (! $storeContext->isEnabled($logicalPath, $data['storeId'])) {
                $logicalPath = $storeContext->enabledPaths($data['storeId'])[0] ?? null;
            }
            $returnTo = $logicalPath ? $storeContext->url($logicalPath) : '/account/profile';
        }

        return redirect($returnTo)->with('success', '已切换店铺');
    }
}
