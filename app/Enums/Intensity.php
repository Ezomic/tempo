<?php

declare(strict_types=1);

namespace App\Enums;

enum Intensity: string
{
    case Recovery = 'recovery';
    case Easy = 'easy';
    case Steady = 'steady';
    case Hard = 'hard';
    case Max = 'max';

    public function label(): string
    {
        return match ($this) {
            self::Recovery => 'Recovery',
            self::Easy => 'Easy',
            self::Steady => 'Steady',
            self::Hard => 'Hard',
            self::Max => 'Max',
        };
    }

    public function zone(): int
    {
        return match ($this) {
            self::Recovery => 1,
            self::Easy => 2,
            self::Steady => 3,
            self::Hard => 4,
            self::Max => 5,
        };
    }

    public function hrPercent(): string
    {
        return match ($this) {
            self::Recovery => '50-60%',
            self::Easy => '60-70%',
            self::Steady => '70-80%',
            self::Hard => '80-90%',
            self::Max => '90-100%',
        };
    }

    public function feel(): string
    {
        return match ($this) {
            self::Recovery => 'very easy, could sing',
            self::Easy => 'conversational',
            self::Steady => 'short sentences only',
            self::Hard => 'a few words only',
            self::Max => 'cannot talk',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Recovery => 'slate',
            self::Easy => 'sky',
            self::Steady => 'emerald',
            self::Hard => 'amber',
            self::Max => 'red',
        };
    }

    /**
     * @return array<int, array{value: string, label: string, zone: int, hr_percent: string, feel: string, color: string}>
     */
    public static function options(): array
    {
        return array_map(static fn (self $i): array => [
            'value' => $i->value,
            'label' => $i->label(),
            'zone' => $i->zone(),
            'hr_percent' => $i->hrPercent(),
            'feel' => $i->feel(),
            'color' => $i->color(),
        ], self::cases());
    }
}
