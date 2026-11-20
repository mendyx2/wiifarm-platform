<?php

use App\Http\Controllers\Member\AwardsController;
use App\Http\Controllers\Member\DashboardController;
use App\Http\Controllers\Member\EPinController;
use App\Http\Controllers\Member\KeyInController;
use App\Http\Controllers\Member\NoticeController;
use App\Http\Controllers\Member\PaymentController;
use App\Http\Controllers\Member\ShopController;
use App\Http\Controllers\Member\StoreController;
use App\Http\Controllers\Member\TeamController;
use App\Http\Controllers\Member\UsdtController;
use App\Http\Controllers\Member\WalletController;
use App\Http\Controllers\Member\WithdrawController;
use App\Http\Controllers\PaystackWebhookController;
use App\Http\Controllers\PublicShop\PublicShopController;
use App\Http\Controllers\PublicSite\PageController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// The public website (specification section 12): pages the admins edit.
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('about', [PageController::class, 'show'])->defaults('slug', 'about')->name('page.about');
Route::get('contact', [PageController::class, 'show'])->defaults('slug', 'contact')->name('page.contact');
Route::get('privacy', [PageController::class, 'show'])->defaults('slug', 'privacy')->name('page.privacy');
Route::get('terms', [PageController::class, 'show'])->defaults('slug', 'terms')->name('page.terms');

// The public shop (specification section 8): no account needed.
Route::get('products', [PublicShopController::class, 'index'])->name('public.shop');
Route::post('products/order', [PublicShopController::class, 'order'])->middleware('throttle:10,1')->name('public.order.store');
Route::get('products/paid', [PublicShopController::class, 'return'])->name('public.return');
Route::get('products/order/{order}', [PublicShopController::class, 'show'])->middleware('signed:relative')->name('public.order');

// Paystack's signed message that a payment succeeded (no session, no CSRF token).
Route::post('webhooks/paystack', PaystackWebhookController::class)->middleware('throttle:120,1')->name('webhooks.paystack');

Route::middleware(['auth', 'member.verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('wallet', WalletController::class)->name('wallet');
    Route::get('notices', [NoticeController::class, 'index'])->name('notices');
    Route::get('package', [PaymentController::class, 'package'])->name('package');
    Route::post('package', [PaymentController::class, 'payPackage'])->middleware('throttle:10,1')->name('package.pay');
    Route::post('package/epin', [PaymentController::class, 'redeemPin'])->middleware('throttle:10,1')->name('package.epin');
    Route::get('awards', [AwardsController::class, 'index'])->name('awards');
    Route::post('awards/{award}/choice', [AwardsController::class, 'choose'])->name('awards.choose');
    Route::get('epins', [EPinController::class, 'index'])->name('epins');
    Route::post('epins', [EPinController::class, 'buy'])->middleware('throttle:10,1')->name('epins.buy');
    Route::get('team/add', [KeyInController::class, 'create'])->name('team.add');
    Route::post('team/add', [KeyInController::class, 'store'])->middleware('throttle:10,1')->name('team.add.store');
    Route::get('deposit', [PaymentController::class, 'deposit'])->name('deposit');
    Route::post('deposit/usdt', [UsdtController::class, 'start'])->middleware('throttle:10,1')->name('deposit.usdt');
    Route::post('deposit/usdt/claim', [UsdtController::class, 'claim'])->middleware('throttle:20,1')->name('deposit.usdt.claim');
    Route::post('deposit/manual', [PaymentController::class, 'depositManual'])->middleware('throttle:10,1')->name('deposit.manual');
    Route::post('deposit', [PaymentController::class, 'payDeposit'])->middleware('throttle:10,1')->name('deposit.pay');
    Route::get('payments/return', [PaymentController::class, 'return'])->name('payments.return');
    Route::post('wallet/transfer', [WithdrawController::class, 'bonusTransfer'])->name('wallet.transfer');
    Route::post('wallet/send', [WithdrawController::class, 'balanceTransfer'])->middleware('throttle:10,1')->name('wallet.send');
    Route::get('wallet/recipient', [WithdrawController::class, 'recipient'])->middleware('throttle:30,1')->name('wallet.recipient');
    Route::get('withdraw', [WithdrawController::class, 'index'])->name('withdraw');
    Route::post('withdraw', [WithdrawController::class, 'store'])->middleware('throttle:6,1')->name('withdraw.store');
    Route::get('shop', [ShopController::class, 'index'])->name('shop');
    Route::post('shop', [ShopController::class, 'store'])->middleware('throttle:10,1')->name('shop.store');
    Route::get('orders', [ShopController::class, 'orders'])->name('orders.index');
    Route::get('store', [StoreController::class, 'index'])->name('store');
    Route::post('store/lookup', [StoreController::class, 'lookup'])->middleware('throttle:20,1')->name('store.lookup');
    Route::post('store/handover', [StoreController::class, 'handover'])->middleware('throttle:20,1')->name('store.handover');
    Route::post('store/collect', [StoreController::class, 'collect'])->name('store.collect');
    Route::get('team', [TeamController::class, 'tree'])->name('team');
    Route::get('team/pv', [TeamController::class, 'pv'])->name('team.pv');
});

// Design reference: every building block of the Wiifarm design system on one
// page, for reviewers. Only on a developer's machine, never on the live site.
if (app()->environment('local', 'testing')) {
    Route::get('design', fn () => Inertia::render('design'))->name('design');
}

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
