<meta name="vapid-public-key" content="{{ config('app.vapid_public_key') }}">
<nav class="app-nav">
    <div class="app-nav-inner">
        <a href="{{ route('feed.index') }}" class="app-brand">
            <span class="app-brand-mark">s</span>
            <span style="color:#e8e8ec;font-size:14px;font-weight:500;letter-spacing:.02em;">skr</span>
        </a>
        <div class="app-links">
            <a href="{{ route('feed.index') }}" class="{{ request()->routeIs('feed.*') ? 'active' : '' }}">
                <svg width=" 14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 12L12 4l9 8" />
                    <path d="M5 10v10h14V10" />
                </svg>
                Лента
            </a>
            <a href="{{ route('chats.index') }}" class="{{ request()->routeIs('chats.*') ? 'active' : '' }}">
                <svg width=" 14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path
                        d="M21 11.5a8.4 8.4 0 0 1-7.6 8.5 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 8-8h.5a8.5 8.5 0 0 1 8 8z" />
                </svg>
                Чаты
            </a>
            <a href="{{ route('friends.index') }}" class="{{ request()->routeIs('friends.*') ? 'active' : '' }}">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                    <circle cx="9" cy="7" r="4" />
                    <path d="M22 21v-2a4 4 0 0 0-3-3.9" />
                    <path d="M16 3.1a4 4 0 0 1 0 7.8" />
                </svg>
                Друзья
            </a>
            <a href="{{ route('settings.index') }}" class="{{ request()->routeIs('settings.*') ? 'active' : '' }}">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                    stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="3" />
                    <path
                        d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-1.8-.3 1.6 1.6 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.6 1.6 0 0 0-1-1.5 1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.6 1.6 0 0 0 1.5-1 1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H9a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 1 1.5 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V9a1.6 1.6 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1z" />
                </svg>
                Настройки
            </a>
        </div>
        <div style="margin-left:auto;display:flex;align-items:center;gap:10px;">
            @php
                $navAllOk = !\App\Models\StatusIncident::where('status', 'ongoing')->exists();
            @endphp
            <a href="{{ route('status.index') }}"
                style="display:flex;align-items:center;gap:6px;font-size:11px;text-decoration:none;padding:4px 10px;border-radius:999px;
                       color:{{ $navAllOk ? '#6dd49a' : '#e8c656' }};
                       background:{{ $navAllOk ? 'rgba(109,212,154,.08)' : 'rgba(232,198,86,.08)' }};
                       border:1px solid {{ $navAllOk ? 'rgba(109,212,154,.20)' : 'rgba(232,198,86,.20)' }};">
                <span
                    style="width:6px;height:6px;border-radius:50%;background:{{ $navAllOk ? '#6dd49a' : '#e8c656' }};box-shadow:0 0 6px {{ $navAllOk ? 'rgba(109,212,154,.6)' : 'rgba(232,198,86,.6)' }};"></span>
                {{ $navAllOk ? 'все системы работают' : 'есть проблемы' }}
            </a>
            <button id="nav-bell-btn" class="bell-btn {{ request()->routeIs('notifications.*') ? 'bell-btn--active' : '' }}" title="Уведомления">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/>
                    <path d="M13.7 21a2 2 0 0 1-3.4 0"/>
                </svg>
                <span id="nav-bell-badge" class="bell-badge" style="display:none;">0</span>
            </button>
            @php $navUser = auth()->user(); @endphp
            <div class="profile-menu-wrap" data-profile-menu-root>
                <button class="profile-menu-toggle" type="button" data-profile-menu-toggle aria-haspopup="menu" aria-expanded="false" aria-label="Открыть меню профиля">
                    @if($navUser->avatar)
                        <img src="/storage/{{ $navUser->avatar }}" alt="">
                    @else
                        {{ mb_strtoupper(mb_substr($navUser->feedName(), 0, 1)) }}
                    @endif
                </button>

                <div class="profile-menu" data-profile-menu hidden>
                    <a href="{{ route('settings.index') }}">
                        <span class="profile-menu-item-icon" aria-hidden="true">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="3"/>
                                <path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-1.8-.3 1.6 1.6 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.6 1.6 0 0 0-1-1.5 1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.6 1.6 0 0 0 1.5-1 1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H9a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 1 1.5 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V9a1.6 1.6 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1z"/>
                            </svg>
                        </span>
                        Настройки
                    </a>
                    <a href="{{ route('profiles.show', $navUser) }}">
                        <span class="profile-menu-item-icon" aria-hidden="true">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21a8 8 0 0 0-16 0"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                        </span>
                        Профиль
                    </a>
                    <div class="profile-menu-divider" aria-hidden="true"></div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="profile-menu-logout" type="submit">
                            <span class="profile-menu-item-icon" aria-hidden="true">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M10 17l5-5-5-5"/>
                                    <path d="M15 12H3"/>
                                    <path d="M21 19V5a2 2 0 0 0-2-2h-5"/>
                                </svg>
                            </span>
                            Выйти
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</nav>
