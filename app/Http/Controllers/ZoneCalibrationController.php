<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Concerns\InteractsWithCurrentUser;
use App\Services\Training\ZoneCalibrationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ZoneCalibrationController extends Controller
{
    use InteractsWithCurrentUser;

    public function apply(Request $request, ZoneCalibrationService $calibration): RedirectResponse
    {
        $applied = $calibration->apply($this->currentUser($request), CarbonImmutable::now());

        return $applied
            ? back()->with('status', 'Heart-rate zones recalibrated.')
            : back()->withErrors(['zones' => 'There is nothing to recalibrate right now.']);
    }
}
