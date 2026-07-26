<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Training\RouteLeaderboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaderboardController extends Controller
{
    public function index(Request $request, RouteLeaderboardService $leaderboard): Response
    {
        return Inertia::render('leaderboard/Index', [
            'boards' => $leaderboard->boards($request->user()),
        ]);
    }
}
