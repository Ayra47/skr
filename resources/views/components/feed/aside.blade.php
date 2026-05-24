@php
    $user = auth()->user();
    $friendIds = $user->friendIds();
    $friends = \App\Models\User::query()
        ->whereIn('id', $friendIds)
        ->select(['id', 'login', 'avatar'])
        ->orderBy('login')
        ->take(20)
        ->get();
@endphp

@if($friends->isNotEmpty())
    <div class="fd-right-section">
        <div class="fd-right-label">друзья</div>
        @foreach($friends as $friend)
            <a href="{{ route('chats.index') }}" class="fd-friend-row" data-friend-id="{{ $friend->id }}">
                <div class="fd-friend-avatar">
                    @if($friend->avatar)
                        <img src="/storage/{{ $friend->avatar }}" alt="">
                    @else
                        {{ mb_strtoupper(mb_substr($friend->login, 0, 1)) }}
                    @endif
                </div>
                <span class="fd-friend-name">{{ $friend->login }}</span>
                <span class="fd-friend-dot" data-friend-dot="{{ $friend->id }}"></span>
            </a>
        @endforeach
    </div>
@endif

<div class="fd-right-section">
    <div class="fd-right-label">тренды</div>
    <div class="fd-soon-block">скоро...</div>
</div>

<div class="fd-right-section">
    <div class="fd-right-label">сообщества</div>
    <div class="fd-soon-block">скоро...</div>
</div>

<div class="fd-security-strip">
    <span class="fd-security-dot"></span>
    e2e · реакции анонимны · 256-bit
</div>
