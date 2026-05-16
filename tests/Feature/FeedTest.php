<?php

namespace Tests\Feature;

use App\Livewire\Feed;
use App\Models\FeedPost;
use App\Models\FeedVote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_tabs_filter_posts_by_visibility_and_relationship(): void
    {
        $viewer = $this->makeUser('viewer', 'viewer-pseudo');
        $friend = $this->makeUser('friend', 'friend-pseudo');
        $stranger = $this->makeUser('stranger', 'stranger-pseudo');

        $this->befriend($viewer, $friend);

        FeedPost::query()->create([
            'user_id' => $friend->id,
            'body' => 'friend public note',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ]);
        FeedPost::query()->create([
            'user_id' => $friend->id,
            'body' => 'friend private note',
            'visibility' => FeedPost::VISIBILITY_FRIENDS,
        ]);
        FeedPost::query()->create([
            'user_id' => $stranger->id,
            'body' => 'stranger public note',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ]);
        FeedPost::query()->create([
            'user_id' => $stranger->id,
            'body' => 'stranger private note',
            'visibility' => FeedPost::VISIBILITY_FRIENDS,
        ]);

        $this->withoutVite();

        $friendsResponse = $this->actingAs($viewer)->get('/');
        $friendsResponse
            ->assertOk()
            ->assertSeeText('friend-pseudo')
            ->assertSeeText('friend public note')
            ->assertSeeText('friend private note')
            ->assertDontSeeText('stranger public note')
            ->assertDontSeeText('stranger private note');

        $allResponse = $this->actingAs($viewer)->get('/?tab=all');
        $allResponse
            ->assertOk()
            ->assertSeeText('friend public note')
            ->assertSeeText('stranger public note')
            ->assertDontSeeText('friend private note')
            ->assertDontSeeText('stranger private note');
    }

    public function test_feed_page_exposes_authenticated_user_id_for_global_notifications(): void
    {
        $viewer = $this->makeUser('viewer', 'viewer-pseudo');

        $this->withoutVite();

        $this->actingAs($viewer)->get('/')
            ->assertOk()
            ->assertSee('window.Laravel', false)
            ->assertSee('userId: '.$viewer->id, false);
    }

    public function test_feed_page_renders_livewire_feed_component(): void
    {
        $viewer = $this->makeUser('viewer', 'viewer-pseudo');

        $this->withoutVite();

        $this->actingAs($viewer)->get('/')
            ->assertOk()
            ->assertSeeLivewire(Feed::class);
    }

    public function test_hidden_attachment_preview_has_explicit_hidden_style(): void
    {
        $stylesheet = file_get_contents(resource_path('css/pages/feed.scss'));

        $this->assertStringContainsString('.feed-attachment-preview[hidden]', $stylesheet);
    }

    public function test_feed_composer_exposes_multiple_attachment_input_for_client_side_previews(): void
    {
        $user = $this->makeUser('author', 'author-pseudo');

        Livewire::actingAs($user)
            ->test(Feed::class)
            ->assertSee('wire:model="attachments"', false)
            ->assertSee('multiple', false)
            ->assertSee('data-feed-attachment-previews', false);
    }

    public function test_whisper_mode_disables_friends_visibility_in_composer(): void
    {
        $user = $this->makeUser('author', 'author-pseudo');

        Livewire::actingAs($user)
            ->test(Feed::class)
            ->set('isWhisper', true)
            ->set('visibility', FeedPost::VISIBILITY_PUBLIC)
            ->assertSee('value="'.FeedPost::VISIBILITY_FRIENDS.'" disabled', false);
    }

    public function test_mine_tab_only_shows_current_user_posts(): void
    {
        $viewer = $this->makeUser('viewer-secret-login', 'viewer-pseudo', 'Viewer Real Name');
        $friend = $this->makeUser('friend-secret-login', 'friend-pseudo', 'Friend Real Name');

        $this->befriend($viewer, $friend);

        FeedPost::query()->create([
            'user_id' => $viewer->id,
            'body' => 'my public note',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ]);
        FeedPost::query()->create([
            'user_id' => $viewer->id,
            'body' => 'my friends note',
            'visibility' => FeedPost::VISIBILITY_FRIENDS,
        ]);
        FeedPost::query()->create([
            'user_id' => $friend->id,
            'body' => 'friend note',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ]);

        $this->withoutVite();

        $this->actingAs($viewer)->get('/?tab=mine')
            ->assertOk()
            ->assertSeeText('my public note')
            ->assertSeeText('my friends note')
            ->assertDontSeeText('friend note');
    }

    public function test_feed_never_renders_author_login_or_name(): void
    {
        $viewer = $this->makeUser('viewer-secret-login', 'viewer-pseudo', 'Viewer Real Name');
        $author = $this->makeUser('author-secret-login', 'author-pseudo', 'Author Real Name');

        FeedPost::query()->create([
            'user_id' => $author->id,
            'body' => 'public note',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ]);

        $this->withoutVite();

        $this->actingAs($viewer)->get('/?tab=all')
            ->assertOk()
            ->assertSeeText('author-pseudo')
            ->assertDontSeeText('author-secret-login')
            ->assertDontSeeText('Author Real Name');
    }

    public function test_missing_pseudonym_does_not_fall_back_to_login(): void
    {
        $viewer = $this->makeUser('viewer-secret-login', 'viewer-pseudo', 'Viewer Real Name');
        $author = $this->makeUser('legacy-secret-login', null, 'Legacy Real Name');

        FeedPost::query()->create([
            'user_id' => $author->id,
            'body' => 'legacy public note',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ]);

        $this->withoutVite();

        $this->actingAs($viewer)->get('/?tab=all')
            ->assertOk()
            ->assertSeeText('anon-'.$author->id)
            ->assertDontSeeText('legacy-secret-login')
            ->assertDontSeeText('Legacy Real Name');
    }

    public function test_user_can_create_posts_for_friends_or_everyone(): void
    {
        $user = $this->makeUser('author', 'author-pseudo');

        $response = $this->actingAs($user)->post('/feed/posts', [
            'body' => 'public launch note',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ]);

        $response->assertRedirectToRoute('feed.index');
        $this->assertDatabaseHas('feed_posts', [
            'user_id' => $user->id,
            'body' => 'public launch note',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ]);

        $response = $this->actingAs($user)->post('/feed/posts', [
            'body' => 'friends-only note',
            'visibility' => FeedPost::VISIBILITY_FRIENDS,
        ]);

        $response->assertRedirectToRoute('feed.index');
        $this->assertDatabaseHas('feed_posts', [
            'user_id' => $user->id,
            'body' => 'friends-only note',
            'visibility' => FeedPost::VISIBILITY_FRIENDS,
        ]);
    }

    public function test_user_can_choose_post_lifetime(): void
    {
        $user = $this->makeUser('author', 'author-pseudo');

        $this->actingAs($user)->post('/feed/posts', [
            'body' => 'one day note',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
            'expires_in' => '24h',
        ])->assertRedirectToRoute('feed.index');

        $post = FeedPost::query()->firstOrFail();

        $this->assertNotNull($post->expires_at);
        $this->assertTrue($post->expires_at->between(now()->addHours(23), now()->addHours(25)));

        $this->actingAs($user)->post('/feed/posts', [
            'body' => 'forever note',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
            'expires_in' => 'forever',
        ])->assertRedirectToRoute('feed.index');

        $this->assertDatabaseHas('feed_posts', [
            'body' => 'forever note',
            'expires_at' => null,
        ]);
    }

    public function test_livewire_user_can_create_post_without_page_reload(): void
    {
        $user = $this->makeUser('author', 'author-pseudo');

        Livewire::actingAs($user)
            ->test(Feed::class)
            ->set('body', 'livewire public note')
            ->set('visibility', FeedPost::VISIBILITY_PUBLIC)
            ->set('expiresIn', FeedPost::EXPIRES_FOREVER)
            ->call('createPost')
            ->assertSet('body', '')
            ->assertSee('livewire public note');

        $this->assertDatabaseHas('feed_posts', [
            'user_id' => $user->id,
            'body' => 'livewire public note',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
            'expires_at' => null,
        ]);
    }

    public function test_livewire_whisper_posts_are_always_public_and_detached_from_the_user(): void
    {
        $user = $this->makeUser('author', 'author-pseudo');

        Livewire::actingAs($user)
            ->test(Feed::class)
            ->set('body', 'quiet note')
            ->set('visibility', FeedPost::VISIBILITY_FRIENDS)
            ->set('isWhisper', true)
            ->call('createPost');

        $this->assertDatabaseHas('feed_posts', [
            'user_id' => null,
            'body' => 'quiet note',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
            'is_whisper' => true,
        ]);
    }

    public function test_feed_post_model_detaches_whisper_posts_from_the_user(): void
    {
        $user = $this->makeUser('author', 'author-pseudo');

        $post = FeedPost::query()->create([
            'user_id' => $user->id,
            'body' => 'quiet model note',
            'visibility' => FeedPost::VISIBILITY_FRIENDS,
            'is_whisper' => true,
        ]);

        $this->assertNull($post->fresh()->user_id);
        $this->assertSame(FeedPost::VISIBILITY_PUBLIC, $post->fresh()->visibility);
    }

    public function test_livewire_votes_and_comments_update_without_page_reload(): void
    {
        $author = $this->makeUser('author', 'author-pseudo');
        $reader = $this->makeUser('reader', 'reader-pseudo');
        $post = FeedPost::query()->create([
            'user_id' => $author->id,
            'body' => 'public note',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ]);

        Livewire::actingAs($reader)
            ->test(Feed::class)
            ->call('setTab', 'all')
            ->call('vote', $post->id, FeedVote::VALUE_UP)
            ->assertSee('1')
            ->set("commentBodies.{$post->id}", 'anonymous reply')
            ->call('createComment', $post->id)
            ->assertSet("commentBodies.{$post->id}", '')
            ->assertSee('1 ответ');

        $this->assertDatabaseHas('feed_votes', [
            'feed_post_id' => $post->id,
            'user_id' => $reader->id,
            'value' => FeedVote::VALUE_UP,
        ]);
        $this->assertDatabaseHas('feed_comments', [
            'feed_post_id' => $post->id,
            'user_id' => $reader->id,
            'body' => 'anonymous reply',
        ]);
    }

    public function test_livewire_comment_count_toggles_three_latest_replies(): void
    {
        $author = $this->makeUser('author', 'author-pseudo');
        $reader = $this->makeUser('reader', 'reader-pseudo');
        $post = FeedPost::query()->create([
            'user_id' => $author->id,
            'body' => 'public note with replies',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ]);

        foreach (['first reply', 'second reply', 'third reply', 'fourth reply'] as $reply) {
            $post->comments()->create([
                'user_id' => $reader->id,
                'body' => $reply,
            ]);
        }

        Livewire::actingAs($reader)
            ->test(Feed::class)
            ->call('setTab', 'all')
            ->assertDontSee('fourth reply')
            ->call('toggleComments', $post->id)
            ->assertSee('first reply')
            ->assertSee('third reply')
            ->assertSee('second reply')
            ->assertDontSee('fourth reply');
    }

    public function test_feed_shows_only_top_root_comment_before_preview_panel_is_opened(): void
    {
        $author = $this->makeUser('author', 'author-pseudo');
        $reader = $this->makeUser('reader', 'reader-pseudo');
        $other = $this->makeUser('other', 'other-pseudo');
        $post = FeedPost::query()->create([
            'user_id' => $author->id,
            'body' => 'public note with top reply',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ]);
        $olderComment = $post->comments()->create([
            'user_id' => $reader->id,
            'body' => 'older quiet reply',
            'created_at' => now()->subMinutes(3),
            'updated_at' => now()->subMinutes(3),
        ]);
        $popularComment = $post->comments()->create([
            'user_id' => $reader->id,
            'body' => 'popular reply',
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);
        $popularComment->votes()->create([
            'user_id' => $other->id,
            'value' => FeedVote::VALUE_UP,
        ]);

        Livewire::actingAs($reader)
            ->test(Feed::class)
            ->call('setTab', 'all')
            ->assertSee('popular reply')
            ->assertDontSee('older quiet reply')
            ->assertDontSee('data-feed-replies-panel="open"', false);
    }

    public function test_livewire_loads_ten_more_preview_comments_for_open_post_panel(): void
    {
        $author = $this->makeUser('author', 'author-pseudo');
        $reader = $this->makeUser('reader', 'reader-pseudo');
        $post = FeedPost::query()->create([
            'user_id' => $author->id,
            'body' => 'public note with many replies',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ]);

        foreach (range(1, 14) as $index) {
            $post->comments()->create([
                'user_id' => $reader->id,
                'body' => 'preview reply '.$index,
            ]);
        }

        Livewire::actingAs($reader)
            ->test(Feed::class)
            ->call('setTab', 'all')
            ->call('toggleComments', $post->id)
            ->assertSee('preview reply 1')
            ->assertSee('preview reply 3')
            ->assertDontSee('preview reply 4')
            ->call('loadMorePostComments', $post->id)
            ->assertSee('preview reply 13')
            ->assertDontSee('preview reply 14');
    }

    public function test_preview_panel_does_not_offer_more_root_comments_for_nested_replies_only(): void
    {
        $author = $this->makeUser('author', 'author-pseudo');
        $reader = $this->makeUser('reader', 'reader-pseudo');
        $post = FeedPost::query()->create([
            'user_id' => $author->id,
            'body' => 'public note with nested replies',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ]);
        $parent = $post->comments()->create([
            'user_id' => $reader->id,
            'body' => 'single root reply',
        ]);

        foreach (range(1, 6) as $index) {
            $post->comments()->create([
                'user_id' => $reader->id,
                'parent_id' => $parent->id,
                'body' => 'nested-only reply '.$index,
            ]);
        }

        Livewire::actingAs($reader)
            ->test(Feed::class)
            ->call('setTab', 'all')
            ->call('toggleComments', $post->id)
            ->assertDontSee('data-feed-replies-load-more', false);
    }

    public function test_livewire_post_modal_shows_all_replies_and_can_create_reply(): void
    {
        $author = $this->makeUser('author', 'author-pseudo');
        $reader = $this->makeUser('reader-secret-login', 'reader-pseudo', 'Reader Real Name');
        $post = FeedPost::query()->create([
            'user_id' => $author->id,
            'body' => 'modal public note',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ]);

        foreach (['first modal reply', 'second modal reply', 'third modal reply', 'fourth modal reply'] as $reply) {
            $post->comments()->create([
                'user_id' => $reader->id,
                'body' => $reply,
            ]);
        }

        Livewire::actingAs($reader)
            ->test(Feed::class)
            ->call('setTab', 'all')
            ->call('openPost', $post->id)
            ->assertSee('Все ответы')
            ->assertSee('first modal reply')
            ->assertSee('fourth modal reply')
            ->assertDontSee('reader-secret-login')
            ->assertDontSee('Reader Real Name')
            ->set("commentBodies.{$post->id}", 'modal created reply')
            ->call('createComment', $post->id)
            ->assertSee('modal created reply')
            ->call('closePost')
            ->assertDontSee('Все ответы');

        $this->assertDatabaseHas('feed_comments', [
            'feed_post_id' => $post->id,
            'user_id' => $reader->id,
            'body' => 'modal created reply',
        ]);
    }

    public function test_comment_authors_are_rendered_as_pseudonyms(): void
    {
        $author = $this->makeUser('author', 'author-pseudo');
        $reader = $this->makeUser('reader-secret-login', 'reader-pseudo', 'Reader Real Name');
        $post = FeedPost::query()->create([
            'user_id' => $author->id,
            'body' => 'public note',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ]);
        $post->comments()->create([
            'user_id' => $reader->id,
            'body' => 'comment with author',
        ]);

        Livewire::actingAs($reader)
            ->test(Feed::class)
            ->call('setTab', 'all')
            ->call('openPost', $post->id)
            ->assertSee('reader-pseudo')
            ->assertDontSee('reader-secret-login')
            ->assertDontSee('Reader Real Name');
    }

    public function test_comment_author_can_edit_and_delete_own_comment(): void
    {
        $author = $this->makeUser('author', 'author-pseudo');
        $reader = $this->makeUser('reader', 'reader-pseudo');
        $post = FeedPost::query()->create([
            'user_id' => $author->id,
            'body' => 'public note',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ]);
        $comment = $post->comments()->create([
            'user_id' => $reader->id,
            'body' => 'original comment',
        ]);

        Livewire::actingAs($reader)
            ->test(Feed::class)
            ->call('setTab', 'all')
            ->call('openPost', $post->id)
            ->call('startEditingComment', $comment->id)
            ->set('editingCommentBody', 'updated comment')
            ->call('updateComment')
            ->assertSee('updated comment')
            ->call('deleteComment', $comment->id)
            ->assertSee('Комментарий удален')
            ->assertDontSee('updated comment');

        $comment->refresh();
        $this->assertSame('updated comment', $comment->body);
        $this->assertNotNull($comment->deleted_at);
    }

    public function test_comment_edit_shows_badge_and_previous_version(): void
    {
        $author = $this->makeUser('author', 'author-pseudo');
        $reader = $this->makeUser('reader', 'reader-pseudo');
        $post = FeedPost::query()->create([
            'user_id' => $author->id,
            'body' => 'public note',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ]);
        $comment = $post->comments()->create([
            'user_id' => $reader->id,
            'body' => 'original visible version',
        ]);

        Livewire::actingAs($reader)
            ->test(Feed::class)
            ->call('setTab', 'all')
            ->call('openPost', $post->id)
            ->call('startEditingComment', $comment->id)
            ->set('editingCommentBody', 'updated visible version')
            ->call('updateComment')
            ->assertSee('ред.')
            ->assertSee('Старая версия')
            ->assertSee('original visible version')
            ->assertSee('updated visible version');

        $this->assertDatabaseHas('feed_comment_edits', [
            'feed_comment_id' => $comment->id,
            'body' => 'original visible version',
        ]);
    }

    public function test_comment_edit_with_same_body_does_not_create_history(): void
    {
        $author = $this->makeUser('author', 'author-pseudo');
        $reader = $this->makeUser('reader', 'reader-pseudo');
        $post = FeedPost::query()->create([
            'user_id' => $author->id,
            'body' => 'public note',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ]);
        $comment = $post->comments()->create([
            'user_id' => $reader->id,
            'body' => 'same body',
        ]);

        Livewire::actingAs($reader)
            ->test(Feed::class)
            ->call('setTab', 'all')
            ->call('openPost', $post->id)
            ->call('startEditingComment', $comment->id)
            ->set('editingCommentBody', 'same body')
            ->call('updateComment')
            ->assertDontSee('ред.');

        $this->assertDatabaseCount('feed_comment_edits', 0);
    }

    public function test_non_author_cannot_edit_or_delete_comment(): void
    {
        $author = $this->makeUser('author', 'author-pseudo');
        $reader = $this->makeUser('reader', 'reader-pseudo');
        $stranger = $this->makeUser('stranger', 'stranger-pseudo');
        $post = FeedPost::query()->create([
            'user_id' => $author->id,
            'body' => 'public note',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ]);
        $comment = $post->comments()->create([
            'user_id' => $reader->id,
            'body' => 'private ownership comment',
        ]);

        Livewire::actingAs($stranger)
            ->test(Feed::class)
            ->call('setTab', 'all')
            ->call('startEditingComment', $comment->id)
            ->assertForbidden();

        Livewire::actingAs($stranger)
            ->test(Feed::class)
            ->call('setTab', 'all')
            ->call('deleteComment', $comment->id)
            ->assertForbidden();
    }

    public function test_user_can_reply_to_comment_and_expand_nested_replies_in_batches(): void
    {
        $author = $this->makeUser('author', 'author-pseudo');
        $reader = $this->makeUser('reader', 'reader-pseudo');
        $post = FeedPost::query()->create([
            'user_id' => $author->id,
            'body' => 'public note',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ]);
        $parent = $post->comments()->create([
            'user_id' => $reader->id,
            'body' => 'parent comment',
        ]);

        foreach (range(1, 6) as $index) {
            $post->comments()->create([
                'user_id' => $reader->id,
                'parent_id' => $parent->id,
                'body' => 'nested reply '.$index,
            ]);
        }

        Livewire::actingAs($reader)
            ->test(Feed::class)
            ->call('setTab', 'all')
            ->call('openPost', $post->id)
            ->assertSee('parent comment')
            ->assertDontSee('nested reply 1')
            ->call('startReplyingToComment', $parent->id)
            ->set('replyBody', 'new nested reply')
            ->call('createCommentReply')
            ->assertSee('nested reply 1')
            ->assertDontSee('nested reply 6')
            ->call('showMoreCommentReplies', $parent->id)
            ->assertSee('new nested reply')
            ->assertSee('nested reply 6');

        $this->assertDatabaseHas('feed_comments', [
            'feed_post_id' => $post->id,
            'user_id' => $reader->id,
            'parent_id' => $parent->id,
            'body' => 'new nested reply',
        ]);
    }

    public function test_reply_created_from_preview_panel_renders_as_nested_tree(): void
    {
        $author = $this->makeUser('author', 'author-pseudo');
        $reader = $this->makeUser('reader', 'reader-pseudo');
        $post = FeedPost::query()->create([
            'user_id' => $author->id,
            'body' => 'public note',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ]);
        $parent = $post->comments()->create([
            'user_id' => $reader->id,
            'body' => 'preview parent comment',
        ]);

        Livewire::actingAs($reader)
            ->test(Feed::class)
            ->call('setTab', 'all')
            ->call('toggleComments', $post->id)
            ->call('startReplyingToComment', $parent->id)
            ->set('replyBody', 'preview nested reply')
            ->call('createCommentReply')
            ->assertSee('preview parent comment')
            ->assertSee('preview nested reply');
    }

    public function test_comment_reactions_toggle_and_sort_comments_by_total_reactions_then_age(): void
    {
        $author = $this->makeUser('author', 'author-pseudo');
        $reader = $this->makeUser('reader', 'reader-pseudo');
        $other = $this->makeUser('other', 'other-pseudo');
        $post = FeedPost::query()->create([
            'user_id' => $author->id,
            'body' => 'public note',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ]);
        $oldComment = $post->comments()->create([
            'user_id' => $reader->id,
            'body' => 'old low reaction comment',
            'created_at' => now()->subMinutes(3),
            'updated_at' => now()->subMinutes(3),
        ]);
        $newComment = $post->comments()->create([
            'user_id' => $reader->id,
            'body' => 'new high reaction comment',
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        $newComment->votes()->create([
            'user_id' => $other->id,
            'value' => FeedVote::VALUE_DOWN,
        ]);

        Livewire::actingAs($reader)
            ->test(Feed::class)
            ->call('setTab', 'all')
            ->call('openPost', $post->id)
            ->assertSeeInOrder(['new high reaction comment', 'old low reaction comment'])
            ->call('voteComment', $oldComment->id, FeedVote::VALUE_UP)
            ->assertSeeInOrder(['new high reaction comment', 'old low reaction comment'])
            ->call('voteComment', $oldComment->id, FeedVote::VALUE_UP)
            ->assertSeeInOrder(['new high reaction comment', 'old low reaction comment']);
    }

    public function test_comment_order_refreshes_after_reopening_post(): void
    {
        $author = $this->makeUser('author', 'author-pseudo');
        $reader = $this->makeUser('reader', 'reader-pseudo');
        $other = $this->makeUser('other', 'other-pseudo');
        $post = FeedPost::query()->create([
            'user_id' => $author->id,
            'body' => 'public note',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ]);
        $oldComment = $post->comments()->create([
            'user_id' => $reader->id,
            'body' => 'old comment after reopen',
            'created_at' => now()->subMinutes(3),
            'updated_at' => now()->subMinutes(3),
        ]);
        $newComment = $post->comments()->create([
            'user_id' => $reader->id,
            'body' => 'new comment after reopen',
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        $newComment->votes()->create([
            'user_id' => $other->id,
            'value' => FeedVote::VALUE_DOWN,
        ]);

        Livewire::actingAs($reader)
            ->test(Feed::class)
            ->call('setTab', 'all')
            ->call('openPost', $post->id)
            ->assertSeeInOrder(['new comment after reopen', 'old comment after reopen'])
            ->call('voteComment', $oldComment->id, FeedVote::VALUE_UP)
            ->assertSeeInOrder(['new comment after reopen', 'old comment after reopen'])
            ->call('closePost')
            ->call('openPost', $post->id)
            ->assertSeeInOrder(['old comment after reopen', 'new comment after reopen']);
    }

    public function test_expired_posts_are_hidden_from_feed(): void
    {
        $viewer = $this->makeUser('viewer', 'viewer-pseudo');
        $author = $this->makeUser('author', 'author-pseudo');

        FeedPost::query()->create([
            'user_id' => $author->id,
            'body' => 'fresh note',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
            'expires_at' => now()->addHour(),
        ]);
        FeedPost::query()->create([
            'user_id' => $author->id,
            'body' => 'expired note',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
            'expires_at' => now()->subMinute(),
        ]);

        $this->withoutVite();

        $this->actingAs($viewer)->get('/?tab=all')
            ->assertOk()
            ->assertSeeText('fresh note')
            ->assertDontSeeText('expired note');
    }

    public function test_empty_post_without_attachment_is_rejected(): void
    {
        $user = $this->makeUser('author', 'author-pseudo');

        $response = $this->actingAs($user)->from('/')->post('/feed/posts', [
            'body' => '   ',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ]);

        $response->assertRedirect('/');
        $response->assertSessionHasErrors('body');
        $this->assertDatabaseCount('feed_posts', 0);
    }

    public function test_user_can_upload_multiple_attachments_with_post(): void
    {
        Storage::fake('local');

        $user = $this->makeUser('author', 'author-pseudo');
        $document = UploadedFile::fake()->create('keys.pdf', 64, 'application/pdf');
        $image = UploadedFile::fake()->image('cover.jpg');

        $response = $this->actingAs($user)->post('/feed/posts', [
            'body' => null,
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
            'attachments' => [$document, $image],
        ]);

        $response->assertRedirectToRoute('feed.index');

        $post = FeedPost::query()->firstOrFail();
        $attachments = $post->attachments()->orderBy('position')->get();

        $this->assertCount(2, $attachments);
        $this->assertSame(['keys.pdf', 'cover.jpg'], $attachments->pluck('name')->all());
        $this->assertSame([1, 2], $attachments->pluck('position')->all());
        $this->assertNull($attachments[0]->thumbnail_path);
        $this->assertNotNull($attachments[1]->thumbnail_path);
        Storage::disk('local')->assertExists($attachments[0]->path);
        Storage::disk('local')->assertExists($attachments[1]->path);
        Storage::disk('local')->assertExists($attachments[1]->thumbnail_path);

        [$width, $height] = getimagesize(Storage::disk('local')->path($attachments[1]->thumbnail_path));

        $this->assertSame(100, $width);
        $this->assertSame(100, $height);
    }

    public function test_livewire_user_can_create_post_with_multiple_attachments(): void
    {
        Storage::fake('local');

        $user = $this->makeUser('author', 'author-pseudo');
        $image = UploadedFile::fake()->image('cover.jpg');
        $video = UploadedFile::fake()->create('clip.mp4', 256, 'video/mp4');

        Livewire::actingAs($user)
            ->test(Feed::class)
            ->set('attachments', [$image, $video])
            ->set('visibility', FeedPost::VISIBILITY_PUBLIC)
            ->set('expiresIn', FeedPost::EXPIRES_FOREVER)
            ->call('createPost');

        $this->assertDatabaseCount('feed_post_attachments', 2);
        $this->assertDatabaseHas('feed_post_attachments', ['name' => 'cover.jpg', 'position' => 1]);
        $this->assertDatabaseHas('feed_post_attachments', ['name' => 'clip.mp4', 'position' => 2]);

        $attachments = FeedPost::query()->firstOrFail()->attachments()->orderBy('position')->get();

        $this->assertNotNull($attachments[0]->thumbnail_path);
        $this->assertNull($attachments[1]->thumbnail_path);
    }

    public function test_feed_accepts_video_attachments_up_to_five_hundred_megabytes(): void
    {
        Storage::fake('local');

        $user = $this->makeUser('author', 'author-pseudo');
        $file = UploadedFile::fake()->create('large-clip.mp4', 500 * 1024, 'video/mp4');

        $this->actingAs($user)->post('/feed/posts', [
            'body' => null,
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
            'expires_in' => 'forever',
            'attachments' => [$file],
        ])->assertRedirectToRoute('feed.index');

        $this->assertDatabaseHas('feed_post_attachments', [
            'name' => 'large-clip.mp4',
            'size' => 500 * 1024 * 1024,
        ]);
    }

    public function test_livewire_temporary_upload_limit_allows_five_hundred_megabytes(): void
    {
        $this->assertSame(['required', 'file', 'max:512000'], config('livewire.temporary_file_upload.rules'));
    }

    public function test_friends_only_attachment_is_not_available_to_stranger(): void
    {
        Storage::fake('local');

        $author = $this->makeUser('author', 'author-pseudo');
        $friend = $this->makeUser('friend', 'friend-pseudo');
        $stranger = $this->makeUser('stranger', 'stranger-pseudo');

        $this->befriend($author, $friend);

        $path = UploadedFile::fake()->create('keys.pdf', 64, 'application/pdf')->store('feed-attachments', 'local');

        $post = FeedPost::query()->create([
            'user_id' => $author->id,
            'body' => 'private file',
            'visibility' => FeedPost::VISIBILITY_FRIENDS,
        ]);
        $attachment = $post->attachments()->create([
            'path' => $path,
            'name' => 'keys.pdf',
            'mime' => 'application/pdf',
            'size' => 65536,
            'position' => 1,
        ]);

        $this->actingAs($friend)->get(route('feed.posts.attachments.show', [$post, $attachment]))
            ->assertOk();

        $this->actingAs($stranger)->get(route('feed.posts.attachments.show', [$post, $attachment]))
            ->assertForbidden();
    }

    public function test_feed_rejects_more_than_ten_attachments(): void
    {
        $user = $this->makeUser('author', 'author-pseudo');
        $attachments = collect(range(1, 11))
            ->map(fn (int $index) => UploadedFile::fake()->create("file-{$index}.txt", 1, 'text/plain'))
            ->all();

        $this->actingAs($user)->from('/')->post('/feed/posts', [
            'body' => null,
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
            'attachments' => $attachments,
        ])->assertRedirect('/')
            ->assertSessionHasErrors('attachments');

        $this->assertDatabaseCount('feed_posts', 0);
    }

    public function test_feed_renders_attachments_as_gallery_in_upload_order(): void
    {
        Storage::fake('local');

        $viewer = $this->makeUser('viewer', 'viewer-pseudo');
        $author = $this->makeUser('author', 'author-pseudo');
        $post = FeedPost::query()->create([
            'user_id' => $author->id,
            'body' => 'mixed attachments',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ]);
        $post->attachments()->createMany([
            [
                'path' => UploadedFile::fake()->create('guide.pdf', 64, 'application/pdf')->store('feed-attachments', 'local'),
                'name' => 'guide.pdf',
                'mime' => 'application/pdf',
                'size' => 65536,
                'position' => 1,
            ],
            [
                'path' => UploadedFile::fake()->image('cover.jpg')->store('feed-attachments', 'local'),
                'thumbnail_path' => UploadedFile::fake()->image('cover-thumb.webp')->store('feed-attachment-thumbnails', 'local'),
                'name' => 'cover.jpg',
                'mime' => 'image/jpeg',
                'size' => 1024,
                'position' => 2,
            ],
            [
                'path' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain')->store('feed-attachments', 'local'),
                'name' => 'notes.txt',
                'mime' => 'text/plain',
                'size' => 1024,
                'position' => 3,
            ],
        ]);

        $this->withoutVite();

        $this->actingAs($viewer)->get('/?tab=all')
            ->assertOk()
            ->assertSee('data-feed-gallery', false)
            ->assertSee('data-feed-gallery-main-item', false)
            ->assertSee('data-feed-gallery-thumb', false)
            ->assertSee('thumbnail=1', false)
            ->assertSeeInOrder(['guide.pdf', 'cover.jpg', 'notes.txt']);
    }

    public function test_user_can_switch_and_toggle_vote_on_visible_post(): void
    {
        $user = $this->makeUser('reader', 'reader-pseudo');
        $post = FeedPost::query()->create([
            'user_id' => $this->makeUser('author', 'author-pseudo')->id,
            'body' => 'visible note',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ]);

        $this->actingAs($user)->post("/feed/posts/{$post->id}/vote", [
            'value' => FeedVote::VALUE_UP,
        ])->assertRedirect();

        $this->assertDatabaseHas('feed_votes', [
            'feed_post_id' => $post->id,
            'user_id' => $user->id,
            'value' => FeedVote::VALUE_UP,
        ]);

        $this->actingAs($user)->post("/feed/posts/{$post->id}/vote", [
            'value' => FeedVote::VALUE_DOWN,
        ])->assertRedirect();

        $this->assertDatabaseCount('feed_votes', 1);
        $this->assertDatabaseHas('feed_votes', [
            'feed_post_id' => $post->id,
            'user_id' => $user->id,
            'value' => FeedVote::VALUE_DOWN,
        ]);

        $this->actingAs($user)->post("/feed/posts/{$post->id}/vote", [
            'value' => FeedVote::VALUE_DOWN,
        ])->assertRedirect();

        $this->assertDatabaseCount('feed_votes', 0);
    }

    public function test_stranger_cannot_comment_on_friends_only_post(): void
    {
        $author = $this->makeUser('author', 'author-pseudo');
        $stranger = $this->makeUser('stranger', 'stranger-pseudo');
        $post = FeedPost::query()->create([
            'user_id' => $author->id,
            'body' => 'private note',
            'visibility' => FeedPost::VISIBILITY_FRIENDS,
        ]);

        $this->actingAs($stranger)->post("/feed/posts/{$post->id}/comments", [
            'body' => 'not allowed',
        ])->assertForbidden();

        $this->assertDatabaseCount('feed_comments', 0);
    }

    public function test_stranger_cannot_vote_on_friends_only_post(): void
    {
        $author = $this->makeUser('author', 'author-pseudo');
        $stranger = $this->makeUser('stranger', 'stranger-pseudo');
        $post = FeedPost::query()->create([
            'user_id' => $author->id,
            'body' => 'private note',
            'visibility' => FeedPost::VISIBILITY_FRIENDS,
        ]);

        $this->actingAs($stranger)->post("/feed/posts/{$post->id}/vote", [
            'value' => FeedVote::VALUE_UP,
        ])->assertForbidden();

        $this->assertDatabaseCount('feed_votes', 0);
    }

    public function test_comment_count_is_rendered_without_commenter_names(): void
    {
        $author = $this->makeUser('author', 'author-pseudo');
        $firstReader = $this->makeUser('reader-one-login', 'reader-one-pseudo', 'Reader One');
        $secondReader = $this->makeUser('reader-two-login', 'reader-two-pseudo', 'Reader Two');
        $thirdReader = $this->makeUser('reader-three-login', 'reader-three-pseudo', 'Reader Three');
        $post = FeedPost::query()->create([
            'user_id' => $author->id,
            'body' => 'public note',
            'visibility' => FeedPost::VISIBILITY_PUBLIC,
        ]);

        foreach ([$firstReader, $secondReader, $thirdReader] as $index => $reader) {
            $this->actingAs($reader)->post("/feed/posts/{$post->id}/comments", [
                'body' => 'private answer '.$index,
            ])->assertRedirect();
        }

        $this->withoutVite();

        $this->actingAs($author)->get('/?tab=all')
            ->assertOk()
            ->assertSeeText('3 ответа')
            ->assertDontSeeText('reader-one-login')
            ->assertDontSeeText('Reader One')
            ->assertDontSeeText('reader-one-pseudo')
            ->assertDontSeeText('reader-two-login')
            ->assertDontSeeText('Reader Two')
            ->assertDontSeeText('reader-two-pseudo')
            ->assertDontSeeText('reader-three-login')
            ->assertDontSeeText('Reader Three')
            ->assertDontSeeText('reader-three-pseudo');
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
