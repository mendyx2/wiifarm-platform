<?php

use App\Http\Controllers\Affiliate\AffiliateAuthController;
use App\Http\Controllers\Affiliate\AffiliateDashboardController;
use App\Http\Controllers\Affiliate\AffiliateWithdrawController;
use Illuminate\Support\Facades\Route;

/*
 * Affiliates (specification section 8): their own sign-up, sign-in and
 * dashboard under /affiliate, and their referral links under /r/{code}.
 */
Route::get('r/{code}', [AffiliateDashboardController::class, 'link'])->name('affiliate.link');

Route::prefix('affiliate')->name('affiliate.')->group(function () {
    Route::middleware('guest:affiliate')->group(function () {
        Route::get('register', [AffiliateAuthController::class, 'showRegister'])->name('register');
        Route::post('register', [AffiliateAuthController::class, 'register'])->middleware('throttle:6,1');
        Route::get('login', [AffiliateAuthController::class, 'showLogin'])->name('login');
        Route::post('login', [AffiliateAuthController::class, 'login']);
    });

    Route::middleware('auth:affiliate')->group(function () {
        Route::get('/', AffiliateDashboardController::class)->name('dashboard');
        Route::post('logout', [AffiliateAuthController::class, 'logout'])->name('logout');
        Route::get('withdraw', [AffiliateWithdrawController::class, 'index'])->name('withdraw');
        Route::post('withdraw', [AffiliateWithdrawController::class, 'store'])->middleware('throttle:6,1')->name('withdraw.store');
    });
});
