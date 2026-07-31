<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreatePlannedWorkoutAction;
use App\Actions\DowngradeWorkoutAction;
use App\Actions\GenerateTrainingPlanAction;
use App\Actions\PushPlannedWorkoutAction;
use App\Actions\PushWorkoutToGarminAction;
use App\Concerns\InteractsWithCurrentUser;
use App\Enums\Intensity;
use App\Enums\Sport;
use App\Enums\WorkoutType;
use App\Http\Requests\GeneratePlanRequest;
use App\Http\Requests\StorePlannedWorkoutRequest;
use App\Models\PlannedWorkout;
use App\Models\PlannedWorkoutStep;
use App\Services\Chronos\ChronosClient;
use App\Services\Routing\RouteGenerator;
use App\Services\Training\AutoRescheduleService;
use App\Services\Training\WorkoutDescriber;
use App\Support\Payload;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class PlanController extends Controller
{
    use InteractsWithCurrentUser;

    public function index(Request $request, ChronosClient $chronos, WorkoutDescriber $describer, RouteGenerator $routes): Response
    {
        $user = $this->currentUser($request);

        $workouts = $this->currentUser($request)->plannedWorkouts()
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
        abort_unless($plannedWorkout->user_id === $this->currentUser($request)->id, 403);

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
        abort_unless($plannedWorkout->user_id === $this->currentUser($request)->id, 403);

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
        abort_unless($plannedWorkout->user_id === $this->currentUser($request)->id, 403);

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

    public function generator(): Response
    {
        return Inertia::render('plan/Generate', [
            'sports' => [
                ['value' => Sport::Run->value, 'label' => 'Run'],
                ['value' => Sport::Bike->value, 'label' => 'Bike'],
            ],
        ]);
    }

    public function generate(GeneratePlanRequest $request, GenerateTrainingPlanAction $generator, ChronosClient $chronos): RedirectResponse
    {
        $user = $this->currentUser($request);
        $today = CarbonImmutable::now();
        $raceDate = CarbonImmutable::parse(Payload::toStr($request->validated('race_date')));
        $sport = Sport::from(Payload::toStr($request->validated('sport')));
        $sessionsPerWeek = Payload::toInt($request->validated('sessions_per_week'));
        $currentCtl = (float) ($user->dailyLoadMetrics()->orderByDesc('date')->value('ctl') ?? 0);

        $busyDates = $chronos->busyDays($today->toDateString(), $raceDate->toDateString());
        $specs = $generator->handle($today, $raceDate, $sessionsPerWeek, $currentCtl, $busyDates);

        // Replace previously generated future sessions, but keep manual ones.
        $user->plannedWorkouts()
            ->whereNotNull('generated_at')
            ->whereDate('date', '>=', $today->toDateString())
            ->delete();

        $manualDates = array_flip($user->plannedWorkouts()
            ->whereNull('generated_at')
            ->whereDate('date', '>=', $today->toDateString())
            ->get(['date'])
            ->map(fn (PlannedWorkout $workout): string => $workout->date->toDateString())
            ->all());

        foreach ($specs as $spec) {
            if (isset($manualDates[$spec['date']])) {
                continue; // don't clobber a day the athlete planned by hand
            }

            $user->plannedWorkouts()->create([
                'date' => $spec['date'],
                'sport' => $sport,
                'workout_type' => $spec['workout_type'],
                'title' => $spec['title'],
                'duration_min' => $spec['duration_min'] > 0 ? $spec['duration_min'] : null,
                'generated_at' => now(),
            ]);
        }

        return to_route('plan.index')->with('status', 'Training plan generated.');
    }

    public function calendar(Request $request): Response
    {
        $today = CarbonImmutable::now();
        $start = $today->startOfWeek(Carbon::MONDAY);
        $weeks = 5;
        $end = $start->addWeeks($weeks)->subDay();

        $byDate = $this->currentUser($request)->plannedWorkouts()
            ->whereBetween('date', [$start->toDateString().' 00:00:00', $end->toDateString().' 23:59:59'])
            ->orderBy('date')
            ->get()
            ->groupBy(fn (PlannedWorkout $w): string => $w->date->toDateString());

        $grid = [];
        for ($w = 0; $w < $weeks; $w++) {
            $days = [];
            for ($d = 0; $d < 7; $d++) {
                $date = $start->addWeeks($w)->addDays($d);
                $key = $date->toDateString();
                $days[] = [
                    'date' => $key,
                    'is_today' => $date->isSameDay($today),
                    'is_past' => $date->lessThan($today->startOfDay()),
                    'workouts' => ($byDate->get($key) ?? collect())
                        ->map(fn (PlannedWorkout $workout): array => [
                            'id' => $workout->id,
                            'title' => $workout->title,
                            'sport' => $workout->sport->value,
                            'workout_type' => $workout->workout_type?->value,
                            'generated' => $workout->generated_at !== null,
                            'pushed' => $workout->isPushed(),
                        ])->values()->all(),
                ];
            }
            $grid[] = ['week_start' => $start->addWeeks($w)->toDateString(), 'days' => $days];
        }

        return Inertia::render('plan/Calendar', ['weeks' => $grid]);
    }

    public function move(Request $request, PlannedWorkout $plannedWorkout, PushPlannedWorkoutAction $push): RedirectResponse
    {
        abort_unless($plannedWorkout->user_id === $this->currentUser($request)->id, 403);

        $validated = $request->validate(['date' => ['required', 'date']]);
        $plannedWorkout->forceFill(['date' => $validated['date']])->save();

        if ($plannedWorkout->isPushed()) {
            try {
                $push->handle($plannedWorkout->refresh());
            } catch (Throwable) {
                // A calendar hiccup shouldn't block moving the session.
            }
        }

        return back()->with('status', 'Session moved.');
    }

    public function reschedule(Request $request, AutoRescheduleService $reschedule): RedirectResponse
    {
        $applied = $reschedule->apply($request->user(), CarbonImmutable::now());

        return $applied
            ? back()->with('status', 'Session rescheduled.')
            : back()->withErrors(['reschedule' => 'There is nothing to reschedule.']);
    }

    public function downgrade(Request $request, PlannedWorkout $plannedWorkout, DowngradeWorkoutAction $action, PushPlannedWorkoutAction $push): RedirectResponse
    {
        abort_unless($plannedWorkout->user_id === $this->currentUser($request)->id, 403);

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
