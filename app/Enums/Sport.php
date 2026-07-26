<?php

declare(strict_types=1);

namespace App\Enums;

enum Sport: string
{
    case Run = 'run';
    case Bike = 'bike';
    case Swim = 'swim';
    case Strength = 'strength';
    case Hike = 'hike';
    case Other = 'other';

    public static function fromGarminTypeKey(?string $typeKey): self
    {
        $key = strtolower((string) $typeKey);

        return match (true) {
            str_contains($key, 'run') => self::Run,
            str_contains($key, 'cycl'), str_contains($key, 'bik'), str_contains($key, 'ride') => self::Bike,
            str_contains($key, 'swim') => self::Swim,
            str_contains($key, 'strength'), str_contains($key, 'gym'), str_contains($key, 'weight') => self::Strength,
            str_contains($key, 'hik'), str_contains($key, 'walk'), str_contains($key, 'trek') => self::Hike,
            default => self::Other,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Run => 'Run',
            self::Bike => 'Bike',
            self::Swim => 'Swim',
            self::Strength => 'Strength',
            self::Hike => 'Hike',
            self::Other => 'Other',
        };
    }

    /**
     * Whether the sport happens outdoors, so weather is relevant to it.
     */
    public function isOutdoor(): bool
    {
        return match ($this) {
            self::Run, self::Bike, self::Hike => true,
            self::Swim, self::Strength, self::Other => false,
        };
    }
}
