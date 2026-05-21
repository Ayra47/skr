<?php

namespace Tests\Feature;

use App\Livewire\Feed;
use App\Models\Community;
use App\Models\CommunityDirectInvite;
use App\Models\CommunityInvite;
use App\Models\CommunityInviteUse;
use App\Models\CommunityJoinRequest;
use App\Models\CommunityKeyEpoch;
use App\Models\CommunityMember;
use App\Models\CommunityPost;
use App\Models\CommunityTopic;
use App\Models\Friend;
use App\Models\ProfileSetting;
use App\Models\User;
use App\Services\Community\CommunityAuditService;
use App\Services\Community\CommunityCreationService;
use App\Services\Community\CommunityDirectInviteService;
use App\Services\Community\CommunityInviteService;
use App\Services\Community\CommunityJoinService;
use App\Services\Community\CommunityMemberManagementService;
use App\Services\Community\CommunityPolicyService;
use App\Services\Community\CommunityPostService;
use App\Services\Community\CommunityReadStateService;
use App\Services\Community\CommunityTopicService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class CommunityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_last_member_slot_only_admits_one_joiner(): void
    {
        $community = Community::factory()->create([
            'join_mode' => Community::JOIN_OPEN,
            'member_count' => 4,
            'member_limit' => 5,
        ]);
        $first = User::factory()->create();
        $second = User::factory()->create();

        $service = $this->joinService();
        $service->joinPublic($first, $community);

        $this->assertThrows(fn () => $service->joinPublic($second, $community->fresh()), RuntimeException::class);

        $this->assertSame(1, CommunityMember::where('community_id', $community->id)->where('status', CommunityMember::STATUS_ACTIVE)->count());
        $this->assertSame(5, $community->fresh()->member_count);
    }

    public function test_single_use_invite_code_only_redeems_once(): void
    {
        $community = Community::factory()->create(['member_count' => 0]);
        $invite = CommunityInvite::factory()->for($community)->create(['max_uses' => 1, 'use_count' => 0]);
        $first = User::factory()->create();
        $second = User::factory()->create();

        $service = $this->joinService();
        $service->joinByInvite($first, $invite->code);

        $this->assertThrows(fn () => $service->joinByInvite($second, $invite->code), InvalidArgumentException::class);

        $this->assertSame(1, $invite->fresh()->use_count);
        $this->assertSame(1, CommunityInviteUse::where('invite_id', $invite->id)->count());
        $this->assertSame(1, CommunityMember::where('community_id', $community->id)->where('status', CommunityMember::STATUS_ACTIVE)->count());
    }

    public function test_same_direct_invite_cannot_be_accepted_twice(): void
    {
        $invitee = User::factory()->create();
        $community = Community::factory()->create(['member_count' => 0]);
        $invite = CommunityDirectInvite::factory()->pending()->for($community)->create(['invitee_id' => $invitee->id]);

        $service = $this->directInviteService();
        $service->acceptInvite($invitee, $invite);

        $this->assertThrows(fn () => $service->acceptInvite($invitee, $invite->fresh()), InvalidArgumentException::class);

        $this->assertSame(1, CommunityMember::where('community_id', $community->id)->where('user_id', $invitee->id)->count());
        $this->assertSame(CommunityDirectInvite::STATUS_ACCEPTED, $invite->fresh()->status);
        $this->assertSame(1, $community->fresh()->member_count);
    }

    public function test_published_post_sequences_are_unique_and_monotonic(): void
    {
        $author = User::factory()->create();
        [$community, $topic, $epoch] = $this->postContext($author);
        $service = new CommunityPostService(new CommunityPolicyService);

        $posts = collect(range(1, 3))
            ->map(fn (int $i) => $service->publishEncryptedPost($author, $community->fresh(), $topic->fresh(), [
                'ciphertext' => 'seq-'.$i,
                'nonce' => 'nonce-'.$i,
                'epoch_id' => $epoch->id,
            ]));

        $this->assertSame([1, 2, 3], $posts->pluck('community_seq')->all());
        $this->assertSame([1, 2, 3], $posts->pluck('topic_seq')->all());
        $this->assertSame($posts->count(), $posts->pluck('community_seq')->unique()->count());
        $this->assertSame($posts->count(), $posts->pluck('topic_seq')->unique()->count());
    }

    public function test_database_rejects_duplicate_community_and_topic_sequences(): void
    {
        $author = User::factory()->create();
        [$community, $topic] = $this->postContext($author);

        CommunityPost::factory()
            ->for($community)
            ->for($topic, 'topic')
            ->for($author, 'author')
            ->create(['community_seq' => 1, 'topic_seq' => 1]);

        $this->expectException(QueryException::class);

        CommunityPost::factory()
            ->for($community)
            ->for($topic, 'topic')
            ->for($author, 'author')
            ->create(['community_seq' => 1, 'topic_seq' => 1]);
    }

    public function test_role_permission_matrix_for_core_actions(): void
    {
        $policy = new CommunityPolicyService;
        $community = Community::factory()->create([
            'invite_policy' => Community::INVITE_POLICY_MODERATORS_ONLY,
            'posting_policy' => Community::POSTING_POLICY_MODERATORS_ONLY,
        ]);
        $topic = CommunityTopic::factory()->for($community)->create();

        foreach ([
            CommunityMember::ROLE_OWNER => true,
            CommunityMember::ROLE_ADMIN => true,
            CommunityMember::ROLE_MODERATOR => true,
            CommunityMember::ROLE_MEMBER => false,
        ] as $role => $canModerate) {
            $user = User::factory()->create();
            CommunityMember::factory()->for($community)->for($user)->create(['role' => $role]);

            $this->assertSame($canModerate, $policy->canInvite($user, $community), "canInvite mismatch for {$role}");
            $this->assertSame($canModerate, $policy->canApproveJoin($user, $community), "canApproveJoin mismatch for {$role}");
            $this->assertSame($canModerate, $policy->canManageTopic($user, $community), "canManageTopic mismatch for {$role}");
            $this->assertSame($canModerate, $policy->canPostInTopic($user, $topic), "canPostInTopic mismatch for {$role}");
        }
    }

    public function test_inactive_members_cannot_perform_privileged_actions_or_mark_read(): void
    {
        foreach ([CommunityMember::STATUS_BANNED, CommunityMember::STATUS_SUSPENDED, CommunityMember::STATUS_LEFT] as $status) {
            $actor = User::factory()->create();
            $target = User::factory()->create();
            $community = Community::factory()->create();
            $topic = CommunityTopic::factory()->for($community)->create(['is_system' => false]);
            $epoch = CommunityKeyEpoch::factory()->for($community)->create();
            CommunityMember::factory()->for($community)->for($actor)->create([
                'role' => CommunityMember::ROLE_OWNER,
                'status' => $status,
            ]);

            $this->assertFalse((new CommunityPolicyService)->canInvite($actor, $community));
            $this->assertFalse((new CommunityPolicyService)->canApproveJoin($actor, $community));
            $this->assertFalse((new CommunityPolicyService)->canManageTopic($actor, $community));
            $this->assertFalse((new CommunityPolicyService)->canPostInTopic($actor, $topic));

            $this->assertThrows(fn () => $this->inviteService()->generateInvite($actor, $community), InvalidArgumentException::class);
            $this->assertThrows(fn () => $this->topicService()->createTopic($actor, $community, ['name' => 'Blocked '.$status]), InvalidArgumentException::class);
            $this->assertThrows(fn () => $this->topicService()->archiveTopic($actor, $topic), InvalidArgumentException::class);
            $this->assertThrows(fn () => (new CommunityPostService(new CommunityPolicyService))->publishEncryptedPost($actor, $community, $topic, [
                'ciphertext' => 'blocked',
                'nonce' => 'blocked',
                'epoch_id' => $epoch->id,
            ]), InvalidArgumentException::class);
            $this->assertThrows(fn () => (new CommunityReadStateService(new CommunityPolicyService))->markTopicRead($actor, $topic, 1), InvalidArgumentException::class);

            $joinRequest = CommunityJoinRequest::factory()->for($community)->for($target, 'user')->create();
            $this->assertThrows(fn () => $this->joinService()->approveJoinRequest($actor, $joinRequest), InvalidArgumentException::class);
        }
    }

    public function test_last_owner_cannot_leave_be_downgraded_or_removed(): void
    {
        $owner = User::factory()->create();
        $community = (new CommunityCreationService(new CommunityAuditService))->create($owner, [
            'name' => 'Last Owner Guard',
        ]);
        $member = CommunityMember::where('community_id', $community->id)
            ->where('user_id', $owner->id)
            ->firstOrFail();
        $service = new CommunityMemberManagementService;

        $this->assertThrows(fn () => $service->leaveCommunity($owner, $community), InvalidArgumentException::class);
        $this->assertThrows(fn () => $service->changeRole($member, CommunityMember::ROLE_ADMIN), InvalidArgumentException::class);
        $this->assertThrows(fn () => $service->removeMember($member, CommunityMember::STATUS_BANNED), InvalidArgumentException::class);
    }

    public function test_archived_topic_remains_readable_but_not_writable_in_ui(): void
    {
        $member = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($member)->create();
        $topic = CommunityTopic::factory()->for($community)->create(['name' => 'Archived Topic', 'is_archived' => true]);
        CommunityKeyEpoch::factory()->for($community)->create();

        $this->actingAs($member)
            ->get(route('communities.show', ['community' => $community, 'topic' => $topic->id]))
            ->assertOk()
            ->assertSeeText('Archived Topic')
            ->assertSeeText('archived')
            ->assertDontSeeText('Publish encrypted post');
    }

    public function test_hidden_private_metadata_and_payloads_do_not_leak_after_access_loss(): void
    {
        config(['features.unified_feed_items_enabled' => true, 'features.community_feed_items_enabled' => true]);

        $author = User::factory()->create();
        $viewer = User::factory()->create();
        $post = $this->communityPost($author, [
            'ciphertext' => 'HARDEN-CIPHERTEXT',
            'nonce' => 'HARDEN-NONCE',
        ], [
            'name' => 'Hidden Hardening Space',
            'visibility' => Community::VISIBILITY_HIDDEN,
        ]);
        CommunityMember::factory()->for($post->community)->for($viewer)->create();

        $this->actingAs($viewer)->postJson(route('bookmarks.store'), [
            'bookmarkable_type' => 'community_post',
            'bookmarkable_id' => $post->id,
        ])->assertCreated();

        CommunityMember::where('community_id', $post->community_id)
            ->where('user_id', $viewer->id)
            ->update(['status' => CommunityMember::STATUS_LEFT, 'left_at' => now()]);

        Livewire::actingAs($viewer)
            ->test(Feed::class, ['tab' => 'friends'])
            ->assertDontSee('Hidden Hardening Space')
            ->assertDontSee('HARDEN-CIPHERTEXT', false)
            ->assertDontSee('HARDEN-NONCE', false);

        $this->actingAs($viewer)
            ->get(route('profiles.show', $author))
            ->assertOk()
            ->assertDontSeeText('Hidden Hardening Space')
            ->assertDontSee('HARDEN-CIPHERTEXT')
            ->assertDontSee('HARDEN-NONCE');

        $this->actingAs($viewer)
            ->get(route('bookmarks.index'))
            ->assertOk()
            ->assertSeeText('Community post unavailable')
            ->assertDontSeeText('Hidden Hardening Space')
            ->assertDontSee('HARDEN-CIPHERTEXT')
            ->assertDontSee('HARDEN-NONCE');

        $this->actingAs($viewer)
            ->get(route('communities.index'))
            ->assertOk()
            ->assertDontSeeText('Hidden Hardening Space');
    }

    public function test_public_community_end_to_end_smoke_lifecycle(): void
    {
        config(['features.unified_feed_items_enabled' => true, 'features.community_feed_items_enabled' => true]);

        $creator = User::factory()->create();
        $friend = User::factory()->create();
        $this->befriend($creator, $friend);

        $community = (new CommunityCreationService(new CommunityAuditService))->create($creator, [
            'name' => 'Public E2E Community',
            'visibility' => Community::VISIBILITY_PUBLIC,
            'join_mode' => Community::JOIN_OPEN,
            'allow_posts_in_member_feed' => true,
        ]);
        $invite = $this->directInviteService()->sendInvite($creator, $community, $friend);
        $accepted = $this->directInviteService()->acceptInvite($friend, $invite);

        $this->assertSame(CommunityMember::STATUS_ACTIVE, $accepted->status);

        $topic = $community->topics()->where('slug', 'general')->firstOrFail();
        $epoch = $community->keyEpochs()->firstOrFail();
        $post = (new CommunityPostService(new CommunityPolicyService))->publishEncryptedPost($creator, $community->fresh(), $topic, [
            'ciphertext' => 'E2E-CIPHERTEXT',
            'nonce' => 'E2E-NONCE',
            'epoch_id' => $epoch->id,
            'visibility' => CommunityPost::VISIBILITY_PUBLIC,
        ]);
        $creator->profileSetting()->updateOrCreate([], [
            'community_activity_visibility' => ProfileSetting::AUDIENCE_EVERYONE,
            'community_posts_profile_visibility' => ProfileSetting::AUDIENCE_EVERYONE,
        ]);

        Livewire::actingAs($friend)
            ->test(Feed::class, ['tab' => 'all'])
            ->assertSee('Encrypted community post')
            ->assertSee('Public E2E Community')
            ->assertDontSee('E2E-CIPHERTEXT', false)
            ->assertDontSee('E2E-NONCE', false);

        $this->actingAs($friend)
            ->get(route('profiles.show', $creator))
            ->assertOk()
            ->assertSeeText('Encrypted community post')
            ->assertSeeText('Public E2E Community')
            ->assertDontSee('E2E-CIPHERTEXT')
            ->assertDontSee('E2E-NONCE');

        $this->actingAs($friend)->postJson(route('bookmarks.store'), [
            'bookmarkable_type' => 'community_post',
            'bookmarkable_id' => $post->id,
        ])->assertCreated();

        $post->update(['expires_at' => now()->subMinute()]);
        $this->artisan('communities:expire-posts')->assertSuccessful();

        Livewire::actingAs($friend)
            ->test(Feed::class, ['tab' => 'all'])
            ->assertDontSee('Public E2E Community')
            ->assertDontSee('E2E-CIPHERTEXT', false)
            ->assertDontSee('E2E-NONCE', false);

        $this->actingAs($friend)
            ->get(route('bookmarks.index'))
            ->assertOk()
            ->assertSeeText('Community post unavailable')
            ->assertDontSee('E2E-CIPHERTEXT')
            ->assertDontSee('E2E-NONCE');
    }

    private function joinService(): CommunityJoinService
    {
        return new CommunityJoinService(new CommunityPolicyService, new CommunityAuditService);
    }

    private function inviteService(): CommunityInviteService
    {
        return new CommunityInviteService(new CommunityPolicyService, new CommunityAuditService);
    }

    private function directInviteService(): CommunityDirectInviteService
    {
        return new CommunityDirectInviteService(new CommunityPolicyService, new CommunityAuditService);
    }

    private function topicService(): CommunityTopicService
    {
        return new CommunityTopicService(new CommunityPolicyService, new CommunityAuditService);
    }

    /**
     * @return array{0: Community, 1: CommunityTopic, 2: CommunityKeyEpoch}
     */
    private function postContext(User $author): array
    {
        $community = Community::factory()->create(['post_count' => 0]);
        $topic = CommunityTopic::factory()->for($community)->create(['post_count' => 0]);
        $epoch = CommunityKeyEpoch::factory()->for($community)->create();
        CommunityMember::factory()->for($community)->for($author)->create();

        return [$community, $topic, $epoch];
    }

    private function communityPost(User $author, array $postAttrs = [], array $communityAttrs = []): CommunityPost
    {
        [$community, $topic] = $this->postContext($author);
        $community->update(array_merge([
            'allow_posts_in_member_feed' => true,
        ], $communityAttrs));

        return CommunityPost::factory()
            ->for($community->fresh())
            ->for($topic, 'topic')
            ->for($author, 'author')
            ->create($postAttrs);
    }

    private function befriend(User $first, User $second): void
    {
        Friend::create(['user_id' => $first->id, 'friend_id' => $second->id]);
        Friend::create(['user_id' => $second->id, 'friend_id' => $first->id]);
    }
}
