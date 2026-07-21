<?php

declare(strict_types=1);

namespace App\Enums;

enum HrvStatus: string
{
    case Balanced = 'balanced';
    case Unbalanced = 'unbalanced';
    case Low = 'low';
    case Poor = 'poor';

    public static function fromGarmin(?string $value): ?self
    {
        return $value === null ? null : self::tryFrom(strtolower($value));
    }
}
