<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>skr — Восстановление доступа</title>
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

        <h1>Восстановление доступа</h1>

        @if (session('error'))
            <div class="error">{{ session('error') }}</div>
        @endif

        {{-- Tab switcher --}}
        <div class="tab-row" id="tabRow">
            <button class="tab-btn active" data-tab="email">По email</button>
            <button class="tab-btn" data-tab="code">Код восстановления</button>
        </div>

        {{-- Tab: Email --}}
        <div id="tab-email">
            @if (session('sent'))
                <div class="error" style="background:rgba(109,212,154,.1);border-color:rgba(109,212,154,.25);color:#9ce4bd;">
                    Если аккаунт с таким логином существует и к нему привязана почта — письмо отправлено.
                </div>
            @else
                <p class="subtitle" style="margin-bottom:16px;">Введите логин — мы пришлём ссылку для сброса пароля.</p>

                <div class="warning-box">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.3L2 21h20L13.7 3.3a2 2 0 0 0-3.4 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <span>Сброс пароля не восстановит переписку — для этого нужен <strong>PIN-код защиты ключа</strong> (вводился при первом входе с нового устройства) или <strong>код восстановления</strong> из настроек безопасности.</span>
                </div>

                <form method="POST" action="{{ route('forgot-password.send') }}">
                    @csrf
                    <div class="form-group">
                        <input type="text" name="login" value="{{ old('login', session('login')) }}"
                            placeholder="Логин" required autofocus>
                    </div>
                    <button type="submit" class="btn">Отправить ссылку</button>
                </form>
            @endif
        </div>

        {{-- Tab: Recovery code --}}
        <div id="tab-code" style="display:none;">
            <p class="subtitle" style="margin-bottom:16px;">Введите логин и код восстановления — вы сразу войдёте в аккаунт и сможете сменить пароль в настройках.</p>

            <div class="warning-box">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <span>Код восстановления — это длинная строка вида <code style="font-family:ui-monospace,monospace;font-size:11px;">eyJjcnYi...</code>, которая была показана в настройках → Безопасность → Резервный код.</span>
            </div>

            @if ($errors->has('backup_code'))
                <div class="error">{{ $errors->first('backup_code') }}</div>
            @endif

            <form method="POST" action="{{ route('login.backup') }}">
                @csrf
                <div class="form-group">
                    <input type="text" name="login" value="{{ old('login') }}" placeholder="Логин" required>
                </div>
                <div class="form-group">
                    <textarea name="backup_code" placeholder="Код восстановления (eyJjcnYi...)" rows="3" required
                        style="resize:vertical;font-family:ui-monospace,'SF Mono',monospace;font-size:11px;"></textarea>
                </div>
                <button type="submit" class="btn">Войти с кодом</button>
            </form>
        </div>

        <p class="link"><a href="{{ route('login') }}">← Вернуться ко входу</a></p>
    </div>

    <script>
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                document.getElementById('tab-email').style.display = btn.dataset.tab === 'email' ? '' : 'none';
                document.getElementById('tab-code').style.display  = btn.dataset.tab === 'code'  ? '' : 'none';
            });
        });
    </script>
</body>
</html>
