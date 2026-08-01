<?php

declare(strict_types=1);

use App\DataObjects\ConnectionStatus;
use App\DataObjects\LoginResult;

it('reads the mfa login token out of a sidecar login response', function () {
    $result = LoginResult::fromSidecar([
        'status' => 'mfa_required',
        'login_token' => 'tok-123',
    ]);

    expect($result->status)->toBe('mfa_required')
        ->and($result->isMfaRequired())->toBeTrue()
        ->and($result->loginToken)->toBe('tok-123');
});

it('reads the display name out of a sidecar login response', function () {
    $result = LoginResult::fromSidecar([
        'status' => 'ok',
        'display_name' => 'Robbin',
    ]);

    expect($result->displayName)->toBe('Robbin')
        ->and($result->loginToken)->toBeNull();
});

it('reads the display name out of a sidecar status response', function () {
    $status = ConnectionStatus::fromSidecar([
        'connected' => true,
        'display_name' => 'Robbin',
    ]);

    expect($status->connected)->toBeTrue()
        ->and($status->displayName)->toBe('Robbin');
});

it('leaves optional sidecar fields null when absent', function () {
    $status = ConnectionStatus::fromSidecar(['connected' => false]);

    expect($status->connected)->toBeFalse()
        ->and($status->displayName)->toBeNull();
});
