<?php

use App\Http\Controllers\AccessModuleConfigController;
use App\Http\Controllers\AccessRequestApprovalController;
use App\Http\Controllers\AccessRequestController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('auth/Login', [
    'canResetPassword' => Route::has('password.request'),
    'status' => session('status'),
]))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('home', 'Home')->name('app.home');
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
    Route::get('requests/{accessRequest}', [AccessRequestController::class, 'show'])->name('requests.show');
    Route::get(
        'requests/{accessRequest}/attachments/{attachment}',
        [AccessRequestController::class, 'downloadAttachment'],
    )->name('requests.attachments.download');
    Route::post('requests/{accessRequest}/cancel', [AccessRequestController::class, 'cancel'])->name('requests.cancel');
    Route::get('approvals', [AccessRequestApprovalController::class, 'index'])->name('approvals.index');
    Route::get('approvals/{approval}', [AccessRequestApprovalController::class, 'show'])->name('approvals.show');
    Route::post('approvals/{approval}/decision', [AccessRequestApprovalController::class, 'decide'])
        ->name('approvals.decide');
});

/* -------------------------------------- */

require __DIR__.'/settings.php';
