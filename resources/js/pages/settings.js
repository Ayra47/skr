import "../../css/pages/settings.scss";
import "../app";

// ─── Constants ────────────────────────────────────────────────────────────────
const AUTH_USER_ID = window.Laravel.userId;
const CSRF = () => document.querySelector('meta[name="csrf-token"]').content;

const PS_A = ['crow','north','silver','amber','lonely','pale','red','iron','velvet','quiet','dark','still','wild','tall','soft'];
const PS_B = ['fox','wind','fern','orbit','echo','tide','moss','ash','kite','spire','hawk','dune','pine','cliff','flare'];

// ─── IDB (read-only — just need the keypair for backup code) ─────────────────
const IDB = {
    db: null,
    async open() {
        return new Promise((resolve, reject) => {
            const req = indexedDB.open('skr_chat', 3);
            req.onsuccess = e => { this.db = e.target.result; resolve(); };
            req.onerror = () => reject(req.error);
            req.onupgradeneeded = e => {
                const db = e.target.result;
                if (!db.objectStoreNames.contains('keys')) {
                    db.createObjectStore('keys');
                }
                if (!db.objectStoreNames.contains('messages')) {
                    const messages = db.createObjectStore('messages', { keyPath: 'id' });
                    messages.createIndex('by_conv', 'conversation_id');
                }
            };
        });
    },
    async get(store, key) {
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(store, 'readonly');
            const req = tx.objectStore(store).get(key);
            req.onsuccess = () => resolve(req.result ?? null);
            req.onerror = () => reject(req.error);
        });
    },
    async put(store, value, key) {
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(store, 'readwrite');
            const req = tx.objectStore(store).put(value, key);
            req.onsuccess = () => resolve();
            req.onerror = () => reject(req.error);
        });
    },
    async getAll(store) {
        return new Promise((resolve, reject) => {
            const tx = this.db.transaction(store, 'readonly');
            const req = tx.objectStore(store).getAll();
            req.onsuccess = () => resolve(req.result ?? []);
            req.onerror = () => reject(req.error);
        });
    },
};

// ─── HTTP helpers ─────────────────────────────────────────────────────────────
async function post(url, data) {
    const r = await fetch(url, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF(), 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(data),
    });
    return r.json();
}

async function del(url) {
    const r = await fetch(url, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': CSRF(), 'Accept': 'application/json' },
    });
    return r.json();
}

// ─── Confirm modal ────────────────────────────────────────────────────────────
function showConfirm({ title, body, okLabel = 'Подтвердить', okStyle = '', iconSvg = '' } = {}) {
    return new Promise(resolve => {
        const backdrop = document.getElementById('confirmModal');
        document.getElementById('confirmModalTitle').textContent = title;
        document.getElementById('confirmModalBody').textContent = body;
        const icon = document.getElementById('confirmModalIcon');
        icon.innerHTML = iconSvg;
        const ok = document.getElementById('confirmModalOk');
        ok.textContent = okLabel;
        ok.style.cssText = okStyle;
        backdrop.style.display = 'flex';

        const close = (result) => {
            backdrop.style.display = 'none';
            ok.replaceWith(ok.cloneNode(true));
            document.getElementById('confirmModalCancel').replaceWith(document.getElementById('confirmModalCancel').cloneNode(true));
            resolve(result);
        };

        document.getElementById('confirmModalOk').addEventListener('click', () => close(true));
        document.getElementById('confirmModalCancel').addEventListener('click', () => close(false));
        backdrop.addEventListener('click', e => { if (e.target === backdrop) { close(false); } }, { once: true });
    });
}

// ─── UI helpers ───────────────────────────────────────────────────────────────
function showMsg(el, type, text) {
    el.className = 'form-msg ' + type;
    el.textContent = text;
    el.style.display = 'block';
    if (type === 'success') { setTimeout(() => { el.style.display = 'none'; }, 3500); }
}

function genPseudo() {
    const a = PS_A[Math.floor(Math.random() * PS_A.length)];
    const b = PS_B[Math.floor(Math.random() * PS_B.length)];
    return `${a}-${b}-${100 + Math.floor(Math.random() * 900)}`;
}

function bytesToBase64(bytes) {
    return btoa(String.fromCharCode(...bytes));
}

function base64ToBytes(value) {
    return Uint8Array.from(atob(value), c => c.charCodeAt(0));
}

