import "../../css/pages/friends.scss";
import "../app";

let codeExpiresAt = null;
let countdownInterval = null;

const expiresAt = window.Laravel.activeCodeExpiresAt;

if (expiresAt) {
    const codeExpiresAt = Date.parse(expiresAt);

    startTimer();
}

function startTimer() {
    if (countdownInterval) clearInterval(countdownInterval);

    countdownInterval = setInterval(() => {
        const now = Date.now();
        const distance = codeExpiresAt - now;

        if (distance < 0) {
            clearInterval(countdownInterval);
            document.getElementById("codeTimer").style.display = "none";
            document.getElementById("codeDisplay").classList.add("expired");
            document.getElementById("codeDisplay").textContent = "Код истёк";
            return;
        }

        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);
        document.getElementById("codeTimer").textContent =
            `Истекает через: ${minutes.toString().padStart(2, "0")}:${seconds.toString().padStart(2, "0")}`;
        document.getElementById("codeTimer").style.display = "block";
    }, 1000);
}

async function createCode() {
    const response = await fetch("/friends/code", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')
                .content,
        },
    });
    const data = await response.json();

    if (data.success) {
        const codeDisplay = document.getElementById("codeDisplay");
        codeDisplay.textContent = data.code;
        codeDisplay.classList.add("active");
        codeDisplay.classList.remove("expired");
        codeExpiresAt = new Date(data.expires_at).getTime();
        startTimer();
    }
}

async function sendFriendRequest() {
    const code = document.getElementById("searchCode").value.trim();
    const messageEl = document.getElementById("addMessage");

    if (!code) {
        showMessage("Введите код", "error");
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
        document.getElementById("friend-" + friendId).remove();
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
    let count = parseInt(badge.textContent) + delta;
    badge.textContent = count;
    if (count <= 0) {
        badge.style.display = "none";
        document.querySelector("#requestsList .empty")?.remove();
        document.querySelector("#requestsList").innerHTML =
            '<div class="empty">Нет входящих запросов</div>';
    }
}

function bindEvents() {
    document.getElementById('createCodeBtn').addEventListener('click', createCode);
    document.getElementById('sendRequestBtn').addEventListener('click', sendFriendRequest);

    document.getElementById('requestsList').addEventListener('click', (e) => {
        const btn = e.target.closest('[data-action]');
        if (!btn) { return; }
        const requestId = parseInt(btn.dataset.requestId);
        if (btn.dataset.action === 'accept') { acceptRequest(requestId); }
        if (btn.dataset.action === 'decline') { declineRequest(requestId); }
    });

    document.getElementById('friendsList').addEventListener('click', (e) => {
        const btn = e.target.closest('[data-friend-id]');
        if (!btn) { return; }
        removeFriend(parseInt(btn.dataset.friendId));
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
        // Add new request to the list
        const requestsList = document.getElementById("requestsList");
        const emptyEl = requestsList.querySelector(".empty");
        if (emptyEl) emptyEl.remove();

        const item = document.createElement('div');
        item.className = 'request-item';
        item.id = 'request-new-' + Date.now();
        const name = document.createElement('span');
        name.className = 'name';
        name.textContent = e.sender_login;
        const actions = document.createElement('div');
        actions.className = 'request-actions';
        const acceptBtn = document.createElement('button');
        acceptBtn.className = 'btn btn-success btn-sm';
        acceptBtn.dataset.action = 'accept';
        acceptBtn.dataset.requestId = e.friend_request_id;
        acceptBtn.textContent = 'Принять';
        const declineBtn = document.createElement('button');
        declineBtn.className = 'btn btn-danger btn-sm';
        declineBtn.dataset.action = 'decline';
        declineBtn.dataset.requestId = e.friend_request_id;
        declineBtn.textContent = 'Отклонить';
        actions.append(acceptBtn, declineBtn);
        item.append(name, actions);
        requestsList.prepend(item);
        updateRequestCount(1);
    });

    channel.listen(".friend.accepted", (e) => {
        // Reload to show new friend
        location.reload();
    });
}
