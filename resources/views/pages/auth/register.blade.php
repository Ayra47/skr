<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>skr — Регистрация</title>
    @vite(['resources/css/pages/register.scss'])
</head>
<body>
    <div class="glow glow-amber"></div>
    <div class="glow glow-blue"></div>
    <div class="container">
        <div class="brand">
            <div class="brand-mark">s</div>
            <div class="brand-text">skr</div>
        </div>

        <h1>Создать аккаунт</h1>
        <p class="subtitle">Минута — и вы внутри.</p>

        @if ($errors->any())
            <div class="error">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('register') }}">
            @csrf
            <div class="form-group">
                <input type="text" id="login" name="login" value="{{ old('login') }}" placeholder="Логин" required autofocus>
            </div>

            <div class="form-group">
                <div class="pseudonym-row">
                    <input
                        type="text"
                        id="pseudonym"
                        name="pseudonym"
                        value="{{ old('pseudonym', $generatedPseudonym ?? '') }}"
                        placeholder="Псевдоним для ленты"
                        maxlength="50"
                        pattern="[a-z0-9-]+"
                    >
                    <button type="button" class="pseudo-refresh" onclick="regeneratePseudonym()" aria-label="Сгенерировать псевдоним">↻</button>
                </div>
                <div class="hint">Под этим именем вас видят в ленте. Позже можно изменить в настройках.</div>
            </div>

            <div class="form-group">
                <input type="password" id="password" name="password" placeholder="Пароль" required>
            </div>

            <div class="form-group">
                <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Повторите пароль" required>
            </div>

            <button type="submit" class="btn">Создать аккаунт</button>
        </form>

        <p class="link">
            Уже есть аккаунт? <a href="{{ route('login') }}">Войти</a>
        </p>
    </div>
    <script>
        const pseudoFirstParts = ['crow', 'north', 'silver', 'amber', 'lonely', 'pale', 'red', 'iron', 'velvet', 'quiet', 'dark', 'still', 'wild', 'tall', 'soft'];
        const pseudoSecondParts = ['fox', 'wind', 'fern', 'orbit', 'echo', 'tide', 'moss', 'ash', 'kite', 'spire', 'hawk', 'dune', 'pine', 'cliff', 'flare'];

        function randomFrom(list) {
            return list[Math.floor(Math.random() * list.length)];
        }

        function regeneratePseudonym() {
            const generated = `${randomFrom(pseudoFirstParts)}-${randomFrom(pseudoSecondParts)}-${Math.floor(100 + Math.random() * 900)}`;
            const input = document.getElementById('pseudonym');
            input.value = generated;
            input.focus();
            input.setSelectionRange(generated.length, generated.length);
        }
    </script>
</body>
</html>
