<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Concerns\InteractsWithCurrentUser;
use App\Enums\Sport;
use App\Models\MeanMaxEffort;
use App\Models\PersonalRecord;
use App\Models\User;
use App\Services\Training\ThresholdTrendService;
use App\Support\Payload;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecordsController extends Controller
{
    use InteractsWithCurrentUser;

    public function index(Request $request, ThresholdTrendService $threshold): Response
    {
        $user = $this->currentUser($request);
        $sport = $request->string('sport')->toString() === 'bike' ? Sport::Bike : Sport::Run;

        return Inertia::render('records/Index', [
            'records' => $this->records($user),
            'meanMax' => $this->meanMax($user, $sport),
            'sport' => $sport->value,
            'availableSports' => $this->availableSports($user),
            'fitnessMarkers' => $threshold->trend($user, CarbonImmutable::now(), 16),
            'shareToken' => $user->public_profile_token,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function records(User $user): array
    {
        $recentCutoff = CarbonImmutable::now()->subDays(14);

        return array_values($user->personalRecords()
            ->with('activity:id,raw_summary')
            ->orderBy('distance_m')
            ->get()
            ->map(fn (PersonalRecord $record): array => [
                'distance_m' => $record->distance_m,
                'distance_label' => $this->distanceLabel($record->distance_m),
                'duration_s' => $record->duration_s,
                'time' => $this->formatTime($record->duration_s),
                'pace' => $this->pacePerKm($record->distance_m / $record->duration_s),
                'achieved_on' => $record->achieved_on->toDateString(),
                'activity_id' => $record->activity_id,
                'is_recent' => $record->achieved_on->greaterThanOrEqualTo($recentCutoff),
            ])
            ->all());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function meanMax(User $user, Sport $sport): array
    {
        return array_values($user->meanMaxEfforts()
            ->where('sport', $sport->value)
            ->orderBy('duration_s')
            ->get()
            ->map(fn (MeanMaxEffort $effort): array => [
                'duration_s' => $effort->duration_s,
                'duration_label' => $this->durationLabel($effort->duration_s),
                'speed_mps' => round($effort->speed_mps, 2),
                'pace' => $this->pacePerKm($effort->speed_mps),
            ])
            ->all());
    }

    /**
     * @return list<string>
     */
    private function availableSports(User $user): array
    {
        return array_values($user->meanMaxEfforts()
            ->select('sport')
            ->distinct()
            ->pluck('sport')
            ->map(fn (mixed $sport): string => $sport instanceof Sport ? $sport->value : Payload::toStr($sport))
            ->all());
    }

    private function distanceLabel(int $metres): string
    {
        return match ($metres) {
            1000 => '1K',
            5000 => '5K',
            10000 => '10K',
            21097 => 'Half marathon',
            42195 => 'Marathon',
            default => number_format($metres / 1000, 1).'K',
        };
    }

    private function durationLabel(int $seconds): string
    {
        return $seconds < 60 ? "{$seconds}s" : ($seconds / 60).'min';
    }

    private function formatTime(int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        return $h > 0
            ? sprintf('%d:%02d:%02d', $h, $m, $s)
            : sprintf('%d:%02d', $m, $s);
    }

    private function pacePerKm(float $speedMps): string
    {
        if ($speedMps <= 0) {
            return '—';
        }

        $secondsPerKm = (int) round(1000 / $speedMps);

        return sprintf('%d:%02d/km', intdiv($secondsPerKm, 60), $secondsPerKm % 60);
    }
}
