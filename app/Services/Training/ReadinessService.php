<?php

declare(strict_types=1);

namespace App\Services\Training;

use App\Enums\HrvStatus;
use App\Models\User;
use App\Models\WellnessDay;

class ReadinessService
{
    private const READY = 'ready';

    private const CAUTION = 'caution';

    private const REST = 'rest';

    /**
     * Today-ish readiness from the most recent wellness day, tempered by how
     * hard recent training has been (the acute:chronic ratio).
     *
     * @return array{verdict: string, hrv_status: string|null, body_battery: int|null, resting_hr: int|null, date: string}|null
     */
    public function snapshot(User $user, ?float $acwr): ?array
    {
        $day = $user->wellnessDays()->orderByDesc('date')->first();

        if ($day === null) {
            return null;
        }

        return [
            'verdict' => $this->verdict($day, $acwr),
            'hrv_status' => $day->hrv_status?->value,
            'body_battery' => $day->body_battery_high,
            'resting_hr' => $day->resting_hr,
            'date' => $day->date->toDateString(),
        ];
    }

    private function verdict(WellnessDay $day, ?float $acwr): string
    {
        $levels = [
            $this->fromHrv($day->hrv_status),
            $this->fromBodyBattery($day->body_battery_high),
            $this->fromLoad($acwr),
        ];

        return max($levels) === 2 ? self::REST : (max($levels) === 1 ? self::CAUTION : self::READY);
    }

    private function fromHrv(?HrvStatus $status): int
    {
        return match ($status) {
            HrvStatus::Poor => 2,
            HrvStatus::Low, HrvStatus::Unbalanced => 1,
            default => 0,
        };
    }

    private function fromBodyBattery(?int $high): int
    {
        return $high !== null && $high < 25 ? 1 : 0;
    }

    private function fromLoad(?float $acwr): int
    {
        return match (true) {
            $acwr === null => 0,
            $acwr > 1.5 => 2,
            $acwr > 1.3 => 1,
            default => 0,
        };
    }
}
