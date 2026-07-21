<?php

declare(strict_types=1);

namespace App\DataObjects;

final readonly class LoginResult
{
    public function __construct(
        public string $status,
        public ?string $loginToken = null,
        public ?string $displayName = null,
    ) {}

    public function isMfaRequired(): bool
    {
        return $this->status === 'mfa_required';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromSidecar(array $payload): self
    {
        return new self(
            status: (string) ($payload['status'] ?? 'ok'),
            loginToken: isset($payload['login_token']) ? (string) $payload['login_token'] : null,
            displayName: isset($payload['display_name']) ? (string) $payload['display_name'] : null,
        );
    }
}
