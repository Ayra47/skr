import "./pusher";

const userId = window.Laravel?.userId;
if (!userId) { throw new Error("bell: no userId"); }

const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

// ─── State ────────────────────────────────────────────────────────────────────
let bellItems = [];
let popoverEl = null;

// ─── Icon/colour map ──────────────────────────────────────────────────────────
const TYPE_META = {
    reaction: {
        color: 'var(--pink-accent)',
        bg: 'rgba(var(--pink-accent-rgb, 241 156 162), 0.1)',
        border: 'rgba(var(--pink-accent-rgb, 241 156 162), 0.3)',
        icon: '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>',
    },
    reply: {
        color: 'var(--success)',
        bg: 'var(--success-soft)',
        border: 'var(--success-soft-2)',
        icon: '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    },
    security: {
        color: 'var(--gold-primary)',
        bg: 'var(--gold-soft)',
        border: 'var(--gold-soft-2)',
        icon: '<path d="M12 3l8 3v6c0 5-3.5 8.5-8 9-4.5-.5-8-4-8-9V6l8-3z"/>',
    },
    key: {
        color: 'var(--gold-primary)',
        bg: 'var(--gold-soft)',
        border: 'var(--gold-soft-2)',
        icon: '<circle cx="8" cy="15" r="4"/><path d="M10.8 12.2L20 3"/><path d="M16 7l3 3"/>',
    },
    friend: {
        color: 'var(--blue-soft)',
        bg: 'rgba(var(--blue-soft-rgb, 138 180 248), 0.1)',
        border: 'rgba(var(--blue-soft-rgb, 138 180 248), 0.3)',
        icon: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/>',
    },
    system: {
        color: 'var(--text-tertiary)',
        bg: 'rgba(var(--text-tertiary-rgb, 138 143 156), 0.1)',
        border: 'rgba(var(--text-tertiary-rgb, 138 143 156), 0.3)',
        icon: '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
    },
};

function meta(type) {
    return TYPE_META[type] ?? TYPE_META.system;
}

// ─── Time formatting ──────────────────────────────────────────────────────────
function relativeTime(iso) {
    const diff = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
    if (diff < 60) { return 'только что'; }
    if (diff < 3600) { return Math.floor(diff / 60) + ' мин'; }
    if (diff < 86400) { return Math.floor(diff / 3600) + ' ч'; }
    return Math.floor(diff / 86400) + ' д';
}

// ─── Badge ────────────────────────────────────────────────────────────────────
function updateBadge() {
    const badge = document.getElementById('nav-bell-badge');
    if (!badge) { return; }
    const count = bellItems.filter(x => x.unread).length;
    if (count > 0) {
        badge.textContent = count > 99 ? '99+' : String(count);
        badge.style.display = 'flex';
    } else {
        badge.style.display = 'none';
    }
}

// ─── Popover rendering ────────────────────────────────────────────────────────
function renderRow(item) {
    const m = meta(item.type);
    return `
        <div style="
            position:relative; display:flex; gap:12px; padding:12px 14px;
            background:${item.unread ? 'rgba(var(--gold-primary-rgb, 232 166 86), 0.025)' : 'transparent'};
            border-bottom:1px solid var(--border);
        ">
            ${item.unread ? `<span style="position:absolute;left:-1px;top:14px;width:3px;height:18px;
                border-radius:0 3px 3px 0;background:var(--gold-primary);"></span>` : ''}
            <span style="
                width:30px;height:30px;border-radius:8px;flex-shrink:0;
                background:${m.bg};border:1px solid ${m.border};color:${m.color};
                display:flex;align-items:center;justify-content:center;
            ">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">${m.icon}</svg>
            </span>
            <div style="flex:1;min-width:0;">
                <div style="display:flex;gap:8px;align-items:baseline;">
                    <span style="font-size:12.5px;font-weight:500;color:var(--text-light);
                        overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;">${item.title}</span>
                    <span style="font-size:10.5px;color:var(--text-tertiary);flex-shrink:0;">${relativeTime(item.created_at)}</span>
                </div>
                <div style="font-size:11.5px;color:var(--text-tertiary);margin-top:2px;
                    overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${item.body}</div>
            </div>
        </div>
    `;
}

function buildPopover() {
    const unreadCount = bellItems.filter(x => x.unread).length;
    const visible = bellItems.slice(0, 3);
    const teaser = bellItems[3];
    const remaining = Math.max(0, bellItems.length - 3);

    const wrap = document.createElement('div');
    const btn = document.getElementById('nav-bell-btn');
    const btnRect = btn?.getBoundingClientRect() ?? { top: 0, right: 0, bottom: 0 };

    wrap.style.cssText = `position:fixed;width:360px;z-index:200;${
        btnRect.bottom + 360 + 44 > window.innerHeight
            ? `bottom:${window.innerHeight - btnRect.top + 10}px;`
            : `top:${btnRect.bottom + 10}px;`
    }${
        btnRect.right > window.innerWidth / 2
            ? `right:${window.innerWidth - btnRect.right}px;`
            : `left:${btnRect.left}px;`
    }`;
    wrap.innerHTML = `
        <div style="
            background:var(--panel-light);border:1px solid var(--border-secondary);border-radius:14px;
            box-shadow:0 24px 60px rgba(0,0,0,.6);overflow:hidden;
        ">
            <div style="
                padding:14px 16px;border-bottom:1px solid var(--border-subtle);
                display:flex;align-items:center;gap:10px;
            ">
                <span style="font-size:13px;font-weight:600;color:var(--text-light);">Уведомления</span>
                ${unreadCount ? `<span style="
                    font-size:10px;color:var(--gold-primary);padding:2px 7px;
                    background:var(--gold-soft);border:1px solid var(--gold-soft-2);
                    border-radius:999px;">${unreadCount} новых</span>` : ''}
                <button id="bell-readall" ${unreadCount ? '' : 'disabled'} style="
                    margin-left:auto;height:26px;padding:0 10px;border-radius:6px;
                    border:1px solid var(--border-secondary);background:transparent;
                    color:${unreadCount ? 'var(--text-tertiary)' : 'var(--text-muted)'};
                    font-size:11.5px;font-family:inherit;cursor:${unreadCount ? 'pointer' : 'not-allowed'};
                    display:inline-flex;align-items:center;gap:5px;opacity:${unreadCount ? 1 : 0.5};
                ">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L20 6"/></svg>
                    Отметить всё
                </button>
            </div>
            <div style="max-height:320px;overflow:auto;">
                ${visible.length ? visible.map(renderRow).join('') : `
                    <div style="padding:24px;text-align:center;font-size:12.5px;color:var(--text-tertiary);">
                        Нет уведомлений
                    </div>
                `}
                ${teaser ? `
                    <div style="position:relative;height:50px;overflow:hidden;
                        mask-image:linear-gradient(180deg,black 0%,transparent 100%);
                        -webkit-mask-image:linear-gradient(180deg,black 0%,transparent 100%);">
                        ${renderRow(teaser)}
                    </div>
                ` : ''}
            </div>
            <a href="/notifications" style="
                display:flex;align-items:center;justify-content:center;gap:8px;
                padding:13px;border-top:1px solid var(--border-subtle);
                background:var(--panel-medium);color:var(--gold-primary);
                font-size:13px;font-weight:500;text-decoration:none;font-family:inherit;
            ">
                Все уведомления${remaining > 0 ? ` · ещё ${remaining}` : ''}
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 12h14"/><path d="M13 6l6 6-6 6"/>
                </svg>
            </a>
        </div>
    `;
    return wrap;
}

// ─── Popover open / close ─────────────────────────────────────────────────────
function closePopover() {
    if (popoverEl) { popoverEl.remove(); popoverEl = null; }
    document.removeEventListener('click', onOutside);
}

function onOutside(e) {
    const btn = document.getElementById('nav-bell-btn');
    if (popoverEl && !popoverEl.contains(e.target) && btn && !btn.contains(e.target)) {
        closePopover();
    }
}

function openPopover() {
    if (popoverEl) { return; }
    popoverEl = buildPopover();
    document.body.appendChild(popoverEl);

    const readAll = popoverEl.querySelector('#bell-readall');
    if (readAll) {
        readAll.addEventListener('click', (e) => {
            e.stopPropagation();
            fetch('/notifications/read-all', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            }).then(() => {
                bellItems = bellItems.map(x => ({ ...x, unread: false }));
                updateBadge();
                closePopover();
            });
        });
    }

    setTimeout(() => document.addEventListener('click', onOutside), 0);
}

// ─── Load initial notifications ───────────────────────────────────────────────
function loadNotifications() {
    fetch('/notifications?json=1', { headers: { Accept: 'application/json' } })
        .then(r => r.json())
        .then(data => {
            bellItems = data.items ?? [];
            updateBadge();
        })
        .catch(() => {});
}

// ─── Bell button wiring ───────────────────────────────────────────────────────
function bindBell() {
    const btn = document.getElementById('nav-bell-btn');
    if (!btn) { return; }
    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        if (popoverEl) { closePopover(); } else { openPopover(); }
    });
}

// ─── WebSocket ────────────────────────────────────────────────────────────────
function subscribeWs() {
    if (!window.Echo) { return; }

    window.Echo.private(`user.${userId}`)
        .listen('.notification', (e) => {
            const item = {
                id: e.id ?? crypto.randomUUID(),
                type: e.data?.type ?? e.type ?? 'system',
                title: e.data?.title ?? e.title ?? '',
                body: e.data?.body ?? e.body ?? '',
                unread: true,
                created_at: new Date().toISOString(),
            };
            bellItems.unshift(item);
            updateBadge();
            if (popoverEl) { closePopover(); openPopover(); }
        })
        .listen('.friend.request', (e) => {
            const item = {
                id: e.id ?? crypto.randomUUID(),
                type: 'friend',
                title: `${e.sender_login} отправил запрос в друзья`,
                body: '',
                unread: true,
                created_at: new Date().toISOString(),
            };
            bellItems.unshift(item);
            updateBadge();
            if (popoverEl) { closePopover(); openPopover(); }
        })
        .listen('.friend.accepted', (e) => {
            const item = {
                id: e.id ?? crypto.randomUUID(),
                type: 'friend',
                title: `${e.sender_login ?? 'Друг'} принял ваш запрос`,
                body: '',
                unread: true,
                created_at: new Date().toISOString(),
            };
            bellItems.unshift(item);
            updateBadge();
            if (popoverEl) { closePopover(); openPopover(); }
        });
}

// ─── Init ─────────────────────────────────────────────────────────────────────
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => { loadNotifications(); bindBell(); subscribeWs(); });
} else {
    loadNotifications();
    bindBell();
    subscribeWs();
}
