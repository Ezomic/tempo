<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\BuildWeeklyDigestAction;
use App\Mail\WeeklyDigestMail;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class SendWeeklyDigestCommand extends Command
{
    protected $signature = 'tempo:weekly-digest {--user= : send to a single user id}';

    protected $description = 'Email each user their weekly training digest.';

    public function handle(BuildWeeklyDigestAction $builder): int
    {
        $today = CarbonImmutable::now();
        $sent = 0;

        $this->users()->each(function (User $user) use ($builder, $today, &$sent): void {
            $digest = $builder->handle($user, $today);
            Mail::to($user->email)->send(new WeeklyDigestMail($digest));
            $sent++;
        });

        $this->info("Sent {$sent} weekly digest(s).");

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, User>
     */
    private function users(): Collection
    {
        $query = User::query();

        $userId = $this->option('user');
        if ($userId !== null) {
            $query->whereKey($userId);
        }

        return $query->get();
    }
}
