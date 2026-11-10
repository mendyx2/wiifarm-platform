<?php

use App\Http\Controllers\Settings\KycController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
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

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/appearance');
    })->name('appearance');
});
