<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\GarminFailure;
use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

class GarminConnectException extends RuntimeException
{
    public function __construct(
        public readonly GarminFailure $reason,
        string $message = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message !== '' ? $message : $reason->name, 0, $previous);
    }

    public static function unreachable(?Throwable $previous = null): self
    {
        return new self(GarminFailure::Unreachable, 'Garmin sidecar is unreachable', $previous);
    }

    public static function fromResponse(Response $response): self
    {
        $reason = match ($response->status()) {
            401 => GarminFailure::AuthFailed,
            429 => GarminFailure::RateLimited,
            502, 503, 504 => GarminFailure::GarminUnreachable,
            default => GarminFailure::Unknown,
        };

        return new self($reason, "Garmin sidecar returned HTTP {$response->status()}");
    }

    public function userMessage(): string
    {
        return $this->reason->message();
    }
}