function normalizeJwkBase64(value) {
    return value.replace(/-/g, '+').replace(/_/g, '/');
}

function showChatSecurityMsg(type, text) {
    const msg = document.getElementById('chatSecurityMsg');
    if (!msg) { return; }
    showMsg(msg, type, text);
}

async function getStoredChatKeypair() {
    await IDB.open();

    return IDB.get('keys', 'keypair_' + AUTH_USER_ID).catch(() => null);
}

async function generateAndUploadChatKeypair() {
    const keyPair = await crypto.subtle.generateKey(
        { name: 'ECDH', namedCurve: 'P-256' },
        true,
        ['deriveKey', 'deriveBits'],
    );
    const privateJwk = await crypto.subtle.exportKey('jwk', keyPair.privateKey);
    const publicJwk = await crypto.subtle.exportKey('jwk', keyPair.publicKey);
    await IDB.put('keys', { privateJwk, publicJwk }, 'keypair_' + AUTH_USER_ID);
    await post('/chat/keys', {
        public_key_jwk: JSON.stringify(publicJwk),
        key_change_source: 'settings',
    });

    return { privateJwk, publicJwk };
}

async function ensureChatKeypair() {
    const stored = await getStoredChatKeypair();
    if (stored?.privateJwk && stored?.publicJwk) {
        return stored;
    }

    const generated = await generateAndUploadChatKeypair();
    await renderChatKeyFingerprint(generated.publicJwk);
    showChatSecurityMsg('success', 'Ключ шифрования создан на этом устройстве');

    return generated;
}

async function chatKeyFingerprint(publicJwk) {
    const encoded = new TextEncoder().encode(JSON.stringify(publicJwk));
    const hash = await crypto.subtle.digest('SHA-256', encoded);

    return Array.from(new Uint8Array(hash))
        .map(b => b.toString(16).padStart(2, '0'))
        .join('')
        .toUpperCase()
        .match(/.{4}/g)
        .join(' ');
}

async function renderChatKeyFingerprint(publicJwk = null) {
    const keyFingerprint = document.getElementById('keyFingerprint');
    if (!keyFingerprint) { return; }

    const jwk = publicJwk ?? (await getStoredChatKeypair())?.publicJwk;
    if (!jwk) {
        keyFingerprint.textContent = 'отпечаток: ключ ещё не создан на этом устройстве';
        return;
    }

    const fp = await chatKeyFingerprint(jwk);
    keyFingerprint.textContent = 'отпечаток: ' + fp.slice(0, 23) + '…';
}

async function deriveExportKey(privateJwk, usage) {
    const dBytes = base64ToBytes(normalizeJwkBase64(privateJwk.d));
    const hkdfKey = await crypto.subtle.importKey('raw', dBytes, 'HKDF', false, ['deriveKey']);

    return crypto.subtle.deriveKey(
        {
            name: 'HKDF',
            hash: 'SHA-256',
            salt: new Uint8Array(32),
            info: new TextEncoder().encode('skr-export-v1'),
        },
        hkdfKey,
        { name: 'AES-GCM', length: 256 },
        false,
        [usage],
    );
}

async function exportChatHistoryToFile() {
    const keypair = await ensureChatKeypair();
    const messages = await IDB.getAll('messages').catch(() => []);
    const json = JSON.stringify(messages);
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const key = await deriveExportKey(keypair.privateJwk, 'encrypt');
    const encrypted = await crypto.subtle.encrypt(
        { name: 'AES-GCM', iv },
        key,
        new TextEncoder().encode(json),
    );
    const blob = new Blob([iv, new Uint8Array(encrypted)], {
        type: 'application/octet-stream',
    });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'chat-history-' + Date.now() + '.enc';
    a.click();
    URL.revokeObjectURL(a.href);
    showChatSecurityMsg('success', 'История экспортирована');
}

async function importChatHistoryFromFile(event) {
    const file = event.target.files?.[0];
    if (!file) { return; }

    const keypair = await ensureChatKeypair();
    const buffer = await file.arrayBuffer();
    const iv = new Uint8Array(buffer, 0, 12);
    const ciphertext = new Uint8Array(buffer, 12);

    try {
        const key = await deriveExportKey(keypair.privateJwk, 'decrypt');
        const decrypted = await crypto.subtle.decrypt({ name: 'AES-GCM', iv }, key, ciphertext);
        const messages = JSON.parse(new TextDecoder().decode(decrypted));
        for (const message of messages) {
            await IDB.put('messages', message);
        }
        showChatSecurityMsg('success', 'Импортировано сообщений: ' + messages.length);
    } catch {
        showChatSecurityMsg('error', 'Не удалось импортировать файл');
    } finally {
        event.target.value = '';
    }
}

