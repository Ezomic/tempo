<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Models\PlannedWorkout;
use App\Models\User;
use App\Services\Weather\WeatherService;
use Carbon\CarbonImmutable;

class DailyRecommendationService
{
    public function __construct(
        private readonly TrainingLoadService $load,
        private readonly ReadinessService $readiness,
        private readonly LoadGuardrailService $guardrails,
        private readonly WeatherService $weather,
    ) {}

    /**
     * A single decision for today, combining readiness, the planned session,
     * the load guardrail band, and the outdoor forecast.
     *
     * @return array{action: string, headline: string, reason: string, planned_workout_id: int|null, factors: list<array{label: string, detail: string}>}
     */
    public function forToday(User $user, CarbonImmutable $today): array
    {
        $plan = $user->plannedWorkouts()->whereDate('date', $today->toDateString())->first();

        $ratio = $this->load->acuteChronic($user, $today)['ratio'];
        $readiness = $this->readiness->snapshot($user, $ratio);
        $score = $readiness['score'] ?? null;
        $guardStatus = $this->guardrails->guardrails($user, $today)['status'];
        $weather = $plan !== null ? $this->weather->forOutdoorSession($plan, $user, $today) : null;

        $isHard = $plan?->workout_type?->isHard() ?? false;
        $downgradeBelow = (int) config('training.readiness.downgrade_below');

        $factors = $this->factors($plan, $score, $guardStatus, $weather);
        $decision = $this->decide($plan, $score, $guardStatus, $weather, $isHard, $downgradeBelow);

        return $decision + ['factors' => $factors];
    }

    /**
     * @param  array{warning?: bool, key?: bool}|null  $weather
     * @return array{action: string, headline: string, reason: string, planned_workout_id: int|null}
     */
    private function decide(?PlannedWorkout $plan, ?int $score, string $guardStatus, ?array $weather, bool $isHard, int $downgradeBelow): array
    {
        if ($plan === null) {
            $rest = $score !== null && $score < $downgradeBelow;

            return $this->result('rest', $rest ? 'Rest today' : 'Optional easy day',
                $rest
                    ? 'Nothing is planned and your readiness is low.'
                    : 'Nothing is planned. An easy hour is fine if you feel good.');
        }

        if ($guardStatus === 'danger') {
            return $isHard
                ? $this->result('ease', 'Ease today\'s session', 'Your load is spiking; take the intensity down.', $plan->id)
                : $this->result('proceed', 'Keep it easy', 'Your load is spiking, so hold this one back.');
        }

        if ($isHard && $score !== null && $score < $downgradeBelow) {
            return $this->result('ease', 'Ease today\'s session', "Readiness is {$score}/100 for a hard session.", $plan->id);
        }

        if (($weather['warning'] ?? false) && ($weather['key'] ?? false)) {
            return $this->result('move', 'Consider moving this session', 'The forecast is rough for a key outdoor session.');
        }

        return $this->result('proceed', 'Proceed as planned', 'Readiness, load, and conditions all look fine.');
    }

    /**
     * @param  array{warning?: bool, key?: bool, reasons?: list<string>}|null  $weather
     * @return list<array{label: string, detail: string}>
     */
    private function factors(?PlannedWorkout $plan, ?int $score, string $guardStatus, ?array $weather): array
    {
        $factors = [];

        $factors[] = ['label' => 'Readiness', 'detail' => $score === null ? 'No data' : "{$score}/100"];
        $factors[] = ['label' => 'Load', 'detail' => ucfirst($guardStatus)];
        $factors[] = ['label' => 'Planned', 'detail' => $plan === null ? 'Nothing planned' : $plan->title];

        if ($weather !== null && ($weather['warning'] ?? false)) {
            $factors[] = ['label' => 'Weather', 'detail' => implode(', ', $weather['reasons'] ?? ['Rough conditions'])];
        }

        return $factors;
    }

    /**
     * @return array{action: string, headline: string, reason: string, planned_workout_id: int|null}
     */
    private function result(string $action, string $headline, string $reason, ?int $plannedWorkoutId = null): array
    {
        return [
            'action' => $action,
            'headline' => $headline,
            'reason' => $reason,
            'planned_workout_id' => $plannedWorkoutId,
        ];
    }
}
