<div>

    {{-- Search --}}
    <div class="bm-search-wrap">
        <svg class="bm-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8" />
            <path d="m21 21-4.4-4.4" />
        </svg>
        <input class="bm-search" type="search" placeholder="Поиск в закладках…"
            autocomplete="off" wire:model.live.debounce.300ms="search">
        @if(filled($search))
            <button class="bm-search-clear" type="button" wire:click="$set('search', '')" aria-label="Очистить">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        @endif
    </div>

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
