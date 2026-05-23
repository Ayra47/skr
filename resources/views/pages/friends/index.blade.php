<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Друзья</title>
    @include('partials.accent-style')
    @vite(['resources/js/pages/friends.js'])
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
</head>

<body>
    @include("components.nav")

    <div class="container">
        <div class="header">
            <h1>Друзья</h1>
        </div>

        @if(session('join_success'))
            <div class="fc-flash fc-flash--success">{{ session('join_success') }}</div>
        @elseif(session('join_error'))
            <div class="fc-flash fc-flash--error">{{ session('join_error') }}</div>
        @endif

        <!-- Код для друзей -->
        <div class="fc-card">
            <div class="fc-head">
                <span class="fc-icon">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="15" r="4"/><path d="M10.8 12.2L20 3"/><path d="M16 7l3 3"/><path d="M14 9l3 3"/></svg>
                </span>
                <div class="fc-head-text">
                    <h2>Код для добавления в друзья</h2>
                    <p>Поделитесь кодом с другом — он действует <span class="fc-accent">5 минут</span>.</p>
                </div>
            </div>
            <div id="inviteBody"></div>
        </div>

        <!-- Поиск по коду -->
        <div class="fc-card">
            <div class="fc-head">
                <span class="fc-icon">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                </span>
                <div class="fc-head-text">
                    <h2>Добавить друга по коду</h2>
                    <p>Введите 10 цифр кода вашего друга.</p>
                </div>
            </div>
            <div id="addMessage" class="message"></div>
            <div class="fa-row">
                <div class="fa-input-wrap">
                    <input type="text" id="searchCode" class="fa-input" placeholder="00 00 00 00 00"
                        inputmode="numeric" autocomplete="off">
                    <span class="fa-counter"><span id="digitCount">0</span>/10</span>
                </div>
                <button id="sendRequestBtn" class="fa-send-btn" disabled>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                    Отправить запрос
                </button>
            </div>
        </div>

        <!-- Запросы в друзья -->
        <div class="fc-card">
            <div class="fr-head">
                <span class="fc-icon">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5 4h14l3 8v6a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-6l3-8z"/></svg>
                </span>
                <h2>Входящие запросы</h2>
                <span class="fr-badge{{ $unreadCount <= 0 ? ' fr-badge--hidden' : '' }}" id="requestBadge">{{ $unreadCount }}</span>
            </div>

            <div id="requestsList" class="fr-list">
                @forelse($pendingRequests as $request)
                    @php
                        $pseudo = $request->sender->pseudonym;
                        $hue    = ord($pseudo[0]) * 37 % 360;
                    @endphp
                    <div class="fr-item" id="request-{{ $request->id }}">
                        <div class="fr-avatar" style="--hue:{{ $hue }}">{{ mb_strtoupper(mb_substr($pseudo, 0, 1)) }}</div>
                        <div class="fr-info">
                            <div class="fr-name">{{ $pseudo }}</div>
                            <div class="fr-sub">запрос по коду · {{ $request->created_at->diffForHumans() }}</div>
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
        </div>

        <!-- Список друзей -->
        <div class="fc-card">
            <div class="fl-head">
                <span class="fc-icon">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/></svg>
                </span>
                <h2>Друзья</h2>
                <span class="fl-count">{{ $allFriends->count() }}</span>
                <div class="fl-search-wrap">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                    <input type="text" id="friendSearch" class="fl-search" placeholder="поиск друзей">
                </div>
            </div>

            <div id="friendsList" class="fl-list">
                @forelse($allFriends as $friend)
                    @php
                        $pseudo  = $friend->pseudonym ?? $friend->login;
                        $hue     = ord($pseudo[0]) * 37 % 360;
                        $isOnline = $friend->last_seen_at?->gt(now()->subMinutes(2)) ?? false;
                    @endphp
                    <div class="fl-item" id="friend-{{ $friend->id }}" data-name="{{ strtolower($pseudo) }}">
                        <div class="fl-avatar-wrap">
                            <div class="fl-avatar" style="--hue:{{ $hue }}">{{ mb_strtoupper(mb_substr($pseudo, 0, 1)) }}</div>
                            @if($isOnline)
                                <span class="fl-online-dot"></span>
                            @endif
                        </div>
                        <div class="fl-info">
                            <div class="fl-name">{{ $pseudo }}</div>
                            <div class="fl-sub">
                                @if($friend->userKey?->fingerprint())
                                    <span class="fl-fp">fp: {{ $friend->userKey->fingerprint() }}</span>
                                    <span class="fl-dot">·</span>
                                @endif
                                <span class="{{ $isOnline ? 'fl-sub--online' : '' }}">{{ $isOnline ? 'в сети' : 'не в сети' }}</span>
                            </div>
                        </div>
                        <a class="fl-write-btn" href="{{ route('chats.index') }}?with={{ $friend->id }}&login={{ urlencode($pseudo) }}">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8v.5z"/></svg>
                            Написать
                        </a>
                        <button class="fl-delete-btn" data-friend-id="{{ $friend->id }}">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                    </div>
                @empty
                    <div class="fl-empty">Пока нет друзей</div>
                @endforelse
            </div>
        </div>
    </div>
</body>

</html>