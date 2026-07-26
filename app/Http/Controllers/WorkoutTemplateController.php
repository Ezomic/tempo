<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreatePlannedWorkoutAction;
use App\Enums\Intensity;
use App\Enums\Sport;
use App\Enums\WorkoutType;
use App\Http\Requests\StoreWorkoutTemplateRequest;
use App\Models\WorkoutTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkoutTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $templates = $request->user()->workoutTemplates()
            ->orderBy('name')
            ->get()
            ->map(fn (WorkoutTemplate $template): array => [
                'id' => $template->id,
                'name' => $template->name,
                'sport' => $template->sport->value,
                'workout_type' => $template->workout_type?->value,
                'steps' => $template->steps,
            ]);

        return Inertia::render('workouts/Index', [
            'templates' => $templates,
            'sports' => [
                ['value' => Sport::Run->value, 'label' => 'Run'],
                ['value' => Sport::Bike->value, 'label' => 'Bike'],
            ],
            'intensityOptions' => Intensity::options(),
            'workoutTypeOptions' => WorkoutType::options(),
        ]);
    }

    public function store(StoreWorkoutTemplateRequest $request): RedirectResponse
    {
        $request->user()->workoutTemplates()->create($request->validated());

        return back()->with('status', 'Workout template saved.');
    }

    public function destroy(Request $request, WorkoutTemplate $workoutTemplate): RedirectResponse
    {
        abort_unless($workoutTemplate->user_id === $request->user()->id, 403);

        $workoutTemplate->delete();

        return back()->with('status', 'Template removed.');
    }

    public function apply(Request $request, WorkoutTemplate $workoutTemplate, CreatePlannedWorkoutAction $action): RedirectResponse
    {
        abort_unless($workoutTemplate->user_id === $request->user()->id, 403);

        $validated = $request->validate(['date' => ['required', 'date']]);

        $action->handle($request->user(), [
            'date' => $validated['date'],
            'sport' => $workoutTemplate->sport,
            'workout_type' => $workoutTemplate->workout_type,
            'title' => $workoutTemplate->name,
            'steps' => $workoutTemplate->steps,
        ]);

        return to_route('plan.index')->with('status', 'Workout added to your plan.');
    }
}
