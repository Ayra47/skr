<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\CommunityDirectInvite;
use App\Models\CommunityKeyEpoch;
use App\Models\CommunityMember;
use App\Models\CommunityPost;
use App\Models\CommunityTopic;
use App\Models\Friend;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_communities_index_shows_public_community(): void
    {
        $user = User::factory()->create();
        Community::factory()->create(['name' => 'Public Design Lab', 'visibility' => Community::VISIBILITY_PUBLIC]);

        $this->actingAs($user)
            ->get(route('communities.index'))
            ->assertOk()
            ->assertSeeText('Public Design Lab');
    }

    public function test_hidden_community_is_hidden_from_non_member(): void
    {
        $user = User::factory()->create();
        Community::factory()->create(['name' => 'Open Studio', 'visibility' => Community::VISIBILITY_PUBLIC]);
        Community::factory()->create(['name' => 'Hidden Studio', 'visibility' => Community::VISIBILITY_HIDDEN]);

        $this->actingAs($user)
            ->get(route('communities.index'))
            ->assertOk()
            ->assertSeeText('Open Studio')
            ->assertDontSeeText('Hidden Studio');
    }

    public function test_active_member_sees_private_and_hidden_communities(): void
    {
        $user = User::factory()->create();
        $private = Community::factory()->create(['name' => 'Private Studio', 'visibility' => Community::VISIBILITY_PRIVATE]);
        $hidden = Community::factory()->create(['name' => 'Hidden Member Studio', 'visibility' => Community::VISIBILITY_HIDDEN]);
        CommunityMember::factory()->for($private)->for($user)->create(['status' => CommunityMember::STATUS_ACTIVE]);
        CommunityMember::factory()->for($hidden)->for($user)->create(['status' => CommunityMember::STATUS_ACTIVE]);

        $this->actingAs($user)
            ->get(route('communities.index'))
            ->assertOk()
            ->assertSeeText('Private Studio')
            ->assertSeeText('Hidden Member Studio');
    }

    public function test_direct_invite_appears_in_sidebar(): void
    {
        $invitee = User::factory()->create();
        $inviter = User::factory()->create(['login' => 'mila', 'pseudonym' => 'Mila']);
        $community = Community::factory()->create(['name' => 'design · skr']);
        CommunityDirectInvite::factory()->pending()->for($community)->create([
            'inviter_id' => $inviter->id,
            'invitee_id' => $invitee->id,
        ]);

        $this->actingAs($invitee)
            ->get(route('communities.index'))
            ->assertOk()
            ->assertSeeText('Приглашения от друзей')
            ->assertSeeText('Mila')
            ->assertSeeText('design · skr');
    }

    public function test_accept_direct_invite_works_from_ui_action(): void
    {
        $invitee = User::factory()->create();
        $community = Community::factory()->create();
        $invite = CommunityDirectInvite::factory()->pending()->for($community)->create(['invitee_id' => $invitee->id]);

        $this->actingAs($invitee)
            ->post(route('communities.direct-invites.accept', $invite))
            ->assertRedirect(route('communities.show', $community));

        $this->assertDatabaseHas('community_direct_invites', [
            'id' => $invite->id,
            'status' => CommunityDirectInvite::STATUS_ACCEPTED,
        ]);
        $this->assertDatabaseHas('community_members', [
            'community_id' => $community->id,
            'user_id' => $invitee->id,
            'status' => CommunityMember::STATUS_PENDING_KEY_DELIVERY,
        ]);
    }

    public function test_accept_private_direct_invite_opens_locked_community_state(): void
    {
        $invitee = User::factory()->create();
        $community = Community::factory()->create(['visibility' => Community::VISIBILITY_PRIVATE]);
        $invite = CommunityDirectInvite::factory()->pending()->for($community)->create(['invitee_id' => $invitee->id]);

        $this->actingAs($invitee)
            ->post(route('communities.direct-invites.accept', $invite))
            ->assertRedirect(route('communities.show', $community));

        $this->actingAs($invitee)
            ->get(route('communities.show', $community))
            ->assertOk()
            ->assertSeeText('Waiting for keys');
    }

    public function test_decline_direct_invite_works_from_ui_action(): void
    {
        $invitee = User::factory()->create();
        $invite = CommunityDirectInvite::factory()->pending()->create(['invitee_id' => $invitee->id]);

        $this->actingAs($invitee)
            ->post(route('communities.direct-invites.decline', $invite))
            ->assertRedirect(route('communities.index'));

        $this->assertDatabaseHas('community_direct_invites', [
            'id' => $invite->id,
            'status' => CommunityDirectInvite::STATUS_DECLINED,
        ]);
    }

    public function test_create_community_form_action_works(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('communities.store'), [
                'name' => 'Created From UI',
                'description' => 'Minimal community',
                'visibility' => Community::VISIBILITY_PUBLIC,
                'join_mode' => Community::JOIN_OPEN,
                'member_limit' => 100,
                'default_post_ttl_seconds' => 86400,
                'invite_policy' => Community::INVITE_POLICY_ALL_MEMBERS,
                'posting_policy' => Community::POSTING_POLICY_EVERYONE,
                'allow_posts_in_member_feed' => '1',
                'hide_real_names' => '0',
                'show_key_fingerprints' => '1',
                'anonymous_reactions_enabled' => '0',
            ]);

        $community = Community::where('name', 'Created From UI')->firstOrFail();
        $response->assertRedirect(route('communities.show', $community));
    }

    public function test_community_detail_visible_to_member(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['name' => 'Member Space', 'visibility' => Community::VISIBILITY_PRIVATE]);
        CommunityMember::factory()->for($community)->for($user)->create(['status' => CommunityMember::STATUS_ACTIVE]);

        $this->actingAs($user)
            ->get(route('communities.show', $community))
            ->assertOk()
            ->assertSeeText('Member Space');
    }

    public function test_community_detail_returns_404_for_hidden_non_member(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['visibility' => Community::VISIBILITY_HIDDEN]);

        $this->actingAs($user)
            ->get(route('communities.show', $community))
            ->assertNotFound();
    }

    public function test_moderator_sees_topic_create_controls(): void
    {
        $moderator = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($moderator)->create(['role' => CommunityMember::ROLE_MODERATOR]);

        $this->actingAs($moderator)
            ->get(route('communities.show', $community))
            ->assertOk()
            ->assertSeeText('Создать тему');
    }

    public function test_regular_member_does_not_see_topic_create_controls(): void
    {
        $member = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->for($community)->for($member)->create(['role' => CommunityMember::ROLE_MEMBER]);

        $this->actingAs($member)
            ->get(route('communities.show', $community))
            ->assertOk()
            ->assertDontSeeText('Создать тему');
    }

    public function test_encrypted_post_placeholder_renders_without_ciphertext_leakage(): void
    {
        $member = User::factory()->create();
        $community = Community::factory()->create();
        $topic = CommunityTopic::factory()->for($community)->create(['name' => 'General']);
        $epoch = CommunityKeyEpoch::factory()->for($community)->create();
        CommunityMember::factory()->for($community)->for($member)->create(['status' => CommunityMember::STATUS_ACTIVE]);
        CommunityPost::factory()->for($community)->for($topic, 'topic')->for($member, 'author')->create([
            'epoch_id' => $epoch->id,
            'ciphertext' => 'SECRET-CIPHERTEXT-LEAK',
            'nonce' => 'SECRET-NONCE-LEAK',
        ]);

        $this->actingAs($member)
            ->get(route('communities.show', ['community' => $community, 'topic' => $topic->id]))
            ->assertOk()
            ->assertSeeText('Encrypted post')
            ->assertDontSee('SECRET-CIPHERTEXT-LEAK')
            ->assertDontSee('SECRET-NONCE-LEAK');
    }

    public function test_composer_does_not_render_or_accept_plaintext_body(): void
    {
        $member = User::factory()->create();
        $community = Community::factory()->create();
        $topic = CommunityTopic::factory()->for($community)->create();
        $epoch = CommunityKeyEpoch::factory()->for($community)->create();
        CommunityMember::factory()->for($community)->for($member)->create(['status' => CommunityMember::STATUS_ACTIVE]);

        $this->actingAs($member)
            ->get(route('communities.show', ['community' => $community, 'topic' => $topic->id]))
            ->assertOk()
            ->assertDontSee('name="body"', false);

        $this->actingAs($member)
            ->post(route('communities.topics.posts.store', [$community, $topic]), [
                'body' => 'plaintext must not be accepted',
                'ciphertext' => 'encrypted',
                'nonce' => 'nonce',
                'epoch_id' => $epoch->id,
            ])
            ->assertSessionHasErrors('body');
    }

    public function test_invite_panel_visible_to_allowed_inviter_and_hidden_from_disallowed_member(): void
    {
        $moderator = User::factory()->create();
        $member = User::factory()->create();
        $invitee = User::factory()->create(['login' => 'invitee']);
        $community = Community::factory()->create(['invite_policy' => Community::INVITE_POLICY_MODERATORS_ONLY]);
        CommunityMember::factory()->for($community)->for($moderator)->create(['role' => CommunityMember::ROLE_MODERATOR]);
        CommunityMember::factory()->for($community)->for($member)->create(['role' => CommunityMember::ROLE_MEMBER]);
        $this->befriend($moderator, $invitee);

        $this->actingAs($moderator)
            ->get(route('communities.show', $community))
            ->assertOk()
            ->assertSeeText('Пригласить друга');

        $this->actingAs($member)
            ->get(route('communities.show', $community))
            ->assertOk()
            ->assertDontSeeText('Пригласить друга');
    }

    private function befriend(User $first, User $second): void
    {
        Friend::create(['user_id' => $first->id, 'friend_id' => $second->id]);
        Friend::create(['user_id' => $second->id, 'friend_id' => $first->id]);
    }
}
