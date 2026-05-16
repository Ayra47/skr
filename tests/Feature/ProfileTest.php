<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\FeedPost;
use App\Models\FeedPostAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_profile_page(): void
    {
        $profileUser = $this->makeUser('profile-login', 'profile-pseudo');

        $this->get(route('profiles.show', $profileUser))
            ->assertRedirect(route('login'));
    }

    public function test_profile_page_renders_public_identity_and_visible_recent_posts(): void
    {
        $viewer = $this->makeUser('viewer-login', 'viewer-pseudo');
        $profileUser = $this->makeUser('private-login', 'public-pseudo', 'Private Name');
        $stranger = $this->makeUser('stranger-login', 'stranger-pseudo');

        $this->befriend($viewer, $profileUser);

        FeedPost::query()->create([
            'user_id' => $profileUser->id,
            'body' => 'public profile post',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ]);
        FeedPost::query()->create([
            'user_id' => $profileUser->id,
            'body' => 'friends profile post',
            'visibility' => FeedPost::VISIBILITY_FRIENDS,
        ]);
        FeedPost::query()->create([
            'user_id' => $profileUser->id,
            'body' => 'hidden whisper post',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
            'is_whisper' => true,
        ]);
        FeedPost::query()->create([
            'user_id' => $stranger->id,
            'body' => 'stranger post',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ]);

        $this->withoutVite();

        $this->actingAs($viewer)->get(route('profiles.show', $profileUser))
            ->assertOk()
            ->assertSeeText('public-pseudo')
            ->assertSeeText('public profile post')
            ->assertSeeText('friends profile post')
            ->assertDontSeeText('hidden whisper post')
            ->assertDontSeeText('private-login')
            ->assertDontSeeText('Private Name')
            ->assertDontSeeText('stranger post')
            ->assertSeeText('Safety code')
            ->assertSeeText('Функция появится позже');
    }

    public function test_profile_page_renders_recent_post_attachments_and_reactions(): void
    {
        $viewer = $this->makeUser('viewer-login', 'viewer-pseudo');
        $profileUser = $this->makeUser('profile-login', 'profile-pseudo');
        $this->befriend($viewer, $profileUser);

        $post = FeedPost::query()->create([
            'user_id' => $profileUser->id,
            'body' => 'post with attachment',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ]);

        FeedPostAttachment::query()->create([
            'feed_post_id' => $post->id,
            'path' => 'feed-attachments/photo.jpg',
            'name' => 'photo.jpg',
            'mime' => 'image/jpeg',
            'size' => 1024,
            'position' => 1,
        ]);

        $this->withoutVite();

        $this->actingAs($viewer)->get(route('profiles.show', $profileUser))
            ->assertOk()
            ->assertSee('feed-gallery', false)
            ->assertSeeText('0 ответов');
    }

    public function test_own_profile_hides_call_button(): void
    {
        $viewer = $this->makeUser('viewer-login', 'viewer-pseudo');

        $this->withoutVite();

        $this->actingAs($viewer)->get(route('profiles.show', $viewer))
            ->assertOk()
            ->assertDontSeeText('Позвонить');
    }

    public function test_non_friend_profile_hides_write_action(): void
    {
        $viewer = $this->makeUser('viewer-login', 'viewer-pseudo');
        $profileUser = $this->makeUser('profile-login', 'profile-pseudo');

        $this->withoutVite();

        $this->actingAs($viewer)->get(route('profiles.show', $profileUser))
            ->assertOk()
            ->assertDontSeeText('Написать');
    }

    public function test_profile_page_shows_common_group_chats_and_marks_groups_as_coming_soon(): void
    {
        $viewer = $this->makeUser('viewer-login', 'viewer-pseudo');
        $profileUser = $this->makeUser('profile-login', 'profile-pseudo');
        $sharedFriend = $this->makeUser('shared-login', 'shared-pseudo');

        $this->befriend($viewer, $profileUser);
        $this->befriend($viewer, $sharedFriend);
        $this->befriend($profileUser, $sharedFriend);

        $conversation = Conversation::query()->create([
            'type' => Conversation::TYPE_GROUP,
            'title' => 'Ключи и протокол',
        ]);

        ConversationMember::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $viewer->id,
            'role' => ConversationMember::ROLE_MEMBER,
            'joined_at' => now(),
        ]);
        ConversationMember::query()->create([
            'conversation_id' => $conversation->id,
            'user_id' => $profileUser->id,
            'role' => ConversationMember::ROLE_ADMIN,
            'joined_at' => now(),
        ]);

        $this->withoutVite();

        $this->actingAs($viewer)->get(route('profiles.show', $profileUser))
            ->assertOk()
            ->assertSeeText('Ключи и протокол')
            ->assertSee(route('chats.index').'?conversation='.$conversation->id, false)
            ->assertSeeText('Общие группы')
            ->assertSeeText('скоро')
            ->assertSeeText('1 общий друг');
    }

    public function test_profile_menu_links_to_current_user_profile(): void
    {
        $viewer = $this->makeUser('viewer-login', 'viewer-pseudo');

        $this->withoutVite();

        $this->actingAs($viewer)->get(route('friends.index'))
            ->assertOk()
            ->assertSee(route('profiles.show', $viewer), false);
    }

    private function makeUser(string $login, ?string $pseudonym, ?string $name = null): User
    {
        return User::factory()->create([
            'login' => $login,
            'name' => $name ?? $login,
            'email' => null,
            'pseudonym' => $pseudonym,
        ]);
    }

    private function befriend(User $first, User $second): void
    {
        DB::table('friends')->insert([
            [
                'user_id' => $first->id,
                'friend_id' => $second->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $second->id,
                'friend_id' => $first->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