async function deriveKeyFromPin(pin, saltB64) {
    const salt = base64ToBytes(saltB64);
    const keyMaterial = await crypto.subtle.importKey(
        'raw',
        new TextEncoder().encode(pin),
        'PBKDF2',
        false,
        ['deriveKey'],
    );

    return crypto.subtle.deriveKey(
        { name: 'PBKDF2', hash: 'SHA-256', salt, iterations: 100000 },
        keyMaterial,
        { name: 'AES-GCM', length: 256 },
        false,
        ['encrypt', 'decrypt'],
    );
}

async function encryptPrivateKey(privateJwk, pin) {
    const salt = crypto.getRandomValues(new Uint8Array(16));
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const saltB64 = bytesToBase64(salt);
    const key = await deriveKeyFromPin(pin, saltB64);
    const ciphertext = await crypto.subtle.encrypt(
        { name: 'AES-GCM', iv },
        key,
        new TextEncoder().encode(JSON.stringify(privateJwk)),
    );

    return JSON.stringify({
        salt: saltB64,
        iv: bytesToBase64(iv),
        ciphertext: bytesToBase64(new Uint8Array(ciphertext)),
    });
}

function showPinDialog({ title, subtitle, confirmLabel, onConfirm, onCancel }) {
    const dialog = document.getElementById('pinDialog');
    dialog.querySelector('.pin-dialog-title').textContent = title;
    dialog.querySelector('.pin-dialog-subtitle').textContent = subtitle;
    const confirmBtn = dialog.querySelector('.pin-dialog-confirm');
    confirmBtn.textContent = confirmLabel;
    confirmBtn.disabled = false;
    dialog.querySelector('.pin-dialog-error').textContent = '';
    const input = dialog.querySelector('.pin-input');
    input.value = '';
    dialog.style.display = 'flex';
    setTimeout(() => input.focus(), 50);
    dialog._onConfirm = onConfirm;
    dialog._onCancel = onCancel;
}

function hidePinDialog() {
    document.getElementById('pinDialog').style.display = 'none';
}

function showRecoveryPhrase(phrase) {
    const dialog = document.getElementById('recoveryPhraseModal');
    dialog.querySelector('.recovery-phrase-text').textContent = phrase;
    dialog.style.display = 'flex';
}

async function setupChatKeyBackup() {
    const keypair = await ensureChatKeypair();
    const recoveryPhrase = btoa(JSON.stringify(keypair.privateJwk));

    showPinDialog({
        title: 'Настройка бэкапа',
        subtitle: 'Введите 6-значный PIN для защиты ключа на сервере',
        confirmLabel: 'Сохранить бэкап',
        onConfirm: async (pin) => {
            try {
                const backupJson = await encryptPrivateKey(keypair.privateJwk, pin);
                const result = await post('/chat/keys/backup', { key_backup: backupJson });
                if (!result.success) {
                    return false;
                }
                hidePinDialog();
                showRecoveryPhrase(recoveryPhrase);
                showChatSecurityMsg('success', 'Бэкап ключа сохранён');
                return true;
            } catch {
                return false;
            }
        },
        onCancel: () => hidePinDialog(),
    });
}

function initChatSecurityDialogs() {
    const pinDialog = document.getElementById('pinDialog');
    if (!pinDialog) { return; }

    pinDialog.querySelector('.pin-dialog-confirm').addEventListener('click', async () => {
        const pin = pinDialog.querySelector('.pin-input').value.trim();
        if (pin.length !== 6) {
            pinDialog.querySelector('.pin-dialog-error').textContent = 'введите 6-значный PIN';
            return;
        }

        const btn = pinDialog.querySelector('.pin-dialog-confirm');
        btn.disabled = true;
        pinDialog.querySelector('.pin-dialog-error').textContent = '';
        const ok = await pinDialog._onConfirm?.(pin);
        if (ok === false) {
            pinDialog.querySelector('.pin-dialog-error').textContent = 'неверный PIN';
            btn.disabled = false;
        }
    });

    pinDialog.querySelector('.pin-input').addEventListener('keydown', e => {
        if (e.key === 'Enter') {
            pinDialog.querySelector('.pin-dialog-confirm').click();
        }
    });
    pinDialog.querySelector('.pin-dialog-cancel').addEventListener('click', () => pinDialog._onCancel?.());

    document.getElementById('recoveryPhraseDoneBtn')?.addEventListener('click', () => {
        document.getElementById('recoveryPhraseModal').style.display = 'none';
    });
}

