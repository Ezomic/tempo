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
