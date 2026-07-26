<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Training\TrainingRecapService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecapController extends Controller
{
    public function index(Request $request, TrainingRecapService $recap): Response
    {
        $period = $request->string('period')->toString() === 'year' ? 'year' : 'month';
        $today = CarbonImmutable::now();
        $from = $period === 'year' ? $today->startOfYear() : $today->startOfMonth();

        return Inertia::render('recap/Index', [
            'recap' => $recap->recap($request->user(), $from, $today),
            'period' => $period,
            'range' => ['from' => $from->toDateString(), 'to' => $today->toDateString()],
        ]);
    }
}
