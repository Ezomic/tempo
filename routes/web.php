<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\LifeEventController;
use App\Http\Controllers\PacingController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\PublicProfileController;
use App\Http\Controllers\RecapController;
use App\Http\Controllers\RecordsController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\WellnessController;
use App\Http\Controllers\WorkoutTemplateController;
use App\Http\Controllers\ZoneCalibrationController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::get('p/{token}', [PublicProfileController::class, 'show'])->name('public-profile.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('activities', [ActivityController::class, 'index'])->name('activities.index');
    Route::get('activities/{activity}', [ActivityController::class, 'show'])->name('activities.show');

    Route::get('records', [RecordsController::class, 'index'])->name('records.index');
    Route::get('leaderboard', [LeaderboardController::class, 'index'])->name('leaderboard.index');
    Route::get('recap', [RecapController::class, 'index'])->name('recap.index');

    Route::post('profile/share', [PublicProfileController::class, 'enable'])->name('public-profile.enable');
    Route::delete('profile/share', [PublicProfileController::class, 'disable'])->name('public-profile.disable');

    Route::get('wellness', [WellnessController::class, 'index'])->name('wellness.index');
    Route::post('life-events', [LifeEventController::class, 'store'])->name('life-events.store');
    Route::delete('life-events/{lifeEvent}', [LifeEventController::class, 'destroy'])->name('life-events.destroy');

    Route::get('goals', [GoalController::class, 'index'])->name('goals.index');
    Route::post('goals', [GoalController::class, 'store'])->name('goals.store');
    Route::delete('goals/{goal}', [GoalController::class, 'destroy'])->name('goals.destroy');

    Route::get('pacing', [PacingController::class, 'index'])->name('pacing.index');
    Route::post('pacing', [PacingController::class, 'plan'])->name('pacing.plan');

    Route::post('zones/calibrate', [ZoneCalibrationController::class, 'apply'])->name('zones.calibrate');

    Route::get('workouts', [WorkoutTemplateController::class, 'index'])->name('workouts.index');
    Route::post('workouts', [WorkoutTemplateController::class, 'store'])->name('workouts.store');
    Route::delete('workouts/{workoutTemplate}', [WorkoutTemplateController::class, 'destroy'])->name('workouts.destroy');
    Route::post('workouts/{workoutTemplate}/apply', [WorkoutTemplateController::class, 'apply'])->name('workouts.apply');

    Route::get('plan', [PlanController::class, 'index'])->name('plan.index');
    Route::get('plan/calendar', [PlanController::class, 'calendar'])->name('plan.calendar');
    Route::post('plan/{plannedWorkout}/move', [PlanController::class, 'move'])->name('plan.move');
    Route::get('plan/generate', [PlanController::class, 'generator'])->name('plan.generate');
    Route::post('plan/generate', [PlanController::class, 'generate'])->name('plan.generate.store');
    Route::post('plan', [PlanController::class, 'store'])->name('plan.store');
    Route::post('plan/{plannedWorkout}/push', [PlanController::class, 'push'])->name('plan.push');
    Route::post('plan/{plannedWorkout}/watch', [PlanController::class, 'pushToWatch'])->name('plan.watch');
    Route::delete('plan/{plannedWorkout}', [PlanController::class, 'destroy'])->name('plan.destroy');
    Route::post('plan/{plannedWorkout}/downgrade', [PlanController::class, 'downgrade'])->name('plan.downgrade');
    Route::post('plan/reschedule', [PlanController::class, 'reschedule'])->name('plan.reschedule');

    Route::post('plan/{plannedWorkout}/route/suggest', [RouteController::class, 'suggest'])
        ->middleware('throttle:20,1')->name('plan.route.suggest');
    Route::post('plan/{plannedWorkout}/route', [RouteController::class, 'save'])->name('plan.route.save');
});

require __DIR__.'/auth.php';
require __DIR__.'/settings.php';
