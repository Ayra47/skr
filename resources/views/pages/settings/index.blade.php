@php
    $profileSettingsPayload = [
        'accent_color' => $profileSettings->accent_color ?? '#5bbeff',
        'theme' => $profileSettings->theme ?? 'dark',
        'show_shared_chats' => $profileSettings->show_shared_chats ?? true,
        'show_shared_groups' => $profileSettings->show_shared_groups ?? true,
        'profile_access' => $profileSettings->profile_access ?? \App\Models\ProfileSetting::AUDIENCE_EVERYONE,
        'online_status_visibility' => $profileSettings->online_status_visibility ?? \App\Models\ProfileSetting::AUDIENCE_EVERYONE,
        'shared_friends_count_visibility' => $profileSettings->shared_friends_count_visibility ?? \App\Models\ProfileSetting::AUDIENCE_EVERYONE,
        'feed_posts_count_visibility' => $profileSettings->feed_posts_count_visibility ?? \App\Models\ProfileSetting::AUDIENCE_EVERYONE,
        'profile_posts_visibility' => $profileSettings->profile_posts_visibility ?? \App\Models\ProfileSetting::AUDIENCE_EVERYONE,
        'avatar_visibility' => $profileSettings->avatar_visibility ?? \App\Models\ProfileSetting::AUDIENCE_EVERYONE,
    ];
@endphp

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>skr — Настройки</title>
    @include('partials.accent-style')
    <script>
        window.Laravel = {
            userId: @json(Auth::id()),
            login: @json($user->login),
            pseudonym: @json($user->pseudonym),
            email: @json($user->email),
            emailVerified: @json((bool) $user->email_verified_at),
            pendingEmail: @json($user->pending_email),
            avatarUrl: @json($user->avatar ? '/storage/' . $user->avatar : null),
            profileSettings: @json($profileSettingsPayload),
            hasBackupCode: @json($hasBackupCode),
            twoFactorEnabled: @json((bool) $user->two_factor_enabled),
        };
    </script>
    @vite(['resources/js/pages/settings.js'])
