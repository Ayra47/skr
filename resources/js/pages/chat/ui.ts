import Toastify from "toastify-js";
import "toastify-js/src/toastify.css";

export function showNotification(
    msg: string,
    time = 3000,
    callback: () => void = () => {},
): void {
    Toastify({
        text: msg,
        duration: time,
        newWindow: true,
        close: true,
        gravity: "bottom",
        position: "right",
        stopOnFocus: true,
        style: {
            background: "var(--panel-2)",
            border: "1px solid var(--border-2)",
            color: "var(--text)",
        },
        onClick: callback,
    }).showToast();
}

export function setAvatarEl(
    el: HTMLElement,
    login: string,
    avatarUrl: string | null,
): void {
    const dot = el.querySelector(".online-dot");
    if (avatarUrl) {
        el.textContent = "";
        const img = document.createElement("img");
        img.src = avatarUrl;
        img.alt = "";
        img.className = "avatar-img";
        el.appendChild(img);
    } else {
        el.textContent = login.charAt(0).toUpperCase();
    }
    if (dot) {
        el.appendChild(dot);
    }
}

export function escapeHtml(str: string): string {
    return str
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
}

export function showKeyLossWarning(): void {
    document.getElementById("chatPane")!.insertAdjacentHTML(
        "afterbegin",
        '<div class="key-warning">⚠ сгенерирован новый ключ шифрования. сохраните резервную копию через «↓ экспорт» (режим «устройство»), иначе при очистке браузера история станет недоступной.</div>',
    );
}

export function updateOnlineIndicator(userId: number, isOnline: boolean): void {
    document
        .querySelectorAll(`[data-partner-id="${userId}"] .online-dot`)
        .forEach((dot) => dot.classList.toggle("online", isOnline));
}

export function updateAllOnlineIndicators(onlineUsers: Set<number>): void {
    document
        .querySelectorAll<HTMLElement>(".conversation-item[data-partner-id]")
        .forEach((item) => {
            const uid = parseInt(item.dataset.partnerId!);
            item
                .querySelectorAll(".online-dot")
                .forEach((dot) => dot.classList.toggle("online", onlineUsers.has(uid)));
        });
}

export function updateChatHeaderStatus(isOnline: boolean): void {
    const el = document.getElementById("chatPartnerStatus");
    if (el) {
        el.textContent = isOnline ? "в сети" : "";
    }
}

export function autoResize(el: HTMLTextAreaElement): void {
    el.style.height = "auto";
    el.style.height = Math.min(el.scrollHeight, 120) + "px";
}

export function updateSendBtn(): void {
    const btn = document.getElementById("sendBtn") as HTMLButtonElement | null;
    const input = document.getElementById("messageInput") as HTMLTextAreaElement | null;
    if (input && btn) {
        btn.classList.toggle("active", input.value.trim().length > 0);
    }
}
