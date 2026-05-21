<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\StoreCommunityRequest;
use App\Models\Community;
use App\Models\CommunityKeyEpoch;
use App\Models\CommunityTopic;
use App\Services\Community\CommunityCreationService;
use App\Services\Community\CommunityPolicyService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommunityController extends Controller
{
    public function __construct(
        private readonly CommunityCreationService $creation,
        private readonly CommunityPolicyService $policy,
    ) {}

    public function show(Request $request, Community $community): JsonResponse|View
    {
        $user = Auth::user();

        if (! $this->policy->canViewCommunity($user, $community)) {
            abort(404);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'community' => $community->only([
                    'id', 'name', 'slug', 'description', 'join_mode', 'visibility',
                    'member_count', 'post_count', 'invite_policy', 'posting_policy',
                    'allow_posts_in_member_feed', 'hide_real_names',
                    'show_key_fingerprints', 'anonymous_reactions_enabled',
                    'created_at',
                ]),
            ]);
        }

        $membership = $this->policy->getMembership($user, $community);
        $topics = $community->topics()
            ->orderByDesc('is_pinned')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $selectedTopic = $this->selectedTopic($request, $topics);
        $posts = $selectedTopic
            ? $selectedTopic->posts()->with(['author'])->orderByDesc('created_at')->limit(50)->get()
            : collect();

        $members = $community->members()->with('user')->orderBy('role')->orderBy('created_at')->get();
        $friends = $user->friends->merge($user->friendOf)->unique('id')->sortBy('login')->values();

        return view('pages.communities.show', [
            'community' => $community,
            'membership' => $membership,
            'topics' => $topics,
            'selectedTopic' => $selectedTopic,
            'posts' => $posts,
            'members' => $members,
            'latestEpoch' => CommunityKeyEpoch::where('community_id', $community->id)->orderByDesc('epoch_number')->first(),
            'friends' => $friends,
            'canInvite' => $this->policy->canInvite($user, $community),
            'canManageTopics' => $this->policy->canManageTopic($user, $community),
        ]);
    }

    public function store(StoreCommunityRequest $request): JsonResponse|RedirectResponse
    {
        $community = $this->creation->create(Auth::user(), $request->validated());

        if (! $request->expectsJson()) {
            return redirect()->route('communities.show', $community)
                ->with('community_status', 'Сообщество создано.');
        }

        return response()->json(['success' => true, 'community' => ['id' => $community->id, 'slug' => $community->slug]], 201);
    }

    private function selectedTopic(Request $request, mixed $topics): ?CommunityTopic
    {
        if ($topics->isEmpty()) {
            return null;
        }

        $topicId = $request->query('topic');

        if ($topicId === null) {
            return $topics->first();
        }

        return $topics->firstWhere('id', $topicId) ?? $topics->first();
    }
}
