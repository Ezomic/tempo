<?php

declare(strict_types=1);

namespace App\Services\Routing;

use App\Enums\Sport;
use App\Models\User;

class PaceEstimator
{
    /** Fallback speeds (m/s) when the user has no history for the sport. */
    private const DEFAULTS = [
        'run' => 3.0,   // ~5:33 /km
        'bike' => 6.5,  // ~23 km/h
        'other' => 3.0,
    ];

    public function metersFor(User $user, Sport $sport, int $durationMin): int
    {
        return (int) round($durationMin * 60 * $this->speed($user, $sport));
    }

    public function speed(User $user, Sport $sport): float
    {
        $avg = $user->activities()
            ->where('sport', $sport->value)
            ->where('avg_speed_mps', '>', 0)
            ->orderByDesc('started_at')
            ->limit(20)
            ->avg('avg_speed_mps');

        return $avg !== null && (float) $avg > 0
            ? (float) $avg
            : self::DEFAULTS[$sport->value];
    }
}
