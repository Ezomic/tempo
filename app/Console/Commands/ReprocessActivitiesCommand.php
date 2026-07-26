<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ReprocessActivitiesAction;
use App\Models\Activity;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class ReprocessActivitiesCommand extends Command
{
    protected $signature = 'tempo:reprocess
        {--activity= : reprocess a single activity id}
        {--user= : limit to a user id}
        {--from= : start date (Y-m-d)}
        {--to= : end date (Y-m-d)}
        {--all : reprocess every archived activity}';

    protected $description = 'Re-parse archived .fit files and recompute derived metrics.';

    public function handle(ReprocessActivitiesAction $action): int
    {
        $activity = $this->option('activity');
        $from = $this->option('from');
        $to = $this->option('to');

        if ($activity === null && $from === null && $to === null && ! $this->option('all')) {
            $this->error('Specify a scope: --activity, --from/--to, or --all.');

            return self::FAILURE;
        }

        $query = Activity::query()->whereNotNull('fit_path');

        if ($activity !== null) {
            $query->whereKey($activity);
        }
        if ($this->option('user') !== null) {
            $query->where('user_id', $this->option('user'));
        }
        if ($from !== null) {
            $query->where('started_at', '>=', CarbonImmutable::parse((string) $from)->startOfDay());
        }
        if ($to !== null) {
            $query->where('started_at', '<=', CarbonImmutable::parse((string) $to)->endOfDay());
        }

        $activities = $query->orderBy('started_at')->get();

        if ($activities->isEmpty()) {
            $this->info('No archived activities matched.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($activities->count());
        $result = $action->handle($activities, function () use ($bar): void {
            $bar->advance();
        });
        $bar->finish();
        $this->newLine(2);

        $this->info("Processed {$result->processed}: {$result->updated} updated, {$result->skipped} skipped, {$result->failed()} failed.");

        foreach ($result->failures as $failure) {
            $this->warn("  activity {$failure['id']}: {$failure['reason']}");
        }

        return self::SUCCESS;
    }
}
