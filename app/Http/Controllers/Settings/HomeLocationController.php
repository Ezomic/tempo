<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Concerns\InteractsWithCurrentUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateHomeLocationRequest;
use App\Services\Routing\HomeLocationService;
use App\Services\Routing\OrsGeocoder;
use App\Services\Routing\RouteGenerator;
use App\Support\Payload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class HomeLocationController extends Controller
{
    use InteractsWithCurrentUser;

    public function edit(Request $request, RouteGenerator $generator): Response
    {
        $user = $this->currentUser($request);

        return Inertia::render('settings/Routes', [
            'home' => $user->home_lat !== null && $user->home_lng !== null
                ? ['lat' => $user->home_lat, 'lng' => $user->home_lng]
                : null,
            'routingConfigured' => $generator->isConfigured(),
        ]);
    }

    public function update(UpdateHomeLocationRequest $request): RedirectResponse
    {
        $this->currentUser($request)->update($request->validated());

        return back()->with('status', 'Home location saved.');
    }

    public function infer(Request $request, HomeLocationService $service): JsonResponse
    {
        $home = $service->infer($request->user());

        if ($home === null) {
            return response()->json(['message' => 'No GPS history to infer a home location from yet.'], 422);
        }

        return response()->json(['lat' => $home->lat, 'lng' => $home->lng]);
    }

    public function geocode(Request $request, OrsGeocoder $geocoder): JsonResponse
    {
        if (! $geocoder->isConfigured()) {
            return response()->json(['message' => 'Address search is not configured.'], 422);
        }

        $query = trim(Payload::toStr($request->input('query')));

        if ($query === '') {
            return response()->json(['results' => []]);
        }

        try {
            $results = $geocoder->search($query);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Address lookup failed. Try again shortly.'], 502);
        }

        return response()->json(['results' => $results]);
    }
}
