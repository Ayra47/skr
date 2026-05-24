import "../../css/pages/friends.scss";
import "../app";
import QRCode from "qrcode";
import { initAccentOnLoad } from "../utils/accent.js";
import { initThemeOnLoad } from "../utils/theme.js";

// ── invite card ──────────────────────────────────────────────────────────────

const CODE_TTL = 300; // seconds
const RING_R   = 38;
const RING_C   = 2 * Math.PI * RING_R; // ≈ 238.76

let codeExpiresAt    = null;
let countdownInterval = null;

const SVG_KEY     = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="15" r="4"/><path d="M10.8 12.2L20 3"/><path d="M16 7l3 3"/><path d="M14 9l3 3"/></svg>`;
const SVG_COPY    = `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>`;
const SVG_QR      = `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3M21 14v7M14 21h3M17 17h4"/></svg>`;
const SVG_REFRESH = `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/></svg>`;

function fmtTime(s) {
    const m   = Math.floor(s / 60);
    const sec = s % 60;
    return `${m}:${sec.toString().padStart(2, '0')}`;
}

function remainingSeconds() {
    if (!codeExpiresAt) { return 0; }
    return Math.max(0, Math.round((codeExpiresAt - Date.now()) / 1000));
}

function buildRingSvg(remaining) {
    const pct     = Math.max(0, remaining / CODE_TTL);
    const expired = remaining <= 0;
    const color   = (!expired && remaining < 30) ? 'var(--danger)' : (expired ? 'var(--danger)' : 'var(--accent)');
    const offset  = RING_C * (1 - pct);
    return `
        <svg width="84" height="84" style="transform:rotate(-90deg);display:block;">
            <circle cx="42" cy="42" r="${RING_R}" fill="none" stroke="#272b36" stroke-width="3"/>
            <circle id="ringProgress" cx="42" cy="42" r="${RING_R}" fill="none"
                stroke="${color}" stroke-width="3" stroke-linecap="round"
                stroke-dasharray="${RING_C}" stroke-dashoffset="${offset}"
                style="transition:stroke-dashoffset 1s linear,stroke .25s"/>
        </svg>
        <div class="fc-ring-text">
            <span class="fc-ring-time" id="ringTime">${expired ? '0:00' : fmtTime(remaining)}</span>
            <span class="fc-ring-label ${expired ? 'fc-ring-label--expired' : ''}" id="ringLabel">${expired ? 'истёк' : 'осталось'}</span>
        </div>`;
}

function buildEmptyState() {
    return `
        <div class="fc-empty-state">
            <div class="fc-empty-placeholder">Нет активного кода</div>
            <button class="fc-create-btn" id="createCodeBtn">
                ${SVG_KEY}
                Создать код
            </button>
        </div>`;
}

function buildActiveState(code, remaining) {
    const expired = remaining <= 0;
    const digits  = code.split('').map(d => `<span class="fc-digit">${d}</span>`).join('');
    return `
        <div class="fc-active-panel${expired ? ' fc-active-panel--expired' : ''}" id="fcActivePanel">
            <div class="fc-ring-wrap" id="fcRingWrap">
                ${buildRingSvg(remaining)}
            </div>
            <div class="fc-digits-wrap">
                <div class="fc-digits-label">Ваш код</div>
                <div class="fc-digits${expired ? ' fc-digits--expired' : ''}" id="fcDigits">${digits}</div>
            </div>
            <div class="fc-action-col">
                <button class="fc-action-btn" id="copyBtn" ${expired ? 'disabled' : ''}>
                    ${SVG_COPY} <span id="copyLabel">Копировать</span>
                </button>
                <button class="fc-action-btn" id="qrBtn" ${expired ? 'disabled' : ''}>
                    ${SVG_QR} QR-код
                </button>
                <button class="fc-action-btn" id="refreshBtn">
                    ${SVG_REFRESH} Обновить
                </button>
            </div>
        </div>`;
}

function renderEmpty() {
    document.getElementById('inviteBody').innerHTML = buildEmptyState();
    document.getElementById('createCodeBtn').addEventListener('click', createCode);
}

function renderActive(code, expiresAtMs) {
    codeExpiresAt = expiresAtMs;
    const remaining = remainingSeconds();
    document.getElementById('inviteBody').innerHTML = buildActiveState(code, remaining);
    document.getElementById('copyBtn').addEventListener('click', () => copyCode(code));
    document.getElementById('qrBtn').addEventListener('click', () => showQrModal(code));
    document.getElementById('refreshBtn').addEventListener('click', createCode);
    startTimer();
}

