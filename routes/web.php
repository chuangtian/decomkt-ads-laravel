<?php

use App\Http\Controllers\ConfigurationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LegacyApiController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ReputationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\StudentDiscountController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::get('/api/public/student-discounts', [LegacyApiController::class, 'publicStudentDiscount']);

Route::middleware(['auth', 'verified', 'page.access'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::redirect('/home', '/')->name('home');
    Route::get('/dashboard', DashboardController::class);
    Route::redirect('/profile', '/account/profile');

    Route::resource('employees', EmployeeController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::put('/employees/{employee}/password', [EmployeeController::class, 'resetPassword'])->name('employees.password');
    Route::resource('roles', RoleController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('stores', StoreController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::put('/stores/{store}/members', [StoreController::class, 'syncMembers'])->name('stores.members');
    Route::resource('tasks', TaskController::class)->only(['store', 'update', 'destroy']);
    Route::get('/collab/kanban', [TaskController::class, 'index'])->name('tasks.index');
    Route::get('/collab/team', TeamController::class)->name('team.index');
    Route::resource('permissions', PermissionController::class)->only(['index', 'store', 'destroy']);
    Route::post('/configuration/{scope}', [ConfigurationController::class, 'store'])->name('configuration.store');
    Route::delete('/configuration/{scope}/{key}', [ConfigurationController::class, 'destroy'])->name('configuration.destroy');
    Route::put('/student-discounts', [StudentDiscountController::class, 'update'])->name('student-discounts.update');
    Route::post('/reputation/items', [ReputationController::class, 'store'])->name('reputation.items.store');
    Route::put('/reputation/items/{entity}/{id}', [ReputationController::class, 'update'])->name('reputation.items.update');
    Route::delete('/reputation/items/{entity}/{id}', [ReputationController::class, 'destroy'])->name('reputation.items.destroy');
    Route::post('/reputation/analyze', [ReputationController::class, 'analyze'])->name('reputation.analyze');
    Route::post('/reputation/weekly-report', [ReputationController::class, 'saveWeeklyReport'])->name('reputation.weekly-report.store');

    $managedPaths = ['/employees', '/roles', '/stores', '/collab/kanban', '/collab/team'];
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
