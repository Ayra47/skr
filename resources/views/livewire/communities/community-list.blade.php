<div>
    {{-- Tools row: filter tabs + search --}}
    <div class="community-tools">
        <div class="community-filter-tabs" role="tablist">
            <button type="button" wire:click="$set('tab', 'all')"
                class="community-filter-tab {{ $tab === 'all' ? 'is-active' : '' }}">Все</button>
            <button type="button" wire:click="$set('tab', 'pinned')"
                class="community-filter-tab {{ $tab === 'pinned' ? 'is-active' : '' }}">Закреплённые</button>
            <button type="button" wire:click="$set('tab', 'unread')"
                class="community-filter-tab {{ $tab === 'unread' ? 'is-active' : '' }}">Непрочитанные</button>
            <button type="button" wire:click="$set('tab', 'admin')"
                class="community-filter-tab {{ $tab === 'admin' ? 'is-active' : '' }}">Где я админ</button>
        </div>

        <div class="community-search-bar">
            <svg class="community-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="7" />
                <path d="M20 20l-3.5-3.5" />
            </svg>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Поиск сообщества…"
                autocomplete="off" class="community-search-input">
            @if (filled($search))
                <button type="button" class="community-search-clear" wire:click="$set('search', '')"
                    aria-label="Очистить">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6L6 18M6 6l12 12" />
                    </svg>
                </button>
            @endif
        </div>
    </div>

    {{-- List --}}
    <div class="community-list">
        @forelse ($communities as $community)
            @include('pages.communities._card', [
                'community' => $community,
                'membership' => $memberships->get($community->id),
                'isAdmin' => $adminCommunityIds->has($community->id),
            ])
        @empty
            <div class="community-empty">
                @if (filled($search))
                    Ничего не найдено по «{{ $search }}»
                @else
                    Здесь пока пусто
                @endif
            </div>
        @endforelse
    </div>

    {{-- Footer note --}}
    <div class="community-list-foot">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
            stroke-linecap="round" stroke-linejoin="round">
            <rect x="4" y="10" width="16" height="11" rx="2.5" />
            <path d="M8 10V7a4 4 0 0 1 8 0v3" />
        </svg>
        Поиск чужих сообществ намеренно недоступен. Только по приглашению.
    </div>
</div>
