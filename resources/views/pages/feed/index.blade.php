@php
    $activeTab = request('tab', 'friends');
@endphp

<x-app-shell title="Лента · skr" :vite="['resources/js/pages/feed.ts']">
    <x-slot:sidebar>
        <x-app-sidebar>
            <x-slot:header>
                <a href="{{ route('feed.index') }}" wire:navigate class="app-brand" style="text-decoration:none">
                    <div class="app-brand-icon">s</div>
                    <div class="app-brand-info">
                        <p class="app-brand-name">skr feed</p>
                        <span class="app-brand-sub">приватная лента</span>
                    </div>
                </a>
                <a href="#fd-composer" class="app-icon-btn" title="Новый пост" aria-label="Новый пост">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                </a>
            </x-slot>

            <x-slot:body>
                <x-feed.filter-shelves :active="$activeTab" />
            </x-slot>
        </x-app-sidebar>
    </x-slot>

    <livewire:feed />

    <x-slot:aside>
        <x-feed.aside />
    </x-slot>
</x-app-shell>
