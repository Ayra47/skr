<x-app-shell title="Друзья · skr" :vite="['resources/js/pages/friends.js']">

    <x-slot:head>
        <script>
            window.Laravel = {
                userId: @json(Auth::id()),
                activeCode: @json($activeCode && $activeCode->isActive() ? $activeCode->code : null),
                activeCodeExpiresAt: @json(
                    $activeCode && $activeCode->isActive()
                    ? $activeCode->expires_at->toIso8601String()
                    : null
                )
            };
        </script>
    </x-slot:head>

    {{-- Left sidebar: friend list --}}
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
                            $fp       = $friend->userKey?->fingerprint();
                        @endphp
                        @php
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

    {{-- Center: code cards --}}
    <div class="fr-content">

        @if(session('join_success'))
            <div class="fr-flash fr-flash--success">{{ session('join_success') }}</div>
        @elseif(session('join_error'))
            <div class="fr-flash fr-flash--error">{{ session('join_error') }}</div>
        @endif

        {{-- Code generator --}}
        <div class="fr-card">
            <div class="fr-card-head">
                <div class="fr-card-icon">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="15" r="4"/><path d="M10.8 12.2L20 3"/><path d="M16 7l3 3"/></svg>
                </div>
                <div>
                    <h2 class="fr-card-title">Код для добавления</h2>
                    <p class="fr-card-sub">Поделитесь кодом с другом — действует <span class="fr-accent">5 минут</span>, одноразовый.</p>
                </div>
            </div>
            <div id="inviteBody"></div>
        </div>

        {{-- Add by code --}}
        <div class="fr-card">
            <div class="fr-card-head">
                <div class="fr-card-icon">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                </div>
                <div>
                    <h2 class="fr-card-title">Добавить по коду</h2>
                    <p class="fr-card-sub">Введите 10 цифр от друга. Запрос зашифруется его публичным ключом.</p>
                </div>
            </div>
            <div id="addMessage" class="fr-message"></div>
            <div class="fr-add-row">
                <div class="fr-code-wrap">
                    <input type="text" id="searchCode" class="fr-code-input"
                        placeholder="00 00 00 00 00" inputmode="numeric" autocomplete="off">
                    <span class="fr-code-counter"><span id="digitCount">0</span>/10</span>
                </div>
                <button id="sendRequestBtn" class="fr-send-btn" disabled>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                    Отправить
                </button>
            </div>
        </div>

    </div>

    {{-- Right aside: incoming requests --}}
    <x-slot:aside>
        <div class="fr-aside-label">
            входящие
            <span class="fr-badge{{ $unreadCount <= 0 ? ' fr-badge--hidden' : '' }}" id="requestBadge">{{ $unreadCount }}</span>
        </div>

        <div id="requestsList" class="fr-req-list">
            @forelse($pendingRequests as $request)
                @php
                    $pseudo = $request->sender->pseudonym;
                @endphp
                <div class="fr-item" id="request-{{ $request->id }}">
                    <div class="fr-avatar">{{ mb_strtoupper(mb_substr($pseudo, 0, 1)) }}</div>
                    <div class="fr-info">
                        <div class="fr-name">{{ $pseudo }}</div>
                        <div class="fr-sub">{{ $request->created_at->diffForHumans() }}</div>
                    </div>
                    <button class="fr-accept-btn" data-action="accept" data-request-id="{{ $request->id }}">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L20 6"/></svg>
                        Принять
                    </button>
                    <button class="fr-decline-btn" data-action="decline" data-request-id="{{ $request->id }}">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                    </button>
                </div>
            @empty
                <div class="fr-empty">Нет входящих запросов</div>
            @endforelse
        </div>
    </x-slot:aside>

</x-app-shell>
