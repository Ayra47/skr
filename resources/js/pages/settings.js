import "../../css/pages/settings.scss";
import "../pusher";

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
            const req = indexedDB.open('skr_chat', 2);
            req.onsuccess = e => { this.db = e.target.result; resolve(); };
            req.onerror = () => reject(req.error);
            req.onupgradeneeded = () => {};
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

    document.getElementById('profileSaveBtn').addEventListener('click', async () => {
        const btn   = document.getElementById('profileSaveBtn');
        const login = document.getElementById('fieldLogin').value.trim();
        const email = document.getElementById('fieldEmail').value.trim() || null;

        btn.disabled = true;
        const data = await post('/settings/profile', {
            login,
            pseudonym: document.getElementById('fieldPseudo').value.trim(),
            email,
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
    initTwoFactor();
    initNotifications();
    initPagination();
});
