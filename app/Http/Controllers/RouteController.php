<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DataObjects\GeoPoint;
use App\Enums\WorkoutType;
use App\Http\Requests\SaveRouteRequest;
use App\Models\PlannedWorkout;
use App\Services\Routing\PaceEstimator;
use App\Services\Routing\RouteGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class RouteController extends Controller
{
    public function suggest(Request $request, PlannedWorkout $plannedWorkout, RouteGenerator $generator, PaceEstimator $pace): JsonResponse
    {
        abort_unless($plannedWorkout->user_id === $request->user()->id, 403);

        if (! $generator->isConfigured()) {
            return response()->json(['message' => 'Route generation is not configured.'], 422);
        }

        $user = $request->user();

        if ($user->home_lat === null || $user->home_lng === null) {
            return response()->json(['message' => 'Set your home location in settings first.'], 422);
        }

        $start = new GeoPoint($user->home_lat, $user->home_lng);
        $meters = $pace->metersFor($user, $plannedWorkout->sport, $plannedWorkout->duration_min ?? 45);
        $mode = $request->string('mode')->value() ?: $this->defaultMode($plannedWorkout);

        try {
            $route = $mode === 'intervals'
                ? $generator->flatOutAndBack($start, $meters, $plannedWorkout->sport)
                : $generator->loop($start, $meters, $plannedWorkout->sport, $request->integer('seed') ?: random_int(1, 999999));
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Could not generate a route right now. Try again shortly.'], 502);
        }

        return response()->json($route->toArray() + ['mode' => $mode]);
    }

    public function save(SaveRouteRequest $request, PlannedWorkout $plannedWorkout): JsonResponse
    {
        abort_unless($plannedWorkout->user_id === $request->user()->id, 403);

        $data = $request->validated();

        $plannedWorkout->update([
            'route_geometry' => $data['coordinates'],
            'route_distance_m' => $data['distance_m'],
            'route_ascent_m' => $data['ascent_m'],
            'route_kind' => $data['kind'],
        ]);

        return response()->json(['status' => 'saved']);
    }

    private function defaultMode(PlannedWorkout $workout): string
    {
        return $workout->workout_type === WorkoutType::Intervals ? 'intervals' : 'loop';
    }
}
