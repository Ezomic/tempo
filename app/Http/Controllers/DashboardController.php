<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\PlannedWorkout;
use App\Services\Training\ReadinessService;
use App\Services\Training\TrainingLoadService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly TrainingLoadService $load,
        private readonly ReadinessService $readiness,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $today = CarbonImmutable::now();

        $load = $this->load->acuteChronic($user, $today);

        return Inertia::render('Dashboard', [
            'hasData' => $user->activities()->exists() || $user->wellnessDays()->exists(),
            'garminConnected' => $user->garminConnection?->isConnected() ?? false,
            'readiness' => $this->readiness->snapshot($user, $load['ratio']),
            'load' => $load,
            'weekly' => $this->load->weeklyBySport($user, $today, 8),
            'recentActivities' => $user->activities()
                ->latest('started_at')
                ->limit(5)
                ->get()
                ->map(fn (Activity $activity): array => [
                    'id' => $activity->id,
                    'sport' => $activity->sport->value,
                    'name' => is_string($activity->raw_summary['activityName'] ?? null)
                        ? $activity->raw_summary['activityName']
                        : ucfirst($activity->sport->value),
                    'distance_m' => $activity->distance_m,
                    'duration_s' => $activity->duration_s,
                    'trimp' => $activity->trimp,
                ]),
            'todayPlan' => $this->todayPlan($user->plannedWorkouts()->whereDate('date', $today->toDateString())->first()),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function todayPlan(?PlannedWorkout $workout): ?array
    {
        if ($workout === null) {
            return null;
        }

        return [
            'sport' => $workout->sport->value,
            'title' => $workout->title,
            'duration_min' => $workout->duration_min,
            'notes' => $workout->notes,
            'pushed' => $workout->isPushed(),
        ];
    }
}
