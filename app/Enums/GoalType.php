<?php

declare(strict_types=1);

namespace App\Enums;

enum GoalType: string
{
    case Ctl = 'ctl';
    case RaceTime = 'race_time';

    public function label(): string
    {
        return match ($this) {
            self::Ctl => 'Fitness (CTL)',
            self::RaceTime => 'Race time',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $t): array => ['value' => $t->value, 'label' => $t->label()],
            self::cases(),
        );
    }
}