async function initChatSecurityPanel() {
    const panel = document.querySelector('.chat-security-panel');
    if (!panel) { return; }

    const storageSelect = document.getElementById('storageSelect');
    const deviceExportRow = document.getElementById('deviceExportRow');

    function toggleDeviceExport(value) {
        deviceExportRow.style.display = value === 'device' ? 'flex' : 'none';
    }

    const settings = await fetch('/chat/settings', {
        headers: { 'Accept': 'application/json' },
    }).then(r => r.json()).catch(() => ({ success: false }));

    if (settings.success) {
        storageSelect.value = settings.storage_preference;
        toggleDeviceExport(settings.storage_preference);
    }

    storageSelect.addEventListener('change', async () => {
        toggleDeviceExport(storageSelect.value);
        const result = await post('/chat/settings', { storage_preference: storageSelect.value });
        if (result.success) {
            showChatSecurityMsg('success', 'Настройки хранения сохранены');
        }
    });

    document.getElementById('exportHistoryBtn').addEventListener('click', exportChatHistoryToFile);
    document.getElementById('importFileInput').addEventListener('change', importChatHistoryFromFile);
    document.getElementById('importTriggerBtn').addEventListener('click', () => {
        document.getElementById('importFileInput').click();
    });
    document.getElementById('setupBackupBtn').addEventListener('click', setupChatKeyBackup);

    await renderChatKeyFingerprint();
}

// ─── Sidebar nav scroll spy ───────────────────────────────────────────────────
function initNav() {
    const btns = document.querySelectorAll('.settings-nav-btn');
    btns.forEach(btn => {
        btn.addEventListener('click', () => {
            const el = document.getElementById('section-' + btn.dataset.section);
            if (el) {
                window.scrollTo({ top: el.getBoundingClientRect().top + window.scrollY - 24, behavior: 'smooth' });
            }
            btns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
        });
    });

    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const id = entry.target.id.replace('section-', '');
                btns.forEach(b => b.classList.toggle('active', b.dataset.section === id));
            }
        });
    }, { rootMargin: '-30% 0px -60% 0px' });

    document.querySelectorAll('.settings-card').forEach(el => observer.observe(el));
}

// ─── Avatar ───────────────────────────────────────────────────────────────────
function initAvatar() {
    const input   = document.getElementById('avatarInput');
    const img     = document.getElementById('avatarImg');
    const initial = document.getElementById('avatarInitial');

    function setAvatar(url) {
        if (url) {
            img.src = url;
            img.style.display = 'block';
            initial.style.display = 'none';
        } else {
            img.style.display = 'none';
            initial.style.display = '';
        }
    }

    setAvatar(window.Laravel.avatarUrl);

    const openPicker = () => input.click();
    document.getElementById('avatarUploadBtn').addEventListener('click', openPicker);
    document.getElementById('avatarUploadBtn2').addEventListener('click', openPicker);

    input.addEventListener('change', async () => {
        if (!input.files[0]) { return; }
        const form = new FormData();
        form.append('avatar', input.files[0]);
        form.append('_token', CSRF());
        const r = await fetch('/settings/avatar', { method: 'POST', body: form, headers: { 'Accept': 'application/json' } });
        const data = await r.json();
        if (data.success) { setAvatar(data.avatar_url); }
        input.value = '';
    });

    document.getElementById('avatarDeleteBtn').addEventListener('click', async () => {
        const data = await del('/settings/avatar');
        if (data.success) { setAvatar(null); }
    });
}

