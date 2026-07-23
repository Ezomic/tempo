<?php

use App\Http\Controllers\Settings\GarminController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('settings/garmin', [GarminController::class, 'edit'])->name('garmin.edit');
    Route::post('settings/garmin/connect', [GarminController::class, 'connect'])
        ->middleware('throttle:5,1')->name('garmin.connect');
    Route::post('settings/garmin/mfa', [GarminController::class, 'mfa'])
        ->middleware('throttle:5,1')->name('garmin.mfa');
    Route::post('settings/garmin/sync', [GarminController::class, 'sync'])
        ->middleware('throttle:10,1')->name('garmin.sync');
    Route::patch('settings/garmin/hr-zones', [GarminController::class, 'updateSettings'])->name('garmin.hr-zones.update');
    Route::delete('settings/garmin', [GarminController::class, 'disconnect'])->name('garmin.disconnect');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
