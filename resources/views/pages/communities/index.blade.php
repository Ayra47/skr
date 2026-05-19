<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Сообщества</title>
    @vite(['resources/js/pages/communities.js'])
    <script>
        window.Laravel = { userId: @json(Auth::id()) };
    </script>
</head>
<body>
    @include('components.nav')

    <main class="communities-root">
        <div class="communities-empty">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            <h2>Сообщества</h2>
            <p>Скоро здесь появятся сообщества</p>
        </div>
    </main>
</body>
</html>
