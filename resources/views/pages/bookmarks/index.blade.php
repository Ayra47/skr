<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Закладки — skr</title>
    <script>window.Laravel = { userId: @json(Auth::id()) };</script>
    @livewireStyles
    @include('partials.accent-style')
    @vite(['resources/js/pages/bookmarks.js'])
</head>

<body class="bm-body">
    @include('components.nav')

    <div class="bm-bg-glow"></div>

    <livewire:bookmarks />

    @livewireScripts
</body>

</html>