</head>
<body>
    @include('components.nav')

    {{-- Confirm modal --}}
    <div class="s-modal-backdrop" id="confirmModal" style="display:none;" role="dialog" aria-modal="true">
        <div class="s-modal">
            <div class="s-modal-icon" id="confirmModalIcon"></div>
            <div class="s-modal-title" id="confirmModalTitle"></div>
            <div class="s-modal-body" id="confirmModalBody"></div>
            <div class="s-modal-actions">
                <button class="s-btn s-btn-ghost" id="confirmModalCancel">Отмена</button>
                <button class="s-btn" id="confirmModalOk">Подтвердить</button>
            </div>
        </div>
    </div>

    <div class="settings-root">
        <div class="settings-glow"></div>

        <div class="settings-inner">

            {{-- flash messages from email verification redirect --}}
            @if(session('success'))
                <div class="flash-msg flash-success">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="flash-msg flash-error">{{ session('error') }}</div>
            @endif

            <header class="settings-header">
                <div>
                    <h1>Настройки</h1>
                    <p class="settings-header-sub">Профиль, безопасность и предпочтения</p>
                </div>
            </header>

            <div class="settings-layout">
                {{-- sidebar nav --}}
                <nav class="settings-nav" id="settingsNav">
                    @foreach([
                        ['profile',   'Профиль',           '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/>'],
                        ['security',  'Безопасность',      '<path d="M12 3l8 3v6c0 5-3.5 8.5-8 9-4.5-.5-8-4-8-9V6l8-3z"/>'],
                        ['notif',     'Уведомления',       '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>'],
                        ['appearance','Внешний вид',       '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="6" r="1"/><circle cx="6" cy="12" r="1"/><circle cx="18" cy="12" r="1"/><circle cx="9" cy="17" r="1"/>'],
                        ['devices',   'Устройства',        '<rect x="2" y="4" width="20" height="14" rx="2"/><path d="M8 21h8M12 18v3"/>'],
                        ['sessions',  'Сессии · история',  '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l3 2"/>'],
                        ['premium',   'Премиум',           '<polygon points="12 2 15 9 22 9 17 14 19 21 12 17 5 21 7 14 2 9 9 9 12 2"/>'],
                        ['logout',    'Выйти',             '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>'],
                    ] as [$id, $label, $icon])
                    <button class="settings-nav-btn {{ $id === 'profile' ? 'active' : '' }}{{ $id === 'logout' ? ' settings-nav-btn-danger' : '' }}" data-section="{{ $id }}">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">{!! $icon !!}</svg>
                        {{ $label }}
                    </button>
                    @endforeach
                </nav>

                {{-- sections --}}
                <div class="settings-sections">

                    {{-- ── PROFILE ── --}}
                    <section class="settings-card" id="section-profile">
                        <div class="card-head">
                            <span class="card-icon">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg>
                            </span>
                            <h2>Профиль</h2>
                        </div>
                        <p class="card-sub">Фото, логин и контактные данные</p>

                        {{-- avatar --}}
                        <div class="profile-top">
                            <div class="avatar-wrap">
                                <div class="avatar-circle" id="avatarCircle">
                                    <img id="avatarImg" src="" alt="" style="display:none;width:100%;height:100%;object-fit:cover;border-radius:50%;">
                                    <span id="avatarInitial">{{ mb_strtoupper(mb_substr($user->login, 0, 1)) }}</span>
                                </div>
                                <button class="avatar-cam-btn" id="avatarUploadBtn" title="Загрузить фото">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                                </button>
                                <input type="file" id="avatarInput" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none;">
                            </div>
                            <div class="profile-meta">
                                <div class="profile-login" id="profileLoginDisplay">{{ $user->login }}</div>
                                <div class="profile-login-hint">Можно изменить ниже</div>
                                <div class="profile-avatar-btns">
                                    <button class="s-btn" id="avatarUploadBtn2">Загрузить</button>
                                    <button class="s-btn s-btn-ghost" id="avatarDeleteBtn">Удалить</button>
                                </div>
                            </div>
                        </div>

                        <div id="profileMsg" class="form-msg" style="display:none;"></div>

                        {{-- login + pseudonym --}}
                        <div class="fields-grid" style="margin-bottom:14px;">
                            <div class="field-wrap">
                                <label class="field-label">Логин</label>
                                <input type="text" class="field-input" id="fieldLogin"
                                    value="{{ $user->login }}" maxlength="255" autocomplete="username">
                            </div>
                            <div class="field-wrap">
                                <label class="field-label">Псевдоним для ленты
                                    <span class="tag-anon" style="margin-left:6px;">анонимно</span>
                                </label>
                                <div class="pseudo-row" style="gap:8px;">
                                    <input type="text" class="field-input field-mono" id="fieldPseudo"
                                        value="{{ $user->pseudonym ?? '' }}" maxlength="50">
                                    <button class="s-btn" id="pseudoGenBtn" title="Сгенерировать">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- bio --}}
                        <div class="field-wrap" style="margin-bottom:14px;">
                            <label class="field-label">О себе</label>
                            <textarea class="field-input field-bio" id="fieldBio" maxlength="255" rows="3" placeholder="Расскажите о себе...">{{ $profileSettings->bio ?? '' }}</textarea>
                            <div class="field-hint"><span id="bioCharCount">{{ mb_strlen($profileSettings->bio ?? '') }}</span>/255</div>
                        </div>

                        {{-- email --}}
                        <div class="field-wrap" style="margin-bottom:14px;">
                            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                                <label class="field-label" style="margin-bottom:0;">Email</label>
                                <button class="s-btn s-btn-ghost" id="emailDetachBtn"
                                    style="{{ $user->email ? '' : 'display:none;' }}font-size:11px;padding:3px 8px;color:rgba(224,85,85,.7);border-color:rgba(220,60,60,.2);">
                                    Отвязать
                                </button>
                            </div>
                            <input type="email" class="field-input" id="fieldEmail"
                                placeholder="you@example.com"
                                value="{{ $user->pending_email ?? $user->email ?? '' }}">
                            {{-- confirmation status --}}
                            @if($user->pending_email)
                                <div class="email-status email-status-pending" id="emailStatusBox">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                    Ожидает подтверждения · {{ $user->pending_email }}
                                    <button class="email-resend-btn" id="emailResendBtn">Отправить снова</button>
                                </div>
                            @elseif($user->email && $user->email_verified_at)
                                <div class="email-status email-status-ok" id="emailStatusBox">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-11"/></svg>
                                    Подтверждён
                                </div>
                            @elseif($user->email)
                                <div class="email-status email-status-pending" id="emailStatusBox">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                    Не подтверждён · не работает
                                </div>
                            @else
                                <div class="email-status" id="emailStatusBox" style="display:none;"></div>
                            @endif
                        </div>

                        <div class="card-actions">
                            <button class="s-btn s-btn-ghost" id="profileCancelBtn">Отмена</button>
                            <button class="s-btn s-btn-primary" id="profileSaveBtn">Сохранить</button>
                        </div>

                        <div class="s-divider profile-visibility-divider"></div>

                        <div class="profile-visibility-head">
                            <h3>Видимость профиля</h3>
                            <div id="profileVisibilityMsg" class="form-msg" style="display:none;"></div>
                        </div>

                        <div class="s-row">
                            <div class="s-row-info">
                                <div class="s-row-label">Показывать общие чаты</div>
                                <div class="s-row-sub">Отображать групповые чаты, в которых состоите вы оба</div>
                            </div>
                            <button class="s-toggle" id="showSharedChatsToggle" aria-label="Показывать общие чаты">
                                <span class="s-toggle-thumb"></span>
                            </button>
                        </div>

                        <div class="s-row">
                            <div class="s-row-info">
                                <div class="s-row-label">Показывать общие группы <span class="soon-tag">в будущем</span></div>
                                <div class="s-row-sub">Настройка уже сохранится, сами группы появятся позже</div>
                            </div>
                            <button class="s-toggle" id="showSharedGroupsToggle" aria-label="Показывать общие группы">
                                <span class="s-toggle-thumb"></span>
                            </button>
                        </div>

                        @foreach([
                            ['profileAccess', 'profile_access', 'Кто может открыть мой профиль?', ['Никто', 'Друзья', 'Все']],
                            ['onlineStatusVisibility', 'online_status_visibility', 'Кто видит, что я в сети?', ['Никто', 'Друзья', 'Все']],
                            ['sharedFriendsCountVisibility', 'shared_friends_count_visibility', 'Показывать ли кол-во общих друзей?', ['Никому', 'Друзьям', 'Всем']],
                            ['feedPostsCountVisibility', 'feed_posts_count_visibility', 'Показывать ли кол-во постов в ленте?', ['Никому', 'Друзьям', 'Всем']],
                            ['profilePostsVisibility', 'profile_posts_visibility', 'Показывать ли посты в профиле?', ['Никому', 'Друзьям', 'Всем']],
                            ['avatarVisibility', 'avatar_visibility', 'Показывать ли аватар?', ['Никому', 'Друзьям', 'Всем']],
                        ] as [$id, $field, $label, $choices])
                            <div class="s-row profile-visibility-row">
                                <div class="s-row-info">
                                    <div class="s-row-label">{{ $label }}</div>
                                </div>
                                <div class="profile-visibility-toggle" data-profile-visibility-field="{{ $field }}" id="{{ $id }}">
                                    <button type="button" data-value="none">{{ $choices[0] }}</button>
                                    <button type="button" data-value="friends">{{ $choices[1] }}</button>
                                    <button type="button" data-value="everyone">{{ $choices[2] }}</button>
                                </div>
                            </div>
                        @endforeach
                    </section>

                    {{-- ── SECURITY ── --}}
                    <section class="settings-card" id="section-security">
                        <div class="card-head">
                            <span class="card-icon">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l8 3v6c0 5-3.5 8.5-8 9-4.5-.5-8-4-8-9V6l8-3z"/></svg>
                            </span>
                            <h2>Безопасность</h2>
                        </div>
                        <p class="card-sub">Пароль, двухфакторная аутентификация и резервный код</p>

                        <div class="s-row">
                            <div class="s-row-info">
                                <div class="s-row-label">Пароль</div>
                                <div class="s-row-sub">Измените пароль учётной записи</div>
                            </div>
                            <button class="s-btn s-btn-icon" id="passwordRowBtn">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="11" rx="2.5"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                                Сменить пароль
                            </button>
                        </div>
                        <div class="password-form" id="passwordForm" style="display:none;">
                            <div id="passwordMsg" class="form-msg" style="display:none;"></div>
                            <div class="fields-grid fields-grid-3">
                                <div class="field-wrap">
                                    <label class="field-label">Текущий пароль</label>
                                    <input type="password" class="field-input" id="fieldCurrentPwd" placeholder="••••••••">
                                </div>
                                <div class="field-wrap">
                                    <label class="field-label">Новый пароль</label>
                                    <input type="password" class="field-input" id="fieldNewPwd" placeholder="••••••••">
                                </div>
                                <div class="field-wrap">
                                    <label class="field-label">Подтверждение</label>
                                    <input type="password" class="field-input" id="fieldConfirmPwd" placeholder="••••••••">
                                </div>
                            </div>
                            <div class="card-actions" style="margin-top:12px;">
                                <button class="s-btn s-btn-ghost" id="passwordCancelBtn">Отмена</button>
                                <button class="s-btn s-btn-primary" id="passwordSaveBtn">Сохранить пароль</button>
                            </div>
                        </div>

                        <div class="s-divider"></div>

                        <div class="s-row" id="twoFactorRow">
                            <div class="s-row-info">
                                <div class="s-row-label">Двухфакторная аутентификация</div>
                                <div class="s-row-sub" id="twoFactorSub">
                                    @if($user->two_factor_enabled)
                                        Включена — код на email при каждом входе
                                    @elseif($user->email)
                                        Выключена — для включения нужна подтверждённая почта
                                    @else
                                        Недоступна — привяжите и подтвердите email
                                    @endif
                                </div>
                            </div>
                            <button class="s-toggle {{ $user->two_factor_enabled ? 'active' : '' }}"
                                id="twoFactorToggle"
                                {{ ! $user->email || ! $user->email_verified_at ? 'disabled' : '' }}
                                aria-label="2FA">
                                <span class="s-toggle-thumb"></span>
                            </button>
                        </div>

                        <div class="s-divider"></div>

                        <div class="s-row" style="align-items:flex-start;">
                            <div class="s-row-info">
                                <div class="s-row-label">Резервный код</div>
                                <div class="s-row-sub">Используется для входа без пароля и восстановления ключа шифрования. Показывается один раз — сохраните в надёжном месте.</div>
                            </div>
                            <button class="s-btn s-btn-icon" id="backupCodeShowBtn">
                                {{ $hasBackupCode ? 'Показать / обновить' : 'Создать код' }}
                            </button>
                        </div>
                        <div class="backup-code-box" id="backupCodeBox" style="display:none;">
                            <div id="backupCodeMsg" class="form-msg" style="display:none;"></div>
                            <div class="backup-code-label">Ваш код восстановления</div>
                            <div class="backup-code-value" id="backupCodeValue"></div>
                            <div class="backup-code-hint">Это ваш приватный ключ шифрования в формате base64. Никому не передавайте.</div>
                            <div style="display:flex;gap:8px;margin-top:12px;">
                                <button class="s-btn" id="backupCodeCopyBtn">Скопировать</button>
                                <button class="s-btn s-btn-ghost" id="backupCodeCloseBtn">Скрыть</button>
                            </div>
                        </div>

                        <div class="s-divider"></div>

                        <div class="chat-security-panel settings-chat-security-panel">
                            <div class="settings-row">
                                <span>хранение:</span>
                                <select id="storageSelect">
                                    <option value="server">сервер (3 мес.)</option>
                                    <option value="browser">браузер</option>
                                    <option value="device">устройство</option>
                                </select>
                            </div>
                            <div id="deviceExportRow" style="display:none;" class="device-export-row">
                                <button class="export-btn" id="exportHistoryBtn">↓ экспорт</button>
                                <input type="file" id="importFileInput" accept=".enc" style="display:none">
                                <button class="export-btn" id="importTriggerBtn">↑ импорт</button>
                            </div>
                            <div class="key-fingerprint" id="keyFingerprint"></div>
                            <div id="chatSecurityMsg" class="form-msg" style="display:none;"></div>
                            <button class="export-btn" id="setupBackupBtn" style="margin-top:6px;width:100%;">бэкап ключа (PIN)</button>
                        </div>
                    </section>

                    {{-- ── NOTIFICATIONS ── --}}
                    <section class="settings-card" id="section-notif">
                        <div class="card-head">
                            <span class="card-icon">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
                            </span>
                            <h2>Уведомления</h2>
                        </div>
                        <p class="card-sub">Где и как получать оповещения о новых сообщениях</p>

                        {{-- Push --}}
                        <div class="s-row">
                            <div class="s-row-info">
                                <div class="s-row-label">Push-уведомления</div>
                                <div class="s-row-sub" id="notifPushSub">Уведомления браузера о новых сообщениях</div>
                            </div>
                            <button class="s-toggle" id="notifPushToggle" aria-label="Push-уведомления">
                                <span class="s-toggle-thumb"></span>
                            </button>
                        </div>
                        <div class="s-sub-option" id="notifPushTextOption" style="display:none;">
                            <label class="s-checkbox-row">
                                <input type="checkbox" id="notifPushText" class="s-checkbox">
                                <span>
                                    <span class="s-checkbox-label">Включать текст сообщения в push</span>
                                    <span class="s-checkbox-sub">Если выключено — будет видно только «новое сообщение»</span>
                                </span>
                            </label>
                        </div>

                        <div class="s-divider"></div>

                        {{-- Email --}}
                        <div class="s-row">
                            <div class="s-row-info">
                                <div class="s-row-label">Email-уведомления</div>
                                <div class="s-row-sub" id="notifEmailSub">
                                    @if($user->email && $user->email_verified_at)
                                        Когда вас нет в сети дольше 5 минут
                                    @else
                                        Недоступно — привяжите и подтвердите email
                                    @endif
                                </div>
                            </div>
                            <button class="s-toggle" id="notifEmailToggle"
                                {{ ! $user->email || ! $user->email_verified_at ? 'disabled' : '' }}
                                aria-label="Email-уведомления">
                                <span class="s-toggle-thumb"></span>
                            </button>
                        </div>
                        <div class="s-sub-option" id="notifEmailTextOption" style="display:none;">
                            <label class="s-checkbox-row">
                                <input type="checkbox" id="notifEmailText" class="s-checkbox">
                                <span>
                                    <span class="s-checkbox-label">Включать имя отправителя в письмо</span>
                                    <span class="s-checkbox-sub">Не рекомендуем — почта обычно не зашифрована</span>
                                </span>
                            </label>
                        </div>

                        <div class="s-divider"></div>

                        {{-- Sound --}}
                        <div class="s-row last">
                            <div class="s-row-info">
                                <div class="s-row-label">Звук в приложении</div>
                                <div class="s-row-sub">Тихий пинг при новом сообщении</div>
                            </div>
                            <button class="s-toggle" id="notifSoundToggle" aria-label="Звук в приложении">
                                <span class="s-toggle-thumb"></span>
                            </button>
                        </div>
                    </section>

                    {{-- ── APPEARANCE ── --}}
                    <section class="settings-card" id="section-appearance">
                        <div class="card-head">
                            <span class="card-icon">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="6" r="1"/><circle cx="6" cy="12" r="1"/><circle cx="18" cy="12" r="1"/><circle cx="9" cy="17" r="1"/></svg>
                            </span>
                            <h2>Внешний вид</h2>
                        </div>
                        <p class="card-sub">Тема оформления интерфейса</p>
                        <div class="theme-grid">
                            <button class="theme-option" data-theme-value="dark">
                                <div class="theme-preview theme-preview-dark"></div>
                                <div class="theme-option-footer">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
                                    <span>Тёмная</span>
                                    <svg class="theme-check" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-11"/></svg>
                                </div>
                            </button>
                            <button class="theme-option" data-theme-value="light">
                                <div class="theme-preview theme-preview-light"></div>
                                <div class="theme-option-footer">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                                    <span>Светлая</span>
                                    <svg class="theme-check" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-11"/></svg>
                                </div>
                            </button>
                            <button class="theme-option" disabled>
                                <div class="theme-preview theme-preview-auto"></div>
                                <div class="theme-option-footer">
                                    <span style="font-family:ui-monospace,monospace;font-size:11px;">auto</span>
                                    <span>Системная</span>
                                    <span class="soon-tag">скоро</span>
                                </div>
                            </button>
                        </div>

                        <div class="accent-picker">
                            <p class="card-sub" style="margin-top:18px;">Основной цвет</p>
                            <div class="accent-swatches" id="accentSwatches">
                                <button class="accent-swatch" data-color="#5bbeff" title="Синий" style="background:#5bbeff;"></button>
                                <button class="accent-swatch" data-color="#a78bfa" title="Фиолетовый" style="background:#a78bfa;"></button>
                                <button class="accent-swatch" data-color="#6dd49a" title="Зелёный" style="background:#6dd49a;"></button>
                                <button class="accent-swatch" data-color="#e8a656" title="Золотой" style="background:#e8a656;"></button>
                                <button class="accent-swatch" data-color="#f19ca2" title="Розовый" style="background:#f19ca2;"></button>
                                <button class="accent-swatch" data-color="#e87b6d" title="Красный" style="background:#e87b6d;"></button>
                                <label class="accent-swatch accent-swatch-custom" title="Свой цвет">
                                    <input type="color" id="accentColorCustom" style="opacity:0;width:0;height:0;position:absolute;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
                                </label>
                            </div>
                            <div class="form-msg" id="accentMsg"></div>
                        </div>
                    </section>

                    {{-- ── DEVICES ── --}}
                    <section class="settings-card" id="section-devices">
                        <div class="card-head">
                            <span class="card-icon">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="14" rx="2"/><path d="M8 21h8M12 18v3"/></svg>
                            </span>
                            <h2>Подключённые устройства</h2>
                        </div>
                        <p class="card-sub">Где открыта ваша сессия прямо сейчас</p>
                        <div class="paginate-list" style="display:flex;flex-direction:column;gap:8px;" data-paginate data-page-init="3" data-page-step="10">
                            @foreach($sessions as $session)
                            <div class="device-item paginate-item">
                                <span class="device-icon">
                                    @if($session->device_type === 'mobile')
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="2" width="10" height="20" rx="2"/><circle cx="12" cy="18" r="1" fill="currentColor" stroke="none"/></svg>
                                    @elseif($session->device_type === 'tablet')
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2"/><circle cx="12" cy="18" r="1" fill="currentColor" stroke="none"/></svg>
                                    @else
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M2 20h20"/></svg>
                                    @endif
                                </span>
                                <div style="flex:1;min-width:0;">
                                    <div class="s-row-label">{{ $session->browser }} · {{ $session->platform }}</div>
                                    <div class="s-row-sub" style="font-family:ui-monospace,monospace;">{{ $session->ip_address }} · последняя активность: {{ $session->last_activity->diffForHumans() }}</div>
                                </div>
                                @if($session->is_current)
                                    <span class="tag-current">это устройство</span>
                                @endif
                            </div>
                            @endforeach
                        </div>
                        <p class="card-sub" style="margin-top:16px;">Если видите незнакомое устройство — немедленно смените пароль.</p>
                    </section>

                    {{-- ── SESSIONS ── --}}
                    <section class="settings-card" id="section-sessions">
                        <div class="card-head">
                            <span class="card-icon">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l3 2"/></svg>
                            </span>
                            <h2>Активные сессии и история</h2>
                        </div>
                        <div class="section-label-sm">Активные сессии</div>
                        <div class="paginate-list" style="display:flex;flex-direction:column;gap:8px;" data-paginate data-page-init="3" data-page-step="10">
                            @foreach($sessions as $session)
                            <div class="session-item paginate-item">
                                <span class="session-dot {{ $session->is_online ? '' : 'session-dot--offline' }}"></span>
                                <div style="flex:1;min-width:0;">
                                    <div class="s-row-label">{{ $session->browser }} · {{ $session->platform }}</div>
                                    <div class="s-row-sub" style="font-family:ui-monospace,monospace;">{{ $session->ip_address }}</div>
                                </div>
                                <span class="s-row-sub">{{ $session->last_activity->diffForHumans() }}</span>
                            </div>
                            @endforeach
                        </div>
                        <div class="section-label-sm" style="margin-top:20px;">История</div>
                        @if($loginHistory->isEmpty())
                            <p class="card-sub">Нет записей</p>
                        @else
                        <div class="history-table" data-paginate data-page-init="6" data-page-step="15">
                            @foreach($loginHistory as $entry)
                            <div class="history-row paginate-item">
                                @if($entry->event === 'login_success')
                                    <span class="history-dot ok"></span>
                                    <span class="history-event">успешный вход</span>
                                @elseif($entry->event === 'login_fail')
                                    <span class="history-dot fail"></span>
                                    <span class="history-event">неуспешный вход</span>
                                @else
                                    <span class="history-dot key"></span>
                                    <span class="history-event">смена ключа</span>
                                @endif
                                <span class="history-meta">{{ $entry->browser }} · {{ $entry->ip_address }}</span>
                                <span class="history-time">{{ $entry->created_at->format('d M H:i') }}</span>
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </section>

                    {{-- ── PREMIUM (soon) ── --}}
                    <section class="settings-card" id="section-premium">
                        <div class="card-head">
                            <span class="card-icon">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15 9 22 9 17 14 19 21 12 17 5 21 7 14 2 9 9 9 12 2"/></svg>
                            </span>
                            <h2>Премиум</h2>
                        </div>
                        <p class="card-sub">Расширенные возможности — скоро</p>
                        <div class="premium-box">
                            <div class="premium-soon-badge">скоро</div>
                            <div class="premium-head">
                                <span class="premium-star-icon">
                                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15 9 22 9 17 14 19 21 12 17 5 21 7 14 2 9 9 9 12 2"/></svg>
                                </span>
                                <div>
                                    <div class="premium-title">skr Premium</div>
                                    <div class="premium-price">489 ₽/мес · отмена в любой момент</div>
                                </div>
                            </div>
                            <ul class="premium-features">
                                @foreach(['Неограниченная история', 'Файлы до 4 ГБ', 'Авторазблокировка по биометрии', 'Расшифровка голосовых', 'Темы и стикеры', 'Приоритетная поддержка'] as $f)
                                <li>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-11"/></svg>
                                    {{ $f }}
                                </li>
                                @endforeach
                            </ul>
                            <button disabled class="s-btn s-btn-disabled">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="11" rx="2.5"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                                Подписка временно недоступна
                            </button>
                        </div>
                    </section>

                    {{-- ── LOGOUT ── --}}
                    <section class="settings-card" id="section-logout" style="border-color:rgba(220,60,60,.18);">
                        <div class="card-head">
                            <span class="card-icon" style="color:#e05555;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            </span>
                            <h2 style="color:#e05555;">Выйти</h2>
                        </div>
                        <p class="card-sub">Завершить текущую сессию на этом устройстве</p>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="s-btn" style="background:rgba(220,60,60,.12);color:#e05555;border-color:rgba(220,60,60,.25);">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                                Выйти из аккаунта
                            </button>
                        </form>
                    </section>

                </div>{{-- /settings-sections --}}
            </div>{{-- /settings-layout --}}
        </div>{{-- /settings-inner --}}
    </div>{{-- /settings-root --}}

    <!-- PIN dialog -->
    <div id="pinDialog" class="modal-overlay" style="display:none">
        <div class="modal-box">
            <div class="modal-title pin-dialog-title"></div>
            <div class="modal-subtitle pin-dialog-subtitle"></div>
            <input type="text" class="pin-input modal-input" maxlength="6" minlength="6"
                placeholder="● ● ● ● ● ●" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
            <div class="pin-dialog-error modal-error"></div>
            <div class="modal-actions">
                <button class="modal-btn-secondary pin-dialog-cancel">отмена</button>
                <button class="modal-btn-primary pin-dialog-confirm">подтвердить</button>
            </div>
        </div>
    </div>

    <!-- Recovery phrase display modal -->
    <div id="recoveryPhraseModal" class="modal-overlay" style="display:none">
        <div class="modal-box">
            <div class="modal-title">фраза восстановления</div>
            <div class="modal-subtitle">сохраните в надёжном месте. если забудете PIN — используйте эту фразу для восстановления ключа.</div>
            <div class="recovery-phrase-text modal-phrase"></div>
            <div class="modal-actions">
                <button class="modal-btn-primary" id="recoveryPhraseDoneBtn">понятно, сохранил</button>
            </div>
        </div>
    </div>
</body>
</html>
