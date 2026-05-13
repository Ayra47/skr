<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>skr — Новый пароль</title>
    @vite(['resources/css/pages/login.scss'])
</head>
<body>
    <div class="glow glow-amber"></div>
    <div class="glow glow-blue"></div>
    <div class="container">
        <div class="brand">
            <div class="brand-mark">s</div>
            <div class="brand-text">skr</div>
        </div>

        <h1>Новый пароль</h1>
        <p class="subtitle">Придумайте новый пароль для входа в аккаунт.</p>

        <div class="warning-box">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.3L2 21h20L13.7 3.3a2 2 0 0 0-3.4 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <span>Переписка останется недоступной, если вы не помните <strong>PIN-код защиты ключа</strong> (вводился при первом входе с нового устройства) или <strong>код восстановления</strong>.</span>
        </div>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ $signedUrl }}">
            @csrf
            <div class="form-group">
                <input type="password" name="password" placeholder="Новый пароль" required autofocus
                    minlength="8">
            </div>
            <div class="form-group">
                <input type="password" name="password_confirmation" placeholder="Повторите пароль" required>
            </div>
            <button type="submit" class="btn">Сохранить пароль</button>
        </form>
    </div>
</body>
</html>
