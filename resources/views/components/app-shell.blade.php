@props([
    'title' => 'skr',
    'vite' => [],
])

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>
    @include('partials.accent-style')
    @livewireStyles
    @if(! empty($vite))
        @vite($vite)
    @endif
    <script>
        window.Laravel = {
            userId: @json(Auth::id()),
        };
    </script>
    @isset($head){{ $head }}@endisset
</head>

<body>
    <div @class([
        'app-shell',
        'has-aside' => isset($aside),
        'has-panel' => isset($panel),
    ])>
        {{ $sidebar }}

        <main class="app-main">
            <div class="app-scroll">
                {{ $slot }}
            </div>
        </main>

        @isset($aside)
            <aside class="app-aside">
                {{ $aside }}
            </aside>
        @endisset

        @isset($panel){{ $panel }}@endisset
    </div>

    @isset($after){{ $after }}@endisset
    @livewireScripts
</body>

</html>
