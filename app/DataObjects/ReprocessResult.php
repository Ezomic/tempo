<?php

declare(strict_types=1);

namespace App\DataObjects;

final readonly class ReprocessResult
{
    /**
     * @param  list<array{id: int, reason: string}>  $failures
     */
    public function __construct(
        public int $processed,
        public int $updated,
        public int $skipped,
        public array $failures,
    ) {}

    public function failed(): int
    {
        return count($this->failures);
    }
}
