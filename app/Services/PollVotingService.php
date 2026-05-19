<?php

namespace App\Services;

use App\Events\PollUpdatedEvent;
use App\Models\FeedPost;
use App\Models\Poll;
use App\Models\PollVote;
use Illuminate\Support\Facades\DB;

class PollVotingService
{
    /**
     * Cast votes for the given option IDs. Idempotent via insertOrIgnore.
     *
     * @param  array<int>  $optionIds
     * @return array<string, mixed>
     */
    public function vote(Poll $poll, int $userId, array $optionIds): array
    {
        if ($poll->isExpired()) {
            return ['error' => 'poll_expired'];
        }

        $optionIds = array_unique(array_map('intval', $optionIds));
        $validOptionIds = $poll->options->pluck('id')->all();

        foreach ($optionIds as $id) {
            if (! in_array($id, $validOptionIds, strict: true)) {
                return ['error' => 'invalid_option'];
            }
        }

        if ($poll->mode === Poll::MODE_SINGLE && count($optionIds) > 1) {
            return ['error' => 'single_choice_only'];
        }

        $maxChoices = $poll->max_choices;
        if ($maxChoices !== null && count($optionIds) > $maxChoices) {
            return ['error' => 'too_many_choices'];
        }

        $voterHash = $poll->voterHashFor($userId);

        DB::transaction(function () use ($poll, $voterHash, $optionIds): void {
            $existingOptionIds = PollVote::query()
                ->where('poll_id', $poll->id)
                ->where('voter_hash', $voterHash)
                ->pluck('option_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $toAdd = array_diff($optionIds, $existingOptionIds);
            $toRemove = array_diff($existingOptionIds, $optionIds);

            foreach ($toAdd as $optionId) {
                $inserted = DB::table('poll_votes')->insertOrIgnore([
                    'poll_id' => $poll->id,
                    'option_id' => $optionId,
                    'voter_hash' => $voterHash,
                    'created_at' => now(),
                ]);

                if ($inserted) {
                    DB::table('poll_options')
                        ->where('id', $optionId)
                        ->increment('votes_count');
                }
            }

            if (! empty($toRemove)) {
                PollVote::query()
                    ->where('poll_id', $poll->id)
                    ->where('voter_hash', $voterHash)
                    ->whereIn('option_id', $toRemove)
                    ->forceDelete();

                DB::table('poll_options')
                    ->whereIn('id', $toRemove)
                    ->decrement('votes_count');
            }
        });

        $poll->load('options');

        broadcast(new PollUpdatedEvent($poll));

        return $this->buildResult($poll, $userId);
    }

    /**
     * Cancel all votes for the user on this poll.
     *
     * @return array<string, mixed>
     */
    public function cancelAll(Poll $poll, int $userId): array
    {
        $voterHash = $poll->voterHashFor($userId);

        DB::transaction(function () use ($poll, $voterHash): void {
            $optionIds = PollVote::query()
                ->where('poll_id', $poll->id)
                ->where('voter_hash', $voterHash)
                ->pluck('option_id')
                ->all();

            PollVote::query()
                ->where('poll_id', $poll->id)
                ->where('voter_hash', $voterHash)
                ->forceDelete();

            if (! empty($optionIds)) {
                DB::table('poll_options')
                    ->whereIn('id', $optionIds)
                    ->decrement('votes_count');
            }
        });

        $poll->load('options');

        broadcast(new PollUpdatedEvent($poll));

        return $this->buildResult($poll, $userId);
    }

    /**
     * Build the poll result payload for the client.
     *
     * @return array<string, mixed>
     */
    public function buildResult(Poll $poll, int $userId): array
    {
        $total = $poll->totalVotes();
        $votedOptionIds = $poll->votedOptionIds($userId);

        return [
            'poll_id' => $poll->id,
            'total_votes' => $total,
            'voted_option_ids' => $votedOptionIds,
            'options' => $poll->options->map(fn ($opt) => [
                'id' => $opt->id,
                'votes_count' => $opt->votes_count,
                'percentage' => $opt->percentage($total),
            ])->all(),
        ];
    }

    /**
     * Recalculate votes_count from the source of truth (poll_votes table).
     */
    public function syncCounts(Poll $poll): void
    {
        DB::transaction(function () use ($poll): void {
            $counts = DB::table('poll_votes')
                ->select('option_id', DB::raw('count(*) as cnt'))
                ->where('poll_id', $poll->id)
                ->whereNull('deleted_at')
                ->groupBy('option_id')
                ->pluck('cnt', 'option_id');

            foreach ($poll->options as $option) {
                $option->update(['votes_count' => (int) ($counts[$option->id] ?? 0)]);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function buildResultForPost(FeedPost $post, int $userId): array
    {
        $poll = $post->poll;

        if ($poll === null) {
            return [];
        }

        $poll->load('options');

        return $this->buildResult($poll, $userId);
    }
}
