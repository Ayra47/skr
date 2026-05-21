<?php

namespace Tests\Feature;

use App\Models\FeedItem;
use App\Models\FeedPost;
use App\Models\ProfileSetting;
use App\Models\User;
use App\Services\FeedVisibilityService;
use App\Services\ProfileActivityReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfileActivityTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makePost(User $user, array $attrs = []): FeedPost
    {
        return FeedPost::query()->create(array_merge([
            'user_id' => $user->id,
            'body' => 'test post',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ], $attrs));
    }

    private function reader(): ProfileActivityReader
    {
        return new ProfileActivityReader(new FeedVisibilityService);
    }

    private function makeFriends(User $a, User $b): void
    {
        DB::table('friends')->insert([
            ['user_id' => $a->id, 'friend_id' => $b->id, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $b->id, 'friend_id' => $a->id, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function profileRoute(User $user): string
    {
        return route('profiles.show', $user);
    }

    // -------------------------------------------------------------------------
    // 1. profile_access=none hides profile from non-self viewer (403)
    // -------------------------------------------------------------------------

    public function test_profile_access_none_hides_profile_from_non_self(): void
    {
        $owner = $this->makeUser();
        $viewer = $this->makeUser();

        $owner->profileSetting()->create(['profile_access' => ProfileSetting::AUDIENCE_NONE]);

        $this->actingAs($viewer)->get($this->profileRoute($owner))->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // 2. profile_posts_visibility=none hides posts section for viewer
    // -------------------------------------------------------------------------

    public function test_profile_posts_visibility_none_hides_posts_for_viewer(): void
    {
        config(['features.unified_feed_items_enabled' => true]);

        $owner = $this->makeUser();
        $viewer = $this->makeUser();

        $owner->profileSetting()->create(['profile_posts_visibility' => ProfileSetting::AUDIENCE_NONE]);

        $post = $this->makePost($owner);

        $this->actingAs($viewer)
            ->get($this->profileRoute($owner))
            ->assertOk()
            ->assertDontSeeText($post->body);
    }

    // -------------------------------------------------------------------------
    // 3. Whisper posts do not appear in profile activity (service-level)
    // -------------------------------------------------------------------------

    public function test_whisper_posts_do_not_appear_in_profile_activity(): void
    {
        $owner = $this->makeUser();
        $viewer = $this->makeUser();

        $this->makePost($owner, ['is_whisper' => true]);

        $posts = $this->reader()->readForProfile($viewer, $owner);

        $this->assertEmpty($posts);
    }

    // -------------------------------------------------------------------------
    // 4. Self sees own friends-only post on their profile
    // -------------------------------------------------------------------------

    public function test_self_sees_own_friends_only_post_on_profile(): void
    {
        $owner = $this->makeUser();

        $post = $this->makePost($owner, ['visibility' => FeedPost::VISIBILITY_FRIENDS]);

        $posts = $this->reader()->readForProfile($owner, $owner);

        $this->assertCount(1, $posts);
        $this->assertSame($post->id, $posts->first()->id);
    }

    // -------------------------------------------------------------------------
    // 5. Feature flag false — ProfileController uses legacy feedPosts() query
    // -------------------------------------------------------------------------

    public function test_feature_flag_false_uses_legacy_recent_posts_query(): void
    {
        config(['features.unified_feed_items_enabled' => false]);

        $owner = $this->makeUser();
        $viewer = $this->makeUser();

        $post = $this->makePost($owner);

        // Force-delete all feed_items: if unified path were active it would return empty.
        FeedItem::withTrashed()->forceDelete();

        $this->actingAs($viewer)
            ->get($this->profileRoute($owner))
            ->assertOk()
            ->assertSeeText($post->body);
    }
}
