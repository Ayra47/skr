<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $profileUser->feedName() }} — профиль</title>
    <script>
        window.Laravel = {
            userId: @json($viewer->id),
        };
    </script>
    @vite(['resources/js/pages/profile.ts'])
</head>

<body>
    @include('components.nav')

    @php
        $profileName = $profileUser->feedName();
        $friendLabel = trans_choice('{0} общих друзей|{1} общий друг|[2,4] общих друга|[5,*] общих друзей', $sharedFriendsCount);
        $postLabel = trans_choice('{0} постов в ленте|{1} пост в ленте|[2,4] поста в ленте|[5,*] постов в ленте', $visiblePostsCount);
        $answerLabel = static function (int $count): string {
            $mod10 = $count % 10;
            $mod100 = $count % 100;

            if ($mod10 === 1 && $mod100 !== 11) {
                return 'ответ';
            }

            if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
                return 'ответа';
            }

            return 'ответов';
        };
        $formatBytes = static function (?int $bytes): string {
            if (! $bytes) {
                return '0 Б';
            }

            $units = ['Б', 'КБ', 'МБ', 'ГБ'];
            $power = min((int) floor(log($bytes, 1024)), count($units) - 1);

            return number_format($bytes / (1024 ** $power), $power === 0 ? 0 : 1, ',', ' ').' '.$units[$power];
        };
    @endphp

    <main class="profile-root">
        <div class="profile-glow"></div>

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
                        @elseif($isFriend)
                            <a class="profile-primary-action" href="{{ route('chats.index') }}?with={{ $profileUser->id }}&login={{ urlencode($profileName) }}">
                                Написать
                            </a>
                            <button class="profile-disabled-action" type="button" disabled>Позвонить</button>
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
                            @forelse($recentPosts as $post)
                                <x-feed-post-card
                                    :post="$post"
                                    :format-bytes="$formatBytes"
                                    :answer-label="$answerLabel"
                                />
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

                    <section class="profile-card">
                        <div class="profile-section-head plain">
                            <h2>Управление контактом</h2>
                        </div>

                        <div class="profile-contact-actions">
                            <button type="button" disabled>Поделиться контактом</button>
                            @if($isFriend && ! $isSelf)
                                <button type="button" disabled>Удалить из друзей</button>
                            @endif
                            <button type="button" disabled>Заблокировать</button>
                            <button type="button" disabled>Пожаловаться</button>
                        </div>
                    </section>
                </aside>
            </div>
        </div>
    </main>
</body>

</html>
