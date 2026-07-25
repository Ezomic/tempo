<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Http;

it('returns geocoding results for an address', function () {
    config(['services.ors.key' => 'k']);
    Http::fake(['*' => Http::response([
        'features' => [
            ['properties' => ['label' => 'Domplein, Utrecht'], 'geometry' => ['coordinates' => [5.121, 52.09]]],
            ['properties' => ['label' => 'Dam, Amsterdam'], 'geometry' => ['coordinates' => [4.89, 52.37]]],
        ],
    ], 200)]);

    $this->actingAs(User::factory()->create())
        ->postJson('/settings/routes/geocode', ['query' => 'domplein'])
        ->assertOk()
        ->assertJsonPath('results.0.label', 'Domplein, Utrecht')
        ->assertJsonPath('results.0.lat', 52.09)
        ->assertJsonPath('results.0.lng', 5.121);
});

it('refuses geocoding when routing is not configured', function () {
    config(['services.ors.key' => null]);

    $this->actingAs(User::factory()->create())
        ->postJson('/settings/routes/geocode', ['query' => 'x'])
        ->assertStatus(422);
});

it('returns empty results for a blank query', function () {
    config(['services.ors.key' => 'k']);

    $this->actingAs(User::factory()->create())
        ->postJson('/settings/routes/geocode', ['query' => '  '])
        ->assertOk()
        ->assertJsonPath('results', []);
});
