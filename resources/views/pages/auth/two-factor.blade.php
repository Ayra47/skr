<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>skr — Подтверждение входа</title>
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

        <h1>Подтверждение входа</h1>
        <p class="subtitle">Код отправлен на вашу почту. Введите его ниже — он действует 10 минут.</p>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        @if (session('resent'))
            <div class="error" style="background:rgba(109,212,154,.1);border-color:rgba(109,212,154,.25);color:#9ce4bd;">
                Новый код отправлен.
            </div>
        @endif

        <form method="POST" action="{{ route('2fa.verify') }}">
            @csrf
            <div class="form-group">
                <input type="text" name="code" placeholder="000000"
                    maxlength="6" inputmode="numeric" pattern="[0-9]{6}"
                    autocomplete="one-time-code" autofocus required
                    style="letter-spacing:.3em;font-size:22px;text-align:center;font-family:ui-monospace,'SF Mono',monospace;">
            </div>

            <div class="meta-row" style="justify-content:flex-start;">
                <label class="checkbox-group">
                    <input type="checkbox" name="remember_device" value="1">
                    <span>Запомнить это устройство на 7 дней</span>
                </label>
            </div>

            <button type="submit" class="btn">Подтвердить</button>
        </form>

        <p class="link">
            Не пришёл код?
            <form method="POST" action="{{ route('2fa.resend') }}" style="display:inline;">
                @csrf
                <button type="submit" style="background:none;border:none;cursor:pointer;color:var(--accent, #e8a656);font-size:inherit;padding:0;">
                    Отправить снова
                </button>
            </form>
        </p>

        <p class="link">
            <a href="{{ route('login') }}">← Вернуться ко входу</a>
        </p>
    </div>
</body>
</html>
