<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Training\WellnessTrendService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WellnessController extends Controller
{
    private const RANGES = [14, 42, 90];

    public function index(Request $request, WellnessTrendService $trend): Response
    {
        $days = (int) $request->integer('days', 42);
        if (! in_array($days, self::RANGES, true)) {
            $days = 42;
        }

        return Inertia::render('wellness/Index', [
            'points' => $trend->trend($request->user(), CarbonImmutable::now(), $days),
            'days' => $days,
            'ranges' => self::RANGES,
        ]);
    }
}
