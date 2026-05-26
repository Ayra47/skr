<x-app-shell title="Сообщения · skr" :vite="['resources/js/pages/chat.js']">

    {{-- Extra head: vapid + full window.Laravel for chat --}}
    <x-slot:head>
        <meta name="vapid-public-key" content="{{ config('app.vapid_public_key') }}">
        <script>
            window.Laravel = {
                userId: @json(Auth::id()),
                pseudonym: @json(Auth::user()->pseudonym),
                hasPublicKey: {{ $hasPublicKey ? 'true' : 'false' }},
                hasKeyBackup: {{ $hasKeyBackup ? 'true' : 'false' }},
                avatars: @json(
                    $conversations->flatMap(fn($c) => $c->isGroup() ? $c->members->map(fn($m) => $m->user) : [$c->otherParticipant(Auth::id())])
                        ->merge($friendsWithoutConv)
                        ->unique('id')
                        ->mapWithKeys(fn($u) => [$u->id => $u->avatar ? '/storage/'.$u->avatar : null])
                ),
                friendIds: @json($allFriends->pluck('id')->all()),
            };
        </script>
    </x-slot:head>

    {{-- Left sidebar --}}
    <x-slot:sidebar>
        <x-app-sidebar>
            <x-slot:header>
                <a href="{{ route('chats.index') }}" class="app-brand" style="text-decoration:none">
                    <div class="app-brand-icon">
                        <svg width="14" height="16" viewBox="0 0 12 14" fill="none">
                            <rect x="1" y="6" width="10" height="8" rx="1.5" stroke="currentColor" stroke-width="1.3"/>
                            <path d="M3 6V4a3 3 0 0 1 6 0v2" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div class="app-brand-info">
                        <p class="app-brand-name">skr chat</p>
                        <span class="app-brand-sub">приватные чаты</span>
                    </div>
                </a>
                <button class="app-icon-btn" id="newGroupBtn" type="button" title="создать группу">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                </button>
            </x-slot:header>

            <x-slot:body>
                <div class="sidebar-wrapper">
                    <div class="sidebar-search">
                        <svg class="sidebar-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"/>
                            <path d="M21 21l-4.4-4.4"/>
                        </svg>
                        <input type="text" placeholder="поиск">
                    </div>
                </div>

                <div class="sidebar-wrapper">
                    <div class="sidebar-filters">
                        <button class="sidebar-filter-btn active" data-filter="all">все</button>
                        <button class="sidebar-filter-btn" data-filter="direct">личные</button>
                        <button class="sidebar-filter-btn" data-filter="group">группы</button>
                        @if($pendingJoinRequests->isNotEmpty())
                            <button class="export-btn" id="groupRequestsBtn" type="button" style="display:none">приглашения</button>
                        @endif
                    </div>
                </div>

                <div class="conversation-list" id="conversationList">
                    @forelse($conversations as $conv)
                        @php
                            $isGroup = $conv->isGroup();
                            $partner = $isGroup ? null : $conv->otherParticipant(auth()->id());
                            $title = $isGroup ? $conv->title : $partner->pseudonym;
                            $role = $isGroup ? $conv->roleFor(auth()->id()) : null;
                            $latestPreview = $latestPreviews[$conv->id] ?? ['payload' => '', 'sender_id' => null, 'type' => null];
                            $previewText = $conv->latestMessage
                                ? ($latestPreview['type'] === \App\Models\Message::TYPE_SYSTEM ? 'обновление группы' : '…')
                                : ($isGroup ? 'группа' : 'нет сообщений');
                        @endphp
                        <div class="conversation-item row-btn conversation-row"
                            data-conv-id="{{ $conv->id }}"
                            data-conv-type="{{ $isGroup ? 'group' : 'direct' }}"
                            @if(!$isGroup) data-partner-id="{{ $partner->id }}" @endif
                            @if($isGroup) data-user-role="{{ $role }}" @endif
                            @if($latestPreview['payload']) data-latest-payload="{{ $latestPreview['payload'] }}" @endif
                            @if($latestPreview['sender_id']) data-latest-sender-id="{{ $latestPreview['sender_id'] }}" @endif
                            data-partner-login="{{ $title }}"
                            data-avatar-url="{{ !$isGroup && $partner->avatar ? '/storage/'.$partner->avatar : ($isGroup && $conv->avatar ? '/storage/'.$conv->avatar : '') }}">
                            <div class="conv-avatar">
                                @if($isGroup && $conv->avatar)
                                    <img src="/storage/{{ $conv->avatar }}" alt="" class="avatar-img">
                                @elseif(!$isGroup && $partner->avatar)
                                    <img src="/storage/{{ $partner->avatar }}" alt="" class="avatar-img">
                                @else
                                    {{ mb_strtoupper(mb_substr($title, 0, 1)) }}
                                @endif
                                @if(!$isGroup)<span class="online-dot"></span>@endif
                            </div>
                            <div class="conv-info">
                                <div class="conv-name conversation-name">{{ $title }}</div>
                                <div class="conv-preview conversation-preview" id="preview-{{ $conv->id }}">{{ $previewText }}</div>
                            </div>
                            <div class="conv-time conversation-time" id="time-{{ $conv->id }}">
                                @if($conv->latestMessage)
                                    {{ $conv->latestMessage->created_at->diffForHumans(null, true) }}
                                @endif
                            </div>
                        </div>
                    @empty
                    @endforelse

                    @if($friendsWithoutConv->isNotEmpty())
                        @if($conversations->isNotEmpty())
                            <div class="conv-section-label">друзья</div>
                        @endif
                        @foreach($friendsWithoutConv as $friend)
                            <div class="conversation-item row-btn conversation-row"
                                data-partner-id="{{ $friend->id }}"
                                data-partner-login="{{ $friend->pseudonym }}"
                                data-avatar-url="{{ $friend->avatar ? '/storage/'.$friend->avatar : '' }}">
                                <div class="conv-avatar">{{ mb_strtoupper(mb_substr($friend->pseudonym, 0, 1)) }}<span class="online-dot"></span></div>
                                <div class="conv-info">
                                    <div class="conv-name conversation-name">{{ $friend->pseudonym }}</div>
                                    <div class="conv-preview conversation-preview">нет сообщений</div>
                                </div>
                            </div>
                        @endforeach
                    @endif

                    @if($conversations->isEmpty() && $friendsWithoutConv->isEmpty())
                        <div class="sidebar-empty" id="emptyState">
                            нет диалогов.<br>сначала добавьте друзей.
                        </div>
                    @endif
                </div>
            </x-slot:body>

        </x-app-sidebar>
    </x-slot:sidebar>

    {{-- Main chat pane --}}
    <div class="chat-pane chat-main" id="chatPane">
        <div id="chatHeader" class="chat-header" style="display:none">
            <div class="chat-header-avatar" id="chatAvatar"></div>
            <div class="chat-header-info">
                <div class="chat-header-title-row">
                    <div class="chat-header-name" id="chatPartnerName"></div>
                    <div class="chat-header-e2e">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        e2e
                    </div>
                    <div class="key-change-warn" id="keyChangeWarn" style="display:none;">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.3 3.3L2 21h20L13.7 3.3a2 2 0 0 0-3.4 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        <span id="keyChangeWarnText"></span>
                    </div>
                </div>
                <div class="chat-header-status" id="chatPartnerStatus"></div>
            </div>
            <div class="chat-header-tools">
                <button class="chat-tool-btn" id="callBtn" type="button" title="позвонить">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.2-1.2a2 2 0 0 1 2.1-.5c.9.3 1.9.6 2.9.7A2 2 0 0 1 22 16.9z"/>
                    </svg>
                </button>
                <button class="chat-tool-btn" id="headerSearchBtn" type="button" title="поиск">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="M21 21l-4.4-4.4"/>
                    </svg>
                </button>
                <div class="chat-more-wrap">
                    <button class="chat-tool-btn" id="chatMoreBtn" type="button" title="ещё">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="5" cy="12" r="1.2" fill="currentColor" stroke="none"/>
                            <circle cx="12" cy="12" r="1.2" fill="currentColor" stroke="none"/>
                            <circle cx="19" cy="12" r="1.2" fill="currentColor" stroke="none"/>
                        </svg>
                    </button>
                    <div class="chat-more-menu" id="chatMoreMenu">
                        <button class="chat-more-item chat-more-item--danger" id="deleteChatBtn" type="button">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/>
                            </svg>
                            Удалить чат
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <div class="msg-search-bar" id="msgSearchBar" style="display:none">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"/><path d="M21 21l-4.4-4.4"/>
            </svg>
            <input type="text" id="msgSearchInput" placeholder="поиск по сообщениям…" autocomplete="off">
            <span class="msg-search-counter" id="msgSearchCounter"></span>
            <button class="msg-search-nav" id="msgSearchPrev" type="button" title="предыдущее">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
            </button>
            <button class="msg-search-nav" id="msgSearchNext" type="button" title="следующее">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
            </button>
            <button class="msg-search-close" id="msgSearchClose" type="button">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="pin-bar" id="pinBar" style="display:none"></div>
        <div class="typing-indicator" id="typingIndicator"></div>
        <div class="no-chat-selected" id="noChatSelected">
            <div class="empty-glow"></div>
            <div class="empty-lock-box">
                <svg width="36" height="40" viewBox="0 0 24 28" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="11" width="20" height="16" rx="3"/>
                    <path d="M6 11V8a6 6 0 0 1 12 0v3"/>
                </svg>
            </div>
            <p class="empty-title">Выберите диалог</p>
            <p class="empty-subtitle">Все сообщения шифруются на устройстве.<br>Сервер видит только зашифрованный поток.</p>
            <div class="empty-badge">
                <span class="empty-badge-dot"></span>
                канал защищён · ed25519 + xchacha20
            </div>
            <div class="empty-shortcuts">
                <span class="empty-shortcut"><kbd>N</kbd> новая группа</span>
                <span class="empty-shortcut"><kbd>F</kbd> поиск</span>
            </div>
        </div>
        <div class="messages-area messages-scroll" id="messagesArea" style="display:none"></div>
        <div class="input-area composer-shell" id="inputArea" style="display:none">
            <div class="upload-progress" id="uploadProgress" hidden>
                <div class="upload-progress-bar" id="uploadProgressBar"></div>
                <span class="upload-progress-label" id="uploadProgressLabel">загрузка…</span>
            </div>
            <div class="composer-box composer" id="composerBox">
                <div class="attach-wrap">
                    <div class="attach-menu" id="attachMenu">
                        <button class="attach-menu-item" id="attachPhotoBtn" type="button">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            Фото
                        </button>
                        <button class="attach-menu-item" id="attachFileBtn" type="button">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21.4 11.6l-9.9 9.9a5 5 0 0 1-7-7l9.9-9.9a3.3 3.3 0 0 1 4.6 4.7L8.9 19.4a1.7 1.7 0 0 1-2.3-2.3l8.5-8.6"/></svg>
                            Файл
                        </button>
                        <button class="attach-menu-item" id="attachLocationBtn" type="button">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            Местоположение
                        </button>
                    </div>
                    <button class="composer-btn" id="attachBtn" type="button" title="прикрепить">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21.4 11.6l-9.9 9.9a5 5 0 0 1-7-7l9.9-9.9a3.3 3.3 0 0 1 4.6 4.7L8.9 19.4a1.7 1.7 0 0 1-2.3-2.3l8.5-8.6"/>
                        </svg>
                    </button>
                </div>
                <input type="file" id="photoAttachInput" style="display:none" accept="image/*">
                <input type="file" id="fileAttachInput" style="display:none" accept="*/*">
                <div id="composerBlockedMsg" class="composer-blocked-msg">Вы не можете отправлять сообщения этому пользователю, так как не являетесь друзьями</div>
                <textarea id="messageInput" class="composer-textarea" placeholder="сообщение…" rows="1"></textarea>
                <button class="composer-btn" id="emojiBtn" type="button" title="эмодзи">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M8 14s1.5 2 4 2 4-2 4-2"/>
                        <line x1="9" y1="9" x2="9.01" y2="9"/>
                        <line x1="15" y1="9" x2="15.01" y2="9"/>
                    </svg>
                </button>
                <button class="send-btn" id="sendBtn">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 2L11 13"/>
                        <path d="M22 2L15 22l-4-9-9-4 20-7z"/>
                    </svg>
                </button>
            </div>
            <div class="composer-hint">
                <span>↵ — отправить · ⇧↵ — новая строка</span>
                <span id="composerFp"></span>
            </div>
        </div>
    </div>

    {{-- Right panel: emoji / info (toggled by JS via width animation, not grid column) --}}
    <x-slot:panel>
        <aside id="chatSidePanel" class="emoji-panel chat-side-panel" aria-label="Панель чата">
            <div class="chat-side-panel-body">
                <div class="chat-side-panel-view chat-side-panel-view--info" data-side-panel-content="info">
                    <div id="directInfoPanel" class="direct-info-panel">общение с человеком</div>
                    <div class="group-panel" id="groupPanel"></div>
                </div>
                <div class="chat-side-panel-view chat-side-panel-view--emoji" data-side-panel-content="emoji">
                    <div id="emojiPickerHost" class="emoji-picker-host"></div>
                </div>
            </div>
            <div class="chat-side-panel-tabs" role="tablist" aria-label="Переключение панели">
                <button class="chat-side-panel-tab" type="button" data-side-panel-tab="info" role="tab">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21a8 8 0 0 0-16 0"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    Инфо
                </button>
                <button class="chat-side-panel-tab is-active" type="button" data-side-panel-tab="emoji" role="tab">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M8 14s1.5 2 4 2 4-2 4-2"/>
                        <line x1="9" y1="9" x2="9.01" y2="9"/>
                        <line x1="15" y1="9" x2="15.01" y2="9"/>
                    </svg>
                    Эмодзи
                </button>
            </div>
        </aside>
    </x-slot:panel>

    {{-- Modals (outside app-shell to avoid overflow:hidden clipping) --}}
    <x-slot:after>
        <div id="groupCreateModal" class="modal-overlay" style="display:none">
            <form class="modal-box" id="groupCreateForm">
                <div class="modal-title">Новая группа</div>
                <input type="text" name="title" class="modal-input" maxlength="60" required placeholder="Название">
                <div class="group-friend-picker">
                    @foreach($allFriends as $friend)
                        <label>
                            <input type="checkbox" name="user_ids[]" value="{{ $friend->id }}">
                            <span>{{ $friend->pseudonym }}</span>
                        </label>
                    @endforeach
                </div>
                <div class="modal-actions">
                    <button class="modal-btn-secondary" id="groupCreateCancel" type="button">отмена</button>
                    <button class="modal-btn-primary" type="submit">создать</button>
                </div>
            </form>
        </div>

        @if($pendingJoinRequests->isNotEmpty())
            <div id="groupRequestsModal" class="modal-overlay" style="display:none">
                <div class="modal-box">
                    <div class="modal-title">Приглашения в группы</div>
                    <div class="group-request-list">
                        @foreach($pendingJoinRequests as $joinRequest)
                            <div class="group-request-row" data-request-id="{{ $joinRequest->id }}">
                                <div>
                                    <strong>{{ $joinRequest->conversation->title }}</strong>
                                    <span>пригласил(а): {{ $joinRequest->invitedBy->pseudonym }}</span>
                                </div>
                                <div class="group-request-actions">
                                    <button class="modal-btn-secondary" type="button" data-action="decline-group-request">отклонить</button>
                                    <button class="modal-btn-primary" type="button" data-action="accept-group-request">принять</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="modal-actions">
                        <button class="modal-btn-secondary" id="groupRequestsClose" type="button">закрыть</button>
                    </div>
                </div>
            </div>
        @endif

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
                <button class="pin-dialog-recovery modal-link" style="display:none">восстановить по фразе</button>
            </div>
        </div>

        <div id="recoveryPhraseRestoreModal" class="modal-overlay" style="display:none">
            <div class="modal-box">
                <div class="modal-title">восстановление по фразе</div>
                <div class="modal-subtitle">вставьте фразу восстановления, которую вы сохранили при настройке бэкапа</div>
                <textarea class="phrase-input modal-textarea" rows="4" placeholder="вставьте фразу…"></textarea>
                <div class="phrase-dialog-error modal-error"></div>
                <div class="modal-actions">
                    <button class="modal-btn-secondary phrase-dialog-cancel">отмена</button>
                    <button class="modal-btn-primary phrase-dialog-confirm">восстановить</button>
                </div>
            </div>
        </div>

        <div id="locationModal" class="modal-overlay" style="display:none">
            <div class="modal-box">
                <div class="modal-title">Местоположение</div>
                <div class="location-modal-section">
                    <div class="location-modal-label">Разовая геолокация</div>
                    <button class="modal-btn-primary" id="locationOnceBtn" type="button">Отправить текущую позицию</button>
                </div>
                <div class="location-modal-divider"></div>
                <div class="location-modal-section">
                    <div class="location-modal-label">Живая геолокация — выберите длительность</div>
                    <div class="location-duration-btns" id="locationDurationBtns">
                        <button class="location-duration-btn" type="button" data-duration="5">5 мин</button>
                        <button class="location-duration-btn" type="button" data-duration="15">15 мин</button>
                        <button class="location-duration-btn" type="button" data-duration="30">30 мин</button>
                        <button class="location-duration-btn" type="button" data-duration="60">1 час</button>
                        <button class="location-duration-btn" type="button" data-duration="180">3 часа</button>
                    </div>
                </div>
                <div class="modal-actions">
                    <button class="modal-btn-secondary" id="locationModalCancel" type="button">Отмена</button>
                </div>
            </div>
        </div>

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
    </x-slot:after>

</x-app-shell>
