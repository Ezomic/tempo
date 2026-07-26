<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Training\PublicProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PublicProfileController extends Controller
{
    public function show(string $token, PublicProfileService $profile): Response
    {
        $user = User::query()->where('public_profile_token', $token)->firstOrFail();

        return Inertia::render('public/Profile', [
            'profile' => $profile->publicData($user),
        ]);
    }

    public function enable(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->public_profile_token === null) {
            $user->forceFill(['public_profile_token' => Str::random(32)])->save();
        }

        return back()->with('status', 'Public profile enabled.');
    }

    public function disable(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['public_profile_token' => null])->save();

        return back()->with('status', 'Public profile disabled.');
    }
}
