<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\PlannedWorkout;
use App\Services\Chronos\ChronosClient;

class PushPlannedWorkoutAction
{
    public function __construct(
        private readonly ChronosClient $chronos,
    ) {}

    public function handle(PlannedWorkout $workout): void
    {
        $event = $this->chronos->createAllDayEvent(
            title: $this->title($workout),
            date: $workout->date->toDateString(),
            description: $workout->notes,
        );

        $workout->update([
            'chronos_event_id' => $event['id'],
            'chronos_url' => $event['url'],
            'pushed_at' => now(),
        ]);
    }

    private function title(PlannedWorkout $workout): string
    {
        $sport = ucfirst($workout->sport->value);
        $title = "{$sport}: {$workout->title}";

        return $workout->duration_min !== null
            ? "{$title} ({$workout->duration_min} min)"
            : $title;
    }
}
