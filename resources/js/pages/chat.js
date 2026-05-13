import "../../css/pages/chat.scss";
import "../app";

import { IDB } from "./chat/idb";
import { CSRF } from "./chat/api";
import { bindEvents } from "./chat/events";
import { setAvatarEl } from "./chat/ui";
import { loadOrGenerateKeyPair } from "./chat/keys";
import { loadStoragePreference } from "./chat/storage";
import { startChatWithFriend } from "./chat/messages";
import { initWebSocket } from "./chat/websocket";

(async () => {
    await IDB.open();
    initWebSocket();
    bindEvents();

    // Apply avatars to Blade-rendered sidebar items
    document.querySelectorAll(".conversation-item").forEach((item) => {
        const avatarUrl = item.dataset.avatarUrl;
        const login = item.dataset.partnerLogin ?? "";
        const el = item.querySelector(".conv-avatar");
        if (el && avatarUrl) {
            setAvatarEl(el, login, avatarUrl);
        }
    });

    await loadOrGenerateKeyPair();
    await loadStoragePreference();

    // Heartbeat: update last_seen_at every 30 seconds
    const sendHeartbeat = () =>
        fetch("/ping", {
            method: "POST",
            headers: { "X-CSRF-TOKEN": CSRF, Accept: "application/json" },
        }).catch(() => {});
    sendHeartbeat();
    setInterval(sendHeartbeat, 30_000);

    const params = new URLSearchParams(location.search);
    const withUserId = parseInt(params.get("with") ?? "0");
    if (withUserId) {
        const el = document.querySelector(`[data-partner-id="${withUserId}"]`);
        if (el) {
            el.click();
        } else {
            const partnerLogin = params.get("login") ?? "пользователь";
            await startChatWithFriend(withUserId, partnerLogin);
        }
    }
})();
