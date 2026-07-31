<?php

declare(strict_types=1);

namespace App\DataObjects;

use App\Support\Payload;

final readonly class ConnectionStatus
{
    public function __construct(
        public bool $connected,
        public ?string $displayName = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromSidecar(array $payload): self
    {
        return new self(
            connected: (bool) ($payload['connected'] ?? false),
            displayName: isset($payload['display_name']) ? Payload::toStr($payload)['display_name'] : null,
        );
    }
}