function updateRing() {
    const remaining = remainingSeconds();
    const expired   = remaining <= 0;
    const pct       = Math.max(0, remaining / CODE_TTL);
    const color     = (!expired && remaining < 30) ? 'var(--danger)' : (expired ? 'var(--danger)' : 'var(--accent)');

    const progress = document.getElementById('ringProgress');
    const timeEl   = document.getElementById('ringTime');
    const labelEl  = document.getElementById('ringLabel');
    if (!progress) { return; }

    progress.style.strokeDashoffset = RING_C * (1 - pct);
    progress.setAttribute('stroke', color);
    timeEl.textContent  = expired ? '0:00' : fmtTime(remaining);
    labelEl.textContent = expired ? 'истёк' : 'осталось';
    labelEl.className   = 'fc-ring-label' + (expired ? ' fc-ring-label--expired' : '');

    if (expired) {
        clearInterval(countdownInterval);
        countdownInterval = null;
        document.getElementById('fcActivePanel')?.classList.add('fc-active-panel--expired');
        document.getElementById('fcDigits')?.classList.add('fc-digits--expired');
        const copyBtn = document.getElementById('copyBtn');
        const qrBtn   = document.getElementById('qrBtn');
        if (copyBtn) { copyBtn.disabled = true; }
        if (qrBtn)   { qrBtn.disabled   = true; }
    }
}

function startTimer() {
    if (countdownInterval) { clearInterval(countdownInterval); }
    countdownInterval = setInterval(updateRing, 1000);
}

async function showQrModal(code) {
    const url = `${window.location.origin}/friends/join/${code}`;
    const dataUrl = await QRCode.toDataURL(url, {
        width: 240,
        margin: 2,
        color: { dark: 'var(--text-light)', light: 'var(--panel-medium)' },
    });

    const backdrop = document.createElement('div');
    backdrop.className = 'fc-qr-backdrop';
    backdrop.innerHTML = `
        <div class="fc-qr-modal" role="dialog" aria-modal="true">
            <div class="fc-qr-header">
                <span>QR-код</span>
                <button class="fc-qr-close" aria-label="Закрыть">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <img class="fc-qr-img" src="${dataUrl}" alt="QR-код" width="240" height="240">
            <p class="fc-qr-hint">Отсканируйте код, чтобы отправить запрос в друзья</p>
        </div>`;

    const close = () => backdrop.remove();
    backdrop.querySelector('.fc-qr-close').addEventListener('click', close);
    backdrop.addEventListener('click', (e) => { if (e.target === backdrop) { close(); } });
    document.addEventListener('keydown', function esc(e) {
        if (e.key === 'Escape') { close(); document.removeEventListener('keydown', esc); }
    });

    document.body.appendChild(backdrop);
    requestAnimationFrame(() => backdrop.classList.add('fc-qr-backdrop--open'));
}

function copyCode(code) {
    navigator.clipboard?.writeText(code);
    const label = document.getElementById('copyLabel');
    const btn   = document.getElementById('copyBtn');
    if (!label || !btn) { return; }
    label.textContent = 'Скопировано';
    btn.classList.add('fc-action-btn--copied');
    setTimeout(() => {
        label.textContent = 'Копировать';
        btn.classList.remove('fc-action-btn--copied');
    }, 1800);
}

async function createCode() {
    const response = await fetch("/friends/code", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content,
        },
    });
    const data = await response.json();

    if (data.success) {
        renderActive(data.code, new Date(data.expires_at).getTime());
    }
}

// ── initialise invite block ──────────────────────────────────────────────────
const initCode      = window.Laravel.activeCode;
const initExpiresAt = window.Laravel.activeCodeExpiresAt;

if (initCode && initExpiresAt) {
    renderActive(initCode, Date.parse(initExpiresAt));
} else {
    renderEmpty();
}

async function sendFriendRequest() {
    const code = document.getElementById("searchCode").value.replace(/\D/g, '');

    if (code.length !== 10) {
        showMessage("Введите 10 цифр", "error");
        return;
    }

    const response = await fetch("/friends/request", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')
                .content,
        },
        body: JSON.stringify({ code }),
    });
    const data = await response.json();

    showMessage(data.message, data.success ? "success" : "error");

    if (data.success) {
        document.getElementById("searchCode").value = "";
        document.getElementById("digitCount").textContent = "0";
        document.getElementById("digitCount").className = "";
        sendBtn.disabled = true;
        sendBtn.classList.remove('fa-send-btn--ready');
    }
}

async function acceptRequest(requestId) {
    const response = await fetch("/friends/request/accept", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')
                .content,
        },
        body: JSON.stringify({ request_id: requestId }),
    });
    const data = await response.json();

    if (data.success) {
        document.getElementById("request-" + requestId).remove();
        updateRequestCount(-1);
        // Reload to show new friend
        setTimeout(() => location.reload(), 500);
    }
}

async function declineRequest(requestId) {
    const response = await fetch("/friends/request/decline", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')
                .content,
        },
        body: JSON.stringify({ request_id: requestId }),
    });
    const data = await response.json();

    if (data.success) {
        document.getElementById("request-" + requestId).remove();
        updateRequestCount(-1);
    }
}

