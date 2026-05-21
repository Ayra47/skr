<?php

namespace App\Http\Controllers;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Services\Community\CommunityDirectInviteService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class CommunitiesController extends Controller
{
    public function __construct(
        private readonly CommunityDirectInviteService $directInvites,
    ) {}

    public function index(): View
    {
        $user = Auth::user();
        $memberships = CommunityMember::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                CommunityMember::STATUS_ACTIVE,
                CommunityMember::STATUS_PENDING_KEY_DELIVERY,
            ])
            ->get()
            ->keyBy('community_id');

        $communities = Community::query()
            ->where('visibility', Community::VISIBILITY_PUBLIC)
            ->orWhereIn('id', $memberships->keys())
            ->orderBy('name')
            ->get();

        return view('pages.communities.index', [
            'communities' => $communities,
            'memberships' => $memberships,
            'directInvites' => $this->directInvites->listPendingForUser($user),
        ]);
    }
}
