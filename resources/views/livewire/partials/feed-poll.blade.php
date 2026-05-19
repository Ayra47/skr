@php
    $total = $poll->totalVotes();
    $isExpired = $poll->isExpired();
    $hasVoted = count($votedOptionIds) > 0;
    $showResults = $hasVoted || $isExpired;
    $isMultiple = $poll->mode === \App\Models\Poll::MODE_MULTIPLE;
    $maxChoices = $poll->max_choices;
@endphp

<div class="feed-poll"
    data-poll
    data-poll-id="{{ $poll->id }}"
    data-post-id="{{ $postId }}"
    data-poll-mode="{{ $poll->mode }}"
    data-poll-max-choices="{{ $maxChoices ?? '' }}"
    data-poll-expired="{{ $isExpired ? 'true' : 'false' }}"
    data-poll-voted-ids="{{ implode(',', $votedOptionIds) }}"
    data-vote-url="{{ route('feed.posts.poll.vote', $postId) }}"
    data-cancel-url="{{ route('feed.posts.poll.vote.cancel', $postId) }}"
>
    <div class="feed-poll-meta">
        @if($isMultiple)
            <span class="feed-poll-type">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="4" height="4" rx="1"/><rect x="3" y="11" width="4" height="4" rx="1"/><rect x="3" y="17" width="4" height="4" rx="1"/><line x1="10" y1="7" x2="21" y2="7"/><line x1="10" y1="13" x2="21" y2="13"/><line x1="10" y1="19" x2="21" y2="19"/></svg>
                {{ $maxChoices ? "до {$maxChoices} вариантов" : 'несколько вариантов' }}
            </span>
        @else
            <span class="feed-poll-type">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>
                один вариант
            </span>
        @endif

        @if($isExpired)
            <span class="feed-poll-status expired">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                опрос завершён
            </span>
        @elseif($poll->closes_at)
            <span class="feed-poll-status">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                до {{ $poll->closes_at->diffForHumans() }}
            </span>
        @endif
    </div>

    <div class="feed-poll-options" data-poll-options>
        @foreach($poll->options as $option)
            @php
                $isSelected = in_array($option->id, $votedOptionIds, strict: true);
                $pct = $showResults ? $option->percentage($total) : 0;
            @endphp
            <button
                class="feed-poll-option {{ $showResults ? 'show-results' : '' }} {{ $isSelected ? 'selected' : '' }}"
                type="button"
                data-poll-option
                data-option-id="{{ $option->id }}"
                data-votes="{{ $option->votes_count }}"
                data-pct="{{ $pct }}"
                @disabled($isExpired)
                aria-pressed="{{ $isSelected ? 'true' : 'false' }}"
            >
                <span class="feed-poll-option-fill" style="width: {{ $showResults ? $pct : 0 }}%" aria-hidden="true"></span>
                <span class="feed-poll-option-check" aria-hidden="true">
                    @if($isMultiple)
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    @else
                        <svg width="8" height="8" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="6"/></svg>
                    @endif
                </span>
                <span class="feed-poll-option-text">{{ $option->text }}</span>
                @if($showResults)
                    <span class="feed-poll-option-stat" data-poll-option-stat>{{ $pct }}%</span>
                @endif
            </button>
        @endforeach
    </div>

    <div class="feed-poll-footer" data-poll-footer>
        <span class="feed-poll-total" data-poll-total>{{ $total }} {{ $total === 1 ? 'голос' : ($total >= 2 && $total <= 4 ? 'голоса' : 'голосов') }}</span>

        @if($hasVoted && ! $isExpired)
            <button class="feed-poll-cancel" type="button" data-poll-cancel>отменить голос</button>
        @endif
    </div>
</div>
