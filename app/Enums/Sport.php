<?php

declare(strict_types=1);

namespace App\Enums;

enum Sport: string
{
    case Run = 'run';
    case Bike = 'bike';
    case Other = 'other';

    public static function fromGarminTypeKey(?string $typeKey): self
    {
        $key = strtolower((string) $typeKey);

        return match (true) {
            str_contains($key, 'run') => self::Run,
            str_contains($key, 'cycl'), str_contains($key, 'bik'), str_contains($key, 'ride') => self::Bike,
            default => self::Other,
        };
    }
}
