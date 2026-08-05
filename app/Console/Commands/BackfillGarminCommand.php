<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\BackfillGarminJob;
use App\Jobs\RecomputeTrainingCurvesJob;
use App\Models\GarminConnection;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Bus;

class BackfillGarminCommand extends Command
{
    protected $signature = 'garmin:backfill
        {--from= : start date (Y-m-d), required}
        {--to= : end date (Y-m-d), defaults to today}
        {--user= : limit to a user id}
        {--slice=30 : days of history per queued job}';

    protected $description = 'Import Garmin history for an explicit date range, past the first-sync window';

    public function handle(): int
    {
        $from = $this->option('from');

        if ($from === null) {
            $this->error('--from is required, for example --from=2024-01-01.');

            return self::FAILURE;
        }

        $start = CarbonImmutable::parse((string) $from)->startOfDay();
        $end = CarbonImmutable::parse((string) ($this->option('to') ?? 'today'))->startOfDay();

        if ($start->greaterThan($end)) {
            $this->error('--from must be on or before --to.');

            return self::FAILURE;
        }

        $slice = max(1, (int) $this->option('slice'));
        $connections = $this->connections();

        if ($connections->isEmpty()) {
            $this->info('No connected Garmin accounts matched.');

            return self::SUCCESS;
        }

        foreach ($connections as $connection) {
            $slices = $this->slices($start, $end, $slice);

            // Chained so the curve rebuild runs once, after the last slice, and
            // so the slices themselves land in chronological order.
            Bus::chain([
                ...array_map(
                    fn (array $window): BackfillGarminJob => new BackfillGarminJob(
                        $connection,
                        $window['start'],
                        $window['end'],
                    ),
                    $slices,
                ),
                new RecomputeTrainingCurvesJob($connection),
            ])->dispatch();

            $this->info('Queued '.count($slices)." slice(s) for user {$connection->user_id}.");
        }

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, GarminConnection>
     */
    private function connections()
    {
        $query = GarminConnection::query()->where('status', GarminConnection::STATUS_CONNECTED);

        if ($this->option('user') !== null) {
            $query->where('user_id', $this->option('user'));
        }

        return $query->get();
    }

    /**
     * @return list<array{start: string, end: string}>
     */
    private function slices(CarbonImmutable $start, CarbonImmutable $end, int $days): array
    {
        $slices = [];

        for ($cursor = $start; $cursor <= $end; $cursor = $cursor->addDays($days)) {
            $slices[] = [
                'start' => $cursor->toDateString(),
                'end' => $cursor->addDays($days - 1)->min($end)->toDateString(),
            ];
        }

        return $slices;
    }
}
