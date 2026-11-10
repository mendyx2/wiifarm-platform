<?php

use App\Http\Controllers\Settings\KycController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TwoFactorController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    Route::get('settings/kyc', [KycController::class, 'edit'])->name('kyc.edit');
    Route::post('settings/kyc', [KycController::class, 'store'])->middleware('throttle:5,1')->name('kyc.store');
    Route::get('settings/kyc/{submission}/{field}', [KycController::class, 'file'])->name('kyc.file');

    // Two-factor sign-in. Turning it on or off and making new backup codes each
    // ask for the member's password, typed on the same page.
    Route::get('settings/two-factor', [TwoFactorController::class, 'edit'])->name('two-factor.edit');
    Route::post('settings/two-factor', [TwoFactorController::class, 'start'])->middleware('throttle:6,1')->name('two-factor.start');
    Route::post('settings/two-factor/backup-codes', [TwoFactorController::class, 'regenerate'])->middleware('throttle:6,1')->name('two-factor.regenerate');
    Route::post('settings/two-factor/confirm', [TwoFactorController::class, 'confirm'])->middleware('throttle:10,1')->name('two-factor.confirm');
    Route::delete('settings/two-factor', [TwoFactorController::class, 'destroy'])->middleware('throttle:6,1')->name('two-factor.destroy');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/appearance');
    })->name('appearance');
});
