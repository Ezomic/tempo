<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ActivityController extends Controller
{
    public function index(Request $request): Response
    {
        $activities = $request->user()->activities()
            ->latest('started_at')
            ->paginate(20)
            ->through(fn (Activity $activity): array => [
                'id' => $activity->id,
                'sport' => $activity->sport->value,
                'started_at' => $activity->started_at->toIso8601String(),
                'distance_m' => $activity->distance_m,
                'duration_s' => $activity->duration_s,
                'avg_hr' => $activity->avg_hr,
                'trimp' => $activity->trimp,
            ]);

        return Inertia::render('activities/Index', ['activities' => $activities]);
    }

    public function show(Request $request, Activity $activity): Response
    {
        abort_unless($activity->user_id === $request->user()->id, 403);

        return Inertia::render('activities/Show', [
            'activity' => [
                'id' => $activity->id,
                'sport' => $activity->sport->value,
                'sub_sport' => $activity->sub_sport,
                'started_at' => $activity->started_at->toIso8601String(),
                'duration_s' => $activity->duration_s,
                'moving_time_s' => $activity->moving_time_s,
                'distance_m' => $activity->distance_m,
                'avg_hr' => $activity->avg_hr,
                'max_hr' => $activity->max_hr,
                'elevation_gain_m' => $activity->elevation_gain_m,
                'avg_speed_mps' => $activity->avg_speed_mps,
                'calories' => $activity->calories,
                'trimp' => $activity->trimp,
                'hr_zone_seconds' => $activity->hr_zone_seconds,
            ],
            'streams' => $this->streams($activity),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function streams(Activity $activity): ?array
    {
        if ($activity->streams_path === null || ! Storage::disk('local')->exists($activity->streams_path)) {
            return null;
        }

        $decoded = json_decode((string) Storage::disk('local')->get($activity->streams_path), true);

        return is_array($decoded) ? $decoded : null;
    }
}
