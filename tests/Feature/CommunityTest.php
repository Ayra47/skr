<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\CommunityAuditLog;
use App\Models\CommunityFile;
use App\Models\CommunityInvite;
use App\Models\CommunityInviteUse;
use App\Models\CommunityJoinRequest;
use App\Models\CommunityKeyEpoch;
use App\Models\CommunityMember;
use App\Models\CommunityMemberKey;
use App\Models\CommunityPost;
use App\Models\CommunityPostReaction;
use App\Models\CommunityTopic;
use App\Models\CommunityTopicUserState;
use App\Models\CommunityUserState;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Community
    // -------------------------------------------------------------------------

    public function test_community_can_be_created_via_factory(): void
    {
        $community = Community::factory()->create();

        $this->assertDatabaseHas('communities', ['id' => $community->id]);
        $this->assertTrue(strlen($community->id) === 36); // UUID
    }

    public function test_community_private_state(): void
    {
        $community = Community::factory()->private()->create();

        $this->assertSame(Community::VISIBILITY_PRIVATE, $community->visibility);
    }

    public function test_community_request_required_state(): void
    {
        $community = Community::factory()->requestRequired()->create();

        $this->assertSame(Community::JOIN_REQUEST, $community->join_mode);
    }

    public function test_community_constants_are_defined(): void
    {
        $this->assertSame('open', Community::JOIN_OPEN);
        $this->assertSame('request', Community::JOIN_REQUEST);
        $this->assertSame('invite_only', Community::JOIN_INVITE_ONLY);
        $this->assertSame('public', Community::VISIBILITY_PUBLIC);
        $this->assertSame('private', Community::VISIBILITY_PRIVATE);
    }

    public function test_community_has_members_relationship(): void
    {
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->create();

        $this->assertCount(1, $community->members);
    }

    public function test_community_has_topics_relationship(): void
    {
        $community = Community::factory()->create();
        CommunityTopic::factory()->for($community)->create();

        $this->assertCount(1, $community->topics);
    }

    public function test_community_has_posts_relationship(): void
    {
        $community = Community::factory()->create();
        CommunityPost::factory()->for($community)->create();

        $this->assertCount(1, $community->posts);
    }

    // -------------------------------------------------------------------------
    // CommunityMember
    // -------------------------------------------------------------------------

    public function test_community_member_can_be_created_via_factory(): void
    {
        $member = CommunityMember::factory()->create();

        $this->assertDatabaseHas('community_members', ['id' => $member->id]);
    }

    public function test_community_member_role_constants(): void
    {
        $this->assertSame('owner', CommunityMember::ROLE_OWNER);
        $this->assertSame('admin', CommunityMember::ROLE_ADMIN);
        $this->assertSame('moderator', CommunityMember::ROLE_MODERATOR);
        $this->assertSame('member', CommunityMember::ROLE_MEMBER);
    }

    public function test_community_member_owner_state(): void
    {
        $member = CommunityMember::factory()->owner()->create();

        $this->assertSame(CommunityMember::ROLE_OWNER, $member->role);
    }

    public function test_community_member_admin_state(): void
    {
        $member = CommunityMember::factory()->admin()->create();

        $this->assertSame(CommunityMember::ROLE_ADMIN, $member->role);
    }

    // -------------------------------------------------------------------------
    // CommunityTopic
    // -------------------------------------------------------------------------

    public function test_community_topic_can_be_created_via_factory(): void
    {
        $topic = CommunityTopic::factory()->create();

        $this->assertDatabaseHas('community_topics', ['id' => $topic->id]);
        $this->assertTrue(strlen($topic->id) === 36); // UUID
    }

    public function test_community_topic_belongs_to_community(): void
    {
        $community = Community::factory()->create();
        $topic = CommunityTopic::factory()->for($community)->create();

        $this->assertSame($community->id, $topic->community_id);
        $this->assertTrue($topic->community->is($community));
    }

    // -------------------------------------------------------------------------
    // CommunityUserState
    // -------------------------------------------------------------------------

    public function test_community_user_state_can_be_created_via_factory(): void
    {
        $state = CommunityUserState::factory()->create();

        $this->assertDatabaseHas('community_user_state', ['id' => $state->id]);
    }

    public function test_community_user_state_casts_booleans(): void
    {
        $state = CommunityUserState::factory()->create(['notifications_enabled' => true, 'muted' => false]);

        $this->assertTrue($state->notifications_enabled);
        $this->assertFalse($state->muted);
    }

    // -------------------------------------------------------------------------
    // CommunityTopicUserState
    // -------------------------------------------------------------------------

    public function test_community_topic_user_state_can_be_created_via_factory(): void
    {
        $state = CommunityTopicUserState::factory()->create();

        $this->assertDatabaseHas('community_topic_user_state', ['id' => $state->id]);
    }

    // -------------------------------------------------------------------------
    // CommunityInvite
    // -------------------------------------------------------------------------

    public function test_community_invite_can_be_created_via_factory(): void
    {
        $invite = CommunityInvite::factory()->create();

        $this->assertDatabaseHas('community_invites', ['id' => $invite->id]);
        $this->assertTrue(strlen($invite->id) === 36); // UUID
    }

    public function test_community_invite_is_usable_when_active(): void
    {
        $invite = CommunityInvite::factory()->create([
            'is_revoked' => false,
            'expires_at' => null,
            'max_uses' => null,
            'use_count' => 0,
        ]);

        $this->assertTrue($invite->isUsable());
    }

    public function test_community_invite_is_not_usable_when_revoked(): void
    {
        $invite = CommunityInvite::factory()->revoked()->create();

        $this->assertFalse($invite->isUsable());
    }

    public function test_community_invite_is_not_usable_when_expired(): void
    {
        $invite = CommunityInvite::factory()->expired()->create();

        $this->assertFalse($invite->isUsable());
        $this->assertTrue($invite->isExpired());
    }

    public function test_community_invite_is_not_usable_when_at_max_uses(): void
    {
        $invite = CommunityInvite::factory()->create([
            'max_uses' => 5,
            'use_count' => 5,
            'is_revoked' => false,
            'expires_at' => null,
        ]);

        $this->assertFalse($invite->isUsable());
    }

    // -------------------------------------------------------------------------
    // CommunityInviteUse
    // -------------------------------------------------------------------------

    public function test_community_invite_use_can_be_created_via_factory(): void
    {
        $use = CommunityInviteUse::factory()->create();

        $this->assertDatabaseHas('community_invite_uses', ['id' => $use->id]);
    }

    // -------------------------------------------------------------------------
    // CommunityJoinRequest
    // -------------------------------------------------------------------------

    public function test_community_join_request_can_be_created_via_factory(): void
    {
        $request = CommunityJoinRequest::factory()->create();

        $this->assertDatabaseHas('community_join_requests', ['id' => $request->id]);
    }

    public function test_community_join_request_status_constants(): void
    {
        $this->assertSame('pending', CommunityJoinRequest::STATUS_PENDING);
        $this->assertSame('approved', CommunityJoinRequest::STATUS_APPROVED);
        $this->assertSame('rejected', CommunityJoinRequest::STATUS_REJECTED);
    }

    public function test_community_join_request_approved_state(): void
    {
        $request = CommunityJoinRequest::factory()->approved()->create();

        $this->assertSame(CommunityJoinRequest::STATUS_APPROVED, $request->status);
        $this->assertNotNull($request->reviewed_by);
    }

    public function test_community_join_request_rejected_state(): void
    {
        $request = CommunityJoinRequest::factory()->rejected()->create();

        $this->assertSame(CommunityJoinRequest::STATUS_REJECTED, $request->status);
    }

    // -------------------------------------------------------------------------
    // CommunityKeyEpoch
    // -------------------------------------------------------------------------

    public function test_community_key_epoch_can_be_created_via_factory(): void
    {
        $epoch = CommunityKeyEpoch::factory()->create();

        $this->assertDatabaseHas('community_key_epochs', ['id' => $epoch->id]);
        $this->assertTrue(strlen($epoch->id) === 36); // UUID
    }

    public function test_community_key_epoch_reason_constants(): void
    {
        $this->assertSame('initial', CommunityKeyEpoch::REASON_INITIAL);
        $this->assertSame('member_removed', CommunityKeyEpoch::REASON_MEMBER_REMOVED);
        $this->assertSame('periodic', CommunityKeyEpoch::REASON_PERIODIC);
    }

    // -------------------------------------------------------------------------
    // CommunityMemberKey
    // -------------------------------------------------------------------------

    public function test_community_member_key_can_be_created_via_factory(): void
    {
        $key = CommunityMemberKey::factory()->create();

        $this->assertDatabaseHas('community_member_keys', ['id' => $key->id]);
    }

    public function test_community_member_key_belongs_to_epoch(): void
    {
        $key = CommunityMemberKey::factory()->create();

        $this->assertNotNull($key->epoch);
        $this->assertSame($key->epoch_id, $key->epoch->id);
    }

    // -------------------------------------------------------------------------
    // CommunityPost
    // -------------------------------------------------------------------------

    public function test_community_post_can_be_created_via_factory(): void
    {
        $post = CommunityPost::factory()->create();

        $this->assertDatabaseHas('community_posts', ['id' => $post->id]);
        $this->assertTrue(strlen($post->id) === 36); // UUID
    }

    public function test_community_post_visibility_constants(): void
    {
        $this->assertSame('public', CommunityPost::VISIBILITY_PUBLIC);
        $this->assertSame('members_only', CommunityPost::VISIBILITY_MEMBERS_ONLY);
        $this->assertSame('private', CommunityPost::VISIBILITY_PRIVATE);
    }

    public function test_community_post_public_state(): void
    {
        $post = CommunityPost::factory()->public()->create();

        $this->assertSame(CommunityPost::VISIBILITY_PUBLIC, $post->visibility);
    }

    public function test_community_post_pinned_state(): void
    {
        $post = CommunityPost::factory()->pinned()->create();

        $this->assertTrue($post->is_pinned);
    }

    public function test_community_post_is_expired_when_past(): void
    {
        $post = CommunityPost::factory()->create(['expires_at' => now()->subMinute()]);

        $this->assertTrue($post->isExpired());
    }

    public function test_community_post_is_not_expired_when_future(): void
    {
        $post = CommunityPost::factory()->create(['expires_at' => now()->addHour()]);

        $this->assertFalse($post->isExpired());
    }

    public function test_community_post_is_not_expired_when_null(): void
    {
        $post = CommunityPost::factory()->create(['expires_at' => null]);

        $this->assertFalse($post->isExpired());
    }

    public function test_community_post_has_files_relationship(): void
    {
        $post = CommunityPost::factory()->create();
        CommunityFile::factory()->for($post, 'post')->create();

        $this->assertCount(1, $post->files);
    }

    public function test_community_post_has_reactions_relationship(): void
    {
        $post = CommunityPost::factory()->create();
        CommunityPostReaction::factory()->for($post, 'post')->create();

        $this->assertCount(1, $post->reactions);
    }

    // -------------------------------------------------------------------------
    // CommunityFile
    // -------------------------------------------------------------------------

    public function test_community_file_can_be_created_via_factory(): void
    {
        $file = CommunityFile::factory()->create();

        $this->assertDatabaseHas('community_files', ['id' => $file->id]);
        $this->assertTrue(strlen($file->id) === 36); // UUID
    }

    // -------------------------------------------------------------------------
    // CommunityPostReaction
    // -------------------------------------------------------------------------

    public function test_community_post_reaction_can_be_created_via_factory(): void
    {
        $reaction = CommunityPostReaction::factory()->create();

        $this->assertDatabaseHas('community_post_reactions', ['id' => $reaction->id]);
    }

    public function test_community_post_reaction_unique_per_user_emoji(): void
    {
        $post = CommunityPost::factory()->create();
        $user = User::factory()->create();

        CommunityPostReaction::factory()->for($post, 'post')->for($user)->create(['emoji' => '👍']);

        $this->expectException(UniqueConstraintViolationException::class);

        CommunityPostReaction::factory()->for($post, 'post')->for($user)->create(['emoji' => '👍']);
    }

    // -------------------------------------------------------------------------
    // CommunityAuditLog
    // -------------------------------------------------------------------------

    public function test_community_audit_log_can_be_created_via_factory(): void
    {
        $log = CommunityAuditLog::factory()->create();

        $this->assertDatabaseHas('community_audit_log', ['id' => $log->id]);
    }

    public function test_community_audit_log_payload_casts_to_array(): void
    {
        $log = CommunityAuditLog::factory()->create(['payload' => ['key' => 'value']]);

        $this->assertIsArray($log->payload);
        $this->assertSame('value', $log->payload['key']);
    }

    public function test_community_audit_log_belongs_to_community(): void
    {
        $community = Community::factory()->create();
        $log = CommunityAuditLog::factory()->for($community)->create();

        $this->assertTrue($log->community->is($community));
    }

    public function test_community_audit_log_action_constants_are_defined(): void
    {
        $this->assertSame('member_added', CommunityAuditLog::ACTION_MEMBER_ADDED);
        $this->assertSame('member_removed', CommunityAuditLog::ACTION_MEMBER_REMOVED);
        $this->assertSame('member_banned', CommunityAuditLog::ACTION_MEMBER_BANNED);
        $this->assertSame('role_changed', CommunityAuditLog::ACTION_ROLE_CHANGED);
        $this->assertSame('key_epoch_rotated', CommunityAuditLog::ACTION_KEY_EPOCH_ROTATED);
    }

    // -------------------------------------------------------------------------
    // Batch 7.1: communities policy fields
    // -------------------------------------------------------------------------

    public function test_community_member_limit_accepts_null(): void
    {
        $community = Community::factory()->create(['member_limit' => null]);

        $this->assertNull($community->member_limit);
    }

    public function test_community_member_limit_accepts_allowed_values(): void
    {
        foreach (Community::ALLOWED_MEMBER_LIMITS as $limit) {
            $community = Community::factory()->create(['member_limit' => $limit]);
            $this->assertSame($limit, $community->member_limit);
        }
    }

    public function test_community_member_limit_rejects_invalid_value(): void
    {
        $this->expectException(QueryException::class);

        Community::factory()->create(['member_limit' => 99]);
    }

    public function test_community_default_post_ttl_accepts_null(): void
    {
        $community = Community::factory()->create(['default_post_ttl_seconds' => null]);

        $this->assertNull($community->default_post_ttl_seconds);
    }

    public function test_community_default_post_ttl_accepts_allowed_values(): void
    {
        foreach (Community::ALLOWED_TTL_SECONDS as $ttl) {
            $community = Community::factory()->create(['default_post_ttl_seconds' => $ttl]);
            $this->assertSame($ttl, $community->default_post_ttl_seconds);
        }
    }

    public function test_community_default_post_ttl_rejects_invalid_value(): void
    {
        $this->expectException(QueryException::class);

        Community::factory()->create(['default_post_ttl_seconds' => 999]);
    }

    public function test_community_policy_constants_are_defined(): void
    {
        $this->assertSame('all_members', Community::INVITE_POLICY_ALL_MEMBERS);
        $this->assertSame('moderators_only', Community::INVITE_POLICY_MODERATORS_ONLY);
        $this->assertSame('everyone', Community::POSTING_POLICY_EVERYONE);
        $this->assertSame('moderators_only', Community::POSTING_POLICY_MODERATORS_ONLY);
    }

    // -------------------------------------------------------------------------
    // Batch 7.1: community_members status model
    // -------------------------------------------------------------------------

    public function test_community_member_status_defaults_to_active(): void
    {
        $member = CommunityMember::factory()->create();

        $this->assertSame(CommunityMember::STATUS_ACTIVE, $member->status);
    }

    public function test_community_member_pending_key_delivery_state(): void
    {
        $member = CommunityMember::factory()->pendingKeyDelivery()->create();

        $this->assertSame(CommunityMember::STATUS_PENDING_KEY_DELIVERY, $member->status);
    }

    public function test_community_member_banned_state(): void
    {
        $member = CommunityMember::factory()->banned()->create();

        $this->assertSame(CommunityMember::STATUS_BANNED, $member->status);
        $this->assertNotNull($member->banned_at);
        $this->assertNotNull($member->ban_reason_code);
    }

    public function test_community_member_ban_reason_code_rejects_invalid_value(): void
    {
        $this->expectException(QueryException::class);

        CommunityMember::factory()->create(['ban_reason_code' => 'bad_value']);
    }

    public function test_community_member_status_constants_are_defined(): void
    {
        $this->assertSame('active', CommunityMember::STATUS_ACTIVE);
        $this->assertSame('pending_key_delivery', CommunityMember::STATUS_PENDING_KEY_DELIVERY);
        $this->assertSame('left', CommunityMember::STATUS_LEFT);
        $this->assertSame('banned', CommunityMember::STATUS_BANNED);
        $this->assertSame('suspended', CommunityMember::STATUS_SUSPENDED);
    }

    // -------------------------------------------------------------------------
    // Batch 7.1: community_member_keys per-device
    // -------------------------------------------------------------------------

    public function test_community_member_key_requires_device_key_id(): void
    {
        $key = CommunityMemberKey::factory()->create();

        $this->assertNotNull($key->device_key_id);
    }

    public function test_community_member_key_unique_per_device(): void
    {
        $key = CommunityMemberKey::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        CommunityMemberKey::factory()->create([
            'community_id' => $key->community_id,
            'epoch_id' => $key->epoch_id,
            'device_key_id' => $key->device_key_id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Batch 7.1: community_posts E2EE fields
    // -------------------------------------------------------------------------

    public function test_community_post_has_no_plaintext_body(): void
    {
        $post = CommunityPost::factory()->create();

        $this->assertArrayNotHasKey('body', $post->getAttributes());
    }

    public function test_community_post_has_ciphertext_and_nonce(): void
    {
        $post = CommunityPost::factory()->create([
            'ciphertext' => 'ENCRYPTED_PAYLOAD',
            'nonce' => 'TEST_NONCE',
        ]);

        $this->assertSame('ENCRYPTED_PAYLOAD', $post->ciphertext);
        $this->assertSame('TEST_NONCE', $post->nonce);
    }

    public function test_community_post_has_community_seq(): void
    {
        $post = CommunityPost::factory()->create(['community_seq' => 42]);

        $this->assertSame(42, $post->community_seq);
    }

    public function test_community_post_moderation_status_constants(): void
    {
        $this->assertSame('visible', CommunityPost::MODERATION_VISIBLE);
        $this->assertSame('hidden', CommunityPost::MODERATION_HIDDEN);
        $this->assertSame('deleted_by_moderator', CommunityPost::MODERATION_DELETED_BY_MODERATOR);
    }

    public function test_community_post_moderation_status_rejects_invalid(): void
    {
        $this->expectException(QueryException::class);

        CommunityPost::factory()->create(['moderation_status' => 'removed']);
    }

    public function test_community_post_ttl_seconds_rejects_zero(): void
    {
        $this->expectException(QueryException::class);

        CommunityPost::factory()->create(['ttl_seconds' => 0]);
    }

    public function test_community_post_client_idempotency_key_is_unique_per_community_and_user(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityPost::factory()->for($community)->for($user, 'author')->create(['client_idempotency_key' => 'key-abc']);

        $this->expectException(UniqueConstraintViolationException::class);

        CommunityPost::factory()->for($community)->for($user, 'author')->create(['client_idempotency_key' => 'key-abc']);
    }

    // -------------------------------------------------------------------------
    // Batch 7.1: community_topics type and policy
    // -------------------------------------------------------------------------

    public function test_community_topic_type_constants(): void
    {
        $this->assertSame('regular', CommunityTopic::TYPE_REGULAR);
        $this->assertSame('announcements', CommunityTopic::TYPE_ANNOUNCEMENTS);
        $this->assertSame('archive', CommunityTopic::TYPE_ARCHIVE);
    }

    public function test_community_topic_is_system_defaults_false(): void
    {
        $topic = CommunityTopic::factory()->create();

        $this->assertFalse($topic->is_system);
    }

    public function test_community_topic_posting_policy_rejects_invalid(): void
    {
        $this->expectException(QueryException::class);

        CommunityTopic::factory()->create(['posting_policy' => 'admins_only']);
    }

    public function test_community_topic_type_rejects_invalid(): void
    {
        $this->expectException(QueryException::class);

        CommunityTopic::factory()->create(['type' => 'secret']);
    }

    // -------------------------------------------------------------------------
    // Batch 7.1: community_topic_user_state seq cursor
    // -------------------------------------------------------------------------

    public function test_community_topic_user_state_has_last_read_topic_seq(): void
    {
        $state = CommunityTopicUserState::factory()->create(['last_read_topic_seq' => 77]);

        $this->assertSame(77, $state->last_read_topic_seq);
    }

    // -------------------------------------------------------------------------
    // Batch 7.1: community_user_state pinned and seq
    // -------------------------------------------------------------------------

    public function test_community_user_state_pinned_defaults_false(): void
    {
        $state = CommunityUserState::factory()->create();

        $this->assertFalse($state->pinned);
    }

    public function test_community_user_state_has_last_read_community_seq(): void
    {
        $state = CommunityUserState::factory()->create(['last_read_community_seq' => 150]);

        $this->assertSame(150, $state->last_read_community_seq);
    }

    // -------------------------------------------------------------------------
    // Batch 7.1: community_invites revoked_at
    // -------------------------------------------------------------------------

    public function test_community_invite_revoked_at_makes_invite_unusable(): void
    {
        $invite = CommunityInvite::factory()->create(['revoked_at' => now()]);

        $this->assertFalse($invite->isUsable());
    }

    public function test_community_invite_revoked_state_sets_revoked_at(): void
    {
        $invite = CommunityInvite::factory()->revoked()->create();

        $this->assertNotNull($invite->revoked_at);
        $this->assertFalse($invite->isUsable());
    }

    // -------------------------------------------------------------------------
    // Batch 7.1: community_files E2EE fields
    // -------------------------------------------------------------------------

    public function test_community_file_accepts_storage_key_and_encrypted_filename(): void
    {
        $file = CommunityFile::factory()->create([
            'storage_key' => 'gcs://bucket/path/to/file',
            'encrypted_filename' => 'ENCRYPTED_NAME_BLOB',
            'mime_bucket' => 'image',
            'size_bytes' => 204800,
            'checksum_sha256' => str_repeat('a', 64),
        ]);

        $this->assertSame('gcs://bucket/path/to/file', $file->storage_key);
        $this->assertSame('ENCRYPTED_NAME_BLOB', $file->encrypted_filename);
        $this->assertSame('image', $file->mime_bucket);
        $this->assertSame(204800, $file->size_bytes);
    }
}
