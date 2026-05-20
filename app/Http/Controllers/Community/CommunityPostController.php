<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\PublishCommunityPostRequest;
use App\Models\Community;
use App\Models\CommunityTopic;
use App\Services\Community\CommunityPostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CommunityPostController extends Controller
{
    public function __construct(
        private readonly CommunityPostService $posts,
    ) {}

    public function store(PublishCommunityPostRequest $request, Community $community, CommunityTopic $topic): JsonResponse
    {
        $post = $this->posts->publishEncryptedPost(
            Auth::user(),
            $community,
            $topic,
            $request->validated(),
        );

        return response()->json(['success' => true, 'post' => ['id' => $post->id, 'community_seq' => $post->community_seq]], 201);
    }
}
