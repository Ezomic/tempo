<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Concerns\InteractsWithCurrentUser;
use App\Enums\GoalType;
use App\Enums\Sport;
use App\Http\Requests\PlanPacingRequest;
use App\Models\MeanMaxEffort;
use App\Models\TrainingGoal;
use App\Models\User;
use App\Services\Routing\GpxParser;
use App\Services\Training\PacingPlanService;
use App\Services\Training\RacePredictorService;
use App\Services\Weather\WeatherForecaster;
use App\Support\Payload;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PacingController extends Controller
{
    use InteractsWithCurrentUser;

    public function index(Request $request, RacePredictorService $predictor): Response
    {
        return Inertia::render('pacing/Index', [
            'raceGoals' => $this->raceGoals($this->currentUser($request), $predictor),
            'plan' => null,
        ]);
    }

    public function plan(
        PlanPacingRequest $request,
        GpxParser $parser,
        PacingPlanService $pacing,
        RacePredictorService $predictor,
        WeatherForecaster $forecaster,
    ): Response {
        $user = $this->currentUser($request);

        try {
            $profile = $parser->parse((string) $request->file('gpx')->get());
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['gpx' => $e->getMessage()]);
        }

        $splitMeters = (int) round((Payload::toFloat($request->validated('split_km'))) * 1000);
        $weather = $this->weatherFor($user, $request->date('race_date'), $forecaster);

        $plan = $pacing->plan(
            $profile,
            Payload::toInt($request->validated('target_seconds')),
            $splitMeters,
            $weather['temp'],
            $weather['wind'],
        );

        return Inertia::render('pacing/Index', [
            'raceGoals' => $this->raceGoals($user, $predictor),
            'plan' => $plan + ['weather' => $weather],
        ]);
    }

    /**
     * @return array{temp: float|null, wind: float|null, applied: bool}
     */
    private function weatherFor(User $user, ?CarbonInterface $raceDate, WeatherForecaster $forecaster): array
    {
        $none = ['temp' => null, 'wind' => null, 'applied' => false];
        $horizon = Payload::toInt(config('training.weather.horizon_days'));
        $today = CarbonImmutable::now();

        if ($raceDate === null || $user->home_lat === null || $user->home_lng === null) {
            return $none;
        }
        if ($raceDate->lessThan($today->startOfDay()) || $raceDate->greaterThan($today->addDays($horizon))) {
            return $none;
        }

        $daily = $forecaster->daily($user->home_lat, $user->home_lng, $raceDate->toDateString(), $raceDate->toDateString());
        $day = $daily[$raceDate->toDateString()] ?? null;
        if ($day === null) {
            return $none;
        }

        return ['temp' => $day['temp_max'], 'wind' => $day['wind_max'], 'applied' => true];
    }

    /**
     * @return list<array{id: int, distance_m: int, target_date: string, predicted_seconds: int|null}>
     */
    private function raceGoals(User $user, RacePredictorService $predictor): array
    {
        $meanMax = $user->meanMaxEfforts()
            ->where('sport', Sport::Run)
            ->get(['duration_s', 'speed_mps'])
            ->mapWithKeys(fn (MeanMaxEffort $e): array => [$e->duration_s => $e->speed_mps])
            ->all();

        $currentCtl = (float) ($user->dailyLoadMetrics()->orderByDesc('date')->value('ctl') ?? 0.0);

        return array_values($user->trainingGoals()
            ->where('type', GoalType::RaceTime)
            ->whereNotNull('distance_m')
            ->orderBy('target_date')
            ->get()
            ->map(fn (TrainingGoal $goal): array => [
                'id' => $goal->id,
                'distance_m' => (int) $goal->distance_m,
                'target_date' => $goal->target_date->toDateString(),
                'predicted_seconds' => $predictor->predictOne($meanMax, (int) $goal->distance_m, $currentCtl),
            ])
            ->all());
    }
}
