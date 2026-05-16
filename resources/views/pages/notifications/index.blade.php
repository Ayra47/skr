<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="vapid-public-key" content="{{ config('app.vapid_public_key') }}">
    <title>Уведомления</title>
    @vite(['resources/js/pages/notifications.js'])
    <script>
        window.Laravel = {
            userId: @json(Auth::id()),
        };
    </script>
</head>

<body>
    @include('components.nav')

    <div class="notif-glow"></div>

    <div class="notif-wrap">
        <header class="notif-header">
            <div>
                <div class="notif-title-row">
                    <h1 class="notif-title">Уведомления</h1>
                    <span id="notif-badge" class="notif-badge" style="display:none;"></span>
                </div>
                <p class="notif-subtitle">Безопасность, запросы и активность — на одном экране</p>
            </div>
            <div class="notif-header-actions">
                <button id="notif-mark-all" class="notif-btn" disabled>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L20 6"/></svg>
                    Отметить всё
                </button>
                <a href="{{ route('settings.index') }}" class="notif-btn">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M13.7 21a2 2 0 0 1-3.4 0"/><path d="M3 3l18 18"/><path d="M6 8a6 6 0 0 1 .7-2.8M18 14c0-1.5-.6-3-1.5-4M18 8a6 6 0 0 0-9.7-4.7"/><path d="M6 8c0 7-3 9-3 9h13"/></svg>
                    Настройки
                </a>
            </div>
        </header>

        <div class="notif-filters">
            <button class="notif-filter-btn active" data-filter="all">Все</button>
            <button class="notif-filter-btn" data-filter="unread">Непрочитанные</button>
            <button class="notif-filter-btn" data-filter="security">Безопасность</button>
            <button class="notif-filter-btn" data-filter="social">Социальные</button>
        </div>

        <div id="notif-container">
            <div class="notif-empty">Загрузка…</div>
        </div>
    </div>
</body>

</html>
