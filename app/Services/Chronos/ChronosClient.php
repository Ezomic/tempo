<?php

declare(strict_types=1);

namespace App\Services\Chronos;

use App\Support\Payload;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

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

        $response = Http::baseUrl($this->requireBaseUrl())
            ->withToken($this->requireToken())
            ->acceptJson()
            ->timeout(15)
            ->post('/events', $payload)
            ->throw();

        $json = $response->json();

        return [
            'id' => Payload::str($json, 'id'),
            'url' => Payload::nullableStr($json, 'url'),
        ];
    }

    /**
     * Update an existing all-day event in place (used when a planned workout
     * moves or its prescription changes).
     *
     * @return array{id: string, url: string|null}
     */
    public function updateAllDayEvent(string $eventId, string $title, string $date, ?string $description): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Chronos integration is not configured.');
        }

        $response = Http::baseUrl($this->requireBaseUrl())
            ->withToken($this->requireToken())
            ->acceptJson()
            ->timeout(15)
            ->patch("/events/{$eventId}", [
                'title' => $title,
                'all_day' => true,
                'starts_at' => $date,
                'ends_at' => $date,
                'description' => $description,
            ])
            ->throw();

        $json = $response->json();

        return [
            'id' => Payload::nullableStr($json, 'id') ?? $eventId,
            'url' => Payload::nullableStr($json, 'url'),
        ];
    }

    public function deleteEvent(string $eventId): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Chronos integration is not configured.');
        }

        Http::baseUrl($this->requireBaseUrl())
            ->withToken($this->requireToken())
            ->acceptJson()
            ->timeout(15)
            ->delete("/events/{$eventId}")
            ->throw();
    }

    /**
     * Dates (Y-m-d) the athlete is heavily booked on, so scheduling can avoid
     * them. Returns an empty list when chronos is not configured or the call
     * fails, so planning degrades gracefully.
     *
     * @return list<string>
     */
    public function busyDays(string $from, string $to): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        try {
            $response = Http::baseUrl($this->requireBaseUrl())
                ->withToken($this->requireToken())
                ->acceptJson()
                ->timeout(15)
                ->get('/free-busy', ['from' => $from, 'to' => $to])
                ->throw();

            $dates = $response->json('busy_dates');
        } catch (Throwable) {
            return [];
        }

        return is_array($dates)
            ? array_values(array_filter($dates, 'is_string'))
            : [];
    }

    /**
     * isConfigured() guards every caller, but PHPStan can't carry that
     * narrowing across the call, so the accessors restate it.
     */
    private function requireBaseUrl(): string
    {
        if ($this->baseUrl === null || $this->baseUrl === '') {
            throw new RuntimeException('Chronos integration is not configured.');
        }

        return $this->baseUrl;
    }

    private function requireToken(): string
    {
        if ($this->token === null || $this->token === '') {
            throw new RuntimeException('Chronos integration is not configured.');
        }

        return $this->token;
    }
}
