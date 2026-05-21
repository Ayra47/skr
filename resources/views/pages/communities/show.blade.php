<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $community->name }} — skr</title>
    @vite(['resources/js/pages/communities.js'])
    <script>
        window.Laravel = { userId: @json(Auth::id()) };
    </script>
</head>

<body class="communities-body">
    @include('components.nav')

    @php
        $isActiveMember = $membership?->status === \App\Models\CommunityMember::STATUS_ACTIVE;
        $canCompose = $isActiveMember && $selectedTopic && ! $selectedTopic->is_archived && $latestEpoch;
    @endphp

    <main class="community-detail-shell">
        @if (session('community_status'))
            <div class="community-notice">{{ session('community_status') }}</div>
        @endif

        @if ($errors->any())
            <div class="community-error">{{ $errors->first() }}</div>
        @endif

        <section class="community-hero">
            <div class="community-hero-icon">{{ mb_strtoupper(mb_substr($community->name, 0, 1)) }}</div>
            <div class="community-hero-copy">
                <div class="community-hero-meta">
                    <span class="community-badge">{{ $community->visibility }}</span>
                    <span class="community-badge community-badge-soft">{{ $community->join_mode }}</span>
                </div>
                <h1>{{ $community->name }}</h1>
                <p>{{ $community->description ?: 'Описание пока не добавлено.' }}</p>
                <div class="community-stats">
                    <span>{{ $community->member_count }} members</span>
                    <span>{{ $community->post_count }} posts</span>
                    <span>{{ $membership ? $membership->role.' · '.$membership->status : 'not a member' }}</span>
                </div>
            </div>

            @if (! $membership)
                <div class="community-join-box">
                    @if ($community->join_mode === \App\Models\Community::JOIN_OPEN)
                        <form method="POST" action="{{ route('communities.join', $community) }}">
                            @csrf
                            <button type="submit" class="community-btn community-btn-primary">Join</button>
                        </form>
                    @elseif ($community->join_mode === \App\Models\Community::JOIN_REQUEST)
                        <form method="POST" action="{{ route('communities.join-requests.store', $community) }}" class="community-form">
                            @csrf
                            <textarea name="message" rows="2" placeholder="Сообщение модераторам"></textarea>
                            <button type="submit" class="community-btn community-btn-primary">Request join</button>
                        </form>
                    @else
                        <p class="community-muted">Invite required</p>
                    @endif
                </div>
            @endif
        </section>

        <div class="community-detail-grid">
            <aside class="community-left">
                <section class="community-panel">
                    <div class="community-panel-head">
                        <h2>Темы</h2>
                        <span>{{ $topics->count() }}</span>
                    </div>

                    <div class="community-topic-list">
                        @forelse ($topics as $topic)
                            <div class="community-topic-row {{ $selectedTopic?->is($topic) ? 'is-active' : '' }}">
                                <a href="{{ route('communities.show', ['community' => $community, 'topic' => $topic->id]) }}">
                                    <strong>{{ $topic->name }}</strong>
                                    <small>{{ $topic->post_count }} posts @if ($topic->is_archived) · archived @endif</small>
                                </a>
                                @if ($canManageTopics && ! $topic->is_system && ! $topic->is_archived)
                                    <form method="POST" action="{{ route('communities.topics.archive', $topic) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="community-icon-btn" title="Archive" aria-label="Archive topic">×</button>
                                    </form>
                                @endif
                            </div>
                        @empty
                            <p class="community-muted">Тем пока нет.</p>
                        @endforelse
                    </div>

                    @if ($canManageTopics)
                        <form method="POST" action="{{ route('communities.topics.store', $community) }}" class="community-form community-topic-create">
                            @csrf
                            <h3>Создать тему</h3>
                            <input name="name" type="text" placeholder="Название темы" required maxlength="100">
                            <textarea name="description" rows="2" placeholder="Описание"></textarea>
                            <button type="submit" class="community-btn community-btn-primary">Создать тему</button>
                        </form>
                    @endif
                </section>

                <section class="community-panel">
                    <div class="community-panel-head">
                        <h2>Участники</h2>
                        <span>{{ $members->count() }}</span>
                    </div>
                    <div class="community-member-list">
                        @foreach ($members as $member)
                            <div class="community-member-row">
                                <span>{{ $member->user->pseudonym ?: $member->user->login }}</span>
                                <small>{{ $member->role }} · {{ $member->status }}</small>
                            </div>
                        @endforeach
                    </div>
                </section>

                @if ($canInvite)
                    <section class="community-panel">
                        <h2>Пригласить друга</h2>
                        <form method="POST" action="{{ route('communities.direct-invites.store', $community) }}" class="community-form">
                            @csrf
                            <label>
                                <span>Друг или user id</span>
                                <input name="invitee_id" type="number" list="community-friends-list" placeholder="user id" required>
                                <datalist id="community-friends-list">
                                    @foreach ($friends as $friend)
                                        <option value="{{ $friend->id }}">{{ $friend->pseudonym ?: $friend->login }}</option>
                                    @endforeach
                                </datalist>
                            </label>
                            <label>
                                <span>Сообщение</span>
                                <textarea name="message" rows="2" maxlength="500"></textarea>
                            </label>
                            <label>
                                <span>Истекает</span>
                                <input name="expires_at" type="datetime-local">
                            </label>
                            <button type="submit" class="community-btn community-btn-primary">Отправить</button>
                        </form>
                    </section>
                @endif

            </aside>

            <section class="community-posts">
                <div class="community-posts-head">
                    <div>
                        <p>Topic</p>
                        <h2>{{ $selectedTopic?->name ?? 'Нет темы' }}</h2>
                    </div>
                    @if ($selectedTopic?->is_archived)
                        <span class="community-badge">archived</span>
                    @endif
                </div>

                @if (! $isActiveMember)
                    <div class="community-locked">
                        <strong>Locked</strong>
                        <p>Посты доступны после активного членства.</p>
                    </div>
                @else
                    <div class="community-post-list">
                        @forelse ($posts as $post)
                            @php
                                $postMember = $members->firstWhere('user_id', $post->user_id);
                                $authorName = $community->hide_real_names
                                    ? ($postMember?->pseudonym ?: $postMember?->community_display_name ?: 'member #'.$post->user_id)
                                    : ($post->author?->pseudonym ?: $post->author?->login ?: 'member #'.$post->user_id);
                            @endphp
                            <article class="community-post-card">
                                <div>
                                    <strong>Encrypted post</strong>
                                    <span>{{ $post->created_at?->toIso8601String() }}</span>
                                </div>
                                <p>{{ $authorName }}</p>
                            </article>
                        @empty
                            <p class="community-muted">В этой теме пока нет постов.</p>
                        @endforelse
                    </div>
                @endif

                <section class="community-panel community-composer">
                    <h2>Composer</h2>
                    @if ($canCompose)
                        <form method="POST" action="{{ route('communities.topics.posts.store', [$community, $selectedTopic]) }}" class="community-form">
                            @csrf
                            <label>
                                <span>ciphertext</span>
                                <textarea name="ciphertext" rows="3" required></textarea>
                            </label>
                            <label>
                                <span>nonce</span>
                                <input name="nonce" type="text" required>
                            </label>
                            <label>
                                <span>epoch_id</span>
                                <input name="epoch_id" type="text" value="{{ $latestEpoch->id }}" required>
                            </label>
                            <button type="submit" class="community-btn community-btn-primary">Publish encrypted post</button>
                        </form>
                    @else
                        <p class="community-muted">Composer disabled: нет активного членства, тема архивирована или нет key epoch.</p>
                    @endif
                </section>
            </section>
        </div>
    </main>
</body>

</html>
