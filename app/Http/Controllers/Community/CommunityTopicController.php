<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\StoreCommunityTopicRequest;
use App\Models\Community;
use App\Models\CommunityTopic;
use App\Services\Community\CommunityTopicService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CommunityTopicController extends Controller
{
    public function __construct(
        private readonly CommunityTopicService $topics,
    ) {}

    public function store(StoreCommunityTopicRequest $request, Community $community): JsonResponse
    {
        $topic = $this->topics->createTopic(Auth::user(), $community, $request->validated());

        return response()->json(['success' => true, 'topic' => ['id' => $topic->id, 'slug' => $topic->slug]], 201);
    }

    public function archive(CommunityTopic $topic): JsonResponse
    {
        $this->topics->archiveTopic(Auth::user(), $topic);

        return response()->json(['success' => true]);
    }
}
