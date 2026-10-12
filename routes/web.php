<?php

use App\Http\Controllers\AccessModuleConfigController;
use App\Http\Controllers\AccessRequestApprovalController;
use App\Http\Controllers\AccessRequestController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'auth/Login')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('users', UserController::class)->except('destroy');
    Route::resource('roles', RoleController::class);
    Route::resource('departments', DepartmentController::class);
    Route::resource('admin/configs', AccessModuleConfigController::class)
        ->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::post('admin/configs/approvers', [AccessModuleConfigController::class, 'storeApprover'])
        ->name('configs.approvers.store');
    Route::delete('admin/configs/approvers/{moduleApprover}', [AccessModuleConfigController::class, 'destroyApprover'])
        ->name('configs.approvers.destroy');
    Route::get('requests', [AccessRequestController::class, 'index'])->name('requests.index');
    Route::get('requests/create', [AccessRequestController::class, 'create'])->name('requests.create');
    Route::post('requests', [AccessRequestController::class, 'store'])->name('requests.store');
    Route::post('requests/{accessRequest}/cancel', [AccessRequestController::class, 'cancel'])->name('requests.cancel');
    Route::get('approvals', [AccessRequestApprovalController::class, 'index'])->name('approvals.index');
    Route::post('approvals/{approval}/decision', [AccessRequestApprovalController::class, 'decide'])
        ->name('approvals.decide');
});

/* -------------------------------------- */

require __DIR__.'/settings.php';
