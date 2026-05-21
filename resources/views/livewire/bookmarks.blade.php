<div class="bm-wrap">

    {{-- Header --}}
    <header class="bm-header">
        <div class="bm-header-left">
            <div class="bm-header-title-row">
                <h1 class="bm-header-title">Закладки</h1>
                <span class="bm-header-count">{{ $totalCount }}</span>
            </div>
            <div class="bm-header-meta">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                    stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" />
                    <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg>
                <span>Видны только вам · хранятся локально и зашифрованно</span>
            </div>
        </div>
        <div class="bm-search-wrap">
            <svg class="bm-search-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8" />
                <path d="m21 21-4.4-4.4" />
            </svg>
            <input class="bm-search" type="search" placeholder="Поиск в закладках..."
                autocomplete="off" wire:model.live.debounce.300ms="search">
        </div>
    </header>

    {{-- Deleted posts banner --}}
    @if($deletedCount > 0)
        <div class="bm-deleted-banner">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
                stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10" />
                <path d="M12 8v4" />
                <path d="M12 16h.01" />
            </svg>
            <span><strong>{{ $deletedCount }}</strong> {{ $deletedCount === 1 ? 'пост сохранён' : 'поста сохранено' }}
                после самоудаления у автора. Эти копии видите только вы.</span>
        </div>
    @endif

    {{-- Tabs --}}
    <div class="bm-tabs">
        <button class="bm-tab {{ $tab === 'all' ? 'bm-tab--active' : '' }}" wire:click="setTab('all')" type="button">Все</button>
        <button class="bm-tab {{ $tab === 'feed_post' ? 'bm-tab--active' : '' }}" wire:click="setTab('feed_post')" type="button">Из ленты</button>
        <button class="bm-tab {{ $tab === 'whisper' ? 'bm-tab--active' : '' }}" wire:click="setTab('whisper')" type="button">Whisper</button>
        <button class="bm-tab {{ $tab === 'community_post' ? 'bm-tab--active' : '' }}" wire:click="setTab('community_post')" type="button">Из сообществ</button>
    </div>

    {{-- Content --}}
    @if($bookmarks->isEmpty())
        <div class="bm-empty">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3"
                stroke-linecap="round" stroke-linejoin="round">
                @if(filled($search))
                    <circle cx="11" cy="11" r="8" />
                    <path d="m21 21-4.4-4.4" />
                @else
                    <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z" />
                @endif
            </svg>
            <p>{{ filled($search) ? 'Ничего не найдено' : 'Здесь будут ваши закладки' }}</p>
            @unless(filled($search))
                <span>Нажмите на закладку у любого поста в ленте</span>
            @endunless
        </div>
    @else
        <div class="bm-list">
            @foreach($bookmarks as $bookmark)
                <div wire:key="bm-{{ $bookmark->id }}">
                    @include('pages.bookmarks._card', ['bookmark' => $bookmark])
                </div>
            @endforeach
        </div>

        @if($hasMore)
            <button data-bm-sentinel wire:click="loadMore" class="bm-load-more-trigger" type="button" aria-hidden="true" tabindex="-1"></button>
        @endif
    @endif

</div>
