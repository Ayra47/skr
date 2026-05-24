@php
    $navUser = auth()->user();

    $navItems = [
        [
            'route' => route('feed.index'),
            'label' => 'Лента',
            'active' => request()->routeIs('feed.*'),
            'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"><path d="M4 11a9 9 0 0 1 9 9"/><path d="M4 4a16 16 0 0 1 16 16"/><circle cx="5" cy="19" r="1" fill="currentColor" stroke="none"/></svg>',
        ],
        [
            'route' => route('chats.index'),
            'label' => 'Чаты',
            'active' => request()->routeIs('chats.*'),
            'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.4 8.4 0 0 1-7.6 8.5 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 8-8h.5a8.5 8.5 0 0 1 8 8z"/></svg>',
        ],
        [
            'route' => route('communities.index'),
            'label' => 'Сообщества',
            'active' => request()->routeIs('communities.*'),
            'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="8" r="3"/><circle cx="16" cy="8" r="3"/><circle cx="12" cy="16" r="3"/><path d="M5 18c.8-1.4 2.1-2.2 3.8-2.4"/><path d="M19 18c-.8-1.4-2.1-2.2-3.8-2.4"/></svg>',
        ],
        [
            'route' => route('friends.index'),
            'label' => 'Друзья',
            'active' => request()->routeIs('friends.*'),
            'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"><path d="M15 20v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 3 18.5V20"/><circle cx="9" cy="8" r="3.5"/><path d="M21 20v-1.3a3.4 3.4 0 0 0-2.6-3.3"/><path d="M16.5 5a3.5 3.5 0 0 1 0 6"/></svg>',
        ],
    ];
@endphp

<div class="sidebar-footer">
    <nav class="section-dock" aria-label="Основные разделы">
        @foreach($navItems as $item)
            <a
                href="{{ $item['route'] }}"
                class="section-tab {{ $item['active'] ? 'is-active' : '' }}"
                aria-current="{{ $item['active'] ? 'page' : 'false' }}"
                title="{{ $item['label'] }}"
            >
                <span class="section-icon">{!! $item['icon'] !!}</span>
                <span class="section-label">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>

    <div class="utility-dock" aria-label="Личные разделы">
        <button
            id="nav-bell-btn"
            class="utility-tab bell-btn {{ request()->routeIs('notifications.*') ? 'is-active' : '' }}"
            type="button"
            title="Уведомления"
        >
            <span class="utility-icon">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
                <span id="nav-bell-badge" class="section-count bell-badge" style="display:none;">0</span>
            </span>
            <span>Уведомления</span>
        </button>

        <a href="{{ route('bookmarks.index') }}" class="utility-tab {{ request()->routeIs('bookmarks.*') ? 'is-active' : '' }}" title="Закладки">
            <span class="utility-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg></span>
            <span>Закладки</span>
        </a>

        <div class="profile-menu-wrap app-profile-menu" data-profile-menu-root>
            <button class="utility-tab profile-menu-toggle" type="button" data-profile-menu-toggle aria-haspopup="menu" aria-expanded="false" aria-label="Профиль">
                <span class="utility-icon profile-utility-avatar">
                    @if($navUser->avatar)
                        <img src="/storage/{{ $navUser->avatar }}" alt="">
                    @else
                        {{ mb_strtoupper(mb_substr($navUser->feedName(), 0, 1)) }}
                    @endif
                </span>
                <span>Профиль</span>
            </button>
            <div class="profile-menu" data-profile-menu hidden>
                <a href="{{ route('settings.index') }}">
                    <span class="profile-menu-item-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-1.8-.3 1.6 1.6 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.6 1.6 0 0 0-1-1.5 1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.6 1.6 0 0 0 1.5-1 1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H9a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 1 1.5 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V9a1.6 1.6 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1z"/></svg></span>
                    Настройки
                </a>
                <a href="{{ route('profiles.show', $navUser) }}">
                    <span class="profile-menu-item-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg></span>
                    Профиль
                </a>
                <div class="profile-menu-divider"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="profile-menu-logout" type="submit">
                        <span class="profile-menu-item-icon"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/><path d="M21 19V5a2 2 0 0 0-2-2h-5"/></svg></span>
                        Выйти
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
