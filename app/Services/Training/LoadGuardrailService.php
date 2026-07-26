<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Models\User;
use Carbon\CarbonImmutable;

class LoadGuardrailService
{
    private const UNKNOWN = 'unknown';

    private const SAFE = 'safe';

    private const CAUTION = 'caution';

    private const DANGER = 'danger';

    public function __construct(private readonly TrainingLoadService $load) {}

    /**
     * Injury-risk guardrails from the acute:chronic ratio and the week-over-week
     * ramp, banded against the configured thresholds.
     *
     * @return array{acwr: float|null, acwr_band: string, ramp_pct: float|null, ramp_band: string, status: string, message: string}
     */
    public function guardrails(User $user, CarbonImmutable $today): array
    {
        $acwr = $this->load->acuteChronic($user, $today)['ratio'];
        $ramp = $this->load->weeklyRamp($user, $today);

        $acwrBand = $this->acwrBand($acwr);
        $rampBand = $this->rampBand($ramp);
        $status = $this->worst($acwrBand, $rampBand);

        return [
            'acwr' => $acwr,
            'acwr_band' => $acwrBand,
            'ramp_pct' => $ramp,
            'ramp_band' => $rampBand,
            'status' => $status,
            'message' => $this->message($status, $acwr, $ramp),
        ];
    }

    private function acwrBand(?float $acwr): string
    {
        if ($acwr === null) {
            return self::UNKNOWN;
        }

        return match (true) {
            $acwr > (float) config('training.acwr.danger') => self::DANGER,
            $acwr > (float) config('training.acwr.safe_max') => self::CAUTION,
            $acwr < (float) config('training.acwr.safe_min') => self::CAUTION,
            default => self::SAFE,
        };
    }

    private function rampBand(?float $ramp): string
    {
        if ($ramp === null) {
            return self::UNKNOWN;
        }

        return match (true) {
            $ramp >= (float) config('training.ramp.danger') => self::DANGER,
            $ramp >= (float) config('training.ramp.caution') => self::CAUTION,
            default => self::SAFE,
        };
    }

    private function worst(string $a, string $b): string
    {
        $rank = [self::UNKNOWN => 0, self::SAFE => 1, self::CAUTION => 2, self::DANGER => 3];

        return $rank[$a] >= $rank[$b] ? $a : $b;
    }

    private function message(string $status, ?float $acwr, ?float $ramp): string
    {
        if ($status === self::UNKNOWN) {
            return 'Not enough training history yet to judge your load.';
        }

        if ($status === self::SAFE) {
            return 'Load is progressing safely.';
        }

        $detraining = $acwr !== null && $acwr < (float) config('training.acwr.safe_min');
        if ($detraining && $status === self::CAUTION) {
            return 'Your load has dropped off. Ease back in gradually rather than jumping straight to hard sessions.';
        }

        if ($status === self::DANGER) {
            return 'Load is spiking. Back off for a day or two to stay ahead of injury.';
        }

        return 'You are ramping up quickly. Hold this week steady rather than adding more.';
    }
}
