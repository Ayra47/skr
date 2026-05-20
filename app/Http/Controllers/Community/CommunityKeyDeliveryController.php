<?php

namespace App\Http\Controllers\Community;

use App\Http\Controllers\Controller;
use App\Http\Requests\Community\DeliverCommunityMemberKeysRequest;
use App\Models\CommunityMember;
use App\Services\Community\CommunityKeyDeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CommunityKeyDeliveryController extends Controller
{
    public function __construct(
        private readonly CommunityKeyDeliveryService $keyDelivery,
    ) {}

    public function store(DeliverCommunityMemberKeysRequest $request, CommunityMember $member): JsonResponse
    {
        $this->keyDelivery->deliverMemberKeys(Auth::user(), $member, $request->validated('keys'));

        return response()->json(['success' => true]);
    }
}
