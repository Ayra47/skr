<?php

namespace App\Console\Commands;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityPost;
use App\Models\CommunityTopic;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('communities:reconcile-counters {--chunk=100 : Records per chunk}')]
#[Description('Reconcile denormalized community and topic counters')]
class ReconcileCommunityCounters extends Command
{
    public function handle(): int
    {
        $communities = 0;
        $topics = 0;
        $chunk = max(1, (int) $this->option('chunk'));

        Community::query()
            ->orderBy('id')
            ->chunkById($chunk, function ($items) use (&$communities): void {
                foreach ($items as $community) {
                    $community->forceFill([
                        'member_count' => CommunityMember::query()
                            ->where('community_id', $community->id)
                            ->where('status', CommunityMember::STATUS_ACTIVE)
                            ->count(),
                        'post_count' => $this->visiblePosts()
                            ->where('community_id', $community->id)
                            ->count(),
                    ])->save();

                    $communities++;
                }
            });

        CommunityTopic::query()
            ->orderBy('id')
            ->chunkById($chunk, function ($items) use (&$topics): void {
                foreach ($items as $topic) {
                    $topic->forceFill([
                        'post_count' => $this->visiblePosts()
                            ->where('topic_id', $topic->id)
                            ->count(),
                    ])->save();

                    $topics++;
                }
            });

        $this->info("Reconciled {$communities} communit(ies) and {$topics} topic(s).");

        return self::SUCCESS;
    }

    private function visiblePosts(): Builder
    {
        return CommunityPost::query()
            ->where('moderation_status', CommunityPost::MODERATION_VISIBLE)
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }
}