async function removeFriend(friendId) {
    if (!confirm("Удалить этого друга?")) return;

    const response = await fetch("/friends/remove", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')
                .content,
        },
        body: JSON.stringify({ friend_id: friendId }),
    });
    const data = await response.json();

    if (data.success) {
        document.getElementById(`friend-${friendId}`)?.remove();
        if (! document.querySelector('#friendsList .fl-item')) {
            document.getElementById('friendsList').innerHTML = '<div class="fl-empty">Пока нет друзей</div>';
        }
    }
}

function showMessage(text, type) {
    const messageEl = document.getElementById("addMessage");
    messageEl.textContent = text;
    messageEl.className = "message " + type;
    messageEl.style.display = "block";
    setTimeout(() => {
        messageEl.style.display = "none";
    }, 3000);
}

function updateRequestCount(delta) {
    const badge = document.getElementById("requestBadge");
    let count = parseInt(badge.textContent || '0') + delta;
    badge.textContent = count;
    badge.classList.toggle('fr-badge--hidden', count <= 0);

    if (count <= 0) {
        const list = document.getElementById("requestsList");
        if (! list.querySelector('.fr-item')) {
            list.innerHTML = '<div class="fr-empty">Нет входящих запросов</div>';
        }
    }
}

function buildRequestItem(requestId, login) {
    const hue    = login.charCodeAt(0) * 37 % 360;
    const initial = login[0].toUpperCase();
    const item    = document.createElement('div');
    item.className = 'fr-item';
    item.id = `request-${requestId}`;
    item.innerHTML = `
        <div class="fr-avatar" style="--hue:${hue}">${initial}</div>
        <div class="fr-info">
            <div class="fr-name">${login}</div>
            <div class="fr-sub">запрос по коду · только что</div>
        </div>
        <button class="fr-accept-btn" data-action="accept" data-request-id="${requestId}">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L20 6"/></svg>
            Принять
        </button>
        <button class="fr-decline-btn" data-action="decline" data-request-id="${requestId}">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>`;
    return item;
}

function bindEvents() {
    // ── search code input formatting ─────────────────────────────────────────
    const searchInput = document.getElementById('searchCode');
    const sendBtn     = document.getElementById('sendRequestBtn');
    const digitCount  = document.getElementById('digitCount');

    searchInput.addEventListener('input', () => {
        const raw     = searchInput.value.replace(/\D/g, '').slice(0, 10);
        const pairs   = raw.match(/.{1,2}/g) ?? [];
        searchInput.value = pairs.join(' ');

        const count = raw.length;
        digitCount.textContent = count;
        digitCount.className   = count === 10 ? 'fa-counter-valid' : '';

        sendBtn.disabled = count !== 10;
        sendBtn.classList.toggle('fa-send-btn--ready', count === 10);
    });

    searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !sendBtn.disabled) { sendFriendRequest(); }
    });

    document.getElementById('sendRequestBtn').addEventListener('click', sendFriendRequest);

    document.getElementById('requestsList').addEventListener('click', (e) => {
        const btn = e.target.closest('[data-action]');
        if (!btn) { return; }
        const requestId = parseInt(btn.dataset.requestId);
        if (btn.dataset.action === 'accept') { acceptRequest(requestId); }
        if (btn.dataset.action === 'decline') { declineRequest(requestId); }
    });

    document.getElementById('friendsList').addEventListener('click', (e) => {
        const btn = e.target.closest('.fl-delete-btn');
        if (!btn) { return; }
        removeFriend(parseInt(btn.dataset.friendId));
    });

    document.getElementById('friendSearch').addEventListener('input', (e) => {
        const q = e.target.value.toLowerCase().trim();
        document.querySelectorAll('#friendsList .fl-item').forEach(item => {
            item.style.display = (!q || item.dataset.name.includes(q)) ? '' : 'none';
        });
    });
}

document.addEventListener('DOMContentLoaded', bindEvents);

// WebSocket for real-time notifications
const userId = window.Laravel.userId;

// Подписка на канал с обработкой ошибок
if (window.Echo && window.Echo.private) {
    const channel = window.Echo.private(`user.${userId}`);

    channel.error((error) => {
        console.error("WebSocket channel error:", error);
    });

    channel.listen(".friend.request", (e) => {
        const requestsList = document.getElementById("requestsList");
        requestsList.querySelector('.fr-empty')?.remove();
        requestsList.prepend(buildRequestItem(e.friend_request_id, e.sender_pseudonym));
        updateRequestCount(1);
    });

    channel.listen(".friend.accepted", (e) => {
        // Reload to show new friend
        location.reload();
    });
}

initThemeOnLoad();
initAccentOnLoad();
