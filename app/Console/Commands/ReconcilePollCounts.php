<?php

namespace App\Console\Commands;

use App\Models\Poll;
use App\Services\PollVotingService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('polls:reconcile')]
#[Description('Reconcile poll votes_count from the source-of-truth poll_votes table')]
class ReconcilePollCounts extends Command
{
    public function handle(PollVotingService $votingService): void
    {
        $count = 0;

        Poll::with('options')->chunk(100, function ($polls) use ($votingService, &$count): void {
            foreach ($polls as $poll) {
                $votingService->syncCounts($poll);
                $count++;
            }
        });

        $this->info("Reconciled {$count} poll(s).");
    }
}
