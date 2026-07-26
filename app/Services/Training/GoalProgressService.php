<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Enums\GoalType;
use App\Enums\Sport;
use App\Models\MeanMaxEffort;
use App\Models\TrainingGoal;
use App\Models\User;
use Carbon\CarbonImmutable;

class GoalProgressService
{
    private const SLOPE_WINDOW_DAYS = 21;

    public function __construct(private readonly RacePredictorService $predictor) {}

    /**
     * Evaluate a goal against current fitness and its extrapolated trajectory.
     *
     * @return array{status: string, current: float|null, target: float, projected: float|null, gap: float|null, unit: string, target_date: string, days_left: int}
     */
    public function evaluate(TrainingGoal $goal, User $user, CarbonImmutable $today): array
    {
        $daysLeft = (int) $today->startOfDay()->diffInDays($goal->target_date->startOfDay(), false);

        return $goal->type === GoalType::Ctl
            ? $this->evaluateCtl($goal, $user, $today, $daysLeft)
            : $this->evaluateRaceTime($goal, $user, $today, $daysLeft);
    }

    /**
     * @return array{status: string, current: float|null, target: float, projected: float|null, gap: float|null, unit: string, target_date: string, days_left: int}
     */
    private function evaluateCtl(TrainingGoal $goal, User $user, CarbonImmutable $today, int $daysLeft): array
    {
        $current = (float) ($user->dailyLoadMetrics()->orderByDesc('date')->value('ctl') ?? 0.0);
        $slope = $this->ctlSlope($user, $today);
        $projected = round(max(0.0, $current + $slope * max($daysLeft, 0)), 1);
        $target = $goal->target_value;

        $status = match (true) {
            $current >= $target => 'ahead',
            $daysLeft <= 0 => 'behind',
            $projected >= $target => 'on_track',
            default => 'behind',
        };

        return [
            'status' => $status,
            'current' => round($current, 1),
            'target' => round($target, 1),
            'projected' => $projected,
            'gap' => round($target - $current, 1),
            'unit' => 'ctl',
            'target_date' => $goal->target_date->toDateString(),
            'days_left' => $daysLeft,
        ];
    }

    /**
     * @return array{status: string, current: float|null, target: float, projected: float|null, gap: float|null, unit: string, target_date: string, days_left: int}
     */
    private function evaluateRaceTime(TrainingGoal $goal, User $user, CarbonImmutable $today, int $daysLeft): array
    {
        $meanMax = $user->meanMaxEfforts()
            ->where('sport', Sport::Run)
            ->get(['duration_s', 'speed_mps'])
            ->mapWithKeys(fn (MeanMaxEffort $e): array => [$e->duration_s => $e->speed_mps])
            ->all();

        $distance = $goal->distance_m ?? 0;
        $currentCtl = (float) ($user->dailyLoadMetrics()->orderByDesc('date')->value('ctl') ?? 0.0);
        $projectedCtl = max(0.0, $currentCtl + $this->ctlSlope($user, $today) * max($daysLeft, 0));

        $now = $this->predictor->predictOne($meanMax, $distance, $currentCtl);
        $atTarget = $this->predictor->predictOne($meanMax, $distance, $currentCtl, $projectedCtl);
        $target = $goal->target_value;

        if ($now === null) {
            return [
                'status' => 'unknown',
                'current' => null,
                'target' => round($target, 0),
                'projected' => null,
                'gap' => null,
                'unit' => 'seconds',
                'target_date' => $goal->target_date->toDateString(),
                'days_left' => $daysLeft,
            ];
        }

        $status = match (true) {
            $now <= $target => 'ahead',
            $daysLeft <= 0 => 'behind',
            $atTarget !== null && $atTarget <= $target => 'on_track',
            default => 'behind',
        };

        return [
            'status' => $status,
            'current' => (float) $now,
            'target' => round($target, 0),
            'projected' => $atTarget === null ? null : (float) $atTarget,
            'gap' => (float) ($now - $target),
            'unit' => 'seconds',
            'target_date' => $goal->target_date->toDateString(),
            'days_left' => $daysLeft,
        ];
    }

    /**
     * CTL change per day over the recent window, from the stored curve.
     */
    private function ctlSlope(User $user, CarbonImmutable $today): float
    {
        $points = $user->dailyLoadMetrics()
            ->whereBetween('date', [$today->subDays(self::SLOPE_WINDOW_DAYS)->toDateString(), $today->toDateString()])
            ->orderBy('date')
            ->get(['ctl']);

        if ($points->count() < 2) {
            return 0.0;
        }

        $first = (float) $points->first()->ctl;
        $last = (float) $points->last()->ctl;

        return ($last - $first) / max(1, $points->count() - 1);
    }
}
