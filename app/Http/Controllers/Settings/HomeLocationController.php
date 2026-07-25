<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateHomeLocationRequest;
use App\Services\Routing\HomeLocationService;
use App\Services\Routing\RouteGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeLocationController extends Controller
{
    public function edit(Request $request, RouteGenerator $generator): Response
    {
        $user = $request->user();

        return Inertia::render('settings/Routes', [
            'home' => $user->home_lat !== null && $user->home_lng !== null
                ? ['lat' => $user->home_lat, 'lng' => $user->home_lng]
                : null,
            'routingConfigured' => $generator->isConfigured(),
        ]);
    }

    public function update(UpdateHomeLocationRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

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
}
