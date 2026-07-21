<?php

declare(strict_types=1);

namespace App\Services\Chronos;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final readonly class ChronosClient
{
    public function __construct(
        private ?string $baseUrl,
        private ?string $token,
    ) {}

    public function isConfigured(): bool
    {
        return $this->baseUrl !== null && $this->baseUrl !== ''
            && $this->token !== null && $this->token !== '';
    }

    /**
     * Create an all-day event in chronos for a planned workout.
     *
     * @param  array{app: string, type: string, id: string, url: string}|null  $source
     * @return array{id: string, url: string|null}
     */
    public function createAllDayEvent(string $title, string $date, ?string $description, ?array $source = null): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Chronos integration is not configured.');
        }

        $payload = [
            'title' => $title,
            'all_day' => true,
            'starts_at' => $date,
            'ends_at' => $date,
            'description' => $description,
        ];

        if ($source !== null) {
            $payload['source'] = $source;
        }

        $response = Http::baseUrl($this->baseUrl)
            ->withToken($this->token)
            ->acceptJson()
            ->timeout(15)
            ->post('/events', $payload)
            ->throw();

        $json = $response->json();

        return [
            'id' => (string) ($json['id'] ?? ''),
            'url' => isset($json['url']) ? (string) $json['url'] : null,
        ];
    }
}
