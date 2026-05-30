<div id="createCommunityModal" class="modal-overlay" role="dialog" aria-modal="true" aria-labelledby="cmWizTitle">
    <div class="modal-box modal-box--wide cm-wiz">

        {{-- Header --}}
        <div class="cm-wiz-header">
            <div class="cm-wiz-header-text">
                <span class="cm-wiz-step-label">Новое сообщество · <span id="cmWizStepNum">шаг 1 из 3</span></span>
                <h2 class="cm-wiz-title" id="cmWizTitle">Идентичность</h2>
            </div>
            <button type="button" class="modal-close" data-cm-close-create aria-label="Закрыть">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 6L6 18M6 6l12 12" />
                </svg>
            </button>
        </div>

        {{-- Progress bar --}}
        <div class="cm-wiz-progress">
            <div class="cm-wiz-bar is-active" data-bar="0"></div>
            <div class="cm-wiz-bar" data-bar="1"></div>
            <div class="cm-wiz-bar" data-bar="2"></div>
        </div>

        {{-- Step 0: Идентичность --}}
        <div class="cm-wiz-pane is-active" data-pane="0">
            <div class="cm-wiz-identity-stack">
                <div class="cm-wiz-field">
                    <label class="cm-wiz-label" for="cmWizName">Название</label>
                    <input id="cmWizName" class="cm-wiz-input" type="text" placeholder="например, design · skr" maxlength="100" autocomplete="off">
                </div>

                <div class="cm-wiz-field">
                    <label class="cm-wiz-label" for="cmWizDesc">Описание</label>
                    <textarea id="cmWizDesc" class="cm-wiz-textarea" rows="2" placeholder="о чём оно — одной строкой" maxlength="140"></textarea>
                    <div class="cm-wiz-char-count"><span id="cmWizDescCount">0</span>/140</div>
                    <div class="cm-wiz-hint">Видно только участникам сообщества</div>
                </div>

                <div class="cm-wiz-field">
                    <div class="cm-wiz-label">Иконка сообщества</div>
                    <div class="cm-wiz-icon-mode" aria-label="Тип иконки сообщества">
                        <span class="cm-wiz-icon-mode-btn is-active">Символ</span>
                        <span class="cm-wiz-icon-mode-btn is-muted">Фото или SVG</span>
                    </div>
                    <div class="cm-wiz-glyphs" id="cmWizGlyphs">
                        @foreach(['◐','◇','☾','✦','✸','◈','❋','✱','✣','◉','◆','✺'] as $g)
                            <button type="button" class="cm-wiz-glyph @if($loop->first) is-active @endif" data-glyph="{{ $g }}">{{ $g }}</button>
                        @endforeach
                    </div>
                </div>

                <div class="cm-wiz-field">
                    <div class="cm-wiz-label">Оттенок</div>
                    <div class="cm-wiz-tints" id="cmWizTints">
                        @foreach([30, 90, 150, 200, 260, 320] as $t)
                            <button type="button" class="cm-wiz-tint-btn @if($loop->first) is-active @endif"
                                data-tint="{{ $t }}"
                                style="background: linear-gradient(135deg, oklch(0.32 0.07 {{ $t }}), oklch(0.18 0.05 {{ ($t + 60) % 360 }}));">
                                @if($loop->first)
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L20 6"/></svg>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="cm-wiz-preview-section">
                    <div class="cm-wiz-label">Предпросмотр</div>
                    <div class="cm-wiz-card-preview">
                        <span class="cm-wiz-avatar-preview" id="cmWizAvatarPreview"
                            style="background: linear-gradient(135deg, oklch(0.32 0.07 30), oklch(0.18 0.05 90));">◐</span>
                        <div class="cm-wiz-card-preview-body">
                            <div class="cm-wiz-card-preview-head">
                                <span class="cm-wiz-name-preview" id="cmWizNamePreview">без названия</span>
                                <span class="cm-wiz-preview-badge">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="11" rx="2.5"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                                    приватное
                                </span>
                            </div>
                            <div class="cm-wiz-preview-desc" id="cmWizDescPreview">описание появится здесь</div>
                            <div class="cm-wiz-preview-meta">1 участник · ваша роль: админ</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="cm-wiz-error" id="cmWizError0" hidden></div>

            <div class="cm-wiz-footer">
                <div class="cm-wiz-footer-note">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="11" rx="2.5"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                    Ключи генерируются на устройстве
                </div>
                <div class="cm-wiz-footer-actions">
                    <button type="button" class="modal-btn-secondary" data-cm-close-create>Отмена</button>
                    <button type="button" class="modal-btn-primary cm-wiz-next-btn" data-cm-wiz-next disabled>
                        Дальше
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- Step 1: Приватность --}}
        <div class="cm-wiz-pane" data-pane="1">
            <div class="cm-wiz-privacy-stack">
                <div class="cm-wiz-field">
                    <div class="cm-wiz-label">Видимость</div>
                    <div class="cm-wiz-radio-cards cm-wiz-radio-cards--2" id="cmWizVisibility">
                        <button type="button" class="cm-wiz-radio-card is-active" data-value="private">
                            <div class="cm-wiz-radio-row">
                                <span class="cm-wiz-radio-dot is-active"></span>
                                <span class="cm-wiz-radio-title">Приватное</span>
                            </div>
                            <div class="cm-wiz-radio-hint">Вход только по одобрению админа или по коду</div>
                        </button>
                        <button type="button" class="cm-wiz-radio-card" data-value="public">
                            <div class="cm-wiz-radio-row">
                                <span class="cm-wiz-radio-dot"></span>
                                <span class="cm-wiz-radio-title">Публичное</span>
                            </div>
                            <div class="cm-wiz-radio-hint">Любой может найти и вступить</div>
                        </button>
                    </div>
                </div>

                <div class="cm-wiz-field">
                    <div class="cm-wiz-label">Кто может приглашать</div>
                    <div class="cm-wiz-radio-cards cm-wiz-radio-cards--2" id="cmWizInvitePolicy">
                        <button type="button" class="cm-wiz-radio-card" data-value="all_members">
                            <div class="cm-wiz-radio-row">
                                <span class="cm-wiz-radio-dot"></span>
                                <span class="cm-wiz-radio-title">Все участники</span>
                            </div>
                            <div class="cm-wiz-radio-hint">Любой может выпустить код</div>
                        </button>
                        <button type="button" class="cm-wiz-radio-card is-active" data-value="moderators_only">
                            <div class="cm-wiz-radio-row">
                                <span class="cm-wiz-radio-dot is-active"></span>
                                <span class="cm-wiz-radio-title">Только модераторы</span>
                            </div>
                            <div class="cm-wiz-radio-hint">Контроль за ростом сообщества</div>
                        </button>
                    </div>
                </div>

                <div class="cm-wiz-field">
                    <div class="cm-wiz-label">Кто может публиковать</div>
                    <div class="cm-wiz-radio-cards cm-wiz-radio-cards--2" id="cmWizPostingPolicy">
                        <button type="button" class="cm-wiz-radio-card is-active" data-value="everyone">
                            <div class="cm-wiz-radio-row">
                                <span class="cm-wiz-radio-dot is-active"></span>
                                <span class="cm-wiz-radio-title">Все</span>
                            </div>
                            <div class="cm-wiz-radio-hint">Свободное сообщество</div>
                        </button>
                        <button type="button" class="cm-wiz-radio-card" data-value="moderators_only">
                            <div class="cm-wiz-radio-row">
                                <span class="cm-wiz-radio-dot"></span>
                                <span class="cm-wiz-radio-title">Только модераторы</span>
                            </div>
                            <div class="cm-wiz-radio-hint">Канальный формат</div>
                        </button>
                    </div>
                </div>

                <div class="cm-wiz-field">
                    <div class="cm-wiz-label">Срок жизни постов по умолчанию</div>
                    <div class="cm-wiz-btn-group cm-wiz-btn-group--grid" id="cmWizTtl">
                        <button type="button" class="cm-wiz-group-btn" data-value="3600">1 час</button>
                        <button type="button" class="cm-wiz-group-btn is-active" data-value="86400">24 часа</button>
                        <button type="button" class="cm-wiz-group-btn" data-value="604800">7 дней</button>
                        <button type="button" class="cm-wiz-group-btn" data-value="">Навсегда</button>
                    </div>
                    <div class="cm-wiz-hint">Авторы смогут переопределить для каждого поста</div>
                </div>

                <div class="cm-wiz-field">
                    <div class="cm-wiz-label">Лимит участников · <span id="cmWizLimitValue">50</span></div>
                    <input id="cmWizLimitRange" class="cm-wiz-range" type="range" min="5" max="250" step="5" value="50">
                    <div class="cm-wiz-range-labels" aria-hidden="true">
                        <span>5</span>
                        <span>50</span>
                        <span>100</span>
                        <span>250</span>
                    </div>
                    <div class="cm-wiz-hint">Маленькие сообщества безопаснее</div>
                </div>
            </div>

            <div class="cm-wiz-error" id="cmWizError1" hidden></div>

            <div class="cm-wiz-footer">
                <div class="cm-wiz-footer-note">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="11" rx="2.5"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                    Ключи генерируются на устройстве
                </div>
                <div class="cm-wiz-footer-actions">
                    <button type="button" class="modal-btn-secondary" data-cm-wiz-back>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="M11 18l-6-6 6-6"/></svg>
                        Назад
                    </button>
                    <button type="button" class="modal-btn-primary" data-cm-wiz-create>
                        Создать
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- Step 2: Готово --}}
        <div class="cm-wiz-pane" data-pane="2">

            <div class="cm-wiz-success-banner">
                <span class="cm-wiz-success-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21l5-14 6 8L3 21z"/><path d="M14 6l2-2"/><path d="M17 9l3-1"/><path d="M18 14l3 1"/></svg>
                </span>
                <div>
                    <div class="cm-wiz-success-title">Сообщество создано</div>
                    <div class="cm-wiz-success-sub">Поделитесь кодом ниже, чтобы пригласить участников</div>
                </div>
            </div>

            <div class="cm-wiz-done-card" id="cmWizDoneCard">
                <span class="cm-wiz-done-avatar" id="cmWizDoneAvatar"></span>
                <div class="cm-wiz-done-info">
                    <div class="cm-wiz-done-head">
                        <strong class="cm-wiz-done-name" id="cmWizDoneName"></strong>
                        <span class="cm-wiz-preview-badge">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="11" rx="2.5"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                            приватное
                        </span>
                    </div>
                    <p class="cm-wiz-done-desc" id="cmWizDoneDesc"></p>
                    <div class="cm-wiz-preview-meta">1 участник · ваша роль: админ</div>
                </div>
            </div>

            <div class="cm-wiz-invite-block">
                <div class="cm-wiz-invite-left">
                    <div class="cm-wiz-qr" aria-label="QR код приглашения" role="img">
                        <svg viewBox="0 0 21 21" width="94" height="94" xmlns="http://www.w3.org/2000/svg">
                            <rect width="21" height="21" fill="#fff"/>
                            <g fill="#000">
                                <rect x="0" y="0" width="7" height="7"/>
                                <rect x="1" y="1" width="5" height="5" fill="#fff"/>
                                <rect x="2" y="2" width="3" height="3"/>
                                <rect x="14" y="0" width="7" height="7"/>
                                <rect x="15" y="1" width="5" height="5" fill="#fff"/>
                                <rect x="16" y="2" width="3" height="3"/>
                                <rect x="0" y="14" width="7" height="7"/>
                                <rect x="1" y="15" width="5" height="5" fill="#fff"/>
                                <rect x="2" y="16" width="3" height="3"/>
                                @foreach([[9,1],[11,1],[8,3],[12,4],[10,6],[13,7],[7,8],[9,9],[10,9],[11,9],[15,9],[18,9],[8,10],[13,10],[16,10],[9,11],[12,11],[17,11],[19,12],[7,13],[11,13],[14,13],[18,13],[8,15],[10,16],[12,16],[15,16],[18,16],[9,18],[13,18],[16,18],[19,19]] as [$x, $y])
                                    <rect x="{{ $x }}" y="{{ $y }}" width="1" height="1"/>
                                @endforeach
                            </g>
                        </svg>
                    </div>
                </div>
                <div class="cm-wiz-invite-right">
                    <div class="cm-wiz-invite-code-label">код приглашения</div>
                    <code class="cm-wiz-invite-code" id="cmWizInviteCode">—</code>
                    <div class="cm-wiz-invite-timer-row">
                        <div class="cm-wiz-invite-ring-wrap">
                            <svg class="cm-wiz-ring-svg" viewBox="0 0 60 60">
                                <circle class="cm-wiz-ring-track" cx="30" cy="30" r="27"/>
                                <circle class="cm-wiz-ring-fill" cx="30" cy="30" r="27"/>
                            </svg>
                            <span class="cm-wiz-ring-time" id="cmWizRingTime">5:00</span>
                        </div>
                        <div class="cm-wiz-invite-note">
                            Код действует <span class="cm-wiz-invite-accent">5 минут</span>.<br>
                            Создайте новый, когда этот истечёт.
                        </div>
                    </div>
                </div>
            </div>

            <div class="cm-wiz-invite-actions">
                <button type="button" class="cm-wiz-copy-btn" id="cmWizCopyBtn">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    Скопировать
                </button>
                <button type="button" class="cm-wiz-copy-btn" id="cmWizShareBtn">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/></svg>
                    Поделиться
                </button>
                <button type="button" class="cm-wiz-copy-btn cm-wiz-refresh-btn" id="cmWizRefreshBtn" aria-label="Обновить код приглашения">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/></svg>
                </button>
            </div>

            <div class="cm-wiz-footer">
                <div class="cm-wiz-footer-note"></div>
                <div class="cm-wiz-footer-actions">
                    <button type="button" class="modal-btn-secondary" data-cm-close-create>Закрыть</button>
                    <a class="modal-btn-primary cm-wiz-goto" id="cmWizGoto" href="#">
                        Перейти к сообществу
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg>
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>
