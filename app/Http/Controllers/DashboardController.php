<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Training\ReadinessService;
use App\Services\Training\TrainingLoadService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly TrainingLoadService $load,
        private readonly ReadinessService $readiness,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $today = CarbonImmutable::now();

        $load = $this->load->acuteChronic($user, $today);

        return Inertia::render('Dashboard', [
            'hasData' => $user->activities()->exists() || $user->wellnessDays()->exists(),
            'garminConnected' => $user->garminConnection?->isConnected() ?? false,
            'readiness' => $this->readiness->snapshot($user, $load['ratio']),
            'load' => $load,
            'weekly' => $this->load->weeklyBySport($user, $today, 8),
        ]);
    }
}
