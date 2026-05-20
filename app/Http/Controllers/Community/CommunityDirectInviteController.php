<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\SendCommunityDirectInviteRequest;
use App\Models\Community;
use App\Models\CommunityDirectInvite;
use App\Models\User;
use App\Services\Community\CommunityDirectInviteService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CommunityDirectInviteController extends Controller
{
    public function __construct(
        private readonly CommunityDirectInviteService $invites,
    ) {}

    public function index(): JsonResponse
    {
        $invitations = $this->invites->listPendingForUser(Auth::user())
            ->map(fn (CommunityDirectInvite $invite): array => [
                'id' => $invite->id,
                'inviter' => [
                    'id' => $invite->inviter->id,
                    'login' => $invite->inviter->login,
                    'display_name' => $this->displayName($invite->inviter),
                ],
                'community' => [
                    'id' => $invite->community->id,
                    'name' => $invite->community->name,
                    'visibility' => $invite->community->visibility,
                ],
                'expires_at' => $invite->expires_at?->toIso8601String(),
                'created_at' => $invite->created_at?->toIso8601String(),
            ]);

        return response()->json(['success' => true, 'invitations' => $invitations]);
    }

    public function store(SendCommunityDirectInviteRequest $request, Community $community): JsonResponse
    {
        $validated = $request->validated();
        $invitee = User::findOrFail($validated['invitee_id']);

        $invite = $this->invites->sendInvite(
            Auth::user(),
            $community,
            $invitee,
            $validated['message'] ?? null,
            isset($validated['expires_at']) ? Carbon::parse($validated['expires_at']) : null,
        );

        return response()->json([
            'success' => true,
            'invite' => [
                'id' => $invite->id,
                'status' => $invite->status,
            ],
        ], 201);
    }

    public function accept(CommunityDirectInvite $invite): JsonResponse
    {
        $member = $this->invites->acceptInvite(Auth::user(), $invite);

        return response()->json([
            'success' => true,
            'member' => [
                'id' => $member->id,
                'status' => $member->status,
            ],
        ]);
    }

    public function decline(CommunityDirectInvite $invite): JsonResponse
    {
        $this->invites->declineInvite(Auth::user(), $invite);

        return response()->json(['success' => true]);
    }

    public function cancel(CommunityDirectInvite $invite): JsonResponse
    {
        $this->invites->cancelInvite(Auth::user(), $invite);

        return response()->json(['success' => true]);
    }

    private function displayName(User $user): string
    {
        return $user->pseudonym ?: $user->name ?: $user->login;
    }
}
