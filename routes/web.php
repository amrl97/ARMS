<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AccessModuleConfigController;
use App\Http\Controllers\AccessRequestController;

Route::inertia('/', 'auth/Login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('users', UserController::class);
    Route::resource('roles', RoleController::class);
    Route::resource('departments', DepartmentController::class);
    Route::resource('admin/configs', AccessModuleConfigController::class)
        ->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::post('admin/configs/approvers', [AccessModuleConfigController::class, 'storeApprover'])
        ->name('configs.approvers.store');
    Route::delete('admin/configs/approvers/{moduleApprover}', [AccessModuleConfigController::class, 'destroyApprover'])
        ->name('configs.approvers.destroy');
    Route::get('requests/create', [AccessRequestController::class, 'create'])->name('requests.create');
});

/* -------------------------------------- */



require __DIR__.'/settings.php';