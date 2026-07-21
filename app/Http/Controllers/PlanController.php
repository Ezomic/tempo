<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\PushPlannedWorkoutAction;
use App\Http\Requests\StorePlannedWorkoutRequest;
use App\Models\PlannedWorkout;
use App\Services\Chronos\ChronosClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PlanController extends Controller
{
    public function index(Request $request, ChronosClient $chronos): Response
    {
        $workouts = $request->user()->plannedWorkouts()
            ->orderBy('date')
            ->get()
            ->map(fn (PlannedWorkout $workout): array => [
                'id' => $workout->id,
                'date' => $workout->date->toDateString(),
                'sport' => $workout->sport->value,
                'title' => $workout->title,
                'notes' => $workout->notes,
                'duration_min' => $workout->duration_min,
                'pushed' => $workout->isPushed(),
                'chronos_url' => $workout->chronos_url,
            ]);

        return Inertia::render('Plan', [
            'workouts' => $workouts,
            'chronosConfigured' => $chronos->isConfigured(),
        ]);
    }

    public function store(StorePlannedWorkoutRequest $request): RedirectResponse
    {
        $request->user()->plannedWorkouts()->create($request->validated());

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

    public function destroy(Request $request, PlannedWorkout $plannedWorkout): RedirectResponse
    {
        abort_unless($plannedWorkout->user_id === $request->user()->id, 403);

        $plannedWorkout->delete();

        return back()->with('status', 'Workout removed.');
    }
}
