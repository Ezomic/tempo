<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\WorkoutType;
use App\Models\Activity;
use App\Models\PlannedWorkout;
use App\Models\TrainingGoal;
use App\Models\User;
use App\Services\Training\AdaptivePlanService;
use App\Services\Training\AdherenceService;
use App\Services\Training\FitnessCurveService;
use App\Services\Training\GoalProgressService;
use App\Services\Training\LoadGuardrailService;
use App\Services\Training\RacePredictorService;
use App\Services\Training\ReadinessService;
use App\Services\Training\TaperReadinessService;
use App\Services\Training\TrainingLoadService;
use App\Services\Training\ZoneDistributionService;
use App\Services\Weather\WeatherService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly TrainingLoadService $load,
        private readonly ReadinessService $readiness,
        private readonly FitnessCurveService $fitnessCurve,
        private readonly LoadGuardrailService $guardrails,
        private readonly ZoneDistributionService $zones,
        private readonly AdaptivePlanService $adaptive,
        private readonly AdherenceService $adherence,
        private readonly WeatherService $weather,
        private readonly RacePredictorService $predictor,
        private readonly GoalProgressService $goals,
        private readonly TaperReadinessService $taper,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $today = CarbonImmutable::now();

        $load = $this->load->acuteChronic($user, $today);
        $readiness = $this->readiness->snapshot($user, $load['ratio']);
        $todayPlan = $user->plannedWorkouts()->whereDate('date', $today->toDateString())->first();

        return Inertia::render('Dashboard', [
            'hasData' => $user->activities()->exists() || $user->wellnessDays()->exists(),
            'garminConnected' => $user->garminConnection?->isConnected() ?? false,
            'readiness' => $readiness,
            'load' => $load,
            'chronicBySport' => $this->load->chronicBySport($user, $today),
            'guardrails' => $this->guardrails->guardrails($user, $today),
            'fitnessCurve' => $this->fitnessCurve($user, $today),
            'zones' => [
                'weekly' => $this->zones->weekly($user, $today, 8),
                'polarization' => $this->zones->polarization($user, $today, 4),
            ],
            'weekly' => $this->load->weeklyBySport($user, $today, 8),
            'adherence' => $this->adherence->weekly($user, $today, 4),
            'recentActivities' => $this->recentActivities($user),
            'racePredictions' => $this->predictor->predictions($user),
            'goals' => $this->dashboardGoals($user, $today),
            'taper' => $this->taper->forNextRace($user, $today),
            'todayPlan' => $this->todayPlan($todayPlan),
            'adaptiveSuggestion' => $this->adaptive->suggestion($todayPlan, $readiness['score'] ?? null),
            'todayWeather' => $todayPlan !== null
                ? $this->weather->forOutdoorSession($todayPlan, $user, $today)
                : null,
        ]);
    }

    /**
     * The five most recent activities, flagging any that overran a day
     * planned as recovery or easy.
     *
     * @return list<array<string, mixed>>
     */
    private function recentActivities(User $user): array
    {
        $activities = $user->activities()
            ->latest('started_at')
            ->limit(5)
            ->get();

        $easyPlans = $this->easyPlansByDate($user, $activities);
        $ceiling = (float) config('training.recovery_ceiling');

        return array_values($activities
            ->map(function (Activity $activity) use ($easyPlans, $ceiling): array {
                $date = $activity->started_at->toDateString();

                return [
                    'id' => $activity->id,
                    'sport' => $activity->sport->value,
                    'name' => is_string($activity->raw_summary['activityName'] ?? null)
                        ? $activity->raw_summary['activityName']
                        : ucfirst($activity->sport->value),
                    'distance_m' => $activity->distance_m,
                    'duration_s' => $activity->duration_s,
                    'trimp' => $activity->trimp,
                    'recovery_flag' => $easyPlans->has($date)
                        && (float) ($activity->trimp ?? 0) > $ceiling,
                ];
            })
            ->all());
    }

    /**
     * Recovery/easy planned workouts on the dates of the given activities,
     * keyed by date.
     *
     * @param  Collection<int, Activity>  $activities
     * @return Collection<string, PlannedWorkout>
     */
    private function easyPlansByDate(User $user, Collection $activities): Collection
    {
        $dates = $activities
            ->map(fn (Activity $activity): string => $activity->started_at->toDateString())
            ->unique()
            ->values()
            ->all();

        if ($dates === []) {
            return collect();
        }

        return $user->plannedWorkouts()
            ->whereIn('workout_type', [WorkoutType::Recovery, WorkoutType::Easy])
            ->whereBetween('date', [min($dates).' 00:00:00', max($dates).' 23:59:59'])
            ->get()
            ->keyBy(fn (PlannedWorkout $workout): string => $workout->date->toDateString());
    }

    /**
     * @return array{current: array{date: string, ctl: float, atl: float, tsb: float}|null, history: list<array{date: string, ctl: float, atl: float, tsb: float}>, projection: list<array{date: string, ctl: float, atl: float, tsb: float}>}
     */
    private function fitnessCurve(User $user, CarbonImmutable $today): array
    {
        $history = $this->fitnessCurve->series($user, $today->subDays(364), $today);

        return [
            'current' => $history === [] ? null : $history[array_key_last($history)],
            'history' => $history,
            'projection' => $this->fitnessCurve->project($user, $today, 14),
        ];
    }

    /**
     * Active goals (target date still ahead) with their live status.
     *
     * @return list<array<string, mixed>>
     */
    private function dashboardGoals(User $user, CarbonImmutable $today): array
    {
        return array_values($user->trainingGoals()
            ->whereDate('target_date', '>=', $today->toDateString())
            ->orderBy('target_date')
            ->get()
            ->map(fn (TrainingGoal $goal): array => [
                'id' => $goal->id,
                'type_label' => $goal->type->label(),
                'progress' => $this->goals->evaluate($goal, $user, $today),
            ])
            ->all());
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
            'id' => $workout->id,
            'sport' => $workout->sport->value,
            'title' => $workout->title,
            'workout_type' => $workout->workout_type?->value,
            'duration_min' => $workout->duration_min,
            'notes' => $workout->notes,
            'pushed' => $workout->isPushed(),
            'adapted' => $workout->adapted_at !== null,
        ];
    }
}