// ─── Profile form ─────────────────────────────────────────────────────────────
function initProfile() {
    const msg        = document.getElementById('profileMsg');
    const origLogin  = window.Laravel.login;
    const origPseudo = window.Laravel.pseudonym;
    const origEmail  = window.Laravel.pendingEmail ?? window.Laravel.email ?? '';

    document.getElementById('pseudoGenBtn').addEventListener('click', () => {
        document.getElementById('fieldPseudo').value = genPseudo();
    });

    document.getElementById('profileCancelBtn').addEventListener('click', () => {
        document.getElementById('fieldLogin').value  = origLogin;
        document.getElementById('fieldPseudo').value = origPseudo;
        document.getElementById('fieldEmail').value  = origEmail;
        msg.style.display = 'none';
    });

    const bioField = document.getElementById('fieldBio');
    const bioCharCount = document.getElementById('bioCharCount');
    if (bioField && bioCharCount) {
        bioField.addEventListener('input', () => {
            bioCharCount.textContent = bioField.value.length;
        });
    }

    document.getElementById('profileSaveBtn').addEventListener('click', async () => {
        const btn   = document.getElementById('profileSaveBtn');
        const login = document.getElementById('fieldLogin').value.trim();
        const email = document.getElementById('fieldEmail').value.trim() || null;
        const bio   = document.getElementById('fieldBio')?.value.trim() || null;

        btn.disabled = true;
        const data = await post('/settings/profile', {
            login,
            pseudonym: document.getElementById('fieldPseudo').value.trim(),
            email,
            bio,
        });
        btn.disabled = false;

        if (data.success) {
            // Update the display login in the profile header
            const display = document.getElementById('profileLoginDisplay');
            if (display) { display.textContent = login; }

            if (data.verification_sent) {
                showMsg(msg, 'success', 'Профиль сохранён · письмо с подтверждением отправлено на ' + email);
                updateEmailStatus('pending', email);
            } else {
                showMsg(msg, 'success', 'Профиль сохранён');
            }
        } else {
            const first = data.errors ? Object.values(data.errors)[0]?.[0] : (data.message ?? 'Ошибка');
            showMsg(msg, 'error', first);
        }
    });

    // Detach email
    const detachBtn = document.getElementById('emailDetachBtn');
    if (detachBtn) {
        detachBtn.addEventListener('click', async () => {
            const email = document.getElementById('fieldEmail').value.trim();
            const confirmed = await showConfirm({
                title: 'Отвязать email?',
                body: `На ${email} будет отправлено письмо с подтверждением. Email будет удалён только после перехода по ссылке.`,
                okLabel: 'Отправить письмо',
                okStyle: 'background:rgba(220,60,60,.15);color:#e05555;border-color:rgba(220,60,60,.3);',
                iconSvg: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z"/><path d="M3 7l9 6 9-6"/></svg>',
            });
            if (!confirmed) { return; }
            detachBtn.disabled = true;
            const data = await del('/settings/email');
            detachBtn.disabled = false;
            if (data.success) {
                msg.className = 'form-msg success';
                msg.textContent = `Письмо отправлено на ${email} — email будет отвязан после перехода по ссылке`;
                msg.style.display = 'block';
            } else {
                showMsg(msg, 'error', data.message ?? 'Ошибка');
            }
        });
    }

    // Resend verification (may not exist if no pending email on load)
    const resendBtn = document.getElementById('emailResendBtn');
    if (resendBtn) {
        resendBtn.addEventListener('click', async () => {
            resendBtn.disabled = true;
            const data = await post('/settings/email/resend', {});
            resendBtn.disabled = false;
            if (data.success) {
                resendBtn.textContent = 'Отправлено ✓';
                setTimeout(() => { resendBtn.textContent = 'Отправить снова'; }, 3000);
            }
        });
    }
}

// Update email status badge dynamically after save
function updateEmailStatus(state, email) {
    const box = document.getElementById('emailStatusBox');
    if (!box) { return; }
    if (state === 'pending') {
        box.className = 'email-status email-status-pending';
        box.innerHTML = `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            Ожидает подтверждения · ${email}
            <button class="email-resend-btn" id="emailResendBtn">Отправить снова</button>`;
        box.style.display = '';
        // Re-bind resend button
        document.getElementById('emailResendBtn')?.addEventListener('click', async (e) => {
            e.currentTarget.disabled = true;
            const data = await post('/settings/email/resend', {});
            e.currentTarget.disabled = false;
            if (data.success) {
                e.currentTarget.textContent = 'Отправлено ✓';
                setTimeout(() => { e.currentTarget.textContent = 'Отправить снова'; }, 3000);
            }
        });
    }
}

