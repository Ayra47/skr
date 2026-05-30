<?php

namespace App\Livewire\Communities;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityUserState;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

class CommunityList extends Component
{
    private const TABS = ['all', 'pinned', 'unread', 'admin'];

    #[Url]
    public string $tab = 'all';

    public string $search = '';

    public function render(): View
    {
        $userId = Auth::id();

        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'all';
        }

        $memberships = CommunityMember::query()
            ->where('user_id', $userId)
            ->whereIn('status', [
                CommunityMember::STATUS_ACTIVE,
                CommunityMember::STATUS_PENDING_KEY_DELIVERY,
            ])
            ->get()
            ->keyBy('community_id');

        $adminRoles = [
            CommunityMember::ROLE_OWNER,
            CommunityMember::ROLE_ADMIN,
            CommunityMember::ROLE_MODERATOR,
        ];

        $adminCommunityIds = $memberships
            ->filter(fn (CommunityMember $member) => in_array($member->role, $adminRoles, true))
            ->pluck('community_id');

        $pinnedCommunityIds = CommunityUserState::query()
            ->where('user_id', $userId)
            ->where('pinned', true)
            ->pluck('community_id');

        $unreadCommunityIds = CommunityUserState::query()
            ->join('communities', 'community_user_state.community_id', '=', 'communities.id')
            ->where('community_user_state.user_id', $userId)
            ->where(function ($query): void {
                $query->where('community_user_state.unread_posts_count', '>', 0)
                    ->orWhereColumn('communities.post_count', '>', 'community_user_state.last_read_community_seq');
            })
            ->pluck('community_user_state.community_id');

        $query = Community::query()
            ->where(function ($query) use ($memberships): void {
                $query->where('visibility', Community::VISIBILITY_PUBLIC)
                    ->orWhereIn('id', $memberships->keys());
            })
            ->orderBy('name');

        match ($this->tab) {
            'pinned' => $query->whereIn('id', $pinnedCommunityIds),
            'unread' => $query->whereIn('id', $unreadCommunityIds),
            'admin' => $query->whereIn('id', $adminCommunityIds),
            default => null,
        };

        if (filled($this->search)) {
            $term = '%'.$this->search.'%';
            $query->where(function ($q) use ($term): void {
                $q->where('name', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }

        $communities = $query->get();

        return view('livewire.communities.community-list', [
            'communities' => $communities,
            'memberships' => $memberships,
            'adminCommunityIds' => $adminCommunityIds->flip(),
        ]);
    }
}
