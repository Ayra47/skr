<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\StoreCommunityTopicRequest;
use App\Models\Community;
use App\Models\CommunityTopic;
use App\Services\Community\CommunityTopicService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommunityTopicController extends Controller
{
    public function __construct(
        private readonly CommunityTopicService $topics,
    ) {}

    public function store(StoreCommunityTopicRequest $request, Community $community): JsonResponse|RedirectResponse
    {
        $topic = $this->topics->createTopic(Auth::user(), $community, $request->validated());

        if (! $request->expectsJson()) {
            return redirect()->route('communities.show', ['community' => $community, 'topic' => $topic->id])
                ->with('community_status', 'Тема создана.');
        }

        return response()->json(['success' => true, 'topic' => ['id' => $topic->id, 'slug' => $topic->slug]], 201);
    }

    public function archive(Request $request, CommunityTopic $topic): JsonResponse|RedirectResponse
    {
        $this->topics->archiveTopic(Auth::user(), $topic);

        if (! $request->expectsJson()) {
            return redirect()->route('communities.show', ['community' => $topic->community, 'topic' => $topic->id])
                ->with('community_status', 'Тема архивирована.');
        }

        return response()->json(['success' => true]);
    }
}