// ─── Password form ────────────────────────────────────────────────────────────
function initPassword() {
    const form = document.getElementById('passwordForm');
    const msg  = document.getElementById('passwordMsg');

    document.getElementById('passwordRowBtn').addEventListener('click', () => {
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
    });

    document.getElementById('passwordCancelBtn').addEventListener('click', () => {
        form.style.display = 'none';
        ['fieldCurrentPwd', 'fieldNewPwd', 'fieldConfirmPwd'].forEach(id => {
            document.getElementById(id).value = '';
        });
        msg.style.display = 'none';
    });

    document.getElementById('passwordSaveBtn').addEventListener('click', async () => {
        const btn        = document.getElementById('passwordSaveBtn');
        const newPwd     = document.getElementById('fieldNewPwd').value;
        const confirmPwd = document.getElementById('fieldConfirmPwd').value;

        if (newPwd !== confirmPwd) {
            showMsg(msg, 'error', 'Пароли не совпадают');
            return;
        }

        btn.disabled = true;
        const data = await post('/settings/password', {
            current_password:      document.getElementById('fieldCurrentPwd').value,
            password:              newPwd,
            password_confirmation: confirmPwd,
        });
        btn.disabled = false;

        if (data.success) {
            ['fieldCurrentPwd', 'fieldNewPwd', 'fieldConfirmPwd'].forEach(id => {
                document.getElementById(id).value = '';
            });
            if (data.verification_sent) {
                msg.className = 'form-msg success';
                msg.textContent = `Письмо с подтверждением отправлено на ${data.email} — пароль изменится после перехода по ссылке`;
                msg.style.display = 'block';
            } else {
                showMsg(msg, 'success', 'Пароль изменён');
            }
        } else {
            const first = data.errors ? Object.values(data.errors)[0]?.[0] : (data.message ?? 'Ошибка');
            showMsg(msg, 'error', first);
        }
    });
}

// ─── Backup code ──────────────────────────────────────────────────────────────
function initBackupCode() {
    const box     = document.getElementById('backupCodeBox');
    const valueEl = document.getElementById('backupCodeValue');
    const msg     = document.getElementById('backupCodeMsg');
    const showBtn = document.getElementById('backupCodeShowBtn');
    const copyBtn = document.getElementById('backupCodeCopyBtn');
    const closeBtn = document.getElementById('backupCodeCloseBtn');

    showBtn.addEventListener('click', async () => {
        box.style.display = 'block';
        showBtn.disabled = true;
        msg.style.display = 'none';
        valueEl.textContent = '…';

        try {
            await IDB.open();

            // Generate new ECDH key pair
            const keyPair = await crypto.subtle.generateKey(
                { name: 'ECDH', namedCurve: 'P-256' },
                true,
                ['deriveKey', 'deriveBits'],
            );
            const privateJwk = await crypto.subtle.exportKey('jwk', keyPair.privateKey);
            const publicJwk  = await crypto.subtle.exportKey('jwk', keyPair.publicKey);

            // Persist new keypair in IndexedDB
            await IDB.put('keys', { privateJwk, publicJwk }, 'keypair_' + AUTH_USER_ID);

            const code = btoa(JSON.stringify(privateJwk));
            valueEl.textContent = code;

            // Hash backup code and upload new public key as 'settings' source
            const hashBuf = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(code));
            const hash = Array.from(new Uint8Array(hashBuf)).map(b => b.toString(16).padStart(2, '0')).join('');
            await post('/settings/backup-code', { hash, public_key_jwk: JSON.stringify(publicJwk) });

            showBtn.textContent = 'Показать / обновить';
        } catch (e) {
            showMsg(msg, 'error', 'Не удалось сгенерировать ключ: ' + e.message);
        }
        showBtn.disabled = false;
    });

    copyBtn.addEventListener('click', () => {
        const code = valueEl.textContent;
        if (!code || code === '…') { return; }
        navigator.clipboard.writeText(code).then(() => {
            copyBtn.textContent = 'Скопировано ✓';
            setTimeout(() => { copyBtn.textContent = 'Скопировать'; }, 2000);
        });
    });

    closeBtn.addEventListener('click', () => {
        box.style.display = 'none';
        valueEl.textContent = '';
    });
}

