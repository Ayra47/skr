<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Состояние сервиса — skr</title>
    <script>window.Laravel = { userId: @json(Auth::id()) };</script>
    @vite(['resources/js/pages/status.js'])
</head>

<body class="st-body">
    @include('components.nav')

    <div class="st-bg-glow"></div>

    <div class="st-wrap">

        {{-- Header --}}
        <header class="st-header">
            <h1 class="st-header-title">Состояние сервиса</h1>
            <p class="st-header-sub">Технический статус, инциденты и публичные обязательства</p>
        </header>

        {{-- Hero --}}
        <section class="st-hero {{ $allOk ? 'st-hero--ok' : 'st-hero--warn' }}">
            <div class="st-hero-glow"></div>
            <div class="st-hero-inner">
                <div class="st-hero-icon {{ $allOk ? 'st-hero-icon--ok' : 'st-hero-icon--warn' }}">
                    @if($allOk)
                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L20 6"/></svg>
                        <span class="st-hero-pulse"></span>
                    @else
                        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/></svg>
                    @endif
                </div>
                <div class="st-hero-text">
                    <h2 class="st-hero-title">{{ $allOk ? 'Все системы работают' : 'Частичная деградация' }}</h2>
                    <p class="st-hero-sub">
                        Обновлено: {{ now()->translatedFormat('j M Y, H:i') }} МСК
                        <span class="st-hero-dot">·</span>
                        авто-обновление каждые 30 секунд
                    </p>
                </div>
                <div class="st-hero-uptime">
                    <div class="st-hero-uptime-val">{{ number_format($overallUptime, 2) }}%</div>
                    <div class="st-hero-uptime-label">uptime · 90 дней</div>
                </div>
            </div>
        </section>

        {{-- Components --}}
        <section class="st-card">
            <div class="st-card-head">
                <h2 class="st-card-title">Компоненты</h2>
                <span class="st-card-label">90 дней</span>
            </div>
            <div class="st-components">
                @foreach($components as $c)
                    @php
                        $bars = $dayBars[$c['id']] ?? array_fill(0, 90, 'ok');
                    @endphp
                    <div class="st-comp">
                        <span class="st-comp-icon">
                            @include('pages.status._icon', ['icon' => $c['icon']])
                        </span>
                        <div class="st-comp-body">
                            <div class="st-comp-row">
                                <span class="st-comp-name">{{ $c['name'] }}</span>
                                @if($c['note'])
                                    <span class="st-comp-note st-comp-note--{{ $c['status'] }}">· {{ $c['note'] }}</span>
                                @endif
                                <span class="st-comp-uptime">{{ number_format($c['uptime'], 2) }}%</span>
                            </div>
                            <div class="st-comp-bars-row">
                                <div class="st-bars">
                                    @foreach($bars as $bar)
                                        <span class="st-bar st-bar--{{ $bar }}"></span>
                                    @endforeach
                                </div>
                                <span class="st-comp-status st-comp-status--{{ $c['status'] }}">
                                    <span class="st-comp-dot"></span>
                                    {{ $c['status'] === 'ok' ? 'работает' : ($c['status'] === 'warn' ? 'есть проблемы' : 'отказ') }}
                                </span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Warrant Canary --}}
        <section class="st-canary">
            <div class="st-canary-head">
                <span class="st-canary-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M16 7h.01"/><path d="M3.5 6c.6.5 1.8 1.5 4 2 4 1 8-1 9-3 .8.7 1.3 1.5 1.3 2.5 0 4-3 7-5.5 7.5-2.5.5-7 .5-9-1.5"/><path d="M14 13c-1 2-2 4-4 5"/><path d="M5 17l-.5 4"/><path d="M9 17l-.5 4"/></svg>
                </span>
                <div class="st-canary-head-text">
                    <h2 class="st-canary-title">Warrant canary</h2>
                    <p class="st-canary-desc">«канарейка в шахте» — публичное заявление о том, что мы не получали тайных запросов</p>
                </div>
                <span class="st-canary-badge {{ $canaryStale ? 'st-canary-badge--stale' : '' }}">
                    <span class="st-canary-badge-dot"></span>
                    {{ $canaryStale ? 'устарела' : 'актуальна' }}
                </span>
            </div>

            <div class="st-canary-body">
                @if($currentCanary)
                    <div class="st-canary-date-label">заявление на {{ $currentCanary->published_at->translatedFormat('j M Y') }}</div>

                    <div class="st-canary-statement">
                        <p>По состоянию на <strong>{{ $currentCanary->published_at->translatedFormat('j M Y') }}</strong> команда skr
                        <strong class="st-canary-accent"> не получала </strong>
                        ни одного запроса от государственных органов на выдачу пользовательских данных,
                        ни одного национального security letter (NSL), ни одного судебного приказа о
                        принудительной модификации программного обеспечения или ключевой инфраструктуры.</p>
                        <p>Это заявление обновляется еженедельно. Если оно перестаёт обновляться —
                        возможно, мы получили такой запрос и не можем сообщить об этом напрямую.</p>
                    </div>

                    <div class="st-canary-sig-row">
                        <div class="st-canary-sig-info">
                            <div class="st-canary-sig-label">подпись Ed25519</div>
                            <div class="st-canary-sig-val" id="canarySig">{{ $currentCanary->signature }}</div>
                        </div>
                        <button class="st-canary-copy-btn" id="canaryCopyBtn" data-sig="{{ $currentCanary->signature }}">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                            <span>скопировать</span>
                        </button>
                    </div>
                @else
                    <div class="st-canary-statement">
                        <p class="st-canary-missing">Заявление ещё не опубликовано.</p>
                    </div>
                @endif

                {{-- History --}}
                @if($canaryHistory->count() > 0)
                    <div class="st-canary-history">
                        <div class="st-canary-history-label">история обновлений</div>
                        <div class="st-canary-history-list" id="canaryHistoryList">
                            @foreach($canaryHistory as $h)
                                <div class="st-canary-history-row {{ $h->is_current ? 'st-canary-history-row--current' : '' }}">
                                    <span class="st-canary-history-dot {{ $h->is_current ? 'st-canary-history-dot--current' : '' }}"></span>
                                    <span class="st-canary-history-date">{{ $h->published_at->translatedFormat('j M Y') }}</span>
                                    <span class="st-canary-history-sig">{{ $h->signature }}</span>
                                    @if($h->is_current)
                                        <span class="st-canary-history-tag">текущая</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        @if($hasMoreCanary)
                            <div class="st-load-more-wrap" id="canaryLoadMore">
                                <button class="st-load-more-btn" id="canaryLoadMoreBtn" data-offset="5">
                                    Загрузить ещё
                                </button>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </section>

        {{-- Incidents --}}
        <section class="st-card" id="incidentsCard">
            <div class="st-card-head">
                <h2 class="st-card-title">Последние инциденты</h2>
                <span class="st-card-label">30 дней</span>
            </div>
            @if($recentIncidents->isEmpty())
                <div class="st-incidents-empty">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L20 6"/></svg>
                    Инцидентов за последние 30 дней не зафиксировано
                </div>
            @else
                <div class="st-incidents" id="incidentsList">
                    @foreach($recentIncidents as $inc)
                        <div class="st-incident st-incident--{{ $inc->kind }}">
                            <span class="st-incident-bar"></span>
                            <div class="st-incident-head">
                                <span class="st-incident-title">{{ $inc->title }}</span>
                                @if($inc->isResolved())
                                    <span class="st-incident-tag st-incident-tag--resolved">устранено</span>
                                @else
                                    <span class="st-incident-tag st-incident-tag--ongoing">текущий</span>
                                @endif
                                <span class="st-incident-meta">
                                    {{ $inc->started_at->translatedFormat('j M Y') }}
                                    @if($inc->formattedDuration())
                                        · {{ $inc->formattedDuration() }}
                                    @endif
                                </span>
                            </div>
                            @if($inc->body)
                                <p class="st-incident-body">{{ $inc->body }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
                @if($hasMoreIncidents)
                    <div class="st-load-more-wrap" id="incidentsLoadMore">
                        <button class="st-load-more-btn" id="incidentsLoadMoreBtn" data-offset="5">
                            Загрузить ещё
                        </button>
                    </div>
                @endif
            @endif
        </section>

        <div class="st-footer">
            Публичный архив warrant canary и ключи подписей —
            <a href="https://github.com/Ayra47/skr" target="_blank" class="st-footer-link">github.com/skr</a>
        </div>

    </div>

</body>
</html>
