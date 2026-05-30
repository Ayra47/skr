<x-app-shell title="Сообщества · skr" :vite="['resources/js/pages/communities.js']">

    <x-slot:head>
        @livewireStyles
    </x-slot:head>

    <x-slot:sidebar>
        <x-app-sidebar>
            <x-slot:header>
                <a href="{{ route('communities.index') }}" wire:navigate class="app-brand" style="text-decoration:none">
                    <div class="app-brand-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="4" y="10" width="16" height="11" rx="2.5" />
                            <path d="M8 10V7a4 4 0 0 1 8 0v3" />
                        </svg>
                    </div>
                    <div class="app-brand-info">
                        <p class="app-brand-name">skr</p>
                        <span class="app-brand-sub">приватные пространства</span>
                    </div>
                </a>
            </x-slot:header>

            <x-slot:body>
                {{-- Stats --}}
                <section class="cm-stats">
                    <h2 class="cm-stats-title">Сообщества</h2>
                    <p class="cm-stats-sub">закрытые группы и приглашения</p>
                    <div class="cm-stats-grid">
                        <div class="cm-stat">
                            <strong>{{ $memberships->count() }}</strong>
                            <span>активных</span>
                        </div>
                        <div class="cm-stat">
                            <strong>{{ $directInvites->count() }}</strong>
                            <span>приглашений</span>
                        </div>
                    </div>
                </section>

                {{-- Actions --}}
                <div class="cm-actions">
                    <button class="cm-action is-primary" type="button" data-cm-open-create>
                        <span class="cm-action-icon">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.9" stroke-linecap="round">
                                <path d="M12 5v14M5 12h14" />
                            </svg>
                        </span>
                        <span>Создать сообщество</span>
                    </button>
                    <button class="cm-action is-join" type="button" data-cm-toggle-join>
                        <span class="cm-action-icon">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="8" cy="15" r="4" />
                                <path d="M10.8 12.2L20 3" />
                                <path d="M16 7l3 3" />
                            </svg>
                        </span>
                        <span>Войти по коду</span>
                    </button>
                </div>

                {{-- Join by invite code panel --}}
                <section class="cm-join-panel" id="cm-join-panel" hidden>
                    <label for="cm-join-input">Код приглашения</label>
                    <div class="cm-join-row">
                        <input id="cm-join-input" type="text" placeholder="ABCD1234"
                            maxlength="20" autocomplete="off" spellcheck="false">
                        <button type="button" data-cm-join-submit>Войти</button>
                    </div>
                    <p class="cm-join-error" id="cm-join-error" hidden></p>
                </section>

                {{-- Friend invites --}}
                @if ($directInvites->isNotEmpty())
                    @include('pages.communities._invites', ['invites' => $directInvites])
                @endif
            </x-slot:body>
        </x-app-sidebar>
    </x-slot:sidebar>

    <div class="community-scroll">
        <div class="community-content">
            @if (session('community_status'))
                <div class="community-notice">{{ session('community_status') }}</div>
            @endif

            <header class="community-header">
                <div class="community-header-row">
                    <h1 class="community-title">Сообщества</h1>
                    <span class="community-title-count">{{ $communities->count() }}</span>
                </div>
                <p class="community-header-sub">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="4" y="10" width="16" height="11" rx="2.5" />
                        <path d="M8 10V7a4 4 0 0 1 8 0v3" />
                    </svg>
                    По приглашению · сквозное шифрование внутри каждого
                </p>
            </header>

            <section class="community-soon-panel" aria-labelledby="community-soon-title">
                <div class="community-soon-glow" aria-hidden="true"></div>
                <div class="community-soon-content">
                    <div class="community-soon-head">
                        <span class="community-soon-icon" aria-hidden="true">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M6 2h12" />
                                <path d="M6 22h12" />
                                <path d="M17 2v6.2a4 4 0 0 1-1.17 2.83L12 14.86l-3.83-3.83A4 4 0 0 1 7 8.2V2" />
                                <path d="M7 22v-6.2a4 4 0 0 1 1.17-2.83L12 9.14l3.83 3.83A4 4 0 0 1 17 15.8V22" />
                            </svg>
                        </span>
                        <h2 id="community-soon-title">Эфемерные пространства</h2>
                        <span class="community-soon-badge">Скоро</span>
                    </div>
                    <p>
                        Временные обсуждения с автоудалением истории. Пространства откроются после настройки заявок,
                        лимитов и правил доступа для администрации.
                    </p>
                </div>
            </section>

            <livewire:communities.community-list />
        </div>
    </div>

    <x-slot:after>
        @include('pages.communities._create_modal')
        @livewireScripts
    </x-slot:after>

</x-app-shell>
