<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Concerns\InteractsWithCurrentUser;
use App\Enums\GoalType;
use App\Http\Requests\StoreGoalRequest;
use App\Models\TrainingGoal;
use App\Services\Training\GoalProgressService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GoalController extends Controller
{
    use InteractsWithCurrentUser;

    public function index(Request $request, GoalProgressService $progress): Response
    {
        return Inertia::render('goals/Index', [
            'goals' => $this->goalsWithProgress($request, $progress),
            'typeOptions' => GoalType::options(),
        ]);
    }

    public function store(StoreGoalRequest $request): RedirectResponse
    {
        $this->currentUser($request)->trainingGoals()->create($request->validated());

        return back()->with('status', 'Goal added.');
    }

    public function destroy(Request $request, TrainingGoal $goal): RedirectResponse
    {
        abort_unless($goal->user_id === $this->currentUser($request)->id, 403);

        $goal->delete();

        return back()->with('status', 'Goal removed.');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function goalsWithProgress(Request $request, GoalProgressService $progress): array
    {
        $user = $this->currentUser($request);
        $today = CarbonImmutable::now();

        return array_values($user->trainingGoals()
            ->orderBy('target_date')
            ->get()
            ->map(fn (TrainingGoal $goal): array => [
                'id' => $goal->id,
                'type' => $goal->type->value,
                'type_label' => $goal->type->label(),
                'target_value' => $goal->target_value,
                'distance_m' => $goal->distance_m,
                'progress' => $progress->evaluate($goal, $user, $today),
            ])
            ->all());
    }
}
