<?php

declare(strict_types=1);

namespace App\Enums;

enum WorkoutType: string
{
    case Recovery = 'recovery';
    case Easy = 'easy';
    case Endurance = 'endurance';
    case Tempo = 'tempo';
    case Intervals = 'intervals';
    case Long = 'long';

    public function label(): string
    {
        return match ($this) {
            self::Recovery => 'Recovery',
            self::Easy => 'Easy',
            self::Endurance => 'Endurance',
            self::Tempo => 'Tempo',
            self::Intervals => 'Intervals',
            self::Long => 'Long',
        };
    }

    /**
     * Rough TRIMP per minute used to project future load from planned
     * sessions that have no completed activity to measure against.
     */
    public function estimatedTrimpPerMinute(): float
    {
        return match ($this) {
            self::Recovery => 0.6,
            self::Easy => 0.9,
            self::Endurance => 1.1,
            self::Long => 1.0,
            self::Tempo => 1.6,
            self::Intervals => 1.8,
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(static fn (self $t): array => [
            'value' => $t->value,
            'label' => $t->label(),
        ], self::cases());
    }
}
