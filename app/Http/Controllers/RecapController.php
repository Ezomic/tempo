<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Concerns\InteractsWithCurrentUser;
use App\Services\Training\ConsistencyService;
use App\Services\Training\TrainingRecapService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecapController extends Controller
{
    use InteractsWithCurrentUser;

    public function index(Request $request, TrainingRecapService $recap, ConsistencyService $consistency): Response
    {
        $period = $request->string('period')->toString() === 'year' ? 'year' : 'month';
        $today = CarbonImmutable::now();
        $from = $period === 'year' ? $today->startOfYear() : $today->startOfMonth();

        return Inertia::render('recap/Index', [
            'recap' => $recap->recap($request->user(), $from, $today),
            'period' => $period,
            'range' => ['from' => $from->toDateString(), 'to' => $today->toDateString()],
            'consistency' => $consistency->heatmap($request->user(), $today),
        ]);
    }
}
