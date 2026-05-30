<section class="cm-inv-section" aria-label="Приглашения от друзей">
    <div class="cm-inv-head">
        <div>
            <h3>Приглашения</h3>
            <p>от друзей</p>
        </div>
        <span class="cm-inv-badge">{{ $invites->count() }}</span>
    </div>

    <div class="cm-inv-list">
        @foreach ($invites as $invite)
            @php
                $friendName = $invite->inviter->pseudonym ?: $invite->inviter->login;
                $initial = mb_strtoupper(mb_substr($friendName, 0, 1));
            @endphp
            <article class="cm-inv-item">
                <div class="cm-inv-avatar" aria-hidden="true">{{ $initial }}</div>
                <div class="cm-inv-info">
                    <strong>{{ $friendName }}</strong>
                    <span>{{ $invite->community->name }}</span>
                    @if ($invite->expires_at)
                        <small>до {{ $invite->expires_at->diffForHumans() }}</small>
                    @endif
                </div>
                <div class="cm-inv-actions">
                    <form method="POST" action="{{ route('communities.direct-invites.accept', $invite) }}">
                        @csrf
                        <button type="submit" class="cm-inv-btn cm-inv-accept"
                            title="Принять" aria-label="Принять приглашение">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 6L9 17l-5-5" />
                            </svg>
                        </button>
                    </form>
                    <form method="POST" action="{{ route('communities.direct-invites.decline', $invite) }}">
                        @csrf
                        <button type="submit" class="cm-inv-btn cm-inv-dismiss"
                            title="Отклонить" aria-label="Отклонить приглашение">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 6L6 18M6 6l12 12" />
                            </svg>
                        </button>
                    </form>
                </div>
            </article>
        @endforeach
    </div>
</section>
