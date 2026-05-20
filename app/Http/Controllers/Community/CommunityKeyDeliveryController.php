<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\DeliverCommunityMemberKeysRequest;
use App\Models\CommunityMember;
use App\Services\Community\CommunityKeyDeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class CommunityKeyDeliveryController extends Controller
{
    public function __construct(
        private readonly CommunityKeyDeliveryService $keyDelivery,
    ) {}

    public function store(DeliverCommunityMemberKeysRequest $request, CommunityMember $member): JsonResponse|RedirectResponse
    {
        $this->keyDelivery->deliverMemberKeys(Auth::user(), $member, $request->validated('keys'));

        if (! $request->expectsJson()) {
            return redirect()->route('communities.show', $member->community)
                ->with('community_status', 'Ключи доставлены.');
        }

        return response()->json(['success' => true]);
    }
}