// ─── 2FA toggle ───────────────────────────────────────────────────────────────
function initTwoFactor() {
    const toggle = document.getElementById('twoFactorToggle');
    const sub    = document.getElementById('twoFactorSub');
    if (!toggle || toggle.disabled) { return; }

    toggle.addEventListener('click', async () => {
        const enabling = !toggle.classList.contains('active');

        if (enabling) {
            const confirmed = await showConfirm({
                title: 'Включить двухфакторную аутентификацию?',
                body: 'При каждом входе на вашу почту будет отправляться код подтверждения.',
                okLabel: 'Включить',
                okStyle: 'background:rgba(109,212,154,.15);color:#6dd49a;border-color:rgba(109,212,154,.3);',
                iconSvg: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
            });
            if (!confirmed) { return; }
        }

        toggle.disabled = true;
        const data = await post('/settings/two-factor', { enabled: enabling });
        toggle.disabled = false;

        if (data.success) {
            toggle.classList.toggle('active', enabling);
            sub.textContent = enabling
                ? 'Включена — код на email при каждом входе'
                : 'Выключена — для включения нужна подтверждённая почта';
        }
    });
}

// ─── Notifications ────────────────────────────────────────────────────────────
async function initNotifications() {
    const soundToggle     = document.getElementById('notifSoundToggle');
    const pushToggle      = document.getElementById('notifPushToggle');
    const pushSub         = document.getElementById('notifPushSub');
    const pushTextOption  = document.getElementById('notifPushTextOption');
    const pushTextCheck   = document.getElementById('notifPushText');
    const emailToggle     = document.getElementById('notifEmailToggle');
    const emailTextOption = document.getElementById('notifEmailTextOption');
    const emailTextCheck  = document.getElementById('notifEmailText');

    const prefs = await fetch('/settings/notifications', {
        headers: { 'Accept': 'application/json' },
    }).then(r => r.json()).catch(() => ({}));

    // ── Sound ──
    soundToggle.classList.toggle('active', prefs.notify_sound ?? true);
    soundToggle.addEventListener('click', async () => {
        const enabling = !soundToggle.classList.contains('active');
        soundToggle.disabled = true;
        const data = await post('/settings/notifications', { notify_sound: enabling });
        soundToggle.disabled = false;
        if (data.success) { soundToggle.classList.toggle('active', enabling); }
    });

    // ── Push (browser notifications) ──
    function applyPushState(enabled) {
        pushToggle.classList.toggle('active', enabled);
        pushTextOption.style.display = enabled ? '' : 'none';
    }

    applyPushState(!!prefs.notify_push);
    pushTextCheck.checked = !!prefs.notify_push_text;

    pushToggle.addEventListener('click', async () => {
        const enabling = !pushToggle.classList.contains('active');

        if (enabling) {
            if (Notification.permission === 'denied') {
                // Browser permanently blocked — can't re-request via JS, show hint
                pushSub.textContent = 'Разрешите уведомления в адресной строке браузера и повторите';
                setTimeout(() => { pushSub.textContent = 'Уведомления браузера о новых сообщениях'; }, 4000);
                return;
            }
            if (Notification.permission === 'default') {
                const result = await Notification.requestPermission();
                if (result !== 'granted') { return; }
            }
        }

        pushToggle.disabled = true;
        const data = await post('/settings/notifications', { notify_push: enabling });
        pushToggle.disabled = false;
        if (data.success) { applyPushState(enabling); }
    });

    pushTextCheck?.addEventListener('change', async () => {
        await post('/settings/notifications', { notify_push_text: pushTextCheck.checked });
    });

    // ── Email ──
    if (emailToggle && !emailToggle.disabled) {
        emailToggle.classList.toggle('active', !!prefs.notify_email);
        if (prefs.notify_email) { emailTextOption.style.display = ''; }
        emailTextCheck.checked = !!prefs.notify_email_text;

        emailToggle.addEventListener('click', async () => {
            const enabling = !emailToggle.classList.contains('active');
            emailToggle.disabled = true;
            const data = await post('/settings/notifications', { notify_email: enabling });
            emailToggle.disabled = false;
            if (data.success) {
                emailToggle.classList.toggle('active', enabling);
                emailTextOption.style.display = enabling ? '' : 'none';
                if (!enabling) { emailTextCheck.checked = false; }
            }
        });
    }

    emailTextCheck?.addEventListener('change', async () => {
        await post('/settings/notifications', { notify_email_text: emailTextCheck.checked });
    });
}

