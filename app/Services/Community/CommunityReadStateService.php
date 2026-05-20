<?php

declare(strict_types=1);

namespace App\Services\Community;

use App\Models\Community;
use App\Models\CommunityTopic;
use App\Models\CommunityTopicUserState;
use App\Models\CommunityUserState;
use App\Models\User;

final class CommunityReadStateService
{
    public function markTopicRead(User $user, CommunityTopic $topic, int $topicSeq): void
    {
        CommunityTopicUserState::where('community_id', $topic->community_id)
            ->where('topic_id', $topic->id)
            ->where('user_id', $user->id)
            ->where('last_read_topic_seq', '<', $topicSeq)
            ->update(['last_read_topic_seq' => $topicSeq]);
    }

    public function markCommunityRead(User $user, Community $community, int $communitySeq): void
    {
        CommunityUserState::where('community_id', $community->id)
            ->where('user_id', $user->id)
            ->where('last_read_community_seq', '<', $communitySeq)
            ->update([
                'last_read_community_seq' => $communitySeq,
                'last_activity_seen_at' => now(),
            ]);
    }
}
