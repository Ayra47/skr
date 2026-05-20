<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\CommunityAuditLog;
use App\Models\CommunityDirectInvite;
use App\Models\CommunityInvite;
use App\Models\CommunityJoinRequest;
use App\Models\CommunityKeyEpoch;
use App\Models\CommunityMember;
use App\Models\CommunityMemberKey;
use App\Models\CommunityPost;
use App\Models\CommunityTopic;
use App\Models\CommunityTopicUserState;
use App\Models\CommunityUserState;
use App\Models\Friend;
use App\Models\User;
use App\Models\UserDeviceKey;
use App\Services\Community\CommunityAuditService;
use App\Services\Community\CommunityCreationService;
use App\Services\Community\CommunityDirectInviteService;
use App\Services\Community\CommunityInviteService;
use App\Services\Community\CommunityJoinService;
use App\Services\Community\CommunityKeyDeliveryService;
use App\Services\Community\CommunityPolicyService;
use App\Services\Community\CommunityPostService;
use App\Services\Community\CommunityReadStateService;
use App\Services\Community\CommunityTopicService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class CommunityServiceTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // CommunityPolicyService
    // -------------------------------------------------------------------------

    #[Test]
    public function policy_get_membership_returns_null_for_non_member(): void
    {
        $policy = new CommunityPolicyService;
        $user = User::factory()->create();
        $community = Community::factory()->create();

        $this->assertNull($policy->getMembership($user, $community));
    }

    #[Test]
    public function policy_get_membership_returns_member_record(): void
    {
        $policy = new CommunityPolicyService;
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $member = CommunityMember::factory()->for($community)->for($user)->create();

        $found = $policy->getMembership($user, $community);

        $this->assertNotNull($found);
        $this->assertEquals($member->id, $found->id);
    }

    #[Test]
    public function policy_is_active_member_returns_false_for_non_member(): void
    {
        $policy = new CommunityPolicyService;
        $user = User::factory()->create();
        $community = Community::factory()->create();

        $this->assertFalse($policy->isActiveMember($user, $community));
    }

    #[Test]
    public function policy_is_active_member_returns_false_for_pending_key_delivery(): void
    {
        $policy = new CommunityPolicyService;
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($user)->pendingKeyDelivery()->create();

        $this->assertFalse($policy->isActiveMember($user, $community));
    }

    #[Test]
    public function policy_is_active_member_returns_true_for_active_member(): void
    {
        $policy = new CommunityPolicyService;
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($user)->create();

        $this->assertTrue($policy->isActiveMember($user, $community));
    }

    #[Test]
    public function policy_role_at_least_enforces_hierarchy(): void
    {
        $policy = new CommunityPolicyService;
        $community = Community::factory()->create();

        $member = CommunityMember::factory()->for($community)->create(['role' => CommunityMember::ROLE_MEMBER]);
        $moderator = CommunityMember::factory()->for($community)->create(['role' => CommunityMember::ROLE_MODERATOR]);
        $admin = CommunityMember::factory()->for($community)->create(['role' => CommunityMember::ROLE_ADMIN]);
        $owner = CommunityMember::factory()->for($community)->create(['role' => CommunityMember::ROLE_OWNER]);

        $this->assertTrue($policy->roleAtLeast($member, CommunityMember::ROLE_MEMBER));
        $this->assertFalse($policy->roleAtLeast($member, CommunityMember::ROLE_MODERATOR));

        $this->assertTrue($policy->roleAtLeast($moderator, CommunityMember::ROLE_MEMBER));
        $this->assertTrue($policy->roleAtLeast($moderator, CommunityMember::ROLE_MODERATOR));
        $this->assertFalse($policy->roleAtLeast($moderator, CommunityMember::ROLE_ADMIN));

        $this->assertTrue($policy->roleAtLeast($admin, CommunityMember::ROLE_MODERATOR));
        $this->assertTrue($policy->roleAtLeast($admin, CommunityMember::ROLE_ADMIN));
        $this->assertFalse($policy->roleAtLeast($admin, CommunityMember::ROLE_OWNER));

        $this->assertTrue($policy->roleAtLeast($owner, CommunityMember::ROLE_OWNER));
    }

    #[Test]
    public function policy_can_invite_all_members_policy_allows_any_active_member(): void
    {
        $policy = new CommunityPolicyService;
        $user = User::factory()->create();
        $community = Community::factory()->create(['invite_policy' => Community::INVITE_POLICY_ALL_MEMBERS]);
        CommunityMember::factory()->for($community)->for($user)->create(['role' => CommunityMember::ROLE_MEMBER]);

        $this->assertTrue($policy->canInvite($user, $community));
    }

    #[Test]
    public function policy_can_invite_moderators_only_policy_blocks_member(): void
    {
        $policy = new CommunityPolicyService;
        $user = User::factory()->create();
        $community = Community::factory()->create(['invite_policy' => Community::INVITE_POLICY_MODERATORS_ONLY]);
        CommunityMember::factory()->for($community)->for($user)->create(['role' => CommunityMember::ROLE_MEMBER]);

        $this->assertFalse($policy->canInvite($user, $community));
    }

    #[Test]
    public function policy_can_invite_moderators_only_policy_allows_moderator(): void
    {
        $policy = new CommunityPolicyService;
        $user = User::factory()->create();
        $community = Community::factory()->create(['invite_policy' => Community::INVITE_POLICY_MODERATORS_ONLY]);
        CommunityMember::factory()->for($community)->for($user)->create(['role' => CommunityMember::ROLE_MODERATOR]);

        $this->assertTrue($policy->canInvite($user, $community));
    }

    #[Test]
    public function policy_can_invite_returns_false_for_non_member(): void
    {
        $policy = new CommunityPolicyService;
        $user = User::factory()->create();
        $community = Community::factory()->create();

        $this->assertFalse($policy->canInvite($user, $community));
    }

    #[Test]
    public function policy_can_approve_join_requires_moderator(): void
    {
        $policy = new CommunityPolicyService;
        $community = Community::factory()->create();
        $memberUser = User::factory()->create();
        $modUser = User::factory()->create();

        CommunityMember::factory()->for($community)->for($memberUser)->create(['role' => CommunityMember::ROLE_MEMBER]);
        CommunityMember::factory()->for($community)->for($modUser)->create(['role' => CommunityMember::ROLE_MODERATOR]);

        $this->assertFalse($policy->canApproveJoin($memberUser, $community));
        $this->assertTrue($policy->canApproveJoin($modUser, $community));
    }

    #[Test]
    public function policy_can_post_in_topic_archived_topic_blocks_everyone(): void
    {
        $policy = new CommunityPolicyService;
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($user)->create(['role' => CommunityMember::ROLE_OWNER]);
        $topic = CommunityTopic::factory()->for($community)->create(['is_archived' => true]);

        $this->assertFalse($policy->canPostInTopic($user, $topic));
    }

    #[Test]
    public function policy_can_post_in_topic_moderators_only_blocks_regular_member(): void
    {
        $policy = new CommunityPolicyService;
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($user)->create(['role' => CommunityMember::ROLE_MEMBER]);
        $topic = CommunityTopic::factory()->for($community)->create([
            'posting_policy' => CommunityTopic::POSTING_POLICY_MODERATORS_ONLY,
        ]);

        $this->assertFalse($policy->canPostInTopic($user, $topic));
    }

    #[Test]
    public function policy_can_post_in_topic_moderators_only_allows_moderator(): void
    {
        $policy = new CommunityPolicyService;
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($user)->create(['role' => CommunityMember::ROLE_MODERATOR]);
        $topic = CommunityTopic::factory()->for($community)->create([
            'posting_policy' => CommunityTopic::POSTING_POLICY_MODERATORS_ONLY,
        ]);

        $this->assertTrue($policy->canPostInTopic($user, $topic));
    }

    #[Test]
    public function policy_can_manage_topic_requires_moderator(): void
    {
        $policy = new CommunityPolicyService;
        $community = Community::factory()->create();
        $memberUser = User::factory()->create();
        $modUser = User::factory()->create();

        CommunityMember::factory()->for($community)->for($memberUser)->create(['role' => CommunityMember::ROLE_MEMBER]);
        CommunityMember::factory()->for($community)->for($modUser)->create(['role' => CommunityMember::ROLE_MODERATOR]);

        $this->assertFalse($policy->canManageTopic($memberUser, $community));
        $this->assertTrue($policy->canManageTopic($modUser, $community));
    }

    // -------------------------------------------------------------------------
    // CommunityAuditService
    // -------------------------------------------------------------------------

    #[Test]
    public function audit_log_creates_record(): void
    {
        $audit = new CommunityAuditService;
        $community = Community::factory()->create();
        $actor = User::factory()->create();

        $audit->log($community, $actor, CommunityAuditLog::ACTION_SETTINGS_UPDATED, ['name' => 'new name']);

        $this->assertDatabaseHas('community_audit_log', [
            'community_id' => $community->id,
            'actor_id' => $actor->id,
            'action' => CommunityAuditLog::ACTION_SETTINGS_UPDATED,
        ]);
    }

    #[Test]
    public function audit_log_strips_sensitive_payload_keys(): void
    {
        $audit = new CommunityAuditService;
        $community = Community::factory()->create();
        $actor = User::factory()->create();

        $audit->log($community, $actor, CommunityAuditLog::ACTION_MEMBER_ADDED, [
            'ciphertext' => 'abc123',
            'nonce' => 'nonce_value',
            'encrypted_key' => 'key_data',
            'code' => 'INV123',
            'encrypted_ban_note' => 'secret',
            'path' => '/private/path',
            'storage_key' => 's3_key',
            'public_key' => 'pub_key_data',
            'fingerprint' => 'fp_data',
            'body' => 'plaintext',
            'encrypted_filename' => 'enc_file',
            'role' => 'member',
        ]);

        $log = CommunityAuditLog::where('community_id', $community->id)->first();
        $this->assertNotNull($log);

        $this->assertArrayHasKey('role', $log->payload);
        $this->assertArrayNotHasKey('ciphertext', $log->payload);
        $this->assertArrayNotHasKey('nonce', $log->payload);
        $this->assertArrayNotHasKey('encrypted_key', $log->payload);
        $this->assertArrayNotHasKey('code', $log->payload);
        $this->assertArrayNotHasKey('encrypted_ban_note', $log->payload);
        $this->assertArrayNotHasKey('path', $log->payload);
        $this->assertArrayNotHasKey('storage_key', $log->payload);
        $this->assertArrayNotHasKey('public_key', $log->payload);
        $this->assertArrayNotHasKey('fingerprint', $log->payload);
        $this->assertArrayNotHasKey('body', $log->payload);
        $this->assertArrayNotHasKey('encrypted_filename', $log->payload);
    }

    #[Test]
    public function audit_log_handles_null_payload(): void
    {
        $audit = new CommunityAuditService;
        $community = Community::factory()->create();
        $actor = User::factory()->create();

        $audit->log($community, $actor, CommunityAuditLog::ACTION_COMMUNITY_CREATED);

        $log = CommunityAuditLog::where('community_id', $community->id)->first();
        $this->assertNull($log->payload);
    }

    #[Test]
    public function audit_log_records_target_user(): void
    {
        $audit = new CommunityAuditService;
        $community = Community::factory()->create();
        $actor = User::factory()->create();
        $target = User::factory()->create();

        $audit->log($community, $actor, CommunityAuditLog::ACTION_MEMBER_BANNED, null, $target);

        $this->assertDatabaseHas('community_audit_log', [
            'community_id' => $community->id,
            'actor_id' => $actor->id,
            'target_user_id' => $target->id,
            'action' => CommunityAuditLog::ACTION_MEMBER_BANNED,
        ]);
    }

    // -------------------------------------------------------------------------
    // CommunityCreationService
    // -------------------------------------------------------------------------

    #[Test]
    public function creation_creates_community_with_defaults(): void
    {
        $service = new CommunityCreationService(new CommunityAuditService);
        $creator = User::factory()->create();

        $community = $service->create($creator, ['name' => 'Test Community']);

        $this->assertDatabaseHas('communities', ['name' => 'Test Community', 'created_by' => $creator->id]);
        $this->assertTrue($community->allow_posts_in_member_feed);
        $this->assertTrue($community->show_key_fingerprints);
        $this->assertFalse($community->hide_real_names);
        $this->assertFalse($community->anonymous_reactions_enabled);
    }

    #[Test]
    public function creation_creates_owner_member(): void
    {
        $service = new CommunityCreationService(new CommunityAuditService);
        $creator = User::factory()->create();

        $community = $service->create($creator, ['name' => 'Test']);

        $this->assertDatabaseHas('community_members', [
            'community_id' => $community->id,
            'user_id' => $creator->id,
            'role' => CommunityMember::ROLE_OWNER,
            'status' => CommunityMember::STATUS_ACTIVE,
        ]);
    }

    #[Test]
    public function creation_creates_default_general_topic(): void
    {
        $service = new CommunityCreationService(new CommunityAuditService);
        $creator = User::factory()->create();

        $community = $service->create($creator, ['name' => 'Test']);

        $topic = CommunityTopic::where('community_id', $community->id)->first();
        $this->assertNotNull($topic);
        $this->assertEquals('general', $topic->name);
        $this->assertTrue($topic->is_system);
    }

    #[Test]
    public function creation_creates_user_state_for_owner(): void
    {
        $service = new CommunityCreationService(new CommunityAuditService);
        $creator = User::factory()->create();

        $community = $service->create($creator, ['name' => 'Test']);

        $this->assertDatabaseHas('community_user_state', [
            'community_id' => $community->id,
            'user_id' => $creator->id,
        ]);
    }

    #[Test]
    public function creation_creates_initial_key_epoch(): void
    {
        $service = new CommunityCreationService(new CommunityAuditService);
        $creator = User::factory()->create();

        $community = $service->create($creator, ['name' => 'Test']);

        $epoch = CommunityKeyEpoch::where('community_id', $community->id)->first();
        $this->assertNotNull($epoch);
        $this->assertEquals(1, $epoch->epoch_number);
        $this->assertEquals(CommunityKeyEpoch::REASON_INITIAL, $epoch->reason);
    }

    #[Test]
    public function creation_logs_community_created_action(): void
    {
        $service = new CommunityCreationService(new CommunityAuditService);
        $creator = User::factory()->create();

        $community = $service->create($creator, ['name' => 'Test']);

        $this->assertDatabaseHas('community_audit_log', [
            'community_id' => $community->id,
            'actor_id' => $creator->id,
            'action' => CommunityAuditLog::ACTION_COMMUNITY_CREATED,
        ]);
    }

    #[Test]
    public function creation_sets_member_count_to_one(): void
    {
        $service = new CommunityCreationService(new CommunityAuditService);
        $creator = User::factory()->create();

        $community = $service->create($creator, ['name' => 'Test']);

        $this->assertEquals(1, $community->member_count);
    }

    // -------------------------------------------------------------------------
    // CommunityInviteService
    // -------------------------------------------------------------------------

    #[Test]
    public function invite_generate_creates_invite(): void
    {
        $service = new CommunityInviteService(new CommunityPolicyService, new CommunityAuditService);
        $user = User::factory()->create();
        $community = Community::factory()->create(['invite_policy' => Community::INVITE_POLICY_ALL_MEMBERS]);
        CommunityMember::factory()->for($community)->for($user)->create();

        $invite = $service->generateInvite($user, $community);

        $this->assertInstanceOf(CommunityInvite::class, $invite);
        $this->assertDatabaseHas('community_invites', ['community_id' => $community->id, 'created_by' => $user->id]);
    }

    #[Test]
    public function invite_generate_throws_if_not_allowed(): void
    {
        $service = new CommunityInviteService(new CommunityPolicyService, new CommunityAuditService);
        $user = User::factory()->create();
        $community = Community::factory()->create(['invite_policy' => Community::INVITE_POLICY_MODERATORS_ONLY]);
        CommunityMember::factory()->for($community)->for($user)->create(['role' => CommunityMember::ROLE_MEMBER]);

        $this->expectException(InvalidArgumentException::class);

        $service->generateInvite($user, $community);
    }

    #[Test]
    public function invite_generate_logs_action(): void
    {
        $service = new CommunityInviteService(new CommunityPolicyService, new CommunityAuditService);
        $user = User::factory()->create();
        $community = Community::factory()->create(['invite_policy' => Community::INVITE_POLICY_ALL_MEMBERS]);
        CommunityMember::factory()->for($community)->for($user)->create();

        $service->generateInvite($user, $community);

        $this->assertDatabaseHas('community_audit_log', [
            'community_id' => $community->id,
            'action' => CommunityAuditLog::ACTION_INVITE_CREATED,
        ]);
    }

    #[Test]
    public function invite_revoke_marks_invite_revoked(): void
    {
        $service = new CommunityInviteService(new CommunityPolicyService, new CommunityAuditService);
        $user = User::factory()->create();
        $community = Community::factory()->create(['invite_policy' => Community::INVITE_POLICY_ALL_MEMBERS]);
        CommunityMember::factory()->for($community)->for($user)->create();
        $invite = CommunityInvite::factory()->for($community)->create(['created_by' => $user->id]);

        $service->revokeInvite($user, $invite);

        $invite->refresh();
        $this->assertTrue($invite->is_revoked);
        $this->assertNotNull($invite->revoked_at);
    }

    #[Test]
    public function invite_revoke_logs_action(): void
    {
        $service = new CommunityInviteService(new CommunityPolicyService, new CommunityAuditService);
        $user = User::factory()->create();
        $community = Community::factory()->create(['invite_policy' => Community::INVITE_POLICY_ALL_MEMBERS]);
        CommunityMember::factory()->for($community)->for($user)->create();
        $invite = CommunityInvite::factory()->for($community)->create(['created_by' => $user->id]);

        $service->revokeInvite($user, $invite);

        $this->assertDatabaseHas('community_audit_log', [
            'community_id' => $community->id,
            'action' => CommunityAuditLog::ACTION_INVITE_REVOKED,
        ]);
    }

    // -------------------------------------------------------------------------
    // CommunityJoinService
    // -------------------------------------------------------------------------

    #[Test]
    public function join_public_adds_member_with_pending_key_delivery_status(): void
    {
        $service = new CommunityJoinService(new CommunityPolicyService, new CommunityAuditService);
        $user = User::factory()->create();
        $community = Community::factory()->create(['join_mode' => Community::JOIN_OPEN, 'member_count' => 1]);

        $member = $service->joinPublic($user, $community);

        $this->assertEquals(CommunityMember::STATUS_PENDING_KEY_DELIVERY, $member->status);
        $this->assertDatabaseHas('community_members', [
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);
    }

    #[Test]
    public function join_public_increments_member_count(): void
    {
        $service = new CommunityJoinService(new CommunityPolicyService, new CommunityAuditService);
        $user = User::factory()->create();
        $community = Community::factory()->create(['join_mode' => Community::JOIN_OPEN, 'member_count' => 5]);

        $service->joinPublic($user, $community);

        $this->assertEquals(6, $community->fresh()->member_count);
    }

    #[Test]
    public function join_public_throws_if_community_not_open(): void
    {
        $service = new CommunityJoinService(new CommunityPolicyService, new CommunityAuditService);
        $user = User::factory()->create();
        $community = Community::factory()->create(['join_mode' => Community::JOIN_INVITE_ONLY]);

        $this->expectException(InvalidArgumentException::class);

        $service->joinPublic($user, $community);
    }

    #[Test]
    public function join_public_throws_if_already_active_member(): void
    {
        $service = new CommunityJoinService(new CommunityPolicyService, new CommunityAuditService);
        $user = User::factory()->create();
        $community = Community::factory()->create(['join_mode' => Community::JOIN_OPEN]);
        CommunityMember::factory()->for($community)->for($user)->create();

        $this->expectException(InvalidArgumentException::class);

        $service->joinPublic($user, $community);
    }

    #[Test]
    public function join_public_throws_if_member_limit_reached(): void
    {
        $service = new CommunityJoinService(new CommunityPolicyService, new CommunityAuditService);
        $user = User::factory()->create();
        $community = Community::factory()->create([
            'join_mode' => Community::JOIN_OPEN,
            'member_count' => 5,
            'member_limit' => 5,
        ]);

        $this->expectException(RuntimeException::class);

        $service->joinPublic($user, $community);
    }

    #[Test]
    public function join_public_creates_user_state(): void
    {
        $service = new CommunityJoinService(new CommunityPolicyService, new CommunityAuditService);
        $user = User::factory()->create();
        $community = Community::factory()->create(['join_mode' => Community::JOIN_OPEN, 'member_count' => 1]);

        $service->joinPublic($user, $community);

        $this->assertDatabaseHas('community_user_state', [
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);
    }

    #[Test]
    public function join_public_logs_member_joined(): void
    {
        $service = new CommunityJoinService(new CommunityPolicyService, new CommunityAuditService);
        $user = User::factory()->create();
        $community = Community::factory()->create(['join_mode' => Community::JOIN_OPEN, 'member_count' => 1]);

        $service->joinPublic($user, $community);

        $this->assertDatabaseHas('community_audit_log', [
            'community_id' => $community->id,
            'action' => CommunityAuditLog::ACTION_MEMBER_JOINED,
        ]);
    }

    #[Test]
    public function join_by_invite_uses_valid_code(): void
    {
        $service = new CommunityJoinService(new CommunityPolicyService, new CommunityAuditService);
        $user = User::factory()->create();
        $community = Community::factory()->create(['member_count' => 1]);
        $invite = CommunityInvite::factory()->for($community)->create();

        $service->joinByInvite($user, $invite->code);

        $this->assertDatabaseHas('community_members', [
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseHas('community_invite_uses', [
            'invite_id' => $invite->id,
            'user_id' => $user->id,
        ]);
    }

    #[Test]
    public function join_by_invite_increments_use_count(): void
    {
        $service = new CommunityJoinService(new CommunityPolicyService, new CommunityAuditService);
        $user = User::factory()->create();
        $community = Community::factory()->create(['member_count' => 1]);
        $invite = CommunityInvite::factory()->for($community)->create(['use_count' => 0]);

        $service->joinByInvite($user, $invite->code);

        $this->assertEquals(1, $invite->fresh()->use_count);
    }

    #[Test]
    public function join_by_invite_throws_for_invalid_code(): void
    {
        $service = new CommunityJoinService(new CommunityPolicyService, new CommunityAuditService);
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        $service->joinByInvite($user, 'INVALIDCODE');
    }

    #[Test]
    public function join_by_invite_throws_for_revoked_invite(): void
    {
        $service = new CommunityJoinService(new CommunityPolicyService, new CommunityAuditService);
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $invite = CommunityInvite::factory()->for($community)->revoked()->create();

        $this->expectException(InvalidArgumentException::class);

        $service->joinByInvite($user, $invite->code);
    }

    #[Test]
    public function join_by_invite_throws_for_expired_invite(): void
    {
        $service = new CommunityJoinService(new CommunityPolicyService, new CommunityAuditService);
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $invite = CommunityInvite::factory()->for($community)->expired()->create();

        $this->expectException(InvalidArgumentException::class);

        $service->joinByInvite($user, $invite->code);
    }

    #[Test]
    public function request_join_creates_pending_request(): void
    {
        $service = new CommunityJoinService(new CommunityPolicyService, new CommunityAuditService);
        $user = User::factory()->create();
        $community = Community::factory()->create(['join_mode' => Community::JOIN_REQUEST]);

        $request = $service->requestJoin($user, $community, 'Please let me in');

        $this->assertEquals(CommunityJoinRequest::STATUS_PENDING, $request->status);
        $this->assertDatabaseHas('community_join_requests', [
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => CommunityJoinRequest::STATUS_PENDING,
        ]);
    }

    #[Test]
    public function request_join_throws_if_community_not_request_mode(): void
    {
        $service = new CommunityJoinService(new CommunityPolicyService, new CommunityAuditService);
        $user = User::factory()->create();
        $community = Community::factory()->create(['join_mode' => Community::JOIN_OPEN]);

        $this->expectException(InvalidArgumentException::class);

        $service->requestJoin($user, $community);
    }

    #[Test]
    public function request_join_throws_if_duplicate_pending_request(): void
    {
        $service = new CommunityJoinService(new CommunityPolicyService, new CommunityAuditService);
        $user = User::factory()->create();
        $community = Community::factory()->create(['join_mode' => Community::JOIN_REQUEST]);
        CommunityJoinRequest::factory()->for($community)->for($user)->create(['status' => CommunityJoinRequest::STATUS_PENDING]);

        $this->expectException(InvalidArgumentException::class);

        $service->requestJoin($user, $community);
    }

    #[Test]
    public function approve_join_request_transitions_to_pending_key_delivery(): void
    {
        $service = new CommunityJoinService(new CommunityPolicyService, new CommunityAuditService);
        $modUser = User::factory()->create();
        $applicant = User::factory()->create();
        $community = Community::factory()->create(['join_mode' => Community::JOIN_REQUEST, 'member_count' => 1]);
        CommunityMember::factory()->for($community)->for($modUser)->create(['role' => CommunityMember::ROLE_MODERATOR]);
        $joinRequest = CommunityJoinRequest::factory()->for($community)->for($applicant)->create(['status' => CommunityJoinRequest::STATUS_PENDING]);

        $member = $service->approveJoinRequest($modUser, $joinRequest);

        $this->assertEquals(CommunityMember::STATUS_PENDING_KEY_DELIVERY, $member->status);
        $this->assertEquals(CommunityJoinRequest::STATUS_APPROVED, $joinRequest->fresh()->status);
    }

    #[Test]
    public function approve_join_request_throws_if_actor_not_moderator(): void
    {
        $service = new CommunityJoinService(new CommunityPolicyService, new CommunityAuditService);
        $memberUser = User::factory()->create();
        $applicant = User::factory()->create();
        $community = Community::factory()->create(['join_mode' => Community::JOIN_REQUEST, 'member_count' => 1]);
        CommunityMember::factory()->for($community)->for($memberUser)->create(['role' => CommunityMember::ROLE_MEMBER]);
        $joinRequest = CommunityJoinRequest::factory()->for($community)->for($applicant)->create(['status' => CommunityJoinRequest::STATUS_PENDING]);

        $this->expectException(InvalidArgumentException::class);

        $service->approveJoinRequest($memberUser, $joinRequest);
    }

    #[Test]
    public function approve_join_request_throws_if_not_pending(): void
    {
        $service = new CommunityJoinService(new CommunityPolicyService, new CommunityAuditService);
        $modUser = User::factory()->create();
        $applicant = User::factory()->create();
        $community = Community::factory()->create(['member_count' => 1]);
        CommunityMember::factory()->for($community)->for($modUser)->create(['role' => CommunityMember::ROLE_MODERATOR]);
        $joinRequest = CommunityJoinRequest::factory()->for($community)->for($applicant)->create(['status' => CommunityJoinRequest::STATUS_APPROVED]);

        $this->expectException(InvalidArgumentException::class);

        $service->approveJoinRequest($modUser, $joinRequest);
    }

    #[Test]
    public function approve_join_request_logs_action(): void
    {
        $service = new CommunityJoinService(new CommunityPolicyService, new CommunityAuditService);
        $modUser = User::factory()->create();
        $applicant = User::factory()->create();
        $community = Community::factory()->create(['member_count' => 1]);
        CommunityMember::factory()->for($community)->for($modUser)->create(['role' => CommunityMember::ROLE_MODERATOR]);
        $joinRequest = CommunityJoinRequest::factory()->for($community)->for($applicant)->create(['status' => CommunityJoinRequest::STATUS_PENDING]);

        $service->approveJoinRequest($modUser, $joinRequest);

        $this->assertDatabaseHas('community_audit_log', [
            'community_id' => $community->id,
            'action' => CommunityAuditLog::ACTION_JOIN_REQUEST_APPROVED,
        ]);
    }

    // -------------------------------------------------------------------------
    // CommunityDirectInviteService
    // -------------------------------------------------------------------------

    private function makeDirectInviteService(): CommunityDirectInviteService
    {
        return new CommunityDirectInviteService(new CommunityPolicyService, new CommunityAuditService);
    }

    #[Test]
    public function direct_invite_friend_active_member_can_invite_when_all_members(): void
    {
        $service = $this->makeDirectInviteService();
        $inviter = User::factory()->create();
        $invitee = User::factory()->create();
        $community = Community::factory()->create(['invite_policy' => Community::INVITE_POLICY_ALL_MEMBERS]);
        CommunityMember::factory()->for($community)->for($inviter)->create(['status' => CommunityMember::STATUS_ACTIVE]);
        $this->befriend($inviter, $invitee);

        $invite = $service->sendInvite($inviter, $community, $invitee, 'Join us');

        $this->assertInstanceOf(CommunityDirectInvite::class, $invite);
        $this->assertEquals(CommunityDirectInvite::STATUS_PENDING, $invite->status);
        $this->assertDatabaseHas('community_audit_log', [
            'community_id' => $community->id,
            'action' => CommunityAuditLog::ACTION_DIRECT_INVITE_CREATED,
        ]);
        $this->assertDatabaseMissing('community_audit_log', ['payload->message' => 'Join us']);
    }

    #[Test]
    public function direct_invite_non_friend_cannot_invite(): void
    {
        $service = $this->makeDirectInviteService();
        $inviter = User::factory()->create();
        $invitee = User::factory()->create();
        $community = Community::factory()->create(['invite_policy' => Community::INVITE_POLICY_ALL_MEMBERS]);
        CommunityMember::factory()->for($community)->for($inviter)->create(['status' => CommunityMember::STATUS_ACTIVE]);

        $this->expectException(InvalidArgumentException::class);

        $service->sendInvite($inviter, $community, $invitee);
    }

    #[Test]
    public function direct_invite_regular_member_cannot_invite_when_moderators_only(): void
    {
        $service = $this->makeDirectInviteService();
        $inviter = User::factory()->create();
        $invitee = User::factory()->create();
        $community = Community::factory()->create(['invite_policy' => Community::INVITE_POLICY_MODERATORS_ONLY]);
        CommunityMember::factory()->for($community)->for($inviter)->create([
            'role' => CommunityMember::ROLE_MEMBER,
            'status' => CommunityMember::STATUS_ACTIVE,
        ]);
        $this->befriend($inviter, $invitee);

        $this->expectException(InvalidArgumentException::class);

        $service->sendInvite($inviter, $community, $invitee);
    }

    #[Test]
    public function direct_invite_moderator_can_invite_when_moderators_only(): void
    {
        $service = $this->makeDirectInviteService();
        $inviter = User::factory()->create();
        $invitee = User::factory()->create();
        $community = Community::factory()->create(['invite_policy' => Community::INVITE_POLICY_MODERATORS_ONLY]);
        CommunityMember::factory()->for($community)->for($inviter)->create([
            'role' => CommunityMember::ROLE_MODERATOR,
            'status' => CommunityMember::STATUS_ACTIVE,
        ]);
        $this->befriend($inviter, $invitee);

        $invite = $service->sendInvite($inviter, $community, $invitee);

        $this->assertModelExists($invite);
    }

    #[Test]
    public function direct_invite_cannot_invite_self(): void
    {
        $service = $this->makeDirectInviteService();
        $inviter = User::factory()->create();
        $community = Community::factory()->create(['invite_policy' => Community::INVITE_POLICY_ALL_MEMBERS]);
        CommunityMember::factory()->for($community)->for($inviter)->create(['status' => CommunityMember::STATUS_ACTIVE]);

        $this->expectException(InvalidArgumentException::class);

        $service->sendInvite($inviter, $community, $inviter);
    }

    #[Test]
    public function direct_invite_cannot_invite_existing_active_member(): void
    {
        $service = $this->makeDirectInviteService();
        $inviter = User::factory()->create();
        $invitee = User::factory()->create();
        $community = Community::factory()->create(['invite_policy' => Community::INVITE_POLICY_ALL_MEMBERS]);
        CommunityMember::factory()->for($community)->for($inviter)->create(['status' => CommunityMember::STATUS_ACTIVE]);
        CommunityMember::factory()->for($community)->for($invitee)->create(['status' => CommunityMember::STATUS_ACTIVE]);
        $this->befriend($inviter, $invitee);

        $this->expectException(InvalidArgumentException::class);

        $service->sendInvite($inviter, $community, $invitee);
    }

    #[Test]
    public function direct_invite_cannot_invite_pending_key_delivery_member(): void
    {
        $service = $this->makeDirectInviteService();
        $inviter = User::factory()->create();
        $invitee = User::factory()->create();
        $community = Community::factory()->create(['invite_policy' => Community::INVITE_POLICY_ALL_MEMBERS]);
        CommunityMember::factory()->for($community)->for($inviter)->create(['status' => CommunityMember::STATUS_ACTIVE]);
        CommunityMember::factory()->for($community)->for($invitee)->pendingKeyDelivery()->create();
        $this->befriend($inviter, $invitee);

        $this->expectException(InvalidArgumentException::class);

        $service->sendInvite($inviter, $community, $invitee);
    }

    #[Test]
    public function direct_invite_cannot_duplicate_pending_invite(): void
    {
        $service = $this->makeDirectInviteService();
        $inviter = User::factory()->create();
        $invitee = User::factory()->create();
        $community = Community::factory()->create(['invite_policy' => Community::INVITE_POLICY_ALL_MEMBERS]);
        CommunityMember::factory()->for($community)->for($inviter)->create(['status' => CommunityMember::STATUS_ACTIVE]);
        $this->befriend($inviter, $invitee);
        $service->sendInvite($inviter, $community, $invitee);

        $this->expectException(InvalidArgumentException::class);

        $service->sendInvite($inviter, $community, $invitee);
    }

    #[Test]
    public function direct_invitee_can_accept_invite(): void
    {
        $service = $this->makeDirectInviteService();
        $inviter = User::factory()->create();
        $invitee = User::factory()->create();
        $community = Community::factory()->create(['member_count' => 1]);
        $invite = CommunityDirectInvite::factory()->pending()->for($community)->create([
            'inviter_id' => $inviter->id,
            'invitee_id' => $invitee->id,
        ]);

        $member = $service->acceptInvite($invitee, $invite);

        $this->assertEquals(CommunityDirectInvite::STATUS_ACCEPTED, $invite->fresh()->status);
        $this->assertNotNull($invite->fresh()->responded_at);
        $this->assertEquals(CommunityMember::STATUS_PENDING_KEY_DELIVERY, $member->status);
        $this->assertDatabaseHas('community_user_state', [
            'community_id' => $community->id,
            'user_id' => $invitee->id,
        ]);
        $this->assertDatabaseHas('community_audit_log', [
            'community_id' => $community->id,
            'action' => CommunityAuditLog::ACTION_DIRECT_INVITE_ACCEPTED,
        ]);
    }

    #[Test]
    public function direct_invite_non_invitee_cannot_accept(): void
    {
        $service = $this->makeDirectInviteService();
        $stranger = User::factory()->create();
        $invite = CommunityDirectInvite::factory()->pending()->create();

        $this->expectException(InvalidArgumentException::class);

        $service->acceptInvite($stranger, $invite);
    }

    #[Test]
    public function direct_invite_accept_creates_pending_key_delivery_member(): void
    {
        $service = $this->makeDirectInviteService();
        $invitee = User::factory()->create();
        $invite = CommunityDirectInvite::factory()->pending()->create(['invitee_id' => $invitee->id]);

        $member = $service->acceptInvite($invitee, $invite);

        $this->assertEquals(CommunityMember::STATUS_PENDING_KEY_DELIVERY, $member->status);
    }

    #[Test]
    public function direct_invite_accept_respects_member_limit(): void
    {
        $service = $this->makeDirectInviteService();
        $invitee = User::factory()->create();
        $community = Community::factory()->create(['member_count' => 5, 'member_limit' => 5]);
        $invite = CommunityDirectInvite::factory()->pending()->for($community)->create(['invitee_id' => $invitee->id]);

        $this->expectException(RuntimeException::class);

        $service->acceptInvite($invitee, $invite);
    }

    #[Test]
    public function direct_invitee_can_decline_invite(): void
    {
        $service = $this->makeDirectInviteService();
        $invitee = User::factory()->create();
        $invite = CommunityDirectInvite::factory()->pending()->create(['invitee_id' => $invitee->id]);

        $service->declineInvite($invitee, $invite);

        $this->assertEquals(CommunityDirectInvite::STATUS_DECLINED, $invite->fresh()->status);
        $this->assertNotNull($invite->fresh()->responded_at);
        $this->assertDatabaseHas('community_audit_log', [
            'community_id' => $invite->community_id,
            'action' => CommunityAuditLog::ACTION_DIRECT_INVITE_DECLINED,
        ]);
    }

    #[Test]
    public function direct_inviter_can_cancel_invite(): void
    {
        $service = $this->makeDirectInviteService();
        $inviter = User::factory()->create();
        $invite = CommunityDirectInvite::factory()->pending()->create(['inviter_id' => $inviter->id]);

        $service->cancelInvite($inviter, $invite);

        $this->assertEquals(CommunityDirectInvite::STATUS_CANCELLED, $invite->fresh()->status);
        $this->assertDatabaseHas('community_audit_log', [
            'community_id' => $invite->community_id,
            'action' => CommunityAuditLog::ACTION_DIRECT_INVITE_CANCELLED,
        ]);
    }

    #[Test]
    public function direct_invite_moderator_admin_owner_can_cancel_invite(): void
    {
        $service = $this->makeDirectInviteService();

        foreach ([CommunityMember::ROLE_MODERATOR, CommunityMember::ROLE_ADMIN, CommunityMember::ROLE_OWNER] as $role) {
            $community = Community::factory()->create();
            $actor = User::factory()->create();
            CommunityMember::factory()->for($community)->for($actor)->create([
                'role' => $role,
                'status' => CommunityMember::STATUS_ACTIVE,
            ]);
            $invite = CommunityDirectInvite::factory()->pending()->for($community)->create();

            $service->cancelInvite($actor, $invite);

            $this->assertEquals(CommunityDirectInvite::STATUS_CANCELLED, $invite->fresh()->status);
        }
    }

    #[Test]
    public function direct_invite_stranger_cannot_cancel_invite(): void
    {
        $service = $this->makeDirectInviteService();
        $stranger = User::factory()->create();
        $invite = CommunityDirectInvite::factory()->pending()->create();

        $this->expectException(InvalidArgumentException::class);

        $service->cancelInvite($stranger, $invite);
    }

    #[Test]
    public function expired_direct_invite_cannot_be_accepted(): void
    {
        $service = $this->makeDirectInviteService();
        $invitee = User::factory()->create();
        $invite = CommunityDirectInvite::factory()->expired()->create(['invitee_id' => $invitee->id]);

        $this->expectException(InvalidArgumentException::class);

        $service->acceptInvite($invitee, $invite);
    }

    #[Test]
    public function direct_invite_list_pending_for_user_returns_non_expired_with_relations(): void
    {
        $service = $this->makeDirectInviteService();
        $invitee = User::factory()->create();
        $pending = CommunityDirectInvite::factory()->pending()->create(['invitee_id' => $invitee->id]);
        CommunityDirectInvite::factory()->expired()->create(['invitee_id' => $invitee->id]);
        CommunityDirectInvite::factory()->declined()->create(['invitee_id' => $invitee->id]);

        $invites = $service->listPendingForUser($invitee);

        $this->assertCount(1, $invites);
        $this->assertTrue($invites->first()->is($pending));
        $this->assertTrue($invites->first()->relationLoaded('inviter'));
        $this->assertTrue($invites->first()->relationLoaded('community'));
    }

    // -------------------------------------------------------------------------
    // CommunityKeyDeliveryService
    // -------------------------------------------------------------------------

    #[Test]
    public function key_delivery_pending_members_returns_pending_key_delivery_members(): void
    {
        $service = new CommunityKeyDeliveryService(new CommunityPolicyService, new CommunityAuditService);
        $community = Community::factory()->create();
        $activeUser = User::factory()->create();
        $pendingUser = User::factory()->create();

        CommunityMember::factory()->for($community)->for($activeUser)->create(['status' => CommunityMember::STATUS_ACTIVE]);
        CommunityMember::factory()->for($community)->for($pendingUser)->pendingKeyDelivery()->create();

        $result = $service->pendingMembers($community);

        $this->assertCount(1, $result);
        $this->assertEquals($pendingUser->id, $result->first()->user_id);
    }

    #[Test]
    public function key_delivery_delivers_keys_for_each_device(): void
    {
        $service = new CommunityKeyDeliveryService(new CommunityPolicyService, new CommunityAuditService);
        $actor = User::factory()->create();
        $memberUser = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($actor)->create(['role' => CommunityMember::ROLE_OWNER]);
        $member = CommunityMember::factory()->for($community)->for($memberUser)->pendingKeyDelivery()->create();
        CommunityKeyEpoch::factory()->for($community)->create(['epoch_number' => 1]);
        $deviceKey = UserDeviceKey::factory()->for($memberUser)->create();

        $service->deliverMemberKeys($actor, $member, [
            ['device_key_id' => $deviceKey->id, 'encrypted_key' => 'enc_key_data'],
        ]);

        $this->assertDatabaseHas('community_member_keys', [
            'community_id' => $community->id,
            'user_id' => $memberUser->id,
            'device_key_id' => $deviceKey->id,
        ]);
    }

    #[Test]
    public function key_delivery_throws_if_device_key_belongs_to_wrong_user(): void
    {
        $service = new CommunityKeyDeliveryService(new CommunityPolicyService, new CommunityAuditService);
        $actor = User::factory()->create();
        $memberUser = User::factory()->create();
        $otherUser = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($actor)->create(['role' => CommunityMember::ROLE_OWNER]);
        $member = CommunityMember::factory()->for($community)->for($memberUser)->pendingKeyDelivery()->create();
        CommunityKeyEpoch::factory()->for($community)->create();
        $otherDeviceKey = UserDeviceKey::factory()->for($otherUser)->create();

        $this->expectException(InvalidArgumentException::class);

        $service->deliverMemberKeys($actor, $member, [
            ['device_key_id' => $otherDeviceKey->id, 'encrypted_key' => 'enc_key'],
        ]);
    }

    #[Test]
    public function key_delivery_throws_if_device_key_is_revoked(): void
    {
        $service = new CommunityKeyDeliveryService(new CommunityPolicyService, new CommunityAuditService);
        $actor = User::factory()->create();
        $memberUser = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($actor)->create(['role' => CommunityMember::ROLE_OWNER]);
        $member = CommunityMember::factory()->for($community)->for($memberUser)->pendingKeyDelivery()->create();
        CommunityKeyEpoch::factory()->for($community)->create();
        $revokedKey = UserDeviceKey::factory()->for($memberUser)->create(['revoked_at' => now()]);

        $this->expectException(InvalidArgumentException::class);

        $service->deliverMemberKeys($actor, $member, [
            ['device_key_id' => $revokedKey->id, 'encrypted_key' => 'enc_key'],
        ]);
    }

    #[Test]
    public function key_delivery_logs_action(): void
    {
        $service = new CommunityKeyDeliveryService(new CommunityPolicyService, new CommunityAuditService);
        $actor = User::factory()->create();
        $memberUser = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($actor)->create(['role' => CommunityMember::ROLE_OWNER]);
        $member = CommunityMember::factory()->for($community)->for($memberUser)->pendingKeyDelivery()->create();
        CommunityKeyEpoch::factory()->for($community)->create(['epoch_number' => 1]);
        $deviceKey = UserDeviceKey::factory()->for($memberUser)->create();

        $service->deliverMemberKeys($actor, $member, [
            ['device_key_id' => $deviceKey->id, 'encrypted_key' => 'enc_key'],
        ]);

        $this->assertDatabaseHas('community_audit_log', [
            'community_id' => $community->id,
            'action' => CommunityAuditLog::ACTION_KEY_DELIVERED,
        ]);
    }

    #[Test]
    public function activate_member_if_all_device_keys_delivered_activates_member(): void
    {
        $service = new CommunityKeyDeliveryService(new CommunityPolicyService, new CommunityAuditService);
        $memberUser = User::factory()->create();
        $community = Community::factory()->create();
        $member = CommunityMember::factory()->for($community)->for($memberUser)->pendingKeyDelivery()->create();
        $epoch = CommunityKeyEpoch::factory()->for($community)->create(['epoch_number' => 1]);
        $deviceKey = UserDeviceKey::factory()->for($memberUser)->create();

        CommunityMemberKey::factory()->for($community)->for($memberUser)->create([
            'epoch_id' => $epoch->id,
            'device_key_id' => $deviceKey->id,
        ]);

        $activated = $service->activateMemberIfAllDeviceKeysDelivered($member);

        $this->assertTrue($activated);
        $this->assertEquals(CommunityMember::STATUS_ACTIVE, $member->fresh()->status);
    }

    #[Test]
    public function activate_member_returns_false_if_not_all_keys_delivered(): void
    {
        $service = new CommunityKeyDeliveryService(new CommunityPolicyService, new CommunityAuditService);
        $memberUser = User::factory()->create();
        $community = Community::factory()->create();
        $member = CommunityMember::factory()->for($community)->for($memberUser)->pendingKeyDelivery()->create();
        CommunityKeyEpoch::factory()->for($community)->create(['epoch_number' => 1]);
        UserDeviceKey::factory()->for($memberUser)->create();
        UserDeviceKey::factory()->for($memberUser)->create();

        $activated = $service->activateMemberIfAllDeviceKeysDelivered($member);

        $this->assertFalse($activated);
        $this->assertEquals(CommunityMember::STATUS_PENDING_KEY_DELIVERY, $member->fresh()->status);
    }

    // -------------------------------------------------------------------------
    // CommunityPostService
    // -------------------------------------------------------------------------

    private function makePostService(): CommunityPostService
    {
        return new CommunityPostService(new CommunityPolicyService);
    }

    #[Test]
    public function post_publish_creates_encrypted_post(): void
    {
        $service = $this->makePostService();
        $user = User::factory()->create();
        $community = Community::factory()->create(['post_count' => 0]);
        CommunityMember::factory()->for($community)->for($user)->create();
        $topic = CommunityTopic::factory()->for($community)->create(['post_count' => 0]);
        $epoch = CommunityKeyEpoch::factory()->for($community)->create();

        $post = $service->publishEncryptedPost($user, $community, $topic, [
            'ciphertext' => 'enc_content',
            'nonce' => 'nonce123',
            'epoch_id' => $epoch->id,
        ]);

        $this->assertInstanceOf(CommunityPost::class, $post);
        $this->assertDatabaseHas('community_posts', [
            'community_id' => $community->id,
            'user_id' => $user->id,
            'ciphertext' => 'enc_content',
            'nonce' => 'nonce123',
        ]);
    }

    #[Test]
    public function post_publish_assigns_community_and_topic_seq(): void
    {
        $service = $this->makePostService();
        $user = User::factory()->create();
        $community = Community::factory()->create(['post_count' => 5]);
        CommunityMember::factory()->for($community)->for($user)->create();
        $topic = CommunityTopic::factory()->for($community)->create(['post_count' => 3]);
        $epoch = CommunityKeyEpoch::factory()->for($community)->create();

        $post = $service->publishEncryptedPost($user, $community, $topic, [
            'ciphertext' => 'c',
            'nonce' => 'n',
            'epoch_id' => $epoch->id,
        ]);

        $this->assertEquals(6, $post->community_seq);
        $this->assertEquals(4, $post->topic_seq);
    }

    #[Test]
    public function post_publish_increments_community_and_topic_post_counts(): void
    {
        $service = $this->makePostService();
        $user = User::factory()->create();
        $community = Community::factory()->create(['post_count' => 2]);
        CommunityMember::factory()->for($community)->for($user)->create();
        $topic = CommunityTopic::factory()->for($community)->create(['post_count' => 1]);
        $epoch = CommunityKeyEpoch::factory()->for($community)->create();

        $service->publishEncryptedPost($user, $community, $topic, [
            'ciphertext' => 'c',
            'nonce' => 'n',
            'epoch_id' => $epoch->id,
        ]);

        $this->assertEquals(3, $community->fresh()->post_count);
        $this->assertEquals(2, $topic->fresh()->post_count);
    }

    #[Test]
    public function post_publish_throws_if_user_not_active_member(): void
    {
        $service = $this->makePostService();
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $topic = CommunityTopic::factory()->for($community)->create();
        $epoch = CommunityKeyEpoch::factory()->for($community)->create();

        $this->expectException(InvalidArgumentException::class);

        $service->publishEncryptedPost($user, $community, $topic, [
            'ciphertext' => 'c',
            'nonce' => 'n',
            'epoch_id' => $epoch->id,
        ]);
    }

    #[Test]
    public function post_publish_throws_if_topic_is_archived(): void
    {
        $service = $this->makePostService();
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($user)->create();
        $topic = CommunityTopic::factory()->for($community)->create(['is_archived' => true]);
        $epoch = CommunityKeyEpoch::factory()->for($community)->create();

        $this->expectException(InvalidArgumentException::class);

        $service->publishEncryptedPost($user, $community, $topic, [
            'ciphertext' => 'c',
            'nonce' => 'n',
            'epoch_id' => $epoch->id,
        ]);
    }

    #[Test]
    public function post_publish_throws_if_topic_moderators_only_and_user_is_member(): void
    {
        $service = $this->makePostService();
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($user)->create(['role' => CommunityMember::ROLE_MEMBER]);
        $topic = CommunityTopic::factory()->for($community)->create([
            'posting_policy' => CommunityTopic::POSTING_POLICY_MODERATORS_ONLY,
        ]);
        $epoch = CommunityKeyEpoch::factory()->for($community)->create();

        $this->expectException(InvalidArgumentException::class);

        $service->publishEncryptedPost($user, $community, $topic, [
            'ciphertext' => 'c',
            'nonce' => 'n',
            'epoch_id' => $epoch->id,
        ]);
    }

    #[Test]
    public function post_publish_no_plaintext_body_stored(): void
    {
        $service = $this->makePostService();
        $user = User::factory()->create();
        $community = Community::factory()->create(['post_count' => 0]);
        CommunityMember::factory()->for($community)->for($user)->create();
        $topic = CommunityTopic::factory()->for($community)->create(['post_count' => 0]);
        $epoch = CommunityKeyEpoch::factory()->for($community)->create();

        $post = $service->publishEncryptedPost($user, $community, $topic, [
            'ciphertext' => 'c',
            'nonce' => 'n',
            'epoch_id' => $epoch->id,
        ]);

        $this->assertArrayNotHasKey('body', $post->toArray());
    }

    #[Test]
    public function post_publish_idempotency_key_returns_existing_post(): void
    {
        $service = $this->makePostService();
        $user = User::factory()->create();
        $community = Community::factory()->create(['post_count' => 0]);
        CommunityMember::factory()->for($community)->for($user)->create();
        $topic = CommunityTopic::factory()->for($community)->create(['post_count' => 0]);
        $epoch = CommunityKeyEpoch::factory()->for($community)->create();

        $payload = [
            'ciphertext' => 'c',
            'nonce' => 'n',
            'epoch_id' => $epoch->id,
            'client_idempotency_key' => 'unique-key-abc',
        ];

        $post1 = $service->publishEncryptedPost($user, $community, $topic, $payload);
        $post2 = $service->publishEncryptedPost($user, $community, $topic, $payload);

        $this->assertEquals($post1->id, $post2->id);
        $this->assertEquals(1, CommunityPost::where('community_id', $community->id)->count());
    }

    #[Test]
    public function post_publish_throws_if_ttl_seconds_not_positive(): void
    {
        $service = $this->makePostService();
        $user = User::factory()->create();
        $community = Community::factory()->create(['post_count' => 0]);
        CommunityMember::factory()->for($community)->for($user)->create();
        $topic = CommunityTopic::factory()->for($community)->create(['post_count' => 0]);
        $epoch = CommunityKeyEpoch::factory()->for($community)->create();

        $this->expectException(InvalidArgumentException::class);

        $service->publishEncryptedPost($user, $community, $topic, [
            'ciphertext' => 'c',
            'nonce' => 'n',
            'epoch_id' => $epoch->id,
            'ttl_seconds' => 0,
        ]);
    }

    #[Test]
    public function post_publish_sets_expires_at_from_ttl_seconds(): void
    {
        $service = $this->makePostService();
        $user = User::factory()->create();
        $community = Community::factory()->create(['post_count' => 0]);
        CommunityMember::factory()->for($community)->for($user)->create();
        $topic = CommunityTopic::factory()->for($community)->create(['post_count' => 0]);
        $epoch = CommunityKeyEpoch::factory()->for($community)->create();

        $post = $service->publishEncryptedPost($user, $community, $topic, [
            'ciphertext' => 'c',
            'nonce' => 'n',
            'epoch_id' => $epoch->id,
            'ttl_seconds' => 3600,
        ]);

        $this->assertNotNull($post->expires_at);
        $this->assertEqualsWithDelta(now()->addHour()->timestamp, $post->expires_at->timestamp, 5);
    }

    // -------------------------------------------------------------------------
    // CommunityReadStateService
    // -------------------------------------------------------------------------

    #[Test]
    public function read_state_mark_topic_read_updates_seq(): void
    {
        $service = new CommunityReadStateService(new CommunityPolicyService);
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($user)->create();
        $topic = CommunityTopic::factory()->for($community)->create();

        CommunityTopicUserState::create([
            'community_id' => $community->id,
            'topic_id' => $topic->id,
            'user_id' => $user->id,
            'last_read_topic_seq' => 0,
            'muted' => false,
            'notifications_enabled' => true,
            'unread_count' => 0,
        ]);

        $service->markTopicRead($user, $topic, 10);

        $this->assertDatabaseHas('community_topic_user_state', [
            'topic_id' => $topic->id,
            'user_id' => $user->id,
            'last_read_topic_seq' => 10,
        ]);
    }

    #[Test]
    public function read_state_mark_topic_read_does_not_go_backwards(): void
    {
        $service = new CommunityReadStateService(new CommunityPolicyService);
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($user)->create();
        $topic = CommunityTopic::factory()->for($community)->create();

        CommunityTopicUserState::create([
            'community_id' => $community->id,
            'topic_id' => $topic->id,
            'user_id' => $user->id,
            'last_read_topic_seq' => 20,
            'muted' => false,
            'notifications_enabled' => true,
            'unread_count' => 0,
        ]);

        $service->markTopicRead($user, $topic, 5);

        $this->assertDatabaseHas('community_topic_user_state', [
            'topic_id' => $topic->id,
            'user_id' => $user->id,
            'last_read_topic_seq' => 20,
        ]);
    }

    #[Test]
    public function read_state_mark_community_read_updates_seq(): void
    {
        $service = new CommunityReadStateService(new CommunityPolicyService);
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($user)->create();

        CommunityUserState::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'notifications_enabled' => true,
            'muted' => false,
            'unread_posts_count' => 0,
            'pinned' => false,
            'last_read_community_seq' => 0,
        ]);

        $service->markCommunityRead($user, $community, 15);

        $this->assertDatabaseHas('community_user_state', [
            'community_id' => $community->id,
            'user_id' => $user->id,
            'last_read_community_seq' => 15,
        ]);
    }

    #[Test]
    public function read_state_mark_community_read_does_not_go_backwards(): void
    {
        $service = new CommunityReadStateService(new CommunityPolicyService);
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($user)->create();

        CommunityUserState::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'notifications_enabled' => true,
            'muted' => false,
            'unread_posts_count' => 0,
            'pinned' => false,
            'last_read_community_seq' => 30,
        ]);

        $service->markCommunityRead($user, $community, 10);

        $this->assertDatabaseHas('community_user_state', [
            'community_id' => $community->id,
            'user_id' => $user->id,
            'last_read_community_seq' => 30,
        ]);
    }

    #[Test]
    public function read_state_mark_community_read_updates_last_activity_seen_at(): void
    {
        $service = new CommunityReadStateService(new CommunityPolicyService);
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($user)->create();

        CommunityUserState::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'notifications_enabled' => true,
            'muted' => false,
            'unread_posts_count' => 0,
            'pinned' => false,
            'last_read_community_seq' => 0,
        ]);

        $service->markCommunityRead($user, $community, 5);

        $state = CommunityUserState::where('community_id', $community->id)->where('user_id', $user->id)->first();
        $this->assertNotNull($state->last_activity_seen_at);
    }

    // -------------------------------------------------------------------------
    // CommunityReadStateService — authorization (Batch 9.1)
    // -------------------------------------------------------------------------

    #[Test]
    public function read_state_non_member_cannot_mark_topic_read(): void
    {
        $service = new CommunityReadStateService(new CommunityPolicyService);
        $user = User::factory()->create();
        $community = Community::factory()->create();
        $topic = CommunityTopic::factory()->for($community)->create();

        $this->expectException(InvalidArgumentException::class);

        $service->markTopicRead($user, $topic, 5);
    }

    #[Test]
    public function read_state_pending_key_delivery_member_cannot_mark_topic_read(): void
    {
        $service = new CommunityReadStateService(new CommunityPolicyService);
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($user)->pendingKeyDelivery()->create();
        $topic = CommunityTopic::factory()->for($community)->create();

        $this->expectException(InvalidArgumentException::class);

        $service->markTopicRead($user, $topic, 5);
    }

    #[Test]
    public function read_state_active_member_can_mark_topic_read(): void
    {
        $service = new CommunityReadStateService(new CommunityPolicyService);
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($user)->create();
        $topic = CommunityTopic::factory()->for($community)->create();
        CommunityTopicUserState::create([
            'community_id' => $community->id,
            'topic_id' => $topic->id,
            'user_id' => $user->id,
            'last_read_topic_seq' => 0,
            'muted' => false,
            'notifications_enabled' => true,
            'unread_count' => 0,
        ]);

        $service->markTopicRead($user, $topic, 3);

        $this->assertDatabaseHas('community_topic_user_state', [
            'topic_id' => $topic->id,
            'user_id' => $user->id,
            'last_read_topic_seq' => 3,
        ]);
    }

    #[Test]
    public function read_state_non_member_cannot_mark_community_read(): void
    {
        $service = new CommunityReadStateService(new CommunityPolicyService);
        $user = User::factory()->create();
        $community = Community::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        $service->markCommunityRead($user, $community, 5);
    }

    #[Test]
    public function read_state_pending_key_delivery_member_cannot_mark_community_read(): void
    {
        $service = new CommunityReadStateService(new CommunityPolicyService);
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($user)->pendingKeyDelivery()->create();

        $this->expectException(InvalidArgumentException::class);

        $service->markCommunityRead($user, $community, 5);
    }

    #[Test]
    public function read_state_active_member_can_mark_community_read(): void
    {
        $service = new CommunityReadStateService(new CommunityPolicyService);
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($user)->create();
        CommunityUserState::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'notifications_enabled' => true,
            'muted' => false,
            'unread_posts_count' => 0,
            'pinned' => false,
            'last_read_community_seq' => 0,
        ]);

        $service->markCommunityRead($user, $community, 7);

        $this->assertDatabaseHas('community_user_state', [
            'community_id' => $community->id,
            'user_id' => $user->id,
            'last_read_community_seq' => 7,
        ]);
    }

    // -------------------------------------------------------------------------
    // CommunityPolicyService — community-level posting_policy fallback (Batch 8.1)
    // -------------------------------------------------------------------------

    #[Test]
    public function policy_community_moderators_only_blocks_regular_member_when_topic_has_no_policy(): void
    {
        $policy = new CommunityPolicyService;
        $user = User::factory()->create();
        $community = Community::factory()->create(['posting_policy' => Community::POSTING_POLICY_MODERATORS_ONLY]);
        CommunityMember::factory()->for($community)->for($user)->create(['role' => CommunityMember::ROLE_MEMBER]);
        $topic = CommunityTopic::factory()->for($community)->create(['posting_policy' => null]);

        $this->assertFalse($policy->canPostInTopic($user, $topic));
    }

    #[Test]
    public function policy_topic_everyone_overrides_community_moderators_only(): void
    {
        $policy = new CommunityPolicyService;
        $user = User::factory()->create();
        $community = Community::factory()->create(['posting_policy' => Community::POSTING_POLICY_MODERATORS_ONLY]);
        CommunityMember::factory()->for($community)->for($user)->create(['role' => CommunityMember::ROLE_MEMBER]);
        $topic = CommunityTopic::factory()->for($community)->create([
            'posting_policy' => CommunityTopic::POSTING_POLICY_EVERYONE,
        ]);

        $this->assertTrue($policy->canPostInTopic($user, $topic));
    }

    #[Test]
    public function policy_topic_moderators_only_blocks_member_even_if_community_everyone(): void
    {
        $policy = new CommunityPolicyService;
        $user = User::factory()->create();
        $community = Community::factory()->create(['posting_policy' => Community::POSTING_POLICY_EVERYONE]);
        CommunityMember::factory()->for($community)->for($user)->create(['role' => CommunityMember::ROLE_MEMBER]);
        $topic = CommunityTopic::factory()->for($community)->create([
            'posting_policy' => CommunityTopic::POSTING_POLICY_MODERATORS_ONLY,
        ]);

        $this->assertFalse($policy->canPostInTopic($user, $topic));
    }

    // -------------------------------------------------------------------------
    // CommunityPostService — TTL fallback (Batch 8.1)
    // -------------------------------------------------------------------------

    #[Test]
    public function post_publish_payload_ttl_overrides_community_default(): void
    {
        $service = $this->makePostService();
        $user = User::factory()->create();
        $community = Community::factory()->create(['post_count' => 0, 'default_post_ttl_seconds' => 86400]);
        CommunityMember::factory()->for($community)->for($user)->create();
        $topic = CommunityTopic::factory()->for($community)->create(['post_count' => 0]);
        $epoch = CommunityKeyEpoch::factory()->for($community)->create();

        $post = $service->publishEncryptedPost($user, $community, $topic, [
            'ciphertext' => 'c',
            'nonce' => 'n',
            'epoch_id' => $epoch->id,
            'ttl_seconds' => 3600,
        ]);

        $this->assertEquals(3600, $post->ttl_seconds);
        $this->assertEqualsWithDelta(now()->addHour()->timestamp, $post->expires_at->timestamp, 5);
    }

    #[Test]
    public function post_publish_uses_community_default_ttl_when_not_in_payload(): void
    {
        $service = $this->makePostService();
        $user = User::factory()->create();
        $community = Community::factory()->create(['post_count' => 0, 'default_post_ttl_seconds' => 86400]);
        CommunityMember::factory()->for($community)->for($user)->create();
        $topic = CommunityTopic::factory()->for($community)->create(['post_count' => 0]);
        $epoch = CommunityKeyEpoch::factory()->for($community)->create();

        $post = $service->publishEncryptedPost($user, $community, $topic, [
            'ciphertext' => 'c',
            'nonce' => 'n',
            'epoch_id' => $epoch->id,
        ]);

        $this->assertEquals(86400, $post->ttl_seconds);
        $this->assertNotNull($post->expires_at);
        $this->assertEqualsWithDelta(now()->addDay()->timestamp, $post->expires_at->timestamp, 5);
    }

    #[Test]
    public function post_publish_no_ttl_means_null_expires_at(): void
    {
        $service = $this->makePostService();
        $user = User::factory()->create();
        $community = Community::factory()->create(['post_count' => 0, 'default_post_ttl_seconds' => null]);
        CommunityMember::factory()->for($community)->for($user)->create();
        $topic = CommunityTopic::factory()->for($community)->create(['post_count' => 0]);
        $epoch = CommunityKeyEpoch::factory()->for($community)->create();

        $post = $service->publishEncryptedPost($user, $community, $topic, [
            'ciphertext' => 'c',
            'nonce' => 'n',
            'epoch_id' => $epoch->id,
        ]);

        $this->assertNull($post->expires_at);
        $this->assertNull($post->ttl_seconds);
    }

    // -------------------------------------------------------------------------
    // CommunityKeyDeliveryService — actor authorisation (Batch 8.1)
    // -------------------------------------------------------------------------

    private function makeKeyDeliveryService(): CommunityKeyDeliveryService
    {
        return new CommunityKeyDeliveryService(new CommunityPolicyService, new CommunityAuditService);
    }

    // -------------------------------------------------------------------------
    // CommunityTopicService
    // -------------------------------------------------------------------------

    private function makeTopicService(): CommunityTopicService
    {
        return new CommunityTopicService(new CommunityPolicyService, new CommunityAuditService);
    }

    private function befriend(User $first, User $second): void
    {
        Friend::create(['user_id' => $first->id, 'friend_id' => $second->id]);
        Friend::create(['user_id' => $second->id, 'friend_id' => $first->id]);
    }

    #[Test]
    public function topic_create_moderator_can_create_topic(): void
    {
        $service = $this->makeTopicService();
        $actor = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($actor)->create(['role' => CommunityMember::ROLE_MODERATOR]);

        $topic = $service->createTopic($actor, $community, ['name' => 'General Discussion']);

        $this->assertDatabaseHas('community_topics', [
            'community_id' => $community->id,
            'name' => 'General Discussion',
            'slug' => 'general-discussion',
            'is_archived' => false,
        ]);
        $this->assertEquals('general-discussion', $topic->slug);
    }

    #[Test]
    public function topic_create_uses_provided_slug(): void
    {
        $service = $this->makeTopicService();
        $actor = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($actor)->create(['role' => CommunityMember::ROLE_MODERATOR]);

        $topic = $service->createTopic($actor, $community, ['name' => 'My Topic', 'slug' => 'custom-slug']);

        $this->assertEquals('custom-slug', $topic->slug);
    }

    #[Test]
    public function topic_create_regular_member_cannot_create_topic(): void
    {
        $service = $this->makeTopicService();
        $actor = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($actor)->create(['role' => CommunityMember::ROLE_MEMBER]);

        $this->expectException(InvalidArgumentException::class);

        $service->createTopic($actor, $community, ['name' => 'New Topic']);
    }

    #[Test]
    public function topic_create_rejects_duplicate_slug_in_same_community(): void
    {
        $service = $this->makeTopicService();
        $actor = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($actor)->create(['role' => CommunityMember::ROLE_MODERATOR]);
        CommunityTopic::factory()->for($community)->create(['slug' => 'existing-slug']);

        $this->expectException(InvalidArgumentException::class);

        $service->createTopic($actor, $community, ['name' => 'Another Topic', 'slug' => 'existing-slug']);
    }

    #[Test]
    public function topic_create_allows_same_slug_in_different_community(): void
    {
        $service = $this->makeTopicService();
        $actor = User::factory()->create();
        $community = Community::factory()->create();
        $otherCommunity = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($actor)->create(['role' => CommunityMember::ROLE_MODERATOR]);
        CommunityTopic::factory()->for($otherCommunity)->create(['slug' => 'shared-slug']);

        $topic = $service->createTopic($actor, $community, ['name' => 'Topic', 'slug' => 'shared-slug']);

        $this->assertEquals('shared-slug', $topic->slug);
    }

    #[Test]
    public function topic_archive_moderator_can_archive_topic(): void
    {
        $service = $this->makeTopicService();
        $actor = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($actor)->create(['role' => CommunityMember::ROLE_MODERATOR]);
        $topic = CommunityTopic::factory()->for($community)->create(['is_archived' => false, 'is_system' => false]);

        $service->archiveTopic($actor, $topic);

        $this->assertDatabaseHas('community_topics', [
            'id' => $topic->id,
            'is_archived' => true,
        ]);
    }

    #[Test]
    public function topic_archive_regular_member_cannot_archive(): void
    {
        $service = $this->makeTopicService();
        $actor = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($actor)->create(['role' => CommunityMember::ROLE_MEMBER]);
        $topic = CommunityTopic::factory()->for($community)->create(['is_archived' => false, 'is_system' => false]);

        $this->expectException(InvalidArgumentException::class);

        $service->archiveTopic($actor, $topic);
    }

    #[Test]
    public function topic_archive_cannot_archive_system_topic(): void
    {
        $service = $this->makeTopicService();
        $actor = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($actor)->create(['role' => CommunityMember::ROLE_MODERATOR]);
        $topic = CommunityTopic::factory()->for($community)->create(['is_archived' => false, 'is_system' => true]);

        $this->expectException(InvalidArgumentException::class);

        $service->archiveTopic($actor, $topic);
    }

    #[Test]
    public function topic_archive_cannot_archive_already_archived_topic(): void
    {
        $service = $this->makeTopicService();
        $actor = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($actor)->create(['role' => CommunityMember::ROLE_MODERATOR]);
        $topic = CommunityTopic::factory()->for($community)->create(['is_archived' => true, 'archived_at' => now(), 'is_system' => false]);

        $this->expectException(InvalidArgumentException::class);

        $service->archiveTopic($actor, $topic);
    }

    #[Test]
    public function key_delivery_admin_can_deliver_keys(): void
    {
        $service = $this->makeKeyDeliveryService();
        $actor = User::factory()->create();
        $memberUser = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($actor)->create(['role' => CommunityMember::ROLE_ADMIN]);
        $member = CommunityMember::factory()->for($community)->for($memberUser)->pendingKeyDelivery()->create();
        CommunityKeyEpoch::factory()->for($community)->create(['epoch_number' => 1]);
        $deviceKey = UserDeviceKey::factory()->for($memberUser)->create();

        $service->deliverMemberKeys($actor, $member, [
            ['device_key_id' => $deviceKey->id, 'encrypted_key' => 'enc_key'],
        ]);

        $this->assertDatabaseHas('community_member_keys', [
            'community_id' => $community->id,
            'user_id' => $memberUser->id,
        ]);
    }

    #[Test]
    public function key_delivery_moderator_can_deliver_keys(): void
    {
        $service = $this->makeKeyDeliveryService();
        $actor = User::factory()->create();
        $memberUser = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($actor)->create(['role' => CommunityMember::ROLE_MODERATOR]);
        $member = CommunityMember::factory()->for($community)->for($memberUser)->pendingKeyDelivery()->create();
        CommunityKeyEpoch::factory()->for($community)->create(['epoch_number' => 1]);
        $deviceKey = UserDeviceKey::factory()->for($memberUser)->create();

        $service->deliverMemberKeys($actor, $member, [
            ['device_key_id' => $deviceKey->id, 'encrypted_key' => 'enc_key'],
        ]);

        $this->assertDatabaseHas('community_member_keys', [
            'community_id' => $community->id,
            'user_id' => $memberUser->id,
        ]);
    }

    #[Test]
    public function key_delivery_regular_member_cannot_deliver_keys(): void
    {
        $service = $this->makeKeyDeliveryService();
        $actor = User::factory()->create();
        $memberUser = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($actor)->create(['role' => CommunityMember::ROLE_MEMBER]);
        $member = CommunityMember::factory()->for($community)->for($memberUser)->pendingKeyDelivery()->create();
        CommunityKeyEpoch::factory()->for($community)->create();
        $deviceKey = UserDeviceKey::factory()->for($memberUser)->create();

        $this->expectException(InvalidArgumentException::class);

        $service->deliverMemberKeys($actor, $member, [
            ['device_key_id' => $deviceKey->id, 'encrypted_key' => 'enc_key'],
        ]);
    }

    #[Test]
    public function key_delivery_non_member_cannot_deliver_keys(): void
    {
        $service = $this->makeKeyDeliveryService();
        $actor = User::factory()->create();
        $memberUser = User::factory()->create();
        $community = Community::factory()->create();
        $member = CommunityMember::factory()->for($community)->for($memberUser)->pendingKeyDelivery()->create();
        CommunityKeyEpoch::factory()->for($community)->create();
        $deviceKey = UserDeviceKey::factory()->for($memberUser)->create();

        $this->expectException(InvalidArgumentException::class);

        $service->deliverMemberKeys($actor, $member, [
            ['device_key_id' => $deviceKey->id, 'encrypted_key' => 'enc_key'],
        ]);
    }
}
