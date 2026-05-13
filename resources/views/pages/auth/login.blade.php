<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>skr — Вход</title>
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

        <h1>С возвращением</h1>
        <p class="subtitle">Войдите, чтобы продолжить работу.</p>

        @if ($errors->any())
            <div class="error">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="form-group">
                <input type="text" id="login" name="login" value="{{ old('login') }}" placeholder="Логин" required autofocus>
            </div>

            <div class="form-group">
                <input type="password" id="password" name="password" placeholder="Пароль" required>
            </div>

            <div class="meta-row">
                <label class="checkbox-group">
                    <input type="checkbox" id="remember" name="remember">
                    <span>Запомнить меня</span>
                </label>
                <a href="{{ route('forgot-password') }}" class="forgot-link">Забыли пароль?</a>
            </div>

            <button type="submit" class="btn">Войти</button>
        </form>

        <p class="link">
            Нет аккаунта? <a href="{{ route('register') }}">Зарегистрироваться</a>
        </p>

        <div class="divider">или</div>

        <details class="backup-section">
            <summary>Войти с кодом восстановления</summary>
            @if ($errors->has('backup_code'))
                <div class="error">{{ $errors->first('backup_code') }}</div>
            @endif
            <form method="POST" action="{{ route('login.backup') }}">
                @csrf
                <div class="form-group">
                    <input type="text" name="login" value="{{ old('login') }}" placeholder="Логин" required>
                </div>
                <div class="form-group">
                    <textarea name="backup_code" placeholder="Код восстановления" rows="3" required style="resize:vertical;font-family:ui-monospace,'SF Mono',monospace;font-size:11px;"></textarea>
                </div>
                <button type="submit" class="btn btn-outline">Восстановить доступ</button>
            </form>
        </details>
    </div>
</body>
</html>
