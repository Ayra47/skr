<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Лента</title>
    <script>
        window.Laravel = {
            userId: @json(Auth::id()),
        };
    </script>
    @livewireStyles
    @vite(['resources/js/pages/feed.ts'])
</head>

<body>
    @include('components.nav')

    <livewire:feed />

    @livewireScripts
</body>

</html>
