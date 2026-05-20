<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\CommunityDirectInvite;
use App\Models\CommunityInvite;
use App\Models\CommunityJoinRequest;
use App\Models\CommunityKeyEpoch;
use App\Models\CommunityMember;
use App\Models\CommunityTopic;
use App\Models\Friend;
use App\Models\User;
use App\Models\UserDeviceKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityHttpTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Auth guard
    // -------------------------------------------------------------------------

    public function test_unauthenticated_user_cannot_access_community_routes(): void
    {
        $community = Community::factory()->create();

        $this->postJson(route('communities.store'))->assertUnauthorized();
        $this->getJson(route('communities.show', $community))->assertUnauthorized();
        $this->postJson(route('communities.join', $community))->assertUnauthorized();
        $this->getJson(route('communities.invitations.index'))->assertUnauthorized();
        $this->postJson(route('communities.direct-invites.store', $community))->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // POST /communities — create community
    // -------------------------------------------------------------------------

    public function test_authenticated_user_can_create_community(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('communities.store'), [
                'name' => 'Test Community',
                'visibility' => Community::VISIBILITY_PUBLIC,
                'join_mode' => Community::JOIN_OPEN,
                'member_limit' => 100,
            ])
            ->assertCreated()
            ->assertJsonStructure(['success', 'community' => ['id', 'slug']]);

        $this->assertDatabaseHas('communities', ['name' => 'Test Community']);
    }

    public function test_create_community_rejects_invalid_member_limit(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('communities.store'), [
                'name' => 'Test',
                'visibility' => Community::VISIBILITY_PUBLIC,
                'join_mode' => Community::JOIN_OPEN,
                'member_limit' => 999,
            ])
            ->assertUnprocessable();
    }

    public function test_create_community_rejects_duplicate_slug(): void
    {
        $user = User::factory()->create();
        Community::factory()->create(['slug' => 'taken-slug']);

        $this->actingAs($user)
            ->postJson(route('communities.store'), [
                'name' => 'Test',
                'slug' => 'taken-slug',
                'visibility' => Community::VISIBILITY_PUBLIC,
                'join_mode' => Community::JOIN_OPEN,
                'member_limit' => 100,
            ])
            ->assertUnprocessable();
    }

    // -------------------------------------------------------------------------
    // GET /communities/{community} — show community
    // -------------------------------------------------------------------------

    public function test_authenticated_user_can_view_public_community(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['visibility' => Community::VISIBILITY_PUBLIC]);

        $this->actingAs($user)
            ->getJson(route('communities.show', $community))
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_non_member_cannot_view_private_community(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['visibility' => Community::VISIBILITY_PRIVATE]);

        $this->actingAs($user)
            ->getJson(route('communities.show', $community))
            ->assertNotFound();
    }

    public function test_non_member_cannot_view_hidden_community(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['visibility' => Community::VISIBILITY_HIDDEN]);

        $this->actingAs($user)
            ->getJson(route('communities.show', $community))
            ->assertNotFound();
    }

    public function test_active_member_can_view_private_community(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['visibility' => Community::VISIBILITY_PRIVATE]);
        CommunityMember::factory()->for($community)->for($user)->create();

        $this->actingAs($user)
            ->getJson(route('communities.show', $community))
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_active_member_can_view_hidden_community(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['visibility' => Community::VISIBILITY_HIDDEN]);
        CommunityMember::factory()->for($community)->for($user)->create();

        $this->actingAs($user)
            ->getJson(route('communities.show', $community))
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // POST /communities/{community}/invites — generate invite
    // -------------------------------------------------------------------------

    public function test_moderator_can_generate_invite(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['invite_policy' => Community::INVITE_POLICY_MODERATORS_ONLY]);
        CommunityMember::factory()->for($community)->for($user)->create(['role' => CommunityMember::ROLE_MODERATOR]);

        $this->actingAs($user)
            ->postJson(route('communities.invites.store', $community))
            ->assertCreated()
            ->assertJsonStructure(['success', 'invite' => ['id', 'code']]);
    }

    public function test_regular_member_cannot_generate_invite_in_moderators_only_community(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['invite_policy' => Community::INVITE_POLICY_MODERATORS_ONLY]);
        CommunityMember::factory()->for($community)->for($user)->create(['role' => CommunityMember::ROLE_MEMBER]);

        $this->actingAs($user)
            ->postJson(route('communities.invites.store', $community))
            ->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // Direct community invitations
    // -------------------------------------------------------------------------

    public function test_send_direct_invite_route_works_for_allowed_friend(): void
    {
        $inviter = User::factory()->create();
        $invitee = User::factory()->create();
        $community = Community::factory()->create(['invite_policy' => Community::INVITE_POLICY_ALL_MEMBERS]);
        CommunityMember::factory()->for($community)->for($inviter)->create(['status' => CommunityMember::STATUS_ACTIVE]);
        $this->befriend($inviter, $invitee);

        $this->actingAs($inviter)
            ->postJson(route('communities.direct-invites.store', $community), [
                'invitee_id' => $invitee->id,
                'message' => 'Join design',
            ])
            ->assertCreated()
            ->assertJsonStructure(['success', 'invite' => ['id', 'status']]);

        $this->assertDatabaseHas('community_direct_invites', [
            'community_id' => $community->id,
            'inviter_id' => $inviter->id,
            'invitee_id' => $invitee->id,
            'status' => CommunityDirectInvite::STATUS_PENDING,
        ]);
    }

    public function test_send_direct_invite_rejects_non_friend(): void
    {
        $inviter = User::factory()->create();
        $invitee = User::factory()->create();
        $community = Community::factory()->create(['invite_policy' => Community::INVITE_POLICY_ALL_MEMBERS]);
        CommunityMember::factory()->for($community)->for($inviter)->create(['status' => CommunityMember::STATUS_ACTIVE]);

        $this->actingAs($inviter)
            ->postJson(route('communities.direct-invites.store', $community), ['invitee_id' => $invitee->id])
            ->assertStatus(422);
    }

    public function test_pending_invitations_endpoint_returns_safe_invite(): void
    {
        $inviter = User::factory()->create(['login' => 'mila', 'pseudonym' => 'Mila']);
        $invitee = User::factory()->create();
        $community = Community::factory()->create([
            'name' => 'design · skr',
            'visibility' => Community::VISIBILITY_PRIVATE,
        ]);
        $invite = CommunityDirectInvite::factory()->pending()->for($community)->create([
            'inviter_id' => $inviter->id,
            'invitee_id' => $invitee->id,
            'message' => 'private message',
        ]);

        $this->actingAs($invitee)
            ->getJson(route('communities.invitations.index'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('invitations.0.id', $invite->id)
            ->assertJsonPath('invitations.0.inviter.login', 'mila')
            ->assertJsonPath('invitations.0.inviter.display_name', 'Mila')
            ->assertJsonPath('invitations.0.community.name', 'design · skr')
            ->assertJsonPath('invitations.0.community.visibility', Community::VISIBILITY_PRIVATE)
            ->assertJsonMissingPath('invitations.0.message')
            ->assertJsonMissingPath('invitations.0.audit_payload')
            ->assertJsonMissingPath('invitations.0.member_list');
    }

    public function test_accept_direct_invite_route_works(): void
    {
        $invitee = User::factory()->create();
        $invite = CommunityDirectInvite::factory()->pending()->create(['invitee_id' => $invitee->id]);

        $this->actingAs($invitee)
            ->postJson(route('communities.direct-invites.accept', $invite))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('member.status', CommunityMember::STATUS_PENDING_KEY_DELIVERY);

        $this->assertEquals(CommunityDirectInvite::STATUS_ACCEPTED, $invite->fresh()->status);
    }

    public function test_decline_direct_invite_route_works(): void
    {
        $invitee = User::factory()->create();
        $invite = CommunityDirectInvite::factory()->pending()->create(['invitee_id' => $invitee->id]);

        $this->actingAs($invitee)
            ->postJson(route('communities.direct-invites.decline', $invite))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertEquals(CommunityDirectInvite::STATUS_DECLINED, $invite->fresh()->status);
    }

    public function test_cancel_direct_invite_route_works(): void
    {
        $inviter = User::factory()->create();
        $invite = CommunityDirectInvite::factory()->pending()->create(['inviter_id' => $inviter->id]);

        $this->actingAs($inviter)
            ->postJson(route('communities.direct-invites.cancel', $invite))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertEquals(CommunityDirectInvite::STATUS_CANCELLED, $invite->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // POST /communities/join-by-invite — join by invite code
    // -------------------------------------------------------------------------

    public function test_user_can_join_by_invite(): void
    {
        $owner = User::factory()->create();
        $user = User::factory()->create();
        $community = Community::factory()->create(['join_mode' => Community::JOIN_INVITE_ONLY]);
        CommunityMember::factory()->for($community)->for($owner)->create(['role' => CommunityMember::ROLE_OWNER]);
        $invite = CommunityInvite::factory()->for($community)->for($owner, 'creator')->create();

        $this->actingAs($user)
            ->postJson(route('communities.join-by-invite'), ['code' => $invite->code])
            ->assertCreated()
            ->assertJsonStructure(['success', 'member' => ['id', 'status']]);
    }

    public function test_join_by_invite_requires_code(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('communities.join-by-invite'), [])
            ->assertUnprocessable();
    }

    // -------------------------------------------------------------------------
    // POST /communities/{community}/join — join public community
    // -------------------------------------------------------------------------

    public function test_user_can_join_public_community(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['join_mode' => Community::JOIN_OPEN]);

        $this->actingAs($user)
            ->postJson(route('communities.join', $community))
            ->assertCreated()
            ->assertJsonStructure(['success', 'member' => ['id', 'status']]);
    }

    // -------------------------------------------------------------------------
    // POST /communities/{community}/join-requests — request to join
    // -------------------------------------------------------------------------

    public function test_user_can_request_to_join(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['join_mode' => Community::JOIN_REQUEST]);

        $this->actingAs($user)
            ->postJson(route('communities.join-requests.store', $community), ['message' => 'Please let me in'])
            ->assertCreated()
            ->assertJsonStructure(['success', 'join_request' => ['id']]);
    }

    // -------------------------------------------------------------------------
    // POST /communities/join-requests/{joinRequest}/approve
    // -------------------------------------------------------------------------

    public function test_moderator_can_approve_join_request(): void
    {
        $moderator = User::factory()->create();
        $applicant = User::factory()->create();
        $community = Community::factory()->create(['join_mode' => Community::JOIN_REQUEST]);
        CommunityMember::factory()->for($community)->for($moderator)->create(['role' => CommunityMember::ROLE_MODERATOR]);
        $joinRequest = CommunityJoinRequest::factory()->for($community)->for($applicant, 'user')->create(['status' => 'pending']);

        $this->actingAs($moderator)
            ->postJson(route('communities.join-requests.approve', $joinRequest))
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_non_moderator_cannot_approve_join_request(): void
    {
        $member = User::factory()->create();
        $applicant = User::factory()->create();
        $community = Community::factory()->create(['join_mode' => Community::JOIN_REQUEST]);
        CommunityMember::factory()->for($community)->for($member)->create(['role' => CommunityMember::ROLE_MEMBER]);
        $joinRequest = CommunityJoinRequest::factory()->for($community)->for($applicant, 'user')->create(['status' => 'pending']);

        $this->actingAs($member)
            ->postJson(route('communities.join-requests.approve', $joinRequest))
            ->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // POST /communities/join-requests/{joinRequest}/reject
    // -------------------------------------------------------------------------

    public function test_moderator_can_reject_join_request(): void
    {
        $moderator = User::factory()->create();
        $applicant = User::factory()->create();
        $community = Community::factory()->create(['join_mode' => Community::JOIN_REQUEST]);
        CommunityMember::factory()->for($community)->for($moderator)->create(['role' => CommunityMember::ROLE_MODERATOR]);
        $joinRequest = CommunityJoinRequest::factory()->for($community)->for($applicant, 'user')->create(['status' => 'pending']);

        $this->actingAs($moderator)
            ->postJson(route('communities.join-requests.reject', $joinRequest))
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    // -------------------------------------------------------------------------
    // POST /communities/{community}/topics — create topic
    // -------------------------------------------------------------------------

    public function test_moderator_can_create_topic(): void
    {
        $moderator = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($moderator)->create(['role' => CommunityMember::ROLE_MODERATOR]);

        $this->actingAs($moderator)
            ->postJson(route('communities.topics.store', $community), ['name' => 'New Topic'])
            ->assertCreated()
            ->assertJsonStructure(['success', 'topic' => ['id', 'slug']]);
    }

    public function test_regular_member_cannot_create_topic(): void
    {
        $member = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($member)->create(['role' => CommunityMember::ROLE_MEMBER]);

        $this->actingAs($member)
            ->postJson(route('communities.topics.store', $community), ['name' => 'New Topic'])
            ->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // PATCH /communities/topics/{topic}/archive
    // -------------------------------------------------------------------------

    public function test_moderator_can_archive_topic(): void
    {
        $moderator = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($moderator)->create(['role' => CommunityMember::ROLE_MODERATOR]);
        $topic = CommunityTopic::factory()->for($community)->create(['is_archived' => false, 'is_system' => false]);

        $this->actingAs($moderator)
            ->patchJson(route('communities.topics.archive', $topic))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('community_topics', ['id' => $topic->id, 'is_archived' => true]);
    }

    // -------------------------------------------------------------------------
    // POST /communities/{community}/topics/{topic}/posts — publish post
    // -------------------------------------------------------------------------

    public function test_active_member_can_publish_encrypted_post(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($user)->create(['role' => CommunityMember::ROLE_MEMBER, 'status' => CommunityMember::STATUS_ACTIVE]);
        $topic = CommunityTopic::factory()->for($community)->create(['is_archived' => false]);
        $epoch = CommunityKeyEpoch::factory()->for($community)->create();

        $this->actingAs($user)
            ->postJson(route('communities.topics.posts.store', [$community, $topic]), [
                'ciphertext' => base64_encode('encrypted-content'),
                'nonce' => base64_encode('nonce-bytes-here'),
                'epoch_id' => $epoch->id,
            ])
            ->assertCreated()
            ->assertJsonStructure(['success', 'post' => ['id', 'community_seq']]);
    }

    public function test_publish_post_rejects_missing_ciphertext(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($user)->create(['status' => CommunityMember::STATUS_ACTIVE]);
        $topic = CommunityTopic::factory()->for($community)->create(['is_archived' => false]);
        $epoch = CommunityKeyEpoch::factory()->for($community)->create();

        $this->actingAs($user)
            ->postJson(route('communities.topics.posts.store', [$community, $topic]), [
                'nonce' => base64_encode('nonce-bytes'),
                'epoch_id' => $epoch->id,
            ])
            ->assertUnprocessable();
    }

    public function test_publish_post_with_body_returns_422(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($user)->create(['role' => CommunityMember::ROLE_MEMBER, 'status' => CommunityMember::STATUS_ACTIVE]);
        $topic = CommunityTopic::factory()->for($community)->create(['is_archived' => false]);
        $epoch = CommunityKeyEpoch::factory()->for($community)->create();

        $this->actingAs($user)
            ->postJson(route('communities.topics.posts.store', [$community, $topic]), [
                'ciphertext' => base64_encode('encrypted-content'),
                'nonce' => base64_encode('nonce-bytes-here'),
                'epoch_id' => $epoch->id,
                'body' => 'plaintext that must never be stored',
            ])
            ->assertUnprocessable();
    }

    public function test_publish_post_without_body_still_works(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($user)->create(['role' => CommunityMember::ROLE_MEMBER, 'status' => CommunityMember::STATUS_ACTIVE]);
        $topic = CommunityTopic::factory()->for($community)->create(['is_archived' => false]);
        $epoch = CommunityKeyEpoch::factory()->for($community)->create();

        $this->actingAs($user)
            ->postJson(route('communities.topics.posts.store', [$community, $topic]), [
                'ciphertext' => base64_encode('encrypted-content'),
                'nonce' => base64_encode('nonce-bytes-here'),
                'epoch_id' => $epoch->id,
            ])
            ->assertCreated();
    }

    public function test_non_member_cannot_publish_post(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $topic = CommunityTopic::factory()->for($community)->create(['is_archived' => false]);
        $epoch = CommunityKeyEpoch::factory()->for($community)->create();

        $this->actingAs($user)
            ->postJson(route('communities.topics.posts.store', [$community, $topic]), [
                'ciphertext' => base64_encode('encrypted-content'),
                'nonce' => base64_encode('nonce-bytes-here'),
                'epoch_id' => $epoch->id,
            ])
            ->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // POST /communities/members/{member}/keys — deliver keys
    // -------------------------------------------------------------------------

    public function test_moderator_can_deliver_member_keys(): void
    {
        $moderator = User::factory()->create();
        $memberUser = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($moderator)->create(['role' => CommunityMember::ROLE_MODERATOR]);
        $member = CommunityMember::factory()->for($community)->for($memberUser)->pendingKeyDelivery()->create();
        CommunityKeyEpoch::factory()->for($community)->create(['epoch_number' => 1]);
        $deviceKey = UserDeviceKey::factory()->for($memberUser)->create();

        $this->actingAs($moderator)
            ->postJson(route('communities.members.keys.store', $member), [
                'keys' => [
                    ['device_key_id' => $deviceKey->id, 'encrypted_key' => base64_encode('enc-key')],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_regular_member_cannot_deliver_keys(): void
    {
        $actor = User::factory()->create();
        $memberUser = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($actor)->create(['role' => CommunityMember::ROLE_MEMBER]);
        $member = CommunityMember::factory()->for($community)->for($memberUser)->pendingKeyDelivery()->create();
        CommunityKeyEpoch::factory()->for($community)->create();
        $deviceKey = UserDeviceKey::factory()->for($memberUser)->create();

        $this->actingAs($actor)
            ->postJson(route('communities.members.keys.store', $member), [
                'keys' => [
                    ['device_key_id' => $deviceKey->id, 'encrypted_key' => base64_encode('enc-key')],
                ],
            ])
            ->assertStatus(422);
    }

    // -------------------------------------------------------------------------
    // POST /communities/topics/{topic}/mark-read
    // -------------------------------------------------------------------------

    public function test_mark_topic_read_stores_state(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($user)->create(['status' => CommunityMember::STATUS_ACTIVE]);
        $topic = CommunityTopic::factory()->for($community)->create(['post_count' => 5]);

        $this->actingAs($user)
            ->postJson(route('communities.topics.mark-read', $topic), ['topic_seq' => 5])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_mark_topic_read_requires_topic_seq(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $topic = CommunityTopic::factory()->for($community)->create();

        $this->actingAs($user)
            ->postJson(route('communities.topics.mark-read', $topic), [])
            ->assertUnprocessable();
    }

    // -------------------------------------------------------------------------
    // POST /communities/{community}/mark-read
    // -------------------------------------------------------------------------

    public function test_mark_community_read_stores_state(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['post_count' => 10]);
        CommunityMember::factory()->for($community)->for($user)->create(['status' => CommunityMember::STATUS_ACTIVE]);

        $this->actingAs($user)
            ->postJson(route('communities.mark-read', $community), ['community_seq' => 10])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_mark_community_read_requires_community_seq(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();

        $this->actingAs($user)
            ->postJson(route('communities.mark-read', $community), [])
            ->assertUnprocessable();
    }

    private function befriend(User $first, User $second): void
    {
        Friend::create(['user_id' => $first->id, 'friend_id' => $second->id]);
        Friend::create(['user_id' => $second->id, 'friend_id' => $first->id]);
    }
}
