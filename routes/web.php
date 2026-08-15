<?php

use App\Http\Controllers\BrandController;
use App\Http\Controllers\ConfigurationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DesignController;
use App\Http\Controllers\DesignDataController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ExternalDataController;
use App\Http\Controllers\LegacyApiController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ReputationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\StudentDiscountController;
use App\Http\Controllers\StudentDiscountPluginController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TeamController;
use App\Services\Stores\StoreContext;
use Illuminate\Support\Facades\Route;

Route::get('/shopify/app', fn () => response('Macfox Student Discount app is connected.', 200, [
    'Content-Type' => 'text/plain; charset=UTF-8',
    'Cache-Control' => 'no-store',
]));

Route::get('/shopify/auth/callback', fn () => redirect('/shopify/app')->withHeaders([
    'Cache-Control' => 'no-store',
]));

Route::middleware(['auth', 'verified', 'page.access'])->group(function () {
    Route::get('/', fn (StoreContext $storeContext) => redirect($storeContext->url()))->name('dashboard');
    Route::redirect('/home', '/')->name('home');
    Route::get('/dashboard', fn (StoreContext $storeContext) => redirect($storeContext->url()));
    Route::redirect('/profile', '/account/profile');

    Route::resource('employees', EmployeeController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::put('/employees/{employee}/password', [EmployeeController::class, 'resetPassword'])->name('employees.password');
    Route::resource('roles', RoleController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('stores', StoreController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('/stores/switch', [StoreController::class, 'switch'])->name('stores.switch');
    Route::put('/stores/{store}/members', [StoreController::class, 'syncMembers'])->name('stores.members');
    Route::put('/stores/{store}/modules', [StoreController::class, 'syncModules'])->name('stores.modules');
    Route::get('/stores/{legacyStoreSlug}/{legacyPath?}', function (string $legacyStoreSlug, ?string $legacyPath, StoreContext $storeContext) {
        $storeContext->switchToSlug($legacyStoreSlug);
        $logicalPath = $legacyPath ? '/'.ltrim($legacyPath, '/') : '/';

        abort_unless($logicalPath === '/' || in_array($logicalPath, StoreContext::allStorePaths(), true), 404);

        return redirect($storeContext->url($logicalPath), 301);
    })->where('legacyPath', '.*')->name('stores.legacy');
    Route::resource('tasks', TaskController::class)->only(['store', 'update', 'destroy']);
    Route::get('/collab/kanban', [TaskController::class, 'index'])->name('tasks.index');
    Route::get('/collab/team', TeamController::class)->name('team.index');
    Route::get('/organic/seo', [SeoController::class, 'index'])->name('organic.seo');
    Route::post('/organic/seo/refresh', [SeoController::class, 'refresh'])->name('organic.seo.refresh');
    Route::get('/workspace/brand', BrandController::class)->name('workspace.brand');
    Route::post('/ecommerce/design/refresh', [DesignDataController::class, 'refresh'])->name('ecommerce.design.refresh');
    Route::post('/data-sync/refresh', [ExternalDataController::class, 'refresh'])->name('data-sync.refresh');
    Route::get('/ecommerce/design', DesignController::class)->name('ecommerce.design');
    Route::get('/api/organic/seo-overview', [SeoController::class, 'overview'])->name('organic.seo.overview');
    Route::resource('permissions', PermissionController::class)->only(['index', 'store', 'destroy']);
    Route::post('/configuration/{scope}', [ConfigurationController::class, 'store'])->name('configuration.store');
    Route::delete('/configuration/{scope}/{key}', [ConfigurationController::class, 'destroy'])->name('configuration.destroy');
    Route::put('/student-discounts', [StudentDiscountController::class, 'update'])->name('student-discounts.update');
    Route::put('/student-discounts/settings', [StudentDiscountController::class, 'settings'])->name('student-discounts.settings');
    Route::put('/student-discounts/smtp', [StudentDiscountController::class, 'smtp'])->name('student-discounts.smtp');
    Route::delete('/student-discounts/smtp', [StudentDiscountController::class, 'resetSmtp'])->name('student-discounts.smtp.reset');
    Route::post('/student-discounts/email-branding', [StudentDiscountController::class, 'emailBranding'])->name('student-discounts.email-branding');
    Route::delete('/student-discounts/email-branding/logo', [StudentDiscountController::class, 'removeEmailLogo'])->name('student-discounts.email-branding.logo.destroy');
    Route::get('/student-discounts/claims/{claim}/evidence', [StudentDiscountController::class, 'evidence'])->name('student-discounts.evidence');
    Route::get('/plugins', fn (StoreContext $storeContext) => redirect(
        $storeContext->url('/plugins/macfox-student-discount'),
        302,
    ))->name('plugins.index');
    Route::get('/plugins/macfox-student-discount', StudentDiscountPluginController::class)->name('plugins.student-discount');
    Route::post('/reputation/items', [ReputationController::class, 'store'])->name('reputation.items.store');
    Route::put('/reputation/items/{entity}/{id}', [ReputationController::class, 'update'])->name('reputation.items.update');
    Route::delete('/reputation/items/{entity}/{id}', [ReputationController::class, 'destroy'])->name('reputation.items.destroy');
    Route::post('/reputation/analyze', [ReputationController::class, 'analyze'])->name('reputation.analyze');
    Route::post('/reputation/weekly-report', [ReputationController::class, 'saveWeeklyReport'])->name('reputation.weekly-report.store');

    Route::prefix('/{storeSlug}')
        ->where(['storeSlug' => '(?!(?:account|api|collab|configuration|dashboard|data-sync|ecommerce|employees|home|organic|permissions|profile|reputation|roles|settings|stores|student-discounts|tasks|workspace)$)[A-Za-z0-9_-]+'])
        ->name('store.')
        ->group(function () {
            Route::get('/', DashboardController::class)->name('dashboard');
            Route::get('/collab/kanban', [TaskController::class, 'index'])->name('tasks.index');
            Route::get('/collab/team', TeamController::class)->name('team.index');
            Route::get('/organic/seo', [SeoController::class, 'index'])->name('organic.seo');
            Route::get('/workspace/brand', BrandController::class)->name('workspace.brand');
            Route::get('/ecommerce/design', DesignController::class)->name('ecommerce.design');
            Route::get('/plugins', fn (string $storeSlug) => redirect(
                route('store.plugins.student-discount', ['storeSlug' => $storeSlug]),
                302,
            ))->name('plugins.index');
            Route::get('/plugins/macfox-student-discount', StudentDiscountPluginController::class)->name('plugins.student-discount');

            $special = ['/collab/kanban', '/collab/team', '/organic/seo', '/workspace/brand', '/ecommerce/design', '/plugins', '/plugins/macfox-student-discount', '/employees', '/roles', '/stores', '/settings'];
            foreach (config('decomkt.pages') as $page) {
                if (in_array($page['path'], $special, true)) {
                    continue;
                }
                Route::get($page['path'], ModuleController::class)->name('module.'.trim(str_replace('/', '.', $page['path']), '.'));
            }
        });

    $managedPaths = ['/employees', '/roles', '/stores', '/collab/kanban', '/collab/team', '/organic/seo', '/workspace/brand', '/ecommerce/design', '/plugins', '/plugins/macfox-student-discount'];
    foreach (config('decomkt.pages') as $page) {
        if (in_array($page['path'], $managedPaths, true)) {
            continue;
        }
        Route::get($page['path'], ModuleController::class)
            ->name('module.'.trim(str_replace('/', '.', $page['path']), '.'));
    }

    Route::any('/api/{path}', LegacyApiController::class)->where('path', '.*')->name('legacy-api');
});

require __DIR__.'/settings.php';
