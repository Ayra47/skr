<x-app-shell title="Закладки · skr" :vite="['resources/js/pages/bookmarks.js']">

    <x-slot:head>
        <script>window.Laravel = { userId: @json(Auth::id()) };</script>
        @livewireStyles
    </x-slot:head>

    <x-slot:sidebar>
        <x-app-sidebar>
            <x-slot:header>
                <a href="{{ route('bookmarks.index') }}" wire:navigate class="app-brand" style="text-decoration:none">
                    <div class="app-brand-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
                        </svg>
                    </div>
                    <div class="app-brand-info">
                        <p class="app-brand-name">skr</p>
                        <span class="app-brand-sub">закладки</span>
                    </div>
                </a>
            </x-slot:header>

            <x-slot:body>
                @php $activeTab = request()->query('tab', 'all'); @endphp
                <nav class="bm-shelves" aria-label="Фильтр закладок">
                    <div class="bm-shelf-label">фильтры</div>

                    <a href="{{ route('bookmarks.index') }}" wire:navigate
                       class="bm-shelf {{ $activeTab === 'all' ? 'is-active' : '' }}">
                        <span class="bm-shelf-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                        </span>
                        <div class="bm-shelf-text">
                            <div class="bm-shelf-title">Все</div>
                            <div class="bm-shelf-meta">все закладки</div>
                        </div>
                    </a>

                    <a href="{{ route('bookmarks.index') }}?tab=feed_post" wire:navigate
                       class="bm-shelf {{ $activeTab === 'feed_post' ? 'is-active' : '' }}">
                        <span class="bm-shelf-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12L12 4l9 8"/><path d="M5 10v10h14V10"/></svg>
                        </span>
                        <div class="bm-shelf-text">
                            <div class="bm-shelf-title">Из ленты</div>
                            <div class="bm-shelf-meta">посты из ленты</div>
                        </div>
                    </a>

                    <a href="{{ route('bookmarks.index') }}?tab=whisper" wire:navigate
                       class="bm-shelf {{ $activeTab === 'whisper' ? 'is-active' : '' }}">
                        <span class="bm-shelf-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11a3 3 0 0 1 6 0"/><path d="M12 19c-3 0-7-2-7-7s4-7 7-7 7 2 7 7-4 7-7 7z"/></svg>
                        </span>
                        <div class="bm-shelf-text">
                            <div class="bm-shelf-title">Whisper</div>
                            <div class="bm-shelf-meta">анонимные посты</div>
                        </div>
                    </a>

                    <a href="{{ route('bookmarks.index') }}?tab=community_post" wire:navigate
                       class="bm-shelf {{ $activeTab === 'community_post' ? 'is-active' : '' }}">
                        <span class="bm-shelf-icon">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/></svg>
                        </span>
                        <div class="bm-shelf-text">
                            <div class="bm-shelf-title">Из сообществ</div>
                            <div class="bm-shelf-meta">посты из сообществ</div>
                        </div>
                    </a>
                </nav>
            </x-slot:body>
        </x-app-sidebar>
    </x-slot:sidebar>

    <div class="bm-scroll">
        <div class="bm-content">
            <livewire:bookmarks />
        </div>
    </div>

    <x-slot:after>
        @livewireScripts
    </x-slot:after>

</x-app-shell>
