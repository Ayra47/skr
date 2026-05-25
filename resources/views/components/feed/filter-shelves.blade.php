@props([
    'active' => 'friends',
])

@php
    $tabs = [
        'all'     => ['label' => 'Все',     'meta' => 'все публикации'],
        'friends' => ['label' => 'Друзья',  'meta' => 'посты друзей'],
        'groups'  => ['label' => 'Группы',  'meta' => 'сообщества'],
        'mine'    => ['label' => 'Мои',     'meta' => 'опубликовано вами'],
    ];

    $shelfIcons = [
        'all'     => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18"/><path d="M12 3a14 14 0 0 0 0 18"/></svg>',
        'friends' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"><path d="M15 20v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 3 18.5V20"/><circle cx="9" cy="8" r="3.5"/><path d="M21 20v-1.3a3.4 3.4 0 0 0-2.6-3.3"/><path d="M16.5 5a3.5 3.5 0 0 1 0 6"/></svg>',
        'groups'  => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="8" r="3"/><circle cx="16" cy="8" r="3"/><circle cx="12" cy="16" r="3"/><path d="M5 18c.8-1.4 2.1-2.2 3.8-2.4"/><path d="M19 18c-.8-1.4-2.1-2.2-3.8-2.4"/></svg>',
        'mine'    => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>',
    ];
@endphp

<nav class="fd-shelves" role="tablist" aria-label="Фильтр ленты">
    <div class="fd-shelves-label">фильтры</div>
    @foreach($tabs as $key => $data)
        <a
            href="{{ $key === 'friends' ? url('/') : url('/?tab='.$key) }}"
            wire:navigate
            role="tab"
            aria-selected="{{ $active === $key ? 'true' : 'false' }}"
            class="fd-shelf {{ $active === $key ? 'is-active' : '' }}"
        >
            <span class="fd-shelf-icon">{!! $shelfIcons[$key] !!}</span>
            <div class="fd-shelf-text">
                <div class="fd-shelf-title">{{ $data['label'] }}</div>
                <div class="fd-shelf-meta">{{ $data['meta'] }}</div>
            </div>
        </a>
    @endforeach
</nav>
