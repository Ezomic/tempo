<?php

declare(strict_types=1);

namespace App\Services\Routing;

use App\DataObjects\GeoPoint;
use App\Models\Activity;
use App\Models\User;
use App\Support\Payload;
use Illuminate\Support\Facades\Storage;

class HomeLocationService
{
    /**
     * Best guess at "home": the median start point of recent activities. Most
     * sessions start from home, so the median shrugs off the occasional away run.
     */
    public function infer(User $user): ?GeoPoint
    {
        $activities = $user->activities()
            ->whereNotNull('streams_path')
            ->orderByDesc('started_at')
            ->limit(30)
            ->get(['id', 'streams_path']);

        $lats = [];
        $lngs = [];

        foreach ($activities as $activity) {
            $point = $this->startPoint($activity);
            if ($point !== null) {
                $lats[] = $point->lat;
                $lngs[] = $point->lng;
            }
        }

        if ($lats === []) {
            return null;
        }

        return new GeoPoint($this->median($lats), $this->median($lngs));
    }

    private function startPoint(Activity $activity): ?GeoPoint
    {
        if ($activity->streams_path === null || ! Storage::disk('local')->exists($activity->streams_path)) {
            return null;
        }

        $decoded = json_decode((string) Storage::disk('local')->get($activity->streams_path), true);
        $lat = Payload::arr($decoded, 'lat')[0] ?? null;
        $lng = Payload::arr($decoded, 'lng')[0] ?? null;

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        return new GeoPoint((float) $lat, (float) $lng);
    }

    /**
     * @param  list<float>  $values
     */
    private function median(array $values): float
    {
        sort($values);
        $count = count($values);
        $mid = intdiv($count, 2);

        return $count % 2 === 1
            ? $values[$mid]
            : ($values[$mid - 1] + $values[$mid]) / 2;
    }
}
