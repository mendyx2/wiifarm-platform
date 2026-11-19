<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AffiliateController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\Auth\AdminSessionController;
use App\Http\Controllers\Admin\AwardController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EPinController;
use App\Http\Controllers\Admin\KycReviewController;
use App\Http\Controllers\Admin\LeadershipController;
use App\Http\Controllers\Admin\MatchingController;
use App\Http\Controllers\Admin\MemberController;
use App\Http\Controllers\Admin\MemberWalletController;
use App\Http\Controllers\Admin\NetworkController;
use App\Http\Controllers\Admin\OnlinePaymentController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PoolsController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StockistController;
use App\Http\Controllers\Admin\UnilevelController;
use App\Http\Controllers\Admin\WithdrawalController;
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

        // Orders and products
        Route::get('orders', [OrderController::class, 'index'])->middleware('admin.can:orders.view')->name('orders.index');
        Route::get('orders/{order}', [OrderController::class, 'show'])->middleware('admin.can:orders.view')->name('orders.show');
        Route::middleware('admin.can:products.manage')->group(function () {
            Route::get('products', [ProductController::class, 'index'])->name('products.index');
            Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
            Route::post('products', [ProductController::class, 'store'])->name('products.store');
            Route::get('products/{product}', [ProductController::class, 'edit'])->name('products.edit');
            Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
            Route::post('products/{product}/photo', [ProductController::class, 'photo'])->name('products.photo');
            Route::delete('products/{product}/photo', [ProductController::class, 'removePhoto'])->name('products.photo.remove');
        });

        // Withdrawals
        Route::get('withdrawals', [WithdrawalController::class, 'index'])->middleware('admin.can:withdrawals.view')->name('withdrawals.index');
        Route::get('withdrawals/methods', [WithdrawalController::class, 'methods'])->middleware('admin.can:withdrawals.methods')->name('withdrawals.methods');
        Route::post('withdrawals/methods', [WithdrawalController::class, 'saveMethod'])->middleware('admin.can:withdrawals.methods')->name('withdrawals.methods.store');
        Route::put('withdrawals/methods/{method}', [WithdrawalController::class, 'saveMethod'])->middleware('admin.can:withdrawals.methods')->name('withdrawals.methods.update');
        Route::get('withdrawals/{withdrawal}', [WithdrawalController::class, 'show'])->middleware('admin.can:withdrawals.view')->name('withdrawals.show');
        Route::post('withdrawals/{withdrawal}/approve', [WithdrawalController::class, 'approve'])->middleware('admin.can:withdrawals.decide')->name('withdrawals.approve');
        Route::post('withdrawals/{withdrawal}/reject', [WithdrawalController::class, 'reject'])->middleware('admin.can:withdrawals.decide')->name('withdrawals.reject');

        // Affiliates
        Route::get('affiliates', [AffiliateController::class, 'index'])->middleware('admin.can:affiliates.view')->name('affiliates.index');
        Route::post('affiliates/{affiliate}/standing', [AffiliateController::class, 'standing'])->middleware('admin.can:affiliates.manage')->name('affiliates.standing');

        // Stockists
        Route::get('stockists', [StockistController::class, 'index'])->middleware('admin.can:stockists.view')->name('stockists.index');
        Route::get('stockists/create', [StockistController::class, 'create'])->middleware('admin.can:stockists.manage')->name('stockists.create');
        Route::post('stockists', [StockistController::class, 'store'])->middleware('admin.can:stockists.manage')->name('stockists.store');
        Route::get('stockists/{store}', [StockistController::class, 'show'])->middleware('admin.can:stockists.view')->name('stockists.show');
        Route::post('stockists/{store}/activate', [StockistController::class, 'activate'])->middleware('admin.can:stockists.manage')->name('stockists.activate');
        Route::post('stockists/{store}/standing', [StockistController::class, 'standing'])->middleware('admin.can:stockists.manage')->name('stockists.standing');
        Route::post('stockists/{store}/allocate', [StockistController::class, 'allocate'])->middleware('admin.can:stock.allocate')->name('stockists.allocate');

        // Pool ledgers, contingency funds and held payouts
        Route::get('pools', PoolsController::class)->middleware('admin.can:pools.view')->name('pools');
        Route::get('awards', [AwardController::class, 'index'])->middleware('admin.can:awards.view')->name('awards.index');
        Route::middleware('admin.can:awards.decide')->group(function () {
            Route::post('awards/{award}/approve', [AwardController::class, 'approve'])->name('awards.approve');
            Route::post('awards/{award}/reject', [AwardController::class, 'reject'])->name('awards.reject');
            Route::post('awards/{award}/delivered', [AwardController::class, 'delivered'])->name('awards.delivered');
        });
        Route::get('epins', [EPinController::class, 'index'])->middleware('admin.can:epins.view')->name('epins.index');
        Route::middleware('admin.can:epins.manage')->group(function () {
            Route::post('epins', [EPinController::class, 'store'])->name('epins.store');
            Route::post('epins/{epin}/cancel', [EPinController::class, 'cancel'])->name('epins.cancel');
        });
        Route::middleware('admin.can:payments.view')->group(function () {
            Route::get('payments', [OnlinePaymentController::class, 'index'])->name('payments.index');
            Route::post('payments/{payment}/check', [OnlinePaymentController::class, 'check'])->middleware('throttle:30,1')->name('payments.check');
        });
        Route::middleware('admin.can:matching.view')->group(function () {
            Route::get('leadership', [LeadershipController::class, 'index'])->name('leadership.index');
            Route::get('leadership/{run}', [LeadershipController::class, 'show'])->name('leadership.show');
            Route::get('unilevel', [UnilevelController::class, 'index'])->name('unilevel.index');
            Route::get('unilevel/{run}', [UnilevelController::class, 'show'])->name('unilevel.show');
            Route::get('matching', [MatchingController::class, 'index'])->name('matching.index');
            Route::get('matching/{run}', [MatchingController::class, 'show'])->name('matching.show');
        });

        // Packages (read only for now; changing a package is a plan setting)
        Route::get('packages', [NetworkController::class, 'packages'])->middleware('admin.can:settings.view')->name('packages');

        // Settings
        Route::get('settings', [SettingsController::class, 'edit'])->middleware('admin.can:settings.view')->name('settings.edit');
        Route::put('settings', [SettingsController::class, 'update'])->middleware('admin.can:settings.edit')->name('settings.update');
        Route::put('settings/leadership', [SettingsController::class, 'updateLeadership'])->middleware('admin.can:settings.edit')->name('settings.leadership');
        Route::put('settings/activity', [SettingsController::class, 'updateActivity'])->middleware('admin.can:settings.edit')->name('settings.activity');

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
