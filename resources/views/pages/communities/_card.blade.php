@php
    $tintHash = abs(crc32($community->name));
    $tint = $tintHash % 360;
    $tintEnd = ($tint + 60) % 360;
    $initial = mb_strtoupper(mb_substr($community->name, 0, 1));
    $isPrivate = $community->visibility !== \App\Models\Community::VISIBILITY_PUBLIC;
    $memberCount = (int) $community->member_count;
    $memberWord = match (true) {
        $memberCount === 1 => 'участник',
        $memberCount < 5 => 'участника',
        default => 'участников',
    };
@endphp

<a class="community-card" href="{{ route('communities.show', $community) }}" wire:navigate>
    <span class="community-card-avatar"
        style="background: linear-gradient(135deg, oklch(0.34 0.07 {{ $tint }}), oklch(0.18 0.05 {{ $tintEnd }}));">
        {{ $initial }}
    </span>

    <span class="community-card-body">
        <span class="community-card-row">
            <strong class="community-card-name">{{ $community->name }}</strong>

            @if ($isPrivate)
                <span class="community-private-badge">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="4" y="10" width="16" height="11" rx="2.5" />
                        <path d="M8 10V7a4 4 0 0 1 8 0v3" />
                    </svg>
                    приватное
                </span>
            @endif

            @if ($membership && $membership->status === \App\Models\CommunityMember::STATUS_PENDING_KEY_DELIVERY)
                <span class="community-badge">ожидает ключ</span>
            @endif
        </span>

        @if ($community->description)
            <p class="community-card-desc">{{ $community->description }}</p>
        @endif

        <span class="community-card-meta">
            <span class="community-card-meta-item">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                    <circle cx="9" cy="7" r="4" />
                    <path d="M22 21v-2a4 4 0 0 0-3-3.9" />
                    <path d="M16 3.1a4 4 0 0 1 0 7.8" />
                </svg>
                {{ $memberCount }} {{ $memberWord }}
            </span>

            <span class="community-card-dot"></span>

            <span class="community-card-meta-item">{{ $community->join_mode }}</span>

            @if ($membership)
                <span class="community-card-dot"></span>
                <span class="community-card-meta-item">ваша роль: {{ $membership->role }}</span>
            @endif
        </span>
    </span>
</a>
