import "../../css/pages/notifications.scss";
import "../app";
import { initAccentOnLoad } from "../utils/accent.js";
import { initThemeOnLoad } from "../utils/theme.js";

const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

// ─── State ────────────────────────────────────────────────────────────────────
let allItems = [];
let activeFilter = 'all';

// ─── Type config ──────────────────────────────────────────────────────────────
const TYPE_CFG = {
    security: { color: 'var(--accent)', bg: 'color-mix(in srgb, var(--accent) 10%, transparent)', border: 'color-mix(in srgb, var(--accent) 22%, transparent)', icon: '<path d="M12 3l8 3v6c0 5-3.5 8.5-8 9-4.5-.5-8-4-8-9V6l8-3z"/>' },
    key:      { color: 'var(--accent)', bg: 'color-mix(in srgb, var(--accent) 10%, transparent)', border: 'color-mix(in srgb, var(--accent) 22%, transparent)', icon: '<circle cx="8" cy="15" r="4"/><path d="M10.8 12.2L20 3"/><path d="M16 7l3 3"/>' },
    friend:   { color: 'var(--blue-soft)', bg: 'rgba(var(--blue-soft-rgb, 138 180 248), 0.1)', border: 'rgba(var(--blue-soft-rgb, 138 180 248), 0.3)', icon: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/>' },
    reaction: { color: 'var(--error-red)', bg: 'rgba(var(--error-red-rgb, 248 113 113), 0.1)', border: 'rgba(var(--error-red-rgb, 248 113 113), 0.3)', icon: '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 1 0-7.8 7.8l1 1.1L12 21l7.8-7.5 1-1.1a5.5 5.5 0 0 0 0-7.8z"/>' },
    reply:    { color: 'var(--success)', bg: 'var(--success-soft)', border: 'var(--success-soft-2)', icon: '<path d="M21 11.5a8.4 8.4 0 0 1-7.6 8.5 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 8-8h.5a8.5 8.5 0 0 1 8 8z"/>' },
    system:   { color: 'var(--text-tertiary)', bg: 'rgba(255,255,255,.05)', border: 'var(--border-secondary)', icon: '<path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>' },
};

function typeCfg(type) {
    return TYPE_CFG[type] ?? TYPE_CFG.system;
}

// ─── Time formatting ──────────────────────────────────────────────────────────
function relativeTime(iso) {
    const diff = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
    if (diff < 60) { return 'только что'; }
    if (diff < 3600) { return Math.floor(diff / 60) + ' мин назад'; }
    if (diff < 86400) { return Math.floor(diff / 3600) + ' ч назад'; }
    if (diff < 172800) { return 'вчера'; }
    return Math.floor(diff / 86400) + ' дн назад';
}

function isToday(iso) {
    return Date.now() - new Date(iso).getTime() < 86400 * 1000;
}

// ─── Actions per type ─────────────────────────────────────────────────────────
function actionsFor(item) {
    switch (item.type) {
        case 'security':
            return [
                { label: 'Это я', cls: 'primary', action: () => markRead(item.id) },
                { label: 'Не я — сменить пароль', cls: 'danger', href: '/settings' },
            ];
        case 'key':
            return [
                { label: 'Верифицировать', cls: 'primary', href: '/chats' },
                { label: 'Позже', cls: 'default', action: () => markRead(item.id) },
            ];
        case 'friend':
            if (item.friend_request_id) {
                return [
                    { label: 'Принять', cls: 'primary', action: () => respondToRequest(item, 'accept') },
                    { label: 'Отклонить', cls: 'default', action: () => respondToRequest(item, 'decline') },
                ];
            }
            return [{ label: 'Перейти к заявкам', cls: 'primary', href: '/friends' }];
        case 'reply':
        case 'reaction':
            return [{ label: 'Открыть пост', cls: 'default', href: item.post_id ? `/?post=${item.post_id}` : '/' }];
        default:
            return [];
    }
}

// ─── API ──────────────────────────────────────────────────────────────────────
function respondToRequest(item, action) {
    const url = action === 'accept' ? '/friends/request/accept' : '/friends/request/decline';
    const newStatus = action === 'accept' ? 'accepted' : 'declined';
    allItems = allItems.map(n => n.id === item.id
        ? { ...n, unread: false, friend_request_id: null, friend_request_status: newStatus }
        : n);
    render();
    markRead(item.id);
    fetch(url, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ request_id: item.friend_request_id }),
    }).catch(() => {});
}

function markRead(id) {
    allItems = allItems.map(n => n.id === id ? { ...n, unread: false } : n);
    render();
    fetch(`/notifications/${id}/read`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, Accept: 'application/json' },
    }).catch(() => {});
}

function markAllRead() {
    fetch('/notifications/read-all', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, Accept: 'application/json' },
    }).then(() => {
        allItems = allItems.map(n => ({ ...n, unread: false }));
        render();
    });
}

// ─── Render helpers ───────────────────────────────────────────────────────────
function svg(paths, size = 16) {
    return `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">${paths}</svg>`;
}

