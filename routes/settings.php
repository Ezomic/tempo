<?php

use App\Http\Controllers\Settings\GarminController;
use App\Http\Controllers\Settings\HomeLocationController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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

    Route::get('settings/routes', [HomeLocationController::class, 'edit'])->name('routes.edit');
    Route::patch('settings/routes', [HomeLocationController::class, 'update'])->name('routes.update');
    Route::post('settings/routes/infer', [HomeLocationController::class, 'infer'])->name('routes.infer');
    Route::post('settings/routes/geocode', [HomeLocationController::class, 'geocode'])
        ->middleware('throttle:30,1')->name('routes.geocode');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::get('settings/appearance', fn () => Inertia::render('settings/Appearance'))->name('appearance.edit');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
