<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreatePlannedWorkoutAction;
use App\Actions\DowngradeWorkoutAction;
use App\Actions\PushPlannedWorkoutAction;
use App\Actions\PushWorkoutToGarminAction;
use App\Enums\Intensity;
use App\Enums\WorkoutType;
use App\Http\Requests\StorePlannedWorkoutRequest;
use App\Models\PlannedWorkout;
use App\Models\PlannedWorkoutStep;
use App\Services\Chronos\ChronosClient;
use App\Services\Routing\RouteGenerator;
use App\Services\Training\WorkoutDescriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class PlanController extends Controller
{
    public function index(Request $request, ChronosClient $chronos, WorkoutDescriber $describer, RouteGenerator $routes): Response
    {
        $user = $request->user();

        $workouts = $request->user()->plannedWorkouts()
            ->with('steps')
            ->orderBy('date')
            ->get()
            ->map(fn (PlannedWorkout $workout): array => [
                'id' => $workout->id,
                'date' => $workout->date->toDateString(),
                'sport' => $workout->sport->value,
                'workout_type' => $workout->workout_type === null ? null : [
                    'value' => $workout->workout_type->value,
                    'label' => $workout->workout_type->label(),
                ],
                'title' => $workout->title,
                'notes' => $workout->notes,
                'duration_min' => $workout->duration_min,
                'description' => $describer->describe($workout),
                'steps' => $workout->steps->map(fn (PlannedWorkoutStep $step): array => [
                    'position' => $step->position,
                    'repeat' => $step->repeat,
                    'duration_min' => $step->duration_min,
                    'recovery_min' => $step->recovery_min,
                    'intensity' => $this->intensityPayload($step->intensity),
                    'recovery_intensity' => $step->recovery_intensity === null
                        ? null
                        : $this->intensityPayload($step->recovery_intensity),
                    'label' => $step->label,
                ])->all(),
                'pushed' => $workout->isPushed(),
                'chronos_url' => $workout->chronos_url,
                'on_watch' => $workout->isOnWatch(),
                'route' => $workout->hasRoute() ? [
                    'coordinates' => $workout->route_geometry,
                    'distance_m' => $workout->route_distance_m,
                    'ascent_m' => $workout->route_ascent_m,
                    'kind' => $workout->route_kind,
                ] : null,
            ]);

        return Inertia::render('Plan', [
            'workouts' => $workouts,
            'chronosConfigured' => $chronos->isConfigured(),
            'intensityOptions' => Intensity::options(),
            'workoutTypeOptions' => WorkoutType::options(),
            'routingConfigured' => $routes->isConfigured(),
            'homeSet' => $user->home_lat !== null && $user->home_lng !== null,
            'garminConnected' => $user->garminConnection?->isConnected() ?? false,
        ]);
    }

    public function store(StorePlannedWorkoutRequest $request, CreatePlannedWorkoutAction $action): RedirectResponse
    {
        $action->handle($request->user(), $request->validated());

        return back()->with('status', 'Workout planned.');
    }

    public function push(Request $request, PlannedWorkout $plannedWorkout, PushPlannedWorkoutAction $action): RedirectResponse
    {
        abort_unless($plannedWorkout->user_id === $request->user()->id, 403);

        try {
            $action->handle($plannedWorkout);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['push' => 'Could not push this workout to chronos. Check the integration settings.']);
        }

        return back()->with('status', 'Pushed to your calendar.');
    }

    public function pushToWatch(Request $request, PlannedWorkout $plannedWorkout, PushWorkoutToGarminAction $action): RedirectResponse
    {
        abort_unless($plannedWorkout->user_id === $request->user()->id, 403);

        try {
            $action->handle($plannedWorkout);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['watch' => 'Could not send this workout to your watch. Check that Garmin is connected.']);
        }

        return back()->with('status', 'Sent to your watch.');
    }

    public function destroy(Request $request, PlannedWorkout $plannedWorkout, ChronosClient $chronos): RedirectResponse
    {
        abort_unless($plannedWorkout->user_id === $request->user()->id, 403);

        $eventId = $plannedWorkout->chronos_event_id;
        if ($eventId !== null && $eventId !== '' && $chronos->isConfigured()) {
            try {
                $chronos->deleteEvent($eventId);
            } catch (Throwable) {
                // A calendar hiccup shouldn't block removing the plan.
            }
        }

        $plannedWorkout->delete();

        return back()->with('status', 'Workout removed.');
    }

    public function downgrade(Request $request, PlannedWorkout $plannedWorkout, DowngradeWorkoutAction $action, PushPlannedWorkoutAction $push): RedirectResponse
    {
        abort_unless($plannedWorkout->user_id === $request->user()->id, 403);

        try {
            $action->handle($plannedWorkout);
        } catch (RuntimeException) {
            return back()->withErrors(['downgrade' => 'This session cannot be downgraded.']);
        }

        // Keep the calendar in sync with the eased session.
        if ($plannedWorkout->isPushed()) {
            try {
                $push->handle($plannedWorkout->refresh());
            } catch (Throwable) {
                // Non-fatal: the plan change stands even if the sync fails.
            }
        }

        return back()->with('status', 'Session eased for today.');
    }

    /**
     * @return array{value: string, label: string, zone: int, hr_percent: string, feel: string, color: string}
     */
    private function intensityPayload(Intensity $intensity): array
    {
        return [
            'value' => $intensity->value,
            'label' => $intensity->label(),
            'zone' => $intensity->zone(),
            'hr_percent' => $intensity->hrPercent(),
            'feel' => $intensity->feel(),
            'color' => $intensity->color(),
        ];
    }
}
