<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationInvite;
use App\Models\ConversationJoinRequest;
use App\Models\ConversationMember;
use App\Models\Friend;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GroupChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_group_with_friends_only(): void
    {
        $owner = $this->user('owner');
        $friend = $this->user('friend');
        $stranger = $this->user('stranger');
        $this->befriend($owner, $friend);

        $this->actingAs($owner)
            ->postJson('/chat/groups', [
                'title' => 'Project',
                'user_ids' => [$friend->id],
            ])
            ->assertCreated()
            ->assertJson(['success' => true]);

        $conversation = Conversation::where('title', 'Project')->firstOrFail();

        $this->assertSame(Conversation::TYPE_GROUP, $conversation->type);
        $this->assertDatabaseHas('conversation_members', [
            'conversation_id' => $conversation->id,
            'user_id' => $owner->id,
            'role' => ConversationMember::ROLE_OWNER,
        ]);
        $this->assertDatabaseHas('conversation_join_requests', [
            'conversation_id' => $conversation->id,
            'invited_user_id' => $friend->id,
            'invited_by_id' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->postJson('/chat/groups', [
                'title' => 'Nope',
                'user_ids' => [$stranger->id],
            ])
            ->assertUnprocessable();
    }

    public function test_owner_and_admin_can_manage_members_but_only_owner_changes_roles(): void
    {
        $owner = $this->user('owner');
        $admin = $this->user('admin');
        $friend = $this->user('friend');
        $member = $this->user('member');
        $this->befriend($admin, $friend);

        $conversation = $this->group($owner, [
            $admin->id => ConversationMember::ROLE_ADMIN,
            $member->id => ConversationMember::ROLE_MEMBER,
        ]);

        $this->actingAs($admin)
            ->postJson("/chat/{$conversation->id}/members", ['user_ids' => [$friend->id]])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('conversation_join_requests', [
            'conversation_id' => $conversation->id,
            'invited_user_id' => $friend->id,
            'invited_by_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->postJson("/chat/{$conversation->id}/members/{$member->id}/admin")
            ->assertForbidden();

        $this->actingAs($owner)
            ->postJson("/chat/{$conversation->id}/members/{$member->id}/admin")
            ->assertOk();

        $this->assertDatabaseHas('conversation_members', [
            'conversation_id' => $conversation->id,
            'user_id' => $member->id,
            'role' => ConversationMember::ROLE_ADMIN,
        ]);

        $this->actingAs($admin)
            ->deleteJson("/chat/{$conversation->id}/members/{$member->id}/admin")
            ->assertForbidden();

        $this->actingAs($owner)
            ->deleteJson("/chat/{$conversation->id}/members/{$member->id}/admin")
            ->assertOk();

        $this->assertDatabaseHas('conversation_members', [
            'conversation_id' => $conversation->id,
            'user_id' => $member->id,
            'role' => ConversationMember::ROLE_MEMBER,
        ]);
    }

    public function test_invite_links_allow_authorized_users_to_join(): void
    {
        $owner = $this->user('owner');
        $guest = $this->user('guest');
        $otherGuest = $this->user('other');
        $conversation = $this->group($owner);

        $permanent = $this->actingAs($owner)
            ->postJson("/chat/{$conversation->id}/invites", ['type' => ConversationInvite::TYPE_PERMANENT])
            ->assertCreated()
            ->json('invite.url');

        $this->actingAs($guest)->get($permanent)->assertRedirect(route('chats.index', ['conversation' => $conversation->id]));
        $this->assertDatabaseHas('conversation_members', [
            'conversation_id' => $conversation->id,
            'user_id' => $guest->id,
        ]);

        $singleUse = $this->actingAs($owner)
            ->postJson("/chat/{$conversation->id}/invites", ['type' => ConversationInvite::TYPE_SINGLE_USE])
            ->assertCreated()
            ->json('invite.url');

        $this->actingAs($otherGuest)->get($singleUse)->assertRedirect(route('chats.index', ['conversation' => $conversation->id]));

        $lateGuest = $this->user('late');
        $this->actingAs($lateGuest)->get($singleUse)->assertRedirect(route('chats.index'));
        $this->assertDatabaseMissing('conversation_members', [
            'conversation_id' => $conversation->id,
            'user_id' => $lateGuest->id,
        ]);
    }

    public function test_invited_friend_must_accept_or_decline_group_request(): void
    {
        $owner = $this->user('owner');
        $friend = $this->user('friend');
        $this->befriend($owner, $friend);
        $conversation = $this->group($owner);

        $this->actingAs($owner)
            ->postJson("/chat/{$conversation->id}/members", ['user_ids' => [$friend->id]])
            ->assertOk();

        $joinRequest = ConversationJoinRequest::where('conversation_id', $conversation->id)
            ->where('invited_user_id', $friend->id)
            ->where('status', ConversationJoinRequest::STATUS_PENDING)
            ->firstOrFail();

        $this->assertDatabaseMissing('conversation_members', [
            'conversation_id' => $conversation->id,
            'user_id' => $friend->id,
        ]);

        $this->actingAs($friend)
            ->postJson("/chat/group-requests/{$joinRequest->id}/accept")
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('conversation_members', [
            'conversation_id' => $conversation->id,
            'user_id' => $friend->id,
        ]);
        $this->assertDatabaseMissing('conversation_join_requests', [
            'id' => $joinRequest->id,
        ]);
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'type' => Message::TYPE_SYSTEM,
        ]);
    }

    public function test_invited_friend_can_decline_group_request(): void
    {
        $owner = $this->user('owner');
        $friend = $this->user('friend');
        $this->befriend($owner, $friend);
        $conversation = $this->group($owner);
        $joinRequest = ConversationJoinRequest::create([
            'conversation_id' => $conversation->id,
            'invited_user_id' => $friend->id,
            'invited_by_id' => $owner->id,
            'status' => ConversationJoinRequest::STATUS_PENDING,
        ]);

        $this->actingAs($friend)
            ->deleteJson("/chat/group-requests/{$joinRequest->id}")
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('conversation_join_requests', [
            'id' => $joinRequest->id,
            'status' => ConversationJoinRequest::STATUS_DECLINED,
        ]);

        $this->actingAs($owner)
            ->postJson("/chat/{$conversation->id}/members", ['user_ids' => [$friend->id]])
            ->assertOk();

        $this->assertSame(1, ConversationJoinRequest::where('conversation_id', $conversation->id)
            ->where('invited_user_id', $friend->id)
            ->count());
        $this->assertDatabaseMissing('conversation_members', [
            'conversation_id' => $conversation->id,
            'user_id' => $friend->id,
        ]);
    }

    public function test_group_member_can_open_chats_index(): void
    {
        $owner = $this->user('owner');
        $member = $this->user('member');
        $conversation = $this->group($owner, [
            $member->id => ConversationMember::ROLE_MEMBER,
        ]);

        $this->actingAs($member)
            ->get('/chats')
            ->assertOk()
            ->assertSee($conversation->title);
    }

    public function test_owner_can_leave_group_and_ownership_is_transferred(): void
    {
        $owner = $this->user('owner');
        $member = $this->user('member');
        $conversation = $this->group($owner, [
            $member->id => ConversationMember::ROLE_MEMBER,
        ]);

        $this->actingAs($owner)
            ->deleteJson("/chat/{$conversation->id}/members/me")
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('conversation_members', [
            'conversation_id' => $conversation->id,
            'user_id' => $owner->id,
        ]);
        $this->assertDatabaseHas('conversation_members', [
            'conversation_id' => $conversation->id,
            'user_id' => $member->id,
            'role' => ConversationMember::ROLE_OWNER,
        ]);
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'type' => Message::TYPE_SYSTEM,
        ]);
    }

    public function test_only_owner_can_delete_group(): void
    {
        $owner = $this->user('owner');
        $member = $this->user('member');
        $conversation = $this->group($owner, [
            $member->id => ConversationMember::ROLE_MEMBER,
        ]);

        $this->actingAs($member)
            ->deleteJson("/chat/{$conversation->id}/group")
            ->assertForbidden();

        $this->actingAs($owner)
            ->deleteJson("/chat/{$conversation->id}/group")
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertModelMissing($conversation);
    }

    public function test_group_messages_require_membership_and_payload_for_each_member(): void
    {
        $owner = $this->user('owner');
        $member = $this->user('member');
        $stranger = $this->user('stranger');
        $conversation = $this->group($owner, [
            $member->id => ConversationMember::ROLE_MEMBER,
        ]);

        $payload = json_encode(['iv' => base64_encode('123456789012'), 'ciphertext' => base64_encode('text')]);

        $this->actingAs($stranger)
            ->getJson("/chat/{$conversation->id}/messages")
            ->assertForbidden();

        $this->actingAs($owner)
            ->postJson("/chat/{$conversation->id}/messages", [
                'encrypted_payloads' => [
                    (string) $owner->id => $payload,
                ],
            ])
            ->assertUnprocessable();

        $this->actingAs($owner)
            ->postJson("/chat/{$conversation->id}/messages", [
                'encrypted_payloads' => [
                    (string) $owner->id => $payload,
                    (string) $member->id => $payload,
                ],
            ])
            ->assertCreated()
            ->assertJson(['success' => true]);
    }

    public function test_owner_and_admin_can_rename_group_and_update_avatar(): void
    {
        Storage::fake('public');

        $owner = $this->user('owner');
        $admin = $this->user('admin');
        $member = $this->user('member');
        $conversation = $this->group($owner, [
            $admin->id => ConversationMember::ROLE_ADMIN,
            $member->id => ConversationMember::ROLE_MEMBER,
        ]);

        $this->actingAs($member)
            ->patchJson("/chat/{$conversation->id}/group", ['title' => 'Nope'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->patchJson("/chat/{$conversation->id}/group", ['title' => 'New title'])
            ->assertOk()
            ->assertJson(['success' => true, 'title' => 'New title']);

        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'title' => 'New title',
        ]);

        $this->actingAs($admin)
            ->postJson("/chat/{$conversation->id}/group/avatar", [
                'avatar' => UploadedFile::fake()->image('group.png'),
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $conversation->refresh();

        $this->assertNotNull($conversation->avatar);
        Storage::disk('public')->assertExists($conversation->avatar);
    }

    private function user(string $login): User
    {
        return User::factory()->create([
            'login' => $login,
            'name' => $login,
            'pseudonym' => $login,
            'email' => null,
        ]);
    }

    private function befriend(User $first, User $second): void
    {
        Friend::create(['user_id' => $first->id, 'friend_id' => $second->id]);
        Friend::create(['user_id' => $second->id, 'friend_id' => $first->id]);
    }

    /**
     * @param  array<int, string>  $members
     */
    private function group(User $owner, array $members = []): Conversation
    {
        $conversation = Conversation::create([
            'type' => Conversation::TYPE_GROUP,
            'title' => 'Group',
        ]);

        ConversationMember::create([
            'conversation_id' => $conversation->id,
            'user_id' => $owner->id,
            'role' => ConversationMember::ROLE_OWNER,
            'joined_at' => now(),
        ]);

        foreach ($members as $userId => $role) {
            ConversationMember::create([
                'conversation_id' => $conversation->id,
                'user_id' => $userId,
                'role' => $role,
                'joined_at' => now(),
            ]);
        }

        return $conversation;
    }
}
