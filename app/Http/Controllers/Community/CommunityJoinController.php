<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\JoinByInviteRequest;
use App\Http\Requests\Community\RequestCommunityJoinRequest;
use App\Models\Community;
use App\Models\CommunityJoinRequest;
use App\Services\Community\CommunityJoinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CommunityJoinController extends Controller
{
    public function __construct(
        private readonly CommunityJoinService $joins,
    ) {}

    public function joinPublic(Request $request, Community $community): JsonResponse|RedirectResponse
    {
        $member = $this->joins->joinPublic(Auth::user(), $community);

        if (! $request->expectsJson()) {
            return redirect()->route('communities.show', $community)
                ->with('community_status', 'Вы присоединились.');
        }

        return response()->json(['success' => true, 'member' => ['id' => $member->id, 'status' => $member->status]], 201);
    }

    public function joinByInvite(JoinByInviteRequest $request): JsonResponse
    {
        $member = $this->joins->joinByInvite(Auth::user(), $request->validated('code'));

        return response()->json(['success' => true, 'member' => ['id' => $member->id, 'status' => $member->status]], 201);
    }

    public function requestJoin(RequestCommunityJoinRequest $request, Community $community): JsonResponse|RedirectResponse
    {
        $joinRequest = $this->joins->requestJoin(Auth::user(), $community, $request->validated('message'));

        if (! $request->expectsJson()) {
            return back()->with('community_status', 'Заявка на вступление отправлена.');
        }

        return response()->json(['success' => true, 'join_request' => ['id' => $joinRequest->id]], 201);
    }

    public function approveJoinRequest(CommunityJoinRequest $joinRequest): JsonResponse
    {
        $member = $this->joins->approveJoinRequest(Auth::user(), $joinRequest);

        return response()->json(['success' => true, 'member' => ['id' => $member->id, 'status' => $member->status]]);
    }

    public function rejectJoinRequest(CommunityJoinRequest $joinRequest): JsonResponse
    {
        $this->joins->rejectJoinRequest(Auth::user(), $joinRequest);

        return response()->json(['success' => true]);
    }
}
