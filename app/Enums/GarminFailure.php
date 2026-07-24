<?php

declare(strict_types=1);

namespace App\Enums;

enum GarminFailure
{
    case Unreachable;
    case AuthFailed;
    case RateLimited;
    case GarminUnreachable;
    case Unknown;

    public function message(): string
    {
        return match ($this) {
            self::Unreachable => 'The Garmin sync service is not reachable right now. Please try again in a moment.',
            self::AuthFailed => 'Garmin did not accept that email and password. Double-check them and try again.',
            self::RateLimited => 'Garmin is temporarily limiting sign-ins. Please wait a few minutes and try again.',
            self::GarminUnreachable => 'Garmin could not be reached right now. Please try again shortly.',
            self::Unknown => 'The sync service could not sign in to Garmin. Please try again shortly.',
        };
    }
}
