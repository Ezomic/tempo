<?php

declare(strict_types=1);

namespace App\Services\Routing;

use App\Support\Payload;
use Illuminate\Support\Facades\Http;

final readonly class OrsGeocoder
{
    public function __construct(
        private ?string $baseUrl,
        private ?string $key,
        private int $timeout = 15,
    ) {}

    public function isConfigured(): bool
    {
        return $this->baseUrl !== null && $this->baseUrl !== ''
            && $this->key !== null && $this->key !== '';
    }

    /**
     * @return array<int, array{label: string, lat: float, lng: float}>
     */
    public function search(string $query, int $limit = 5): array
    {
        $response = Http::baseUrl((string) $this->baseUrl)
            ->timeout($this->timeout)
            ->get('/geocode/search', [
                'api_key' => $this->key,
                'text' => $query,
                'size' => $limit,
            ])
            ->throw();

        $features = Payload::arr($response->json(), 'features');

        return array_values(array_filter(array_map(
            static fn (mixed $feature): array => [
                'label' => Payload::str($feature, 'properties', 'label'),
                'lat' => Payload::float($feature, 'geometry', 'coordinates', 1),
                'lng' => Payload::float($feature, 'geometry', 'coordinates', 0),
            ],
            $features,
        ), static fn (array $r): bool => $r['label'] !== ''));
    }
}
