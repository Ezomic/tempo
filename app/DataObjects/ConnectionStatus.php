<?php

declare(strict_types=1);

namespace App\DataObjects;

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
            displayName: isset($payload['display_name']) ? (string) $payload['display_name'] : null,
        );
    }
}
