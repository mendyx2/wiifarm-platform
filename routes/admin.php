<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\Auth\AdminSessionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\KycReviewController;
use App\Http\Controllers\Admin\MemberController;
use App\Http\Controllers\Admin\MemberWalletController;
use App\Http\Controllers\Admin\NetworkController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use Illuminate\Support\Facades\Route;

/*
 * The admin panel (specification section 11), under /admin. Admins sign in
 * with the "admin" guard, separately from members. Every page and action
 * names the permission it needs (config/admin_permissions.php).
 */
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('login', [AdminSessionController::class, 'create'])->name('login');
        Route::post('login', [AdminSessionController::class, 'store']);
    });

    Route::middleware(['auth:admin', 'admin.active'])->group(function () {
        Route::post('logout', [AdminSessionController::class, 'destroy'])->name('logout');
        Route::get('/', DashboardController::class)->name('dashboard');

        // Members
        Route::get('members', [MemberController::class, 'index'])->middleware('admin.can:members.view')->name('members.index');
        Route::get('members/{member}', [MemberController::class, 'show'])->middleware('admin.can:members.view')->name('members.show');
        Route::patch('members/{member}', [MemberController::class, 'update'])->middleware('admin.can:members.edit')->name('members.update');
        Route::post('members/{member}/ban', [MemberController::class, 'ban'])->middleware('admin.can:members.ban')->name('members.ban');
        Route::post('members/{member}/unban', [MemberController::class, 'unban'])->middleware('admin.can:members.ban')->name('members.unban');
        Route::post('members/{member}/block-withdrawals', [MemberController::class, 'blockWithdrawals'])->middleware('admin.can:members.block_withdrawals')->name('members.block-withdrawals');
        Route::post('members/{member}/unblock-withdrawals', [MemberController::class, 'unblockWithdrawals'])->middleware('admin.can:members.block_withdrawals')->name('members.unblock-withdrawals');
        Route::post('members/{member}/sponsor', [MemberController::class, 'changeSponsor'])->middleware('admin.can:members.change_sponsor')->name('members.sponsor');
        Route::get('members/{member}/wallets/{wallet}', [MemberWalletController::class, 'show'])->middleware('admin.can:members.view')->name('members.wallet');
        Route::get('members/{member}/tree', [NetworkController::class, 'tree'])->middleware('admin.can:members.view')->name('members.tree');
        Route::get('members/{member}/pv', [NetworkController::class, 'pv'])->middleware('admin.can:members.view')->name('members.pv');
        Route::post('members/{member}/wallets', [MemberWalletController::class, 'adjust'])->middleware('admin.can:members.adjust_wallet')->name('members.wallet.adjust');

        // KYC review
        Route::get('kyc', [KycReviewController::class, 'index'])->middleware('admin.can:kyc.view')->name('kyc.index');
        Route::get('kyc/{submission}', [KycReviewController::class, 'show'])->middleware('admin.can:kyc.view')->name('kyc.show');
        Route::get('kyc/{submission}/files/{field}', [KycReviewController::class, 'file'])->middleware('admin.can:kyc.view')->name('kyc.file');
        Route::post('kyc/{submission}/approve', [KycReviewController::class, 'approve'])->middleware('admin.can:kyc.review')->name('kyc.approve');
        Route::post('kyc/{submission}/reject', [KycReviewController::class, 'reject'])->middleware('admin.can:kyc.review')->name('kyc.reject');

        // Packages (read only for now; changing a package is a plan setting)
        Route::get('packages', [NetworkController::class, 'packages'])->middleware('admin.can:settings.view')->name('packages');

        // Settings
        Route::get('settings', [SettingsController::class, 'edit'])->middleware('admin.can:settings.view')->name('settings.edit');
        Route::put('settings', [SettingsController::class, 'update'])->middleware('admin.can:settings.edit')->name('settings.update');

        // Admins and roles (super-admin only)
        Route::middleware('admin.can:admins.manage')->group(function () {
            Route::get('admins', [AdminController::class, 'index'])->name('admins.index');
            Route::get('admins/create', [AdminController::class, 'create'])->name('admins.create');
            Route::post('admins', [AdminController::class, 'store'])->name('admins.store');
            Route::get('admins/{admin}', [AdminController::class, 'edit'])->name('admins.edit');
            Route::put('admins/{admin}', [AdminController::class, 'update'])->name('admins.update');
            Route::post('admins/{admin}/deactivate', [AdminController::class, 'deactivate'])->name('admins.deactivate');
            Route::post('admins/{admin}/activate', [AdminController::class, 'activate'])->name('admins.activate');
        });
        Route::middleware('admin.can:roles.manage')->group(function () {
            Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
            Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
            Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
            Route::get('roles/{role}', [RoleController::class, 'edit'])->name('roles.edit');
            Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
            Route::delete('roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
        });

        // The audit log (read only)
        Route::get('audit', AuditLogController::class)->middleware('admin.can:audit.view')->name('audit');
    });
});