function renderCard(item) {
    const cfg = typeCfg(item.type);
    const actions = actionsFor(item);

    const actionsHtml = actions.length ? `
        <div class="notif-actions">
            ${actions.map((a, i) => {
                if (a.href) {
                    return `<a href="${a.href}" class="notif-action-btn ${a.cls}">${a.label}</a>`;
                }
                return `<button class="notif-action-btn ${a.cls}" data-action-id="${item.id}" data-action-idx="${i}">${a.label}</button>`;
            }).join('')}
        </div>
    ` : '';

    return `
        <article class="notif-card" data-id="${item.id}">
            ${item.unread ? '<span class="notif-card-accent"></span>' : ''}
            <span class="notif-icon" style="background:${cfg.bg};border:1px solid ${cfg.border};color:${cfg.color};">
                ${svg(cfg.icon)}
            </span>
            <div class="notif-body">
                <div class="notif-row">
                    <span class="notif-card-title">${escHtml(item.title)}</span>
                    ${item.subject ? `<span class="notif-subject">· ${escHtml(item.subject)}</span>` : ''}
                    <span class="notif-when">${relativeTime(item.created_at)}</span>
                </div>
                ${item.body ? `<p class="notif-text">${escHtml(item.body)}</p>` : ''}
                ${item.friend_request_status ? renderRequestStatus(item.friend_request_status) : actionsHtml}
            </div>
        </article>
    `;
}

function renderRequestStatus(status) {
    const isAccepted = status === 'accepted';
    const color = isAccepted ? 'var(--success)' : 'var(--text-tertiary)';
    const label = isAccepted ? 'Принято' : 'Отклонено';
    const icon = isAccepted
        ? '<path d="M5 13l4 4L20 6"/>'
        : '<path d="M18 6L6 18M6 6l12 12"/>';
    return `
        <div style="display:flex;align-items:center;gap:6px;margin-top:10px;font-size:12px;color:${color};">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">${icon}</svg>
            ${label}
        </div>`;
}

function escHtml(str) {
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// ─── Main render ──────────────────────────────────────────────────────────────
function render() {
    const unreadCount = allItems.filter(n => n.unread).length;

    // Badge in title
    const badge = document.getElementById('notif-badge');
    if (badge) {
        badge.textContent = unreadCount + ' новых';
        badge.style.display = unreadCount > 0 ? '' : 'none';
    }

    // "Mark all" button
    const markAllBtn = document.getElementById('notif-mark-all');
    if (markAllBtn) {
        markAllBtn.disabled = unreadCount === 0;
    }

    // Filter buttons
    document.querySelectorAll('.notif-filter-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.filter === activeFilter);
        if (btn.dataset.filter === 'unread') {
            btn.textContent = unreadCount > 0 ? `Непрочитанные · ${unreadCount}` : 'Непрочитанные';
        }
    });

    // Filter items
    const filtered = allItems.filter(n => {
        if (activeFilter === 'unread') { return n.unread; }
        if (activeFilter === 'security') { return n.type === 'security' || n.type === 'key'; }
        if (activeFilter === 'social') { return ['friend', 'reaction', 'reply'].includes(n.type); }
        return true;
    });

    // Group into today / earlier
    const today = filtered.filter(n => isToday(n.created_at));
    const earlier = filtered.filter(n => !isToday(n.created_at));

    const container = document.getElementById('notif-container');
    if (!container) { return; }

    if (filtered.length === 0) {
        container.innerHTML = '<div class="notif-empty">Здесь пока тихо</div>';
        return;
    }

    let html = '';
    if (today.length > 0) {
        html += '<div class="notif-group-label">Сегодня</div>';
        html += `<div class="notif-list">${today.map(renderCard).join('')}</div>`;
    }
    if (earlier.length > 0) {
        html += '<div class="notif-group-label" style="margin-top:24px;">Ранее</div>';
        html += `<div class="notif-list">${earlier.map(renderCard).join('')}</div>`;
    }
    container.innerHTML = html;

    // Wire action buttons
    container.querySelectorAll('[data-action-id]').forEach(btn => {
        btn.addEventListener('click', () => {
            const item = allItems.find(n => n.id === btn.dataset.actionId);
            if (!item) { return; }
            const actions = actionsFor(item).filter(a => !a.href);
            const idx = parseInt(btn.dataset.actionIdx ?? '0', 10);
            actions[idx]?.action?.();
        });
    });
}

// ─── Init ─────────────────────────────────────────────────────────────────────
function init() {
    // Filter buttons
    document.querySelectorAll('.notif-filter-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            activeFilter = btn.dataset.filter;
            render();
        });
    });

    // Mark all button
    document.getElementById('notif-mark-all')?.addEventListener('click', markAllRead);

    // Load data
    fetch('/notifications?json=1', { headers: { Accept: 'application/json' } })
        .then(r => r.json())
        .then(data => {
            allItems = data.items ?? [];
            render();
        })
        .catch(() => {
            const container = document.getElementById('notif-container');
            if (container) { container.innerHTML = '<div class="notif-empty">Не удалось загрузить уведомления</div>'; }
        });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

initThemeOnLoad();
initAccentOnLoad();
