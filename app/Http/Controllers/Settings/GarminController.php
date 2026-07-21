<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\ConnectGarminAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\CompleteGarminMfaRequest;
use App\Http\Requests\Settings\ConnectGarminRequest;
use App\Http\Requests\Settings\UpdateHrZoneSettingsRequest;
use App\Jobs\SyncGarminJob;
use App\Models\GarminConnection;
use App\Models\HrZoneSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class GarminController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $connection = $user->garminConnection;
        $settings = $user->hrZoneSettings;

        return Inertia::render('settings/Garmin', [
            'connection' => $connection === null ? null : [
                'status' => $connection->status,
                'display_name' => $connection->garmin_display_name,
                'sync_status' => $connection->sync_status,
                'sync_error' => $connection->sync_error,
                'last_synced_at_diff' => $connection->last_synced_at?->diffForHumans(),
            ],
            'settings' => [
                'max_hr' => $settings?->max_hr,
                'resting_hr' => $settings?->resting_hr,
                'lthr' => $settings?->lthr,
                'sex' => $settings === null ? HrZoneSettings::SEX_MALE : $settings->sex,
            ],
            'stats' => [
                'activities' => $user->activities()->count(),
                'wellness_days' => $user->wellnessDays()->count(),
            ],
            'login_token' => $request->session()->get('garmin_login_token'),
        ]);
    }

    public function connect(ConnectGarminRequest $request, ConnectGarminAction $action): RedirectResponse
    {
        $connection = $this->connectionFor($request);

        try {
            $result = $action->handle($connection, $request->string('email')->value(), $request->string('password')->value());
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['email' => 'Could not sign in to Garmin. Check your email and password and try again.']);
        }

        if ($result->isMfaRequired()) {
            return back()
                ->with('garmin_login_token', $result->loginToken)
                ->with('status', 'Enter the code from your authenticator to finish connecting.');
        }

        return back()->with('status', 'Garmin account connected.');
    }

    public function mfa(CompleteGarminMfaRequest $request, ConnectGarminAction $action): RedirectResponse
    {
        $connection = $this->connectionFor($request);

        try {
            $action->completeMfa($connection, $request->string('login_token')->value(), $request->string('code')->value());
        } catch (Throwable $e) {
            report($e);

            return back()
                ->withErrors(['code' => 'Could not verify that code. Request a new one and try again.'])
                ->with('garmin_login_token', $request->string('login_token')->value());
        }

        return back()->with('status', 'Garmin account connected.');
    }

    public function sync(Request $request): RedirectResponse
    {
        $connection = $request->user()->garminConnection;

        abort_if($connection === null || ! $connection->isConnected(), 422);

        SyncGarminJob::dispatch($connection);

        return back()->with('status', 'Sync started. New activities and wellness will appear shortly.');
    }

    public function updateSettings(UpdateHrZoneSettingsRequest $request): RedirectResponse
    {
        HrZoneSettings::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $request->validated(),
        );

        return back()->with('status', 'Heart-rate settings saved.');
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $request->user()->garminConnection?->delete();

        return back()->with('status', 'Garmin account disconnected.');
    }

    private function connectionFor(Request $request): GarminConnection
    {
        return GarminConnection::query()->firstOrCreate(['user_id' => $request->user()->id]);
    }
}
