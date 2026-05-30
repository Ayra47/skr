<x-app-shell title="{{ $profileUser->feedName() }} — профиль · skr" :vite="['resources/js/pages/friends.js', 'resources/js/pages/profile.ts']">

    {{-- Left sidebar: friend list + nav --}}
    <x-slot:sidebar>
        <x-app-sidebar>
            <x-slot:header>
                <a href="{{ route('friends.index') }}" wire:navigate class="app-brand" style="text-decoration:none">
                    <div class="app-brand-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/></svg>
                    </div>
                    <div class="app-brand-info">
                        <p class="app-brand-name">skr</p>
                        <span class="app-brand-sub">друзья</span>
                    </div>
                </a>
            </x-slot:header>

            <x-slot:body>
                <div class="fr-search">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                    <input type="text" id="friendSearch" placeholder="поиск">
                </div>

                @php
                    $onlineCount = $allFriends->filter(fn($f) => $f->last_seen_at?->gt(now()->subMinutes(2)))->count();
                @endphp
                <div class="fr-section-label">{{ $allFriends->count() }} друзей · {{ $onlineCount }} онлайн</div>

                <div id="friendsList" class="fr-list">
                    @forelse($allFriends as $friend)
                        @php
                            $pseudo   = $friend->pseudonym ?? $friend->login;
                            $friendOnline = $friend->last_seen_at?->gt(now()->subMinutes(2)) ?? false;
                            $lastSeen = $friendOnline
                                ? 'онлайн'
                                : ($friend->last_seen_at ? $friend->last_seen_at->diffForHumans() : 'не в сети');
                        @endphp
                        <div class="fr-row{{ $friend->id === $profileUser->id ? ' fr-row--active' : '' }}"
                            id="friend-{{ $friend->id }}"
                            data-name="{{ strtolower($pseudo) }}"
                            data-user-id="{{ $friend->id }}"
                            data-login="{{ $pseudo }}">
                            <div class="fr-avatar-wrap">
                                @if($friend->avatar)
                                    <img src="/storage/{{ $friend->avatar }}" class="fr-avatar fr-avatar--img" alt="">
                                @else
                                    <div class="fr-avatar">{{ mb_strtoupper(mb_substr($pseudo, 0, 1)) }}</div>
                                @endif
                                @if($friendOnline)<span class="fr-online-dot"></span>@endif
                            </div>
                            <div class="fr-info">
                                <div class="fr-name">{{ $pseudo }}</div>
                                <div class="fr-last-seen{{ $friendOnline ? ' fr-last-seen--online' : '' }}">{{ $lastSeen }}</div>
                            </div>
                            <button class="fr-menu-btn" type="button" aria-label="Действия" tabindex="-1">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/></svg>
                            </button>
                        </div>
                    @empty
                        <div class="fr-empty">Пока нет друзей</div>
                    @endforelse
                </div>
            </x-slot:body>
        </x-app-sidebar>
    </x-slot:sidebar>

    {{-- Main: profile content --}}
    @php
        $profileName = $profileUser->feedName();
        $friendLabel = trans_choice('{0} общих друзей|{1} общий друг|[2,4] общих друга|[5,*] общих друзей', $sharedFriendsCount ?? 0);
        $postLabel = trans_choice('{0} постов в ленте|{1} пост в ленте|[2,4] поста в ленте|[5,*] постов в ленте', $visiblePostsCount ?? 0);
        $answerLabel = static function (int $count): string {
            $mod10 = $count % 10;
            $mod100 = $count % 100;
            if ($mod10 === 1 && $mod100 !== 11) { return 'ответ'; }
            if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) { return 'ответа'; }
            return 'ответов';
        };
        $formatBytes = static function (?int $bytes): string {
            if (! $bytes) { return '0 Б'; }
            $units = ['Б', 'КБ', 'МБ', 'ГБ'];
            $power = min((int) floor(log($bytes, 1024)), count($units) - 1);
            return number_format($bytes / (1024 ** $power), $power === 0 ? 0 : 1, ',', ' ').' '.$units[$power];
        };
    @endphp

    <div class="profile-page">
        <div class="profile-inner">
            <section class="profile-hero">
                <div class="profile-avatar-large">
                    @if($showAvatar && $profileUser->avatar)
                        <img src="/storage/{{ $profileUser->avatar }}" alt="">
                    @else
                        {{ mb_strtoupper(mb_substr($profileName, 0, 1)) }}
                    @endif
                    <span class="{{ $isOnline ? 'online' : '' }}"></span>
                </div>

                <div class="profile-hero-main">
                    <div class="profile-title-line">
                        <h1>{{ $profileName }}</h1>
                        <span class="profile-badge">псевдоним</span>
                    </div>

                    <div class="profile-meta-line">
                        <span class="profile-presence {{ $isOnline ? 'online' : '' }}">
                            <i></i>
                            {{ $isOnline ? 'в сети' : 'не в сети' }}
                        </span>
                        <span>·</span>
                        <span>в skr с {{ $profileUser->created_at->translatedFormat('F Y') }}</span>
                        @if($isFriend && $friendship)
                            <span>·</span>
                            <span>друзья с {{ \Illuminate\Support\Carbon::parse($friendship->created_at)->translatedFormat('F Y') }}</span>
                        @endif
                    </div>

                    @if($bio)
                        <p class="profile-bio">{{ $bio }}</p>
                    @elseif($isSelf)
                        <p class="profile-bio-muted">Описание профиля пока не заполнено.</p>
                    @endif

                    <div class="profile-actions">
                        @if($isSelf)
                            <a class="profile-primary-action" href="{{ route('settings.index') }}">Редактировать</a>
                        @else
                            @if($isFriend)
                                <a class="profile-primary-action" href="{{ route('chats.index') }}?with={{ $profileUser->id }}&login={{ urlencode($profileName) }}">
                                    Написать
                                </a>
                                <button class="profile-disabled-action" type="button" disabled>Позвонить</button>
                                <div class="profile-actions-divider"></div>
                                <button class="profile-disabled-action profile-share-btn" type="button">Поделиться</button>
                                <button class="profile-disabled-action profile-remove-friend-btn" type="button" data-friend-id="{{ $profileUser->id }}">Удалить из друзей</button>
                            @else
                                <button class="profile-disabled-action profile-share-btn" type="button">Поделиться</button>
                            @endif
                            <button class="profile-disabled-action" type="button" disabled>Заблокировать</button>
                            <button class="profile-disabled-action" type="button" disabled>Пожаловаться</button>
                        @endif
                    </div>
                </div>
            </section>

            <section class="profile-stats" aria-label="Статистика профиля">
                <div>
                    <strong>—</strong>
                    <span>общие группы</span>
                </div>
                <div>
                    <strong>{{ $showSharedFriendsCount ? $sharedFriendsCount : '—' }}</strong>
                    <span>{{ $friendLabel }}</span>
                </div>
                <div>
                    <strong>{{ $showPostsCount ? $visiblePostsCount : '—' }}</strong>
                    <span>{{ $postLabel }}</span>
                </div>
                <div>
                    <strong>{{ $directConversation?->created_at?->translatedFormat('j F') ?? '—' }}</strong>
                    <span>чат начат</span>
                </div>
            </section>

            <div class="profile-layout">
                <div class="profile-main-column">
                    <section class="profile-card profile-soon-card">
                        <div class="profile-section-head">
                            <h2>Safety code — 4 слова</h2>
                            <span>скоро</span>
                        </div>
                        <p>Функция появится позже. Здесь будут слова для сверки ключей и fingerprint.</p>
                        <div class="profile-soon-grid" aria-hidden="true">
                            <div></div>
                            <div></div>
                            <div></div>
                            <div></div>
                        </div>
                    </section>

                    <section class="profile-activity">
                        <div class="profile-section-head plain">
                            <h2>Недавняя активность</h2>
                        </div>

                        @if(! $showPosts)
                            <div class="profile-empty">Посты скрыты настройками приватности.</div>
                        @else
                            @forelse($recentPosts as $activity)
                                @if($activity instanceof \App\Services\FeedCard)
                                    @if($activity->isCommunityPost())
                                        <x-community-feed-post-card :card="$activity" />
                                    @elseif($activity->isFeedPost())
                                        <x-feed-post-card
                                            :post="$activity->feedPost"
                                            :format-bytes="$formatBytes"
                                            :answer-label="$answerLabel"
                                        />
                                    @endif
                                @else
                                    <x-feed-post-card
                                        :post="$activity"
                                        :format-bytes="$formatBytes"
                                        :answer-label="$answerLabel"
                                    />
                                @endif
                            @empty
                                <div class="profile-empty">Пока нет видимых постов.</div>
                            @endforelse
                        @endif
                    </section>
                </div>

                <aside class="profile-side-column">
                    <section class="profile-card">
                        <div class="profile-section-head">
                            <h2>Общие группы</h2>
                            <span>скоро</span>
                        </div>
                        <div class="profile-empty compact">Появятся, когда в приложении будут сообщества.</div>
                    </section>

                    <section class="profile-card">
                        <div class="profile-section-head plain">
                            <h2>Общие чаты</h2>
                            @if($showSharedChats)
                                <span>{{ $sharedGroupChats->count() }}</span>
                            @endif
                        </div>

                        <div class="profile-group-list">
                            @if(! $showSharedChats)
                                <div class="profile-empty compact">Скрыто настройками приватности.</div>
                            @else
                                @forelse($sharedGroupChats as $chat)
                                    <a class="profile-group-item" href="{{ route('chats.index') }}?conversation={{ $chat->id }}">
                                        <div>
                                            @if($chat->avatar)
                                                <img src="/storage/{{ $chat->avatar }}" alt="">
                                            @else
                                                {{ mb_strtoupper(mb_substr($chat->title, 0, 1)) }}
                                            @endif
                                        </div>
                                        <span>
                                            <strong>{{ $chat->title }}</strong>
                                            <small>{{ $chat->members_count }} участников</small>
                                        </span>
                                    </a>
                                @empty
                                    <div class="profile-empty compact">Общих групповых чатов пока нет.</div>
                                @endforelse
                            @endif
                        </div>
                    </section>

                </aside>
            </div>
        </div>
    </div>

    {{-- Remove friend confirmation modal --}}
    @if($isFriend && ! $isSelf)
        <div id="removeFriendModal" class="modal-overlay" style="display:none">
            <div class="modal-box">
                <div class="modal-title">Удалить из друзей?</div>
                <p class="modal-subtitle">{{ $profileUser->feedName() }} будет удалён из вашего списка друзей. Это действие необратимо.</p>
                <div class="modal-actions">
                    <button class="modal-btn-secondary" id="removeFriendCancel" type="button">Отмена</button>
                    <button class="modal-btn-danger" id="removeFriendConfirm" type="button">Удалить</button>
                </div>
            </div>
        </div>
    @endif

</x-app-shell>
