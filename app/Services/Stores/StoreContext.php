<?php

namespace App\Services\Stores;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StoreContext
{
    private ?object $employee = null;

    private ?Collection $stores = null;

    private ?object $store = null;

    public function __construct(private readonly Request $request) {}

    public function employee(): ?object
    {
        if ($this->employee !== null || ! $this->request->user()) {
            return $this->employee;
        }

        return $this->employee = DB::table('Employee')
            ->where(function ($query): void {
                $email = $this->request->user()?->email;
                $query->where('email', $email)->orWhere('username', $email);
            })->first();
    }

    public function availableStores(): Collection
    {
        if ($this->stores !== null) {
            return $this->stores;
        }

        $employee = $this->employee();
        if (! $employee) {
            return $this->stores = collect();
        }

        $query = DB::table('Store')->where('Store.status', 'ACTIVE');
        if (! $employee->isDefaultAdmin) {
            $query->join('StoreMember', 'StoreMember.storeId', '=', 'Store.id')
                ->where('StoreMember.employeeId', $employee->id);
        }

        return $this->stores = $query
            ->orderByRaw("CASE WHEN Store.id = 'default-store' THEN 0 ELSE 1 END")
            ->orderBy('Store.name')
            ->get(['Store.id', 'Store.slug', 'Store.name', 'Store.logoUrl', 'Store.timezone', 'Store.status']);
    }

    public function store(): ?object
    {
        if ($this->store !== null) {
            return $this->store;
        }

        $stores = $this->availableStores();
        $requested = $this->request->session()->get('current_store_id') ?: $this->employee()?->lastStoreId;
        $this->store = $stores->firstWhere('id', $requested)
            ?? $stores->firstWhere('id', 'default-store')
            ?? $stores->first();

        if ($this->store && $requested !== $this->store->id) {
            $this->remember($this->store->id);
        }

        return $this->store;
    }

    public function id(): string
    {
        return (string) ($this->store()?->id ?? 'default-store');
    }

    public function canAccessStore(string $storeId): bool
    {
        return $this->availableStores()->contains('id', $storeId);
    }

    public function membership(?string $storeId = null): ?object
    {
        $employee = $this->employee();
        if (! $employee) {
            return null;
        }

        return DB::table('StoreMember')->where('storeId', $storeId ?? $this->id())->where('employeeId', $employee->id)->first();
    }

    public function switchTo(string $storeId): void
    {
        abort_unless($this->canAccessStore($storeId), 403, '没有访问该店铺的权限');
        $this->store = $this->availableStores()->firstWhere('id', $storeId);
        $this->remember($storeId);
    }

    public function switchToSlug(string $slug): void
    {
        $store = $this->availableStores()->firstWhere('slug', $slug);
        abort_unless($store, 404, '店铺不存在或没有访问权限');
        $this->store = $store;
        $this->remember($store->id);
    }

    public function setForBackgroundJob(string $storeId): void
    {
        abort_unless(app()->runningInConsole(), 403);
        $this->store = DB::table('Store')->where('id', $storeId)->where('status', 'ACTIVE')->first();
        abort_unless($this->store, 404, 'Store not found or disabled.');
    }

    /** @return list<string> */
    public function enabledPaths(?string $storeId = null): array
    {
        $storeId ??= $this->id();
        if (! Schema::hasTable('StorePage')) {
            return self::allStorePaths();
        }

        $query = DB::table('StorePage')->where('storeId', $storeId);
        if (! (clone $query)->exists()) {
            return self::allStorePaths();
        }

        $paths = $query->where('enabled', true)
            ->orderBy('position')->pluck('path')->all();

        return $paths;
    }

    public function isEnabled(string $path, ?string $storeId = null): bool
    {
        return in_array($path, $this->enabledPaths($storeId), true);
    }

    /** @return list<string> */
    public static function allStorePaths(): array
    {
        return collect(config('store_navigation.groups'))
            ->flatMap(fn (array $group) => $group['items'])
            ->pluck('path')->unique()->values()->all();
    }

    public function url(string $logicalPath = '/'): string
    {
        $slug = $this->store()?->slug;
        if (! $slug) {
            return '/account/profile';
        }

        return '/'.$slug.($logicalPath === '/' ? '' : '/'.ltrim($logicalPath, '/'));
    }

    private function remember(string $storeId): void
    {
        if ($this->request->hasSession()) {
            $this->request->session()->put('current_store_id', $storeId);
        }
        $employee = $this->employee();
        if ($employee && Schema::hasColumn('Employee', 'lastStoreId') && $employee->lastStoreId !== $storeId) {
            DB::table('Employee')->where('id', $employee->id)->update(['lastStoreId' => $storeId, 'updatedAt' => now()]);
            $employee->lastStoreId = $storeId;
        }
    }
}