function initProfileVisibility() {
    const prefs = {
        show_shared_chats: true,
        show_shared_groups: true,
        profile_access: 'everyone',
        online_status_visibility: 'everyone',
        shared_friends_count_visibility: 'everyone',
        feed_posts_count_visibility: 'everyone',
        profile_posts_visibility: 'everyone',
        avatar_visibility: 'everyone',
        ...(window.Laravel.profileSettings ?? {}),
    };
    const msg = document.getElementById('profileVisibilityMsg');
    const sharedChatsToggle = document.getElementById('showSharedChatsToggle');
    const sharedGroupsToggle = document.getElementById('showSharedGroupsToggle');

    function applyToggle(toggle, enabled) {
        toggle?.classList.toggle('active', enabled);
    }

    function applyChoice(field, value) {
        document.querySelectorAll(`[data-profile-visibility-field="${field}"] button`).forEach(button => {
            button.classList.toggle('active', button.dataset.value === value);
        });
    }

    async function save() {
        const data = await post('/settings/profile/visibility', prefs);

        if (data.success) {
            showMsg(msg, 'success', 'Настройки профиля сохранены');

            return;
        }

        const first = data.errors ? Object.values(data.errors)[0]?.[0] : (data.message ?? 'Ошибка');
        showMsg(msg, 'error', first);
    }

    applyToggle(sharedChatsToggle, prefs.show_shared_chats);
    applyToggle(sharedGroupsToggle, prefs.show_shared_groups);
    [
        'profile_access',
        'online_status_visibility',
        'shared_friends_count_visibility',
        'feed_posts_count_visibility',
        'profile_posts_visibility',
        'avatar_visibility',
    ].forEach(field => applyChoice(field, prefs[field]));

    sharedChatsToggle?.addEventListener('click', async () => {
        prefs.show_shared_chats = !prefs.show_shared_chats;
        applyToggle(sharedChatsToggle, prefs.show_shared_chats);
        await save();
    });

    sharedGroupsToggle?.addEventListener('click', async () => {
        prefs.show_shared_groups = !prefs.show_shared_groups;
        applyToggle(sharedGroupsToggle, prefs.show_shared_groups);
        await save();
    });

    document.querySelectorAll('[data-profile-visibility-field] button').forEach(button => {
        button.addEventListener('click', async () => {
            const field = button.closest('[data-profile-visibility-field]')?.dataset.profileVisibilityField;

            if (!field || !button.dataset.value) {
                return;
            }

            prefs[field] = button.dataset.value;
            applyChoice(field, button.dataset.value);
            await save();
        });
    });
}

// ─── Init ─────────────────────────────────────────────────────────────────────
function initPagination() {
    document.querySelectorAll('[data-paginate]').forEach(container => {
        const init = parseInt(container.dataset.pageInit ?? '3');
        const step = parseInt(container.dataset.pageStep ?? '10');
        const items = [...container.children].filter(el => el.classList.contains('paginate-item'));

        if (items.length <= init) { return; }

        let shown = init;

        const footer = document.createElement('div');
        footer.className = 'paginate-footer';
        const btn = document.createElement('button');
        btn.className = 'paginate-btn';
        btn.textContent = 'Показать ещё';
        footer.appendChild(btn);
        container.after(footer);

        function apply() {
            items.forEach((item, i) => {
                if (i < shown) {
                    item.style.display = '';
                    item.classList.remove('paginate-peek');
                } else if (i === shown) {
                    item.style.display = '';
                    item.classList.add('paginate-peek');
                } else {
                    item.style.display = 'none';
                    item.classList.remove('paginate-peek');
                }
            });

            if (shown >= items.length) {
                footer.remove();
            }
        }

        btn.addEventListener('click', () => {
            shown = Math.min(shown + step, items.length);
            apply();
        });

        apply();
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initNav();
    initAvatar();
    initProfile();
    initPassword();
    initBackupCode();
    initChatSecurityDialogs();
    initChatSecurityPanel().catch(() => {
        showChatSecurityMsg('error', 'Не удалось загрузить настройки шифрования');
    });
    initTwoFactor();
    initNotifications();
    initProfileVisibility();
    initPagination();
});
