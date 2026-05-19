<?php

namespace App\Http\Controllers;

use App\Models\FeedPost;
use App\Services\PollVotingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PollController extends Controller
{
    public function __construct(private readonly PollVotingService $votingService) {}

    public function vote(Request $request, FeedPost $post): JsonResponse
    {
        $data = $request->validate([
            'option_ids' => ['required', 'array', 'min:1'],
            'option_ids.*' => ['integer'],
        ]);

        $poll = $post->poll;

        if ($poll === null) {
            return response()->json(['error' => 'no_poll'], 404);
        }

        $poll->load('options');

        $result = $this->votingService->vote($poll, $request->user()->id, $data['option_ids']);

        if (isset($result['error'])) {
            return response()->json($result, 422);
        }

        return response()->json($result);
    }

    public function cancelVote(Request $request, FeedPost $post): JsonResponse
    {
        $poll = $post->poll;

        if ($poll === null) {
            return response()->json(['error' => 'no_poll'], 404);
        }

        $poll->load('options');

        $result = $this->votingService->cancelAll($poll, $request->user()->id);

        return response()->json($result);
    }
}
