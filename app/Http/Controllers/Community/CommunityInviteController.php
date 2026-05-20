<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\GenerateCommunityInviteRequest;
use App\Models\Community;
use App\Models\CommunityInvite;
use App\Services\Community\CommunityInviteService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CommunityInviteController extends Controller
{
    public function __construct(
        private readonly CommunityInviteService $invites,
    ) {}

    public function store(GenerateCommunityInviteRequest $request, Community $community): JsonResponse
    {
        $validated = $request->validated();

        $invite = $this->invites->generateInvite(
            Auth::user(),
            $community,
            $validated['max_uses'] ?? null,
            isset($validated['expires_at']) ? Carbon::parse($validated['expires_at']) : null,
        );

        return response()->json(['success' => true, 'invite' => ['id' => $invite->id, 'code' => $invite->code]], 201);
    }

    public function destroy(CommunityInvite $invite): JsonResponse
    {
        $this->invites->revokeInvite(Auth::user(), $invite);

        return response()->json(['success' => true]);
    }
}
