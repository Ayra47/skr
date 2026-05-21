<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\MarkCommunityReadRequest;
use App\Http\Requests\Community\MarkCommunityTopicReadRequest;
use App\Models\Community;
use App\Models\CommunityTopic;
use App\Services\Community\CommunityReadStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CommunityReadStateController extends Controller
{
    public function __construct(
        private readonly CommunityReadStateService $readState,
    ) {}

    public function markTopicRead(MarkCommunityTopicReadRequest $request, CommunityTopic $topic): JsonResponse
    {
        $this->readState->markTopicRead(Auth::user(), $topic, $request->validated('topic_seq'));

        return response()->json(['success' => true]);
    }

    public function markCommunityRead(MarkCommunityReadRequest $request, Community $community): JsonResponse
    {
        $this->readState->markCommunityRead(Auth::user(), $community, $request->validated('community_seq'));

        return response()->json(['success' => true]);
    }
}
