<x-app-shell title="Уведомления · skr" :vite="['resources/js/pages/notifications.js']">

    <x-slot:head>
        <meta name="vapid-public-key" content="{{ config('app.vapid_public_key') }}">
        <script>
            window.Laravel = {
                userId: @json(Auth::id()),
            };
        </script>
    </x-slot:head>

    <x-slot:sidebar>
        <x-app-sidebar>
            <x-slot:header>
                <a href="{{ route('notifications.index') }}" class="app-brand" style="text-decoration:none">
                    <div class="app-brand-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/>
                            <path d="M13.7 21a2 2 0 0 1-3.4 0"/>
                        </svg>
                    </div>
                    <div class="app-brand-info">
                        <p class="app-brand-name">skr</p>
                        <span class="app-brand-sub">уведомления</span>
                    </div>
                </a>
                <a href="{{ route('settings.index') }}" class="app-icon-btn" title="Настройки" aria-label="Настройки уведомлений">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="3"/>
                        <path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-1.8-.3 1.6 1.6 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.6 1.6 0 0 0-1-1.5 1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.6 1.6 0 0 0 1.5-1 1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H9a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 1 1.5 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V9a1.6 1.6 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1z"/>
                    </svg>
                </a>
            </x-slot:header>

            <x-slot:body>
                <nav class="nt-shelves" aria-label="Фильтр уведомлений">
                    <div class="nt-shelf-label">фильтры</div>

                    <button class="nt-shelf is-active" data-filter="all" type="button">
                        <span class="nt-shelf-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18"/><path d="M12 3a14 14 0 0 0 0 18"/></svg>
                        </span>
                        <div class="nt-shelf-text">
                            <div class="nt-shelf-title">Все</div>
                            <div class="nt-shelf-meta">все уведомления</div>
                        </div>
                    </button>

                    <button class="nt-shelf" data-filter="unread" type="button">
                        <span class="nt-shelf-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
                        </span>
                        <div class="nt-shelf-text">
                            <div class="nt-shelf-title">Непрочитанные</div>
                            <div class="nt-shelf-meta">требуют внимания</div>
                        </div>
                        <span class="nt-badge" id="sidebar-unread-badge" style="display:none"></span>
                    </button>

                    <button class="nt-shelf" data-filter="security" type="button">
                        <span class="nt-shelf-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l8 3v6c0 5-3.5 8.5-8 9-4.5-.5-8-4-8-9V6l8-3z"/></svg>
                        </span>
                        <div class="nt-shelf-text">
                            <div class="nt-shelf-title">Безопасность</div>
                            <div class="nt-shelf-meta">вход, ключи</div>
                        </div>
                    </button>

                    <button class="nt-shelf" data-filter="social" type="button">
                        <span class="nt-shelf-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/></svg>
                        </span>
                        <div class="nt-shelf-text">
                            <div class="nt-shelf-title">Социальные</div>
                            <div class="nt-shelf-meta">друзья, реакции</div>
                        </div>
                    </button>
                </nav>
            </x-slot:body>
        </x-app-sidebar>
    </x-slot:sidebar>

    <div class="nt-scroll">
        <div class="nt-content">
            <div class="nt-topbar">
                <div class="nt-title-row">
                    <h1 class="nt-title">Уведомления</h1>
                    <span id="notif-badge" class="nt-badge" style="display:none"></span>
                </div>
                <button id="notif-mark-all" class="nt-topbar-btn" disabled>
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L20 6"/></svg>
                    Отметить всё
                </button>
            </div>
            <div id="notif-container">
                <div class="nt-empty">Загрузка…</div>
            </div>
        </div>
    </div>

</x-app-shell>
