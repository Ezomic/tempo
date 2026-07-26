<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Training\ZoneCalibrationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ZoneCalibrationController extends Controller
{
    public function apply(Request $request, ZoneCalibrationService $calibration): RedirectResponse
    {
        $applied = $calibration->apply($request->user(), CarbonImmutable::now());

        return $applied
            ? back()->with('status', 'Heart-rate zones recalibrated.')
            : back()->withErrors(['zones' => 'There is nothing to recalibrate right now.']);
    }
}
