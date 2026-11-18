<?php

use App\Http\Controllers\Member\ShopController;
use App\Http\Controllers\Member\TeamController;
use App\Http\Controllers\Member\WalletController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'member.verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::get('wallet', WalletController::class)->name('wallet');
    Route::get('shop', [ShopController::class, 'index'])->name('shop');
    Route::post('shop', [ShopController::class, 'store'])->middleware('throttle:10,1')->name('shop.store');
    Route::get('orders', [ShopController::class, 'orders'])->name('orders.index');
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
