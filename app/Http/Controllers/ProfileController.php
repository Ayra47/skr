<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\FeedVote;
use App\Models\ProfileSetting;
use App\Models\User;
use App\Services\FeedVisibilityService;
use App\Services\ProfileActivityReader;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(User $user): View
    {
        $viewer = Auth::user();
        $isFriend = $viewer->isFriendWith($user->id);
        $profileSetting = $user->profileSetting;

        if (! $viewer->is($user)) {
            $access = $profileSetting?->profile_access ?? ProfileSetting::AUDIENCE_EVERYONE;

            if ($access === ProfileSetting::AUDIENCE_NONE ||
                ($access === ProfileSetting::AUDIENCE_FRIENDS && ! $isFriend)) {
                $allFriends = $viewer->friends->merge($viewer->friendOf)->unique('id');
                $allFriends->load('userKey');

                return view('pages.profile.hidden', [
                    'profileUser' => $user,
                    'viewer' => $viewer,
                    'allFriends' => $allFriends,
                ]);
            }
        }

        $isSelf = $viewer->is($user);

        // Returns true if the viewer can see a section with the given audience setting.
        $canSee = function (string $setting) use ($isSelf, $isFriend, $profileSetting): bool {
            if ($isSelf) {
                return true;
            }

            $audience = $profileSetting?->$setting ?? ProfileSetting::AUDIENCE_EVERYONE;

            return match ($audience) {
                ProfileSetting::AUDIENCE_EVERYONE => true,
                ProfileSetting::AUDIENCE_FRIENDS => $isFriend,
                default => false,
            };
        };

        $actuallyOnline = $user->last_seen_at?->gt(now()->subMinutes(2)) ?? false;
        $isOnline = $canSee('online_status_visibility') && $actuallyOnline;

        $showAvatar = $canSee('avatar_visibility');
        $showSharedFriendsCount = $canSee('shared_friends_count_visibility');
        $showPostsCount = $canSee('feed_posts_count_visibility');
        $showPosts = $canSee('profile_posts_visibility');
        $showSharedChats = $isSelf || ($profileSetting?->show_shared_chats ?? true);
        $showSharedGroups = $isSelf || ($profileSetting?->show_shared_groups ?? true);

        $friendIds = $viewer->friendIds();

        if (config('features.unified_feed_items_enabled')) {
            $activityReader = new ProfileActivityReader(new FeedVisibilityService);
            $recentPosts = config('features.community_feed_items_enabled')
                ? $activityReader->readCardsForProfile($viewer, $user)
                : $activityReader->readForProfile($viewer, $user);
        } else {
            $recentPosts = $user->feedPosts()
                ->with([
                    'author',
                    'attachments' => fn ($query) => $query->orderBy('position'),
                ])
                ->withCount([
                    'comments',
                    'votes as up_votes_count' => fn ($query) => $query->where('value', FeedVote::VALUE_UP),
                    'votes as down_votes_count' => fn ($query) => $query->where('value', FeedVote::VALUE_DOWN),
                ])
                ->visibleTo($viewer, $friendIds)
                ->visibleOnProfile()
                ->live()
                ->latest()
                ->limit(5)
                ->get();
        }

        $visiblePostsCount = $user->feedPosts()
            ->visibleTo($viewer, $friendIds)
            ->visibleOnProfile()
            ->live()
            ->count();

        $sharedGroupChats = Conversation::query()
            ->where('type', Conversation::TYPE_GROUP)
            ->whereHas('members', fn ($query) => $query->where('user_id', $viewer->id))
            ->whereHas('members', fn ($query) => $query->where('user_id', $user->id))
            ->withCount('members')
            ->latest()
            ->get();

        $sharedFriendsCount = DB::table('friends as viewer_friends')
            ->join('friends as profile_friends', 'profile_friends.friend_id', '=', 'viewer_friends.friend_id')
            ->where('viewer_friends.user_id', $viewer->id)
            ->where('profile_friends.user_id', $user->id)
            ->count();

        $friendship = DB::table('friends')
            ->where('user_id', $viewer->id)
            ->where('friend_id', $user->id)
            ->first();

        $directConversation = Conversation::query()
            ->where('type', Conversation::TYPE_DIRECT)
            ->where(function ($query) use ($viewer, $user) {
                $query->where([
                    ['user_a_id', '=', min($viewer->id, $user->id)],
                    ['user_b_id', '=', max($viewer->id, $user->id)],
                ]);
            })
            ->first();

        $allFriends = $viewer->friends->merge($viewer->friendOf)->unique('id');
        $allFriends->load('userKey');

        return view('pages.profile.show', [
            'allFriends' => $allFriends,
            'bio' => $user->profileSetting?->bio,
            'directConversation' => $directConversation,
            'isFriend' => $isFriend,
            'isSelf' => $isSelf,
            'isOnline' => $isOnline,
            'profileUser' => $user,
            'recentPosts' => $recentPosts,
            'sharedFriendsCount' => $sharedFriendsCount,
            'sharedGroupChats' => $sharedGroupChats,
            'showAvatar' => $showAvatar,
            'showPosts' => $showPosts,
            'showPostsCount' => $showPostsCount,
            'showSharedChats' => $showSharedChats,
            'showSharedFriendsCount' => $showSharedFriendsCount,
            'visiblePostsCount' => $visiblePostsCount,
            'viewer' => $viewer,
            'friendship' => $friendship,
        ]);
    }
}
