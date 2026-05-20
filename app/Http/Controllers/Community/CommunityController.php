<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\StoreCommunityRequest;
use App\Models\Community;
use App\Services\Community\CommunityCreationService;
use App\Services\Community\CommunityPolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CommunityController extends Controller
{
    public function __construct(
        private readonly CommunityCreationService $creation,
        private readonly CommunityPolicyService $policy,
    ) {}

    public function show(Community $community): JsonResponse
    {
        if (! $this->policy->canViewCommunity(Auth::user(), $community)) {
            abort(404);
        }

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

    public function store(StoreCommunityRequest $request): JsonResponse
    {
        $community = $this->creation->create(Auth::user(), $request->validated());

        return response()->json(['success' => true, 'community' => ['id' => $community->id, 'slug' => $community->slug]], 201);
    }
}
