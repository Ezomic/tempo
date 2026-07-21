<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PlanController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('activities', [ActivityController::class, 'index'])->name('activities.index');
    Route::get('activities/{activity}', [ActivityController::class, 'show'])->name('activities.show');

    Route::get('plan', [PlanController::class, 'index'])->name('plan.index');
    Route::post('plan', [PlanController::class, 'store'])->name('plan.store');
    Route::post('plan/{plannedWorkout}/push', [PlanController::class, 'push'])->name('plan.push');
    Route::delete('plan/{plannedWorkout}', [PlanController::class, 'destroy'])->name('plan.destroy');
});

require __DIR__.'/settings.php';
