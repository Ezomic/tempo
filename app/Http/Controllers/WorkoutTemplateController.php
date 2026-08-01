<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreatePlannedWorkoutAction;
use App\Concerns\InteractsWithCurrentUser;
use App\Enums\Intensity;
use App\Enums\Sport;
use App\Enums\WorkoutType;
use App\Http\Requests\StoreWorkoutTemplateRequest;
use App\Models\WorkoutTemplate;
use App\Support\Payload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkoutTemplateController extends Controller
{
    use InteractsWithCurrentUser;

    public function index(Request $request): Response
    {
        $templates = $this->currentUser($request)->workoutTemplates()
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
            'sports' => array_map(
                fn (Sport $sport): array => ['value' => $sport->value, 'label' => $sport->label()],
                [Sport::Run, Sport::Bike, Sport::Swim, Sport::Strength, Sport::Hike],
            ),
            'intensityOptions' => Intensity::options(),
            'workoutTypeOptions' => WorkoutType::options(),
        ]);
    }

    public function store(StoreWorkoutTemplateRequest $request): RedirectResponse
    {
        $this->currentUser($request)->workoutTemplates()->create($request->validated());

        return back()->with('status', 'Workout template saved.');
    }

    public function destroy(Request $request, WorkoutTemplate $workoutTemplate): RedirectResponse
    {
        abort_unless($workoutTemplate->user_id === $this->currentUser($request)->id, 403);

        $workoutTemplate->delete();

        return back()->with('status', 'Template removed.');
    }

    public function apply(Request $request, WorkoutTemplate $workoutTemplate, CreatePlannedWorkoutAction $action): RedirectResponse
    {
        abort_unless($workoutTemplate->user_id === $this->currentUser($request)->id, 403);

        $validated = Payload::assoc($request->validate(['date' => ['required', 'date']]));

        $action->handle($this->currentUser($request), [
            'date' => Payload::toStr($validated['date']),
            'sport' => $workoutTemplate->sport,
            'workout_type' => $workoutTemplate->workout_type,
            'title' => $workoutTemplate->name,
            'steps' => $workoutTemplate->steps,
        ]);

        return to_route('plan.index')->with('status', 'Workout added to your plan.');
    }
}
