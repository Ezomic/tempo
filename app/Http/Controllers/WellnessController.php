<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\LifeEvent;
use App\Models\User;
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
        $user = $request->user();
        $days = (int) $request->integer('days', 42);
        if (! in_array($days, self::RANGES, true)) {
            $days = 42;
        }

        $today = CarbonImmutable::now();
        $from = $today->subDays($days - 1)->startOfDay();

        return Inertia::render('wellness/Index', [
            'points' => $trend->trend($user, $today, $days),
            'days' => $days,
            'ranges' => self::RANGES,
            'lifeEvents' => $this->lifeEvents($user, $from, $today),
            'kinds' => LifeEvent::KINDS,
        ]);
    }

    /**
     * @return list<array{id: int, date: string, kind: string, note: string|null}>
     */
    private function lifeEvents(User $user, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return array_values($user->lifeEvents()
            ->whereBetween('date', [$from->toDateString().' 00:00:00', $to->toDateString().' 23:59:59'])
            ->orderBy('date')
            ->get()
            ->map(fn (LifeEvent $event): array => [
                'id' => $event->id,
                'date' => $event->date->toDateString(),
                'kind' => $event->kind,
                'note' => $event->note,
            ])
            ->all());
    }
}
