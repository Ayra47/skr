<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Друзья</title>
    @vite(['resources/js/pages/friends.js'])
    <script>
        window.Laravel = {
            userId: @json(Auth::id()),
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
            <a href="{{ route('chats.index') }}" class="back-link">К чатам</a>
        </div>

        <!-- Код для друзей -->
        <div class="card">
            <h2>🔑 Код для добавления в друзья</h2>
            <div class=" code-section">
                <div id="codeDisplay" class="code-display">
                    {{ $activeCode ? $activeCode->code : 'Нет активного кода' }}
                </div>
                <button id="createCodeBtn" class="btn btn-primary">
                    Создать код
                </button>
            </div>
            <div id="codeTimer" class="timer" style="display: none;"></div>
        </div>

        <!-- Поиск по коду -->
        <div class="card">
            <h2>🔍 Добавить друга по коду</h2>
            <div id="addMessage" class="message"></div>
            <div class="search-section">
                <input type="text" id="searchCode" placeholder="Введите код друга (10 цифр)" maxlength="10">
                <button id="sendRequestBtn" class="btn btn-primary">Отправить запрос</button>
            </div>
        </div>

        <!-- Запросы в друзья -->
        <div class="card">
            <h2>📥 Входящие запросы <span id="requestBadge" class="badge">{{ $unreadCount }}</span></h2>
            <div id="requestsList" class="requests-list">
                @forelse($pendingRequests as $request)
                    <div class="request-item" id="request-{{ $request->id }}">
                        <span class="name">{{ $request->sender->pseudonym }}</span>
                        <div class="request-actions">
                            <button class="btn btn-success btn-sm" data-action="accept" data-request-id="{{ $request->id }}">Принять</button>
                            <button class="btn btn-danger btn-sm" data-action="decline" data-request-id="{{ $request->id }}">Отклонить</button>
                        </div>
                    </div>
                @empty
                    <div class="empty">Нет входящих запросов</div>
                @endforelse
            </div>
        </div>

        <!-- Список друзей -->
        <div class="card">
            <h2>👥 Друзья</h2>
            <div id="friendsList" class="friends-list">
                @forelse($allFriends as $friend)
                    <div class="friend-item" id="friend-{{ $friend->id }}">
                        <span class="name">{{ $friend->pseudonym }}</span>
                        <div class="action-group">
                            <a href="{{ route('chats.index') }}?with={{ $friend->id }}&login={{ urlencode($friend->pseudonym) }}"
                                class="btn btn-chat btn-sm">Написать</a>
                            <button class="btn btn-danger btn-sm" data-friend-id="{{ $friend->id }}">Удалить</button>
                        </div>
                    </div>
                @empty
                    <div class="empty">Пока нет друзей</div>
                @endforelse
            </div>
        </div>
    </div>
</body>

</html>