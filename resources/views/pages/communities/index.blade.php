<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Сообщества — skr</title>
    @include('partials.accent-style')
    @vite(['resources/js/pages/communities.js'])
    <script>
        window.Laravel = { userId: @json(Auth::id()) };
    </script>
</head>

<body class="communities-body">
    @include('components.nav')

    <main class="communities-shell">
        <aside class="communities-sidebar">
            @if (session('community_status'))
                <div class="community-notice">{{ session('community_status') }}</div>
            @endif

            @if ($errors->any())
                <div class="community-error">{{ $errors->first() }}</div>
            @endif

            <section class="community-panel">
                <div class="community-panel-head">
                    <h2>Приглашения от друзей</h2>
                    <span>{{ $directInvites->count() }}</span>
                </div>

                <div class="community-invite-list">
                    @forelse ($directInvites as $invite)
                        <article class="community-invite-card">
                            <p>
                                <strong>{{ $invite->inviter->pseudonym ?: $invite->inviter->login }}</strong>
                                invited me to
                                <a href="{{ route('communities.show', $invite->community) }}">{{ $invite->community->name }}</a>
                            </p>
                            @if ($invite->expires_at)
                                <span>до {{ $invite->expires_at->format('d.m.Y H:i') }}</span>
                            @endif
                            <div class="community-actions">
                                <form method="POST" action="{{ route('communities.direct-invites.accept', $invite) }}">
                                    @csrf
                                    <button type="submit" class="community-btn community-btn-primary">Принять</button>
                                </form>
                                <form method="POST" action="{{ route('communities.direct-invites.decline', $invite) }}">
                                    @csrf
                                    <button type="submit" class="community-btn community-btn-ghost">Отклонить</button>
                                </form>
                            </div>
                        </article>
                    @empty
                        <p class="community-muted">Новых приглашений нет.</p>
                    @endforelse
                </div>
            </section>

            <section class="community-panel">
                <h2>Создать сообщество</h2>
                <form method="POST" action="{{ route('communities.store') }}" class="community-form">
                    @csrf

                    <label>
                        <span>Название</span>
                        <input name="name" type="text" value="{{ old('name') }}" required maxlength="100">
                    </label>

                    <label>
                        <span>Описание</span>
                        <textarea name="description" rows="3">{{ old('description') }}</textarea>
                    </label>

                    <div class="community-form-grid">
                        <label>
                            <span>Видимость</span>
                            <select name="visibility">
                                <option value="public">public</option>
                                <option value="private">private</option>
                                <option value="hidden">hidden</option>
                            </select>
                        </label>

                        <label>
                            <span>Вступление</span>
                            <select name="join_mode">
                                <option value="open">open</option>
                                <option value="request">request</option>
                                <option value="invite_only">invite_only</option>
                            </select>
                        </label>

                        <label>
                            <span>Лимит</span>
                            <select name="member_limit">
                                <option value="">none</option>
                                @foreach ([5, 50, 100, 500, 1000, 5000] as $limit)
                                    <option value="{{ $limit }}">{{ $limit }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            <span>TTL</span>
                            <select name="default_post_ttl_seconds">
                                <option value="">none</option>
                                <option value="3600">1h</option>
                                <option value="86400">24h</option>
                                <option value="604800">7d</option>
                            </select>
                        </label>

                        <label>
                            <span>Инвайты</span>
                            <select name="invite_policy">
                                <option value="all_members">all_members</option>
                                <option value="moderators_only">moderators_only</option>
                            </select>
                        </label>

                        <label>
                            <span>Постинг</span>
                            <select name="posting_policy">
                                <option value="everyone">everyone</option>
                                <option value="moderators_only">moderators_only</option>
                            </select>
                        </label>
                    </div>

                    <div class="community-checks">
                        <label><input type="hidden" name="allow_posts_in_member_feed" value="0"><input type="checkbox" name="allow_posts_in_member_feed" value="1" checked> posts in member feed</label>
                        <label><input type="hidden" name="hide_real_names" value="0"><input type="checkbox" name="hide_real_names" value="1"> hide real names</label>
                        <label><input type="hidden" name="show_key_fingerprints" value="0"><input type="checkbox" name="show_key_fingerprints" value="1" checked> key fingerprints</label>
                        <label><input type="hidden" name="anonymous_reactions_enabled" value="0"><input type="checkbox" name="anonymous_reactions_enabled" value="1"> anonymous reactions</label>
                    </div>

                    <button type="submit" class="community-btn community-btn-primary">Создать</button>
                </form>
            </section>
        </aside>

        <section class="communities-main">
            <div class="communities-title">
                <div>
                    <p>Communities</p>
                    <h1>Сообщества</h1>
                </div>
                <span>{{ $communities->count() }} visible</span>
            </div>

            <div class="community-list">
                @forelse ($communities as $community)
                    @php $membership = $memberships->get($community->id); @endphp
                    <a class="community-row" href="{{ route('communities.show', $community) }}">
                        <span class="community-avatar">{{ mb_strtoupper(mb_substr($community->name, 0, 1)) }}</span>
                        <span class="community-row-body">
                            <strong>{{ $community->name }}</strong>
                            <small>{{ $community->visibility }} · {{ $community->join_mode }} · {{ $community->member_count }} members</small>
                        </span>
                        @if ($membership)
                            <span class="community-badge">{{ $membership->status }}</span>
                        @else
                            <span class="community-badge community-badge-soft">public</span>
                        @endif
                    </a>
                @empty
                    <div class="community-empty">
                        <h2>Сообществ пока нет</h2>
                        <p>Создайте первое или примите приглашение от друга.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </main>
</body>

</html>
