<x-app-shell title="{{ $profileUser->feedName() }} — профиль · skr" :vite="['resources/js/pages/friends.js']">

    {{-- Left sidebar: friend list + nav --}}
    <x-slot:sidebar>
        <x-app-sidebar>
            <x-slot:header>
                <a href="{{ route('friends.index') }}" wire:navigate class="app-brand" style="text-decoration:none">
                    <div class="app-brand-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/></svg>
                    </div>
                    <div class="app-brand-info">
                        <p class="app-brand-name">skr</p>
                        <span class="app-brand-sub">друзья</span>
                    </div>
                </a>
            </x-slot:header>

            <x-slot:body>
                <div class="fr-search">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                    <input type="text" id="friendSearch" placeholder="поиск">
                </div>

                @php
                    $onlineCount = $allFriends->filter(fn($f) => $f->last_seen_at?->gt(now()->subMinutes(2)))->count();
                @endphp
                <div class="fr-section-label">{{ $allFriends->count() }} друзей · {{ $onlineCount }} онлайн</div>

                <div id="friendsList" class="fr-list">
                    @forelse($allFriends as $friend)
                        @php
                            $pseudo   = $friend->pseudonym ?? $friend->login;
                            $isOnline = $friend->last_seen_at?->gt(now()->subMinutes(2)) ?? false;
                            $lastSeen = $isOnline
                                ? 'онлайн'
                                : ($friend->last_seen_at ? $friend->last_seen_at->diffForHumans() : 'не в сети');
                        @endphp
                        <div class="fr-row" id="friend-{{ $friend->id }}"
                            data-name="{{ strtolower($pseudo) }}"
                            data-user-id="{{ $friend->id }}"
                            data-login="{{ $pseudo }}">
                            <div class="fr-avatar-wrap">
                                @if($friend->avatar)
                                    <img src="/storage/{{ $friend->avatar }}" class="fr-avatar fr-avatar--img" alt="">
                                @else
                                    <div class="fr-avatar">{{ mb_strtoupper(mb_substr($pseudo, 0, 1)) }}</div>
                                @endif
                                @if($isOnline)<span class="fr-online-dot"></span>@endif
                            </div>
                            <div class="fr-info">
                                <div class="fr-name">{{ $pseudo }}</div>
                                <div class="fr-last-seen{{ $isOnline ? ' fr-last-seen--online' : '' }}">{{ $lastSeen }}</div>
                            </div>
                            <button class="fr-menu-btn" type="button" aria-label="Действия" tabindex="-1">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/></svg>
                            </button>
                        </div>
                    @empty
                        <div class="fr-empty">Пока нет друзей</div>
                    @endforelse
                </div>
            </x-slot:body>
        </x-app-sidebar>
    </x-slot:sidebar>

    {{-- Center: hidden profile message --}}
    <div class="ph-hidden">
        <div class="ph-hidden-icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        </div>
        <h2 class="ph-hidden-title">Профиль скрыт</h2>
        <p class="ph-hidden-sub">{{ $profileUser->feedName() }} ограничил доступ к своему профилю</p>
        <a href="javascript:history.back()" class="ph-hidden-back">Назад</a>
    </div>

</x-app-shell>
