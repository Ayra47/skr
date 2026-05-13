import { AUTH_USER_ID } from "./constants";
import { state } from "./state";
import { IDB } from "./idb";
import { Crypto } from "./crypto";
import { fetchJson, post, patch, del, delWithBody } from "./api";
import { openContextMenu } from "./context-menu";
import {
    showNotification,
    setAvatarEl,
    escapeHtml,
    autoResize,
    updateSendBtn,
    updateChatHeaderStatus,
} from "./ui";
import { getPartnerPublicKey, updateKeyChangeWarn } from "./keys";
import { renderFileBubble, renderPhotoBubble, type FilePayload } from "./file-display";
import { renderLocationMapBubble, freezeSession } from "./location-map";
import { clearLiveSession, stopSessionByUuid } from "./location";
import type { Message } from "./types";

interface ReplyTo {
    id: number;
    snippet: string;
    sender_alias: string;
}

interface TextMessageContent {
    type: "text";
    text: string;
    reply_to?: ReplyTo;
    forwarded_from?: string;
}

interface FileMessageContent {
    type: "file";
    text?: string;
    file: FilePayload;
    expires_at?: string;
}

interface PhotoMessageContent {
    type: "photo";
    text?: string;
    preview: FilePayload;
    original: FilePayload;
    expires_at?: string;
}

interface LocationMessageContent {
    type: "location";
    lat: number;
    lng: number;
    accuracy: number;
}

interface LocationLiveMessageContent {
    type: "location_live";
    session_id: string;
    lat: number;
    lng: number;
    accuracy: number;
    duration_minutes: number;
    expires_at: string;
}

let replyingTo: { msgId: number; snippet: string; senderAlias: string } | null = null;
let pinnedMsgIds = new Set<number>();
let isSelectMode = false;
let selectedIds = new Set<number>();

interface MessagesResponse {
    success: boolean;
    data: Message[];
    has_more: boolean;
}

interface SendResponse {
    success: boolean;
    id: number;
    created_at: string;
}

interface ConversationResponse {
    success: boolean;
    conversation_id: number;
}

export async function openConversation(
    convId: number,
    partnerId: number,
    partnerLogin: string,
): Promise<void> {
    cancelReply();
    clearPinBar();
    resetSelectMode();
    state.currentConvId = convId;
    window.currentConvId = convId;
    state.currentPartnerId = partnerId;
    state.currentPartnerLogin = partnerLogin;
    state.oldestMessageId = null;

    document
        .querySelectorAll(".conversation-item")
        .forEach((el) => el.classList.remove("active"));
    document
        .querySelector<HTMLElement>(`[data-conv-id="${convId}"]`)
        ?.classList.add("active");

    document.getElementById("noChatSelected")?.style.setProperty("display", "none");
    (document.getElementById("chatHeader") as HTMLElement).style.display = "flex";
    (document.getElementById("inputArea") as HTMLElement).style.display = "block";
    await window.emojiPanelOnChatOpen?.();

    setAvatarEl(
        document.getElementById("chatAvatar")!,
        partnerLogin,
        window.Laravel.avatars?.[partnerId] ?? null,
    );
    document.getElementById("chatPartnerName")!.textContent = partnerLogin;
    updateChatHeaderStatus(state.onlineUsers.has(partnerId));
    (document.getElementById("keyChangeWarn") as HTMLElement).style.display = "none";

    document.getElementById("messagesArea")!.innerHTML =
        '<div class="no-chat-selected" style="flex:1"><p class="empty-title">загрузка…</p></div>';

    loadMessages();
    loadPinBar().catch(() => {});
    (document.getElementById("messageInput") as HTMLTextAreaElement).focus();

    getPartnerPublicKey(partnerId)
        .then(() => updateKeyChangeWarn(partnerId))
        .catch(() => {});
}

export async function loadMessages(before: number | null = null): Promise<void> {
    const url =
        `/chat/${state.currentConvId}/messages` +
        (before ? `?before_id=${before}` : "");
    const data = await fetchJson<MessagesResponse>(url);
    if (!data.success) {
        return;
    }

    const storePref = (document.getElementById("storageSelect") as HTMLSelectElement).value;

    if (!before) {
        document.getElementById("messagesArea")!.innerHTML = "";
        const notice = document.createElement("div");
        notice.className = "e2e-notice";
        notice.innerHTML = `<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> сообщения и звонки защищены сквозным шифрованием`;
        document.getElementById("messagesArea")!.appendChild(notice);
        if (data.has_more) {
            const btn = document.createElement("button");
            btn.className = "load-more-btn";
            btn.textContent = "↑ загрузить ранее";
            btn.onclick = () => loadMessages(state.oldestMessageId);
            document.getElementById("messagesArea")!.appendChild(btn);
        }
    }

    for (const msg of data.data) {
        if (!state.oldestMessageId || msg.id < state.oldestMessageId) {
            state.oldestMessageId = msg.id;
        }
        await appendMessage(msg, before ? "prepend-before-btn" : "append", true);
        if (storePref === "browser" || storePref === "device") {
            await IDB.putMessage({ ...msg, conversation_id: state.currentConvId! });
        }
    }

    markVisibleMessagesRead();
    if (!before) {
        const area = document.getElementById("messagesArea")!;
        area.scrollTop = area.scrollHeight;
    }
}

export async function appendMessage(
    msg: Message,
    mode: "append" | "prepend-before-btn" = "append",
    isFromHistory = false,
): Promise<void> {
    const isOwn = msg.sender_id === AUTH_USER_ID;
    let text = "…";
    try {
        const partnerKey = await getPartnerPublicKey(
            isOwn ? state.currentPartnerId! : msg.sender_id,
        );
        const aesKey = await Crypto.deriveAesKey(state.myPrivateKey!, partnerKey);
        const payload = JSON.parse(msg.encrypted_payload) as {
            iv: string;
            ciphertext: string;
        };
        text = await Crypto.decrypt(aesKey, payload.iv, payload.ciphertext);
    } catch {
        text = "[не удалось расшифровать — ключ изменился]";
    }

    let parsedFile: FileMessageContent | null = null;
    let parsedPhoto: PhotoMessageContent | null = null;
    let parsedLocation: LocationMessageContent | null = null;
    let parsedLocationLive: LocationLiveMessageContent | null = null;
    let parsedReplyTo: ReplyTo | null = null;
    let parsedForwardedFrom: string | null = null;
    try {
        const candidate = JSON.parse(text) as TextMessageContent | FileMessageContent | PhotoMessageContent | LocationMessageContent | LocationLiveMessageContent;
        if (candidate?.type === "text") {
            const tc = candidate as TextMessageContent;
            text = tc.text;
            parsedReplyTo = tc.reply_to ?? null;
            parsedForwardedFrom = tc.forwarded_from ?? null;
        } else if (candidate?.type === "file" && (candidate as FileMessageContent).file?.id) {
            parsedFile = candidate as FileMessageContent;
        } else if (candidate?.type === "photo" && (candidate as PhotoMessageContent).preview?.id) {
            parsedPhoto = candidate as PhotoMessageContent;
        } else if (candidate?.type === "location") {
            parsedLocation = candidate as LocationMessageContent;
        } else if (candidate?.type === "location_live") {
            parsedLocationLive = candidate as LocationLiveMessageContent;
        }
    } catch {
        // plain text message
    }

    const msgType = parsedLocationLive ? "location_live"
        : parsedLocation ? "location"
        : parsedPhoto ? "photo"
        : parsedFile ? "file"
        : "text";

    const snippet = msgType === "file" ? "📎 " + (parsedFile?.file?.name ?? "Файл")
        : msgType === "photo" ? "🖼 Фото"
        : msgType === "location" || msgType === "location_live" ? "📍 Геолокация"
        : text.slice(0, 80);

    const bubble = parsedLocationLive
        ? createLocationLiveBubble(msg.id, isOwn, parsedLocationLive, msg.created_at, msg.delivered_at, msg.read_at, isFromHistory)
        : parsedLocation
            ? createLocationBubble(msg.id, isOwn, parsedLocation, msg.created_at, msg.delivered_at, msg.read_at)
            : parsedPhoto
                ? createPhotoBubble(msg.id, isOwn, parsedPhoto, msg.created_at, msg.delivered_at, msg.read_at)
                : parsedFile
                    ? createFileBubble(msg.id, isOwn, parsedFile, msg.created_at, msg.delivered_at, msg.read_at)
                    : createBubble(msg.id, isOwn, text, msg.created_at, msg.delivered_at, msg.read_at, msg.encrypted_payload, msg.edited_at ?? null, parsedReplyTo, parsedForwardedFrom);

    bubble.dataset.type = msgType;
    bubble.dataset.encryptedPayload = msg.encrypted_payload;
    bindContextMenu(bubble, msg.id, isOwn, msg.created_at, msgType, snippet);

    const area = document.getElementById("messagesArea")!;
    const btn = area.querySelector(".load-more-btn");

    if (mode === "prepend-before-btn" && btn) {
        btn.insertAdjacentElement("afterend", bubble);
    } else {
        area.appendChild(bubble);
    }
}

export function createBubble(
    id: number,
    isOwn: boolean,
    text: string,
    createdAt: string,
    deliveredAt: string | null,
    readAt: string | null,
    encryptedPayload = "",
    editedAt: string | null = null,
    replyTo: ReplyTo | null = null,
    forwardedFrom: string | null = null,
): HTMLElement {
    const div = document.createElement("div");
    div.className = "message-bubble " + (isOwn ? "own" : "other");
    div.id = "msg-" + id;
    div.dataset.id = String(id);
    div.dataset.own = isOwn ? "1" : "0";
    if (encryptedPayload) { div.dataset.encryptedPayload = encryptedPayload; }

    const timeStr = new Date(createdAt).toLocaleTimeString("ru", {
        hour: "2-digit",
        minute: "2-digit",
    });
    const statusIcon = isOwn ? getStatusIcon(deliveredAt, readAt) : "";

    if (forwardedFrom) {
        const fwd = document.createElement("div");
        fwd.className = "forwarded-header";
        fwd.textContent = "➥ " + forwardedFrom;
        div.appendChild(fwd);
    }

    if (replyTo) {
        const quote = document.createElement("div");
        quote.className = "reply-quote";
        quote.dataset.replyId = String(replyTo.id);
        quote.addEventListener("click", () => scrollToMessage(replyTo.id));
        const aliasEl = document.createElement("span");
        aliasEl.className = "reply-quote-alias";
        aliasEl.textContent = replyTo.sender_alias;
        const snippetEl = document.createElement("span");
        snippetEl.className = "reply-quote-text";
        snippetEl.textContent = replyTo.snippet;
        quote.appendChild(aliasEl);
        quote.appendChild(snippetEl);
        div.appendChild(quote);
    }

    const textDiv = document.createElement("div");
    textDiv.className = "bubble-text";
    textDiv.textContent = text;
    div.appendChild(textDiv);

    const meta = document.createElement("div");
    meta.className = "message-meta";

    const timeSpan = document.createElement("span");
    timeSpan.textContent = timeStr;
    meta.appendChild(timeSpan);

    if (editedAt) {
        const badge = document.createElement("span");
        badge.className = "edited-badge";
        badge.textContent = "изм.";
        badge.addEventListener("click", (e) => { e.stopPropagation(); openEditHistory(id); });
        meta.appendChild(badge);
    }

    if (isOwn) {
        const statusSpan = document.createElement("span");
        statusSpan.className = "status-icon";
        statusSpan.id = "status-" + id;
        statusSpan.innerHTML = statusIcon;
        meta.appendChild(statusSpan);
    }

    div.appendChild(meta);

    return div;
}

function createFileBubble(
    id: number,
    isOwn: boolean,
    content: FileMessageContent,
    createdAt: string,
    deliveredAt: string | null,
    readAt: string | null,
): HTMLElement {
    const div = document.createElement("div");
    div.className = "message-bubble " + (isOwn ? "own" : "other");
    div.id = "msg-" + id;
    div.dataset.id = String(id);
    div.dataset.own = isOwn ? "1" : "0";

    const timeStr = new Date(createdAt).toLocaleTimeString("ru", {
        hour: "2-digit",
        minute: "2-digit",
    });
    const statusIcon = isOwn ? getStatusIcon(deliveredAt, readAt) : "";

    const fileBubble = renderFileBubble(content.file, content.expires_at);
    div.appendChild(fileBubble);

    if (content.text) {
        const caption = document.createElement("div");
        caption.className = "file-caption";
        caption.textContent = content.text;
        div.appendChild(caption);
    }

    const meta = document.createElement("div");
    meta.className = "message-meta";
    meta.innerHTML = `<span>${timeStr}</span>${isOwn ? `<span class="status-icon" id="status-${id}">${statusIcon}</span>` : ""}`;
    div.appendChild(meta);

    return div;
}

function createLocationBubble(
    id: number,
    isOwn: boolean,
    content: LocationMessageContent,
    createdAt: string,
    deliveredAt: string | null,
    readAt: string | null,
): HTMLElement {
    const div = document.createElement("div");
    div.className = "message-bubble " + (isOwn ? "own" : "other");
    div.id = "msg-" + id;
    div.dataset.id = String(id);
    div.dataset.own = isOwn ? "1" : "0";

    const mapEl = renderLocationMapBubble({
        lat: content.lat,
        lng: content.lng,
        sessionId: null,
        isOwn,
        expiresAt: null,
    });
    div.appendChild(mapEl);

    const timeStr = new Date(createdAt).toLocaleTimeString("ru", { hour: "2-digit", minute: "2-digit" });
    const statusIcon = isOwn ? getStatusIcon(deliveredAt, readAt) : "";
    const meta = document.createElement("div");
    meta.className = "message-meta";
    meta.innerHTML = `<span>${timeStr}</span>${isOwn ? `<span class="status-icon" id="status-${id}">${statusIcon}</span>` : ""}`;
    div.appendChild(meta);

    return div;
}

function createLocationLiveBubble(
    id: number,
    isOwn: boolean,
    content: LocationLiveMessageContent,
    createdAt: string,
    deliveredAt: string | null,
    readAt: string | null,
    isFromHistory = false,
): HTMLElement {
    const div = document.createElement("div");
    div.className = "message-bubble " + (isOwn ? "own" : "other");
    div.id = "msg-" + id;
    div.dataset.id = String(id);
    div.dataset.own = isOwn ? "1" : "0";

    const isExpired = new Date(content.expires_at).getTime() < Date.now();
    const canStop = isOwn && !isExpired && state.currentConvId;

    console.log('isOwn', isOwn);
    console.log('!isExpired', !isExpired);
    console.log('state.currentConvId', state.currentConvId);

    const mapEl = renderLocationMapBubble({
        lat: content.lat,
        lng: content.lng,
        sessionId: content.session_id,
        isOwn,
        expiresAt: content.expires_at,
        isActive: !isExpired,
        onStop: canStop ? async () => {
            await stopSessionByUuid(state.currentConvId!, content.session_id);
            clearLiveSession();
            freezeSession(content.session_id);
        } : undefined,
    });
    div.appendChild(mapEl);

    // When loading from history, verify server state to handle early-stopped sessions.
    // New messages (live send or WS echo) skip this to avoid race conditions.
    if (isFromHistory && !isExpired && state.currentConvId) {
        fetchJson<{ success: boolean; is_active: boolean }>(
            `/chat/${state.currentConvId}/location/${content.session_id}`,
        ).then((data) => {
            if (data.success && !data.is_active) {
                freezeSession(content.session_id);
            }
        }).catch(() => {});
    }

    const timeStr = new Date(createdAt).toLocaleTimeString("ru", { hour: "2-digit", minute: "2-digit" });
    const statusIcon = isOwn ? getStatusIcon(deliveredAt, readAt) : "";
    const meta = document.createElement("div");
    meta.className = "message-meta";
    meta.innerHTML = `<span>${timeStr}</span>${isOwn ? `<span class="status-icon" id="status-${id}">${statusIcon}</span>` : ""}`;
    div.appendChild(meta);

    return div;
}

export async function sendLocationMessage(lat: number, lng: number, accuracy: number): Promise<void> {
    if (!state.currentConvId || !state.currentPartnerId) { return; }

    const partnerKey = await getPartnerPublicKey(state.currentPartnerId);
    const aesKey = await Crypto.deriveAesKey(state.myPrivateKey!, partnerKey);

    const content: LocationMessageContent = { type: "location", lat, lng, accuracy };
    const { iv, ciphertext } = await Crypto.encrypt(aesKey, JSON.stringify(content));
    const payload = JSON.stringify({ iv, ciphertext });

    const data = await post<SendResponse>(`/chat/${state.currentConvId}/messages`, { encrypted_payload: payload });
    if (data.success) {
        const msg: Message = { id: data.id, sender_id: AUTH_USER_ID, encrypted_payload: payload, created_at: data.created_at, delivered_at: null, read_at: null };
        await appendMessage(msg, "append");
        document.getElementById("messagesArea")!.scrollTop = document.getElementById("messagesArea")!.scrollHeight;
        const storePref = (document.getElementById("storageSelect") as HTMLSelectElement).value;
        if (storePref === "browser" || storePref === "device") {
            await IDB.putMessage({ ...msg, conversation_id: state.currentConvId! });
        }
    }
}

export async function sendLiveLocationMessage(
    sessionId: string, lat: number, lng: number, accuracy: number,
    durationMinutes: number, expiresAt: string,
): Promise<void> {
    if (!state.currentConvId || !state.currentPartnerId) { return; }

    const partnerKey = await getPartnerPublicKey(state.currentPartnerId);
    const aesKey = await Crypto.deriveAesKey(state.myPrivateKey!, partnerKey);

    const content: LocationLiveMessageContent = { type: "location_live", session_id: sessionId, lat, lng, accuracy, duration_minutes: durationMinutes, expires_at: expiresAt };
    const { iv, ciphertext } = await Crypto.encrypt(aesKey, JSON.stringify(content));
    const payload = JSON.stringify({ iv, ciphertext });

    const data = await post<SendResponse>(`/chat/${state.currentConvId}/messages`, { encrypted_payload: payload });
    if (data.success) {
        const msg: Message = { id: data.id, sender_id: AUTH_USER_ID, encrypted_payload: payload, created_at: data.created_at, delivered_at: null, read_at: null };
        await appendMessage(msg, "append");
        document.getElementById("messagesArea")!.scrollTop = document.getElementById("messagesArea")!.scrollHeight;
        const storePref = (document.getElementById("storageSelect") as HTMLSelectElement).value;
        if (storePref === "browser" || storePref === "device") {
            await IDB.putMessage({ ...msg, conversation_id: state.currentConvId! });
        }
    }
}

function createPhotoBubble(
    id: number,
    isOwn: boolean,
    content: PhotoMessageContent,
    createdAt: string,
    deliveredAt: string | null,
    readAt: string | null,
): HTMLElement {
    const div = document.createElement("div");
    div.className = "message-bubble " + (isOwn ? "own" : "other");
    div.id = "msg-" + id;
    div.dataset.id = String(id);
    div.dataset.own = isOwn ? "1" : "0";

    const timeStr = new Date(createdAt).toLocaleTimeString("ru", { hour: "2-digit", minute: "2-digit" });
    const statusIcon = isOwn ? getStatusIcon(deliveredAt, readAt) : "";

    const photoBubble = renderPhotoBubble(content.preview, content.original, content.expires_at);
    div.appendChild(photoBubble);

    if (content.text) {
        const caption = document.createElement("div");
        caption.className = "file-caption";
        caption.textContent = content.text;
        div.appendChild(caption);
    }

    const meta = document.createElement("div");
    meta.className = "message-meta";
    meta.innerHTML = `<span>${timeStr}</span>${isOwn ? `<span class="status-icon" id="status-${id}">${statusIcon}</span>` : ""}`;
    div.appendChild(meta);

    return div;
}

export async function sendPhotoMessage(
    previewResult: { fileUuid: string; fileKey: string; name: string; mime: string; size: number; chunks: number; chunkSize: number; expiresAt: string },
    originalResult: { fileUuid: string; fileKey: string; name: string; mime: string; size: number; chunks: number; chunkSize: number; expiresAt: string },
    caption: string,
): Promise<void> {
    if (!state.currentConvId || !state.currentPartnerId) { return; }

    const partnerKey = await getPartnerPublicKey(state.currentPartnerId);
    const aesKey = await Crypto.deriveAesKey(state.myPrivateKey!, partnerKey);

    const photoContent: PhotoMessageContent = {
        type: "photo",
        preview: { id: previewResult.fileUuid, key: previewResult.fileKey, chunks: previewResult.chunks, chunk_size: previewResult.chunkSize, name: previewResult.name, mime: previewResult.mime, size: previewResult.size },
        original: { id: originalResult.fileUuid, key: originalResult.fileKey, chunks: originalResult.chunks, chunk_size: originalResult.chunkSize, name: originalResult.name, mime: originalResult.mime, size: originalResult.size },
        expires_at: previewResult.expiresAt,
    };
    if (caption) { photoContent.text = caption; }

    const { iv, ciphertext } = await Crypto.encrypt(aesKey, JSON.stringify(photoContent));
    const payload = JSON.stringify({ iv, ciphertext });

    const data = await post<SendResponse>(`/chat/${state.currentConvId}/messages`, { encrypted_payload: payload });
    if (data.success) {
        const msg: Message = { id: data.id, sender_id: AUTH_USER_ID, encrypted_payload: payload, created_at: data.created_at, delivered_at: null, read_at: null };
        await appendMessage(msg, "append");
        const area = document.getElementById("messagesArea")!;
        area.scrollTop = area.scrollHeight;

        const storePref = (document.getElementById("storageSelect") as HTMLSelectElement).value;
        if (storePref === "browser" || storePref === "device") {
            await IDB.putMessage({ ...msg, conversation_id: state.currentConvId! });
        }
    }
}

export async function sendFileMessage(
    fileUuid: string,
    fileKeyB64: string,
    name: string,
    mime: string,
    size: number,
    chunks: number,
    chunkSize: number,
    expiresAt: string,
    caption: string,
): Promise<void> {
    if (!state.currentConvId || !state.currentPartnerId) {
        return;
    }

    const partnerKey = await getPartnerPublicKey(state.currentPartnerId);
    const aesKey = await Crypto.deriveAesKey(state.myPrivateKey!, partnerKey);

    const fileContent: FileMessageContent = {
        type: "file",
        file: {
            id: fileUuid,
            key: fileKeyB64,
            chunks,
            chunk_size: chunkSize,
            name,
            mime,
            size,
        },
        expires_at: expiresAt,
    };
    if (caption) {
        fileContent.text = caption;
    }

    const { iv, ciphertext } = await Crypto.encrypt(aesKey, JSON.stringify(fileContent));
    const payload = JSON.stringify({ iv, ciphertext });

    const data = await post<SendResponse>(`/chat/${state.currentConvId}/messages`, {
        encrypted_payload: payload,
    });

    if (data.success) {
        const msg: Message = {
            id: data.id,
            sender_id: AUTH_USER_ID,
            encrypted_payload: payload,
            created_at: data.created_at,
            delivered_at: null,
            read_at: null,
        };
        await appendMessage(msg, "append");
        const area = document.getElementById("messagesArea")!;
        area.scrollTop = area.scrollHeight;

        const storePref = (document.getElementById("storageSelect") as HTMLSelectElement).value;
        if (storePref === "browser" || storePref === "device") {
            await IDB.putMessage({ ...msg, conversation_id: state.currentConvId! });
        }
    }
}

export function getStatusIcon(
    deliveredAt: string | null,
    readAt: string | null,
): string {
    if (readAt) {
        return '<span style="color:#E8A656">✓✓</span>';
    }
    if (deliveredAt) {
        return '<span style="color:#5b606d">✓✓</span>';
    }
    return '<span style="color:#272b36">✓</span>';
}

export function markVisibleMessagesRead(): void {
    if (!state.currentConvId) {
        return;
    }
    const unread = [...document.querySelectorAll<HTMLElement>(".message-bubble.other")]
        .filter((el) => !el.dataset.read)
        .map((el) => parseInt(el.dataset.id!));
    if (!unread.length) {
        return;
    }
    unread.forEach((id) => {
        const el = document.getElementById("msg-" + id);
        if (el) {
            el.dataset.read = "1";
        }
    });
    post("/chat/messages/read", {
        message_ids: unread,
        conversation_id: state.currentConvId,
    }).catch(() => {});
}

export async function sendMessage(): Promise<void> {
    const input = document.getElementById("messageInput") as HTMLTextAreaElement;
    const text = input.value.trim();
    if (!text || !state.currentConvId) {
        return;
    }

    const btn = document.getElementById("sendBtn") as HTMLButtonElement;
    btn.disabled = true;
    input.value = "";
    autoResize(input);
    updateSendBtn();

    try {
        const partnerKey = await getPartnerPublicKey(state.currentPartnerId!);
        const aesKey = await Crypto.deriveAesKey(state.myPrivateKey!, partnerKey);

        const currentReply = replyingTo;
        const plaintext = currentReply
            ? JSON.stringify({ type: "text", text, reply_to: { id: currentReply.msgId, snippet: currentReply.snippet, sender_alias: currentReply.senderAlias } })
            : text;

        const { iv, ciphertext } = await Crypto.encrypt(aesKey, plaintext);
        const payload = JSON.stringify({ iv, ciphertext });

        const body: Record<string, unknown> = { encrypted_payload: payload };
        if (currentReply) { body.reply_to_id = currentReply.msgId; }

        const data = await post<SendResponse>(`/chat/${state.currentConvId}/messages`, body);
        if (data.success) {
            cancelReply();
            const msg: Message = {
                id: data.id,
                sender_id: AUTH_USER_ID,
                encrypted_payload: payload,
                created_at: data.created_at,
                delivered_at: null,
                read_at: null,
                reply_to_id: currentReply?.msgId ?? null,
            };
            await appendMessage(msg, "append");
            const area = document.getElementById("messagesArea")!;
            area.scrollTop = area.scrollHeight;

            const storePref = (document.getElementById("storageSelect") as HTMLSelectElement).value;
            if (storePref === "browser" || storePref === "device") {
                await IDB.putMessage({ ...msg, conversation_id: state.currentConvId! });
            }
        }
    } catch (e) {
        showNotification("ошибка отправки: " + (e as Error).message);
    } finally {
        btn.disabled = false;
        input.focus();
    }
}

export function handleInputKeydown(e: KeyboardEvent): void {
    if (e.key === "Enter" && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
}

let typingThrottle = false;

export function handleInputKeyup(): void {
    if (!state.currentConvId || typingThrottle) {
        return;
    }
    typingThrottle = true;
    setTimeout(() => {
        typingThrottle = false;
    }, 1500);
    post("/chat/typing", { conversation_id: state.currentConvId }).catch(() => {});
}

export function updateConvPreview(convId: number, timestamp: string): void {
    const timeEl = document.getElementById("time-" + convId);
    if (timeEl && timestamp) {
        timeEl.textContent = new Date(timestamp).toLocaleTimeString("ru", {
            hour: "2-digit",
            minute: "2-digit",
        });
    }
}

export async function applyMessageEdit(
    msgId: number,
    encryptedPayload: string,
    editedAt: string,
): Promise<void> {
    const bubble = document.getElementById("msg-" + msgId);
    if (!bubble || !state.myPrivateKey || !state.currentPartnerId) { return; }

    let text = "…";
    try {
        const partnerKey = await getPartnerPublicKey(state.currentPartnerId);
        const aesKey = await Crypto.deriveAesKey(state.myPrivateKey, partnerKey);
        const payload = JSON.parse(encryptedPayload) as { iv: string; ciphertext: string };
        text = await Crypto.decrypt(aesKey, payload.iv, payload.ciphertext);
    } catch {
        text = "[не удалось расшифровать — ключ изменился]";
    }

    bubble.dataset.encryptedPayload = encryptedPayload;

    const textDiv = bubble.querySelector<HTMLElement>(".bubble-text");
    if (textDiv) { textDiv.textContent = text; }

    const meta = bubble.querySelector<HTMLElement>(".message-meta");
    if (meta && !meta.querySelector(".edited-badge")) {
        const badge = document.createElement("span");
        badge.className = "edited-badge";
        badge.textContent = "изм.";
        badge.addEventListener("click", (e) => { e.stopPropagation(); openEditHistory(msgId); });
        const statusSpan = meta.querySelector(".status-icon");
        if (statusSpan) {
            meta.insertBefore(badge, statusSpan);
        } else {
            meta.appendChild(badge);
        }
    }

    void editedAt;
}

function scrollToMessage(msgId: number): void {
    const target = document.getElementById("msg-" + msgId);
    if (target) {
        target.scrollIntoView({ behavior: "smooth", block: "center" });
        target.classList.add("msg-highlight");
        setTimeout(() => target.classList.remove("msg-highlight"), 1500);
    } else {
        showNotification("сообщение недоступно");
    }
}

function bindContextMenu(
    bubble: HTMLElement,
    msgId: number,
    isOwn: boolean,
    createdAt: string,
    type: string,
    snippet: string,
): void {
    const senderAlias = isOwn ? window.Laravel.pseudonym : (state.currentPartnerLogin ?? "");

    const show = (x: number, y: number): void => {
        const items: Array<{ label: string; action: () => void; danger?: boolean }> = [];

        items.push({
            label: "Ответить",
            action: () => startReply(msgId, snippet, senderAlias),
        });

        if (type === "text") {
            items.push({
                label: "Копировать",
                action: () => {
                    const textDiv = bubble.querySelector<HTMLElement>(".bubble-text");
                    if (textDiv?.textContent) {
                        navigator.clipboard.writeText(textDiv.textContent).catch(() => {});
                    }
                },
            });
        }

        if (isOwn && type === "text") {
            const ageMs = Date.now() - new Date(createdAt).getTime();
            if (ageMs < 48 * 3600 * 1000) {
                items.push({ label: "Редактировать", action: () => startInlineEdit(bubble, msgId) });
            }
        }

        items.push({
            label: pinnedMsgIds.has(msgId) ? "Открепить" : "Закрепить",
            action: () => togglePin(msgId),
        });

        items.push({ label: "Переслать", action: () => forwardMessages([msgId]) });

        items.push({ label: "Выбрать", action: () => enterSelectMode(msgId) });

        items.push({ label: "Удалить...", action: () => showDeleteModal(msgId, isOwn), danger: true });

        openContextMenu(x, y, items);
    };

    bubble.addEventListener("click", (e) => {
        if (!isSelectMode) { return; }
        e.stopPropagation();
        toggleSelectMsg(parseInt(bubble.dataset.id ?? "0", 10));
    });

    bubble.addEventListener("contextmenu", (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (isSelectMode) {
            toggleSelectMsg(msgId);
            return;
        }
        show(e.clientX, e.clientY);
    });

    let longPressTimer: ReturnType<typeof setTimeout> | null = null;
    let touchMoved = false;

    bubble.addEventListener("touchstart", (e) => {
        touchMoved = false;
        const touch = e.touches[0];
        longPressTimer = setTimeout(() => {
            if (!touchMoved) { show(touch.clientX, touch.clientY); }
        }, 500);
    }, { passive: true });

    bubble.addEventListener("touchmove", () => {
        touchMoved = true;
        if (longPressTimer) { clearTimeout(longPressTimer); longPressTimer = null; }
    }, { passive: true });

    bubble.addEventListener("touchend", () => {
        if (longPressTimer) { clearTimeout(longPressTimer); longPressTimer = null; }
    }, { passive: true });
}

function startReply(msgId: number, snippet: string, senderAlias: string): void {
    replyingTo = { msgId, snippet, senderAlias };
    showReplyPreviewBar();
    (document.getElementById("messageInput") as HTMLTextAreaElement | null)?.focus();
}

export function cancelReply(): void {
    replyingTo = null;
    document.getElementById("replyPreviewBar")?.remove();
}

function showReplyPreviewBar(): void {
    document.getElementById("replyPreviewBar")?.remove();
    if (!replyingTo) { return; }

    const bar = document.createElement("div");
    bar.id = "replyPreviewBar";
    bar.className = "reply-preview-bar";

    const content = document.createElement("div");
    content.className = "reply-preview-content";

    const aliasEl = document.createElement("span");
    aliasEl.className = "reply-preview-alias";
    aliasEl.textContent = replyingTo.senderAlias;

    const snippetEl = document.createElement("span");
    snippetEl.className = "reply-preview-snippet";
    snippetEl.textContent = replyingTo.snippet;

    content.appendChild(aliasEl);
    content.appendChild(snippetEl);

    const cancelBtn = document.createElement("button");
    cancelBtn.className = "reply-preview-cancel";
    cancelBtn.textContent = "×";
    cancelBtn.setAttribute("aria-label", "Отменить ответ");
    cancelBtn.addEventListener("click", cancelReply);

    bar.appendChild(content);
    bar.appendChild(cancelBtn);

    const inputArea = document.getElementById("inputArea");
    if (inputArea) {
        inputArea.prepend(bar);
    }
}

let pinBarPins: Array<{ msgId: number; snippet: string }> = [];
let pinBarIndex = 0;

function clearPinBar(): void {
    pinnedMsgIds.clear();
    pinBarPins = [];
    pinBarIndex = 0;
    const bar = document.getElementById("pinBar");
    if (bar) { bar.innerHTML = ""; bar.style.display = "none"; }
}

async function loadPinBar(): Promise<void> {
    if (!state.currentConvId || !state.myPrivateKey || !state.currentPartnerId) { return; }

    const data = await fetchJson<{ success: boolean; data: Array<{ message_id: number; encrypted_payload: string }> }>(
        `/chat/${state.currentConvId}/pins`,
    );
    if (!data.success) { return; }

    for (const pin of data.data) {
        pinnedMsgIds.add(pin.message_id);
        const snippet = await decryptPinSnippet(pin.encrypted_payload);
        pinBarPins.push({ msgId: pin.message_id, snippet });
    }
    renderPinBar();
}

async function addPinToBar(msgId: number, encryptedPayload: string): Promise<void> {
    if (!state.myPrivateKey || !state.currentPartnerId) { return; }
    if (pinBarPins.some((p) => p.msgId === msgId)) { return; }

    const snippet = await decryptPinSnippet(encryptedPayload);
    pinBarPins.push({ msgId, snippet });
    renderPinBar();
}

function removePinFromBar(msgId: number): void {
    pinnedMsgIds.delete(msgId);
    const idx = pinBarPins.findIndex((p) => p.msgId === msgId);
    if (idx === -1) { return; }
    pinBarPins.splice(idx, 1);
    if (pinBarIndex >= pinBarPins.length && pinBarIndex > 0) { pinBarIndex = pinBarPins.length - 1; }
    renderPinBar();
}

function renderPinBar(): void {
    const bar = document.getElementById("pinBar");
    if (!bar) { return; }

    if (!pinBarPins.length) {
        bar.innerHTML = "";
        bar.style.display = "none";
        bar.onclick = null;
        return;
    }

    const current = pinBarPins[pinBarIndex];
    bar.innerHTML = "";
    bar.style.display = "flex";

    const icon = document.createElement("span");
    icon.className = "pin-bar-icon";
    icon.textContent = "📌";

    const body = document.createElement("div");
    body.className = "pin-bar-body";

    const titleRow = document.createElement("div");
    titleRow.className = "pin-bar-title";
    titleRow.textContent = "Закреплённое сообщение";

    if (pinBarPins.length > 1) {
        const counter = document.createElement("span");
        counter.className = "pin-bar-counter";
        counter.textContent = `${pinBarIndex + 1}/${pinBarPins.length}`;
        titleRow.appendChild(counter);
    }

    const snippetEl = document.createElement("div");
    snippetEl.className = "pin-bar-text";
    snippetEl.textContent = current.snippet;

    body.appendChild(titleRow);
    body.appendChild(snippetEl);
    bar.appendChild(icon);
    bar.appendChild(body);

    bar.onclick = () => {
        scrollToMessage(current.msgId);
        pinBarIndex = (pinBarIndex + 1) % pinBarPins.length;
        renderPinBar();
    };
}

async function decryptPinSnippet(encryptedPayload: string): Promise<string> {
    let snippet = "сообщение";
    if (!state.myPrivateKey || !state.currentPartnerId) { return snippet; }
    try {
        const partnerKey = await getPartnerPublicKey(state.currentPartnerId);
        const aesKey = await Crypto.deriveAesKey(state.myPrivateKey, partnerKey);
        const payload = JSON.parse(encryptedPayload) as { iv: string; ciphertext: string };
        const text = await Crypto.decrypt(aesKey, payload.iv, payload.ciphertext);
        try {
            const parsed = JSON.parse(text) as { type: string; text?: string; file?: { name: string } };
            if (parsed.type === "text" && parsed.text) { snippet = parsed.text.slice(0, 60); }
            else if (parsed.type === "file") { snippet = "📎 " + (parsed.file?.name ?? "Файл"); }
            else if (parsed.type === "photo") { snippet = "🖼 Фото"; }
            else if (parsed.type === "location" || parsed.type === "location_live") { snippet = "📍 Геолокация"; }
            else { snippet = text.slice(0, 60); }
        } catch { snippet = text.slice(0, 60); }
    } catch { }
    return snippet;
}

async function togglePin(msgId: number): Promise<void> {
    if (!state.currentConvId) { return; }
    if (pinnedMsgIds.has(msgId)) {
        await del(`/chat/${state.currentConvId}/messages/${msgId}/pin`);
        removePinFromBar(msgId);
    } else {
        await post(`/chat/${state.currentConvId}/messages/${msgId}/pin`, {});
        pinnedMsgIds.add(msgId);
        const bubble = document.getElementById("msg-" + msgId);
        const enc = bubble?.dataset.encryptedPayload ?? "";
        if (enc) { await addPinToBar(msgId, enc); }
    }
}

export async function applyPinUpdate(msgId: number, convId: number, encryptedPayload: string, pinned: boolean): Promise<void> {
    if (convId !== state.currentConvId) { return; }
    if (pinned) {
        pinnedMsgIds.add(msgId);
        await addPinToBar(msgId, encryptedPayload);
    } else {
        removePinFromBar(msgId);
    }
}

function startInlineEdit(bubble: HTMLElement, msgId: number): void {
    if (bubble.querySelector(".edit-inline-wrap")) { return; }

    const textDiv = bubble.querySelector<HTMLElement>(".bubble-text");
    if (!textDiv) { return; }

    const originalText = textDiv.textContent ?? "";

    const wrap = document.createElement("div");
    wrap.className = "edit-inline-wrap";

    const textarea = document.createElement("textarea");
    textarea.className = "edit-inline-input";
    textarea.value = originalText;
    textarea.rows = Math.max(2, Math.min(6, originalText.split("\n").length + 1));

    const btns = document.createElement("div");
    btns.className = "edit-inline-btns";

    const saveBtn = document.createElement("button");
    saveBtn.className = "edit-save-btn";
    saveBtn.textContent = "сохранить";

    const cancelBtn = document.createElement("button");
    cancelBtn.className = "edit-cancel-btn";
    cancelBtn.textContent = "отмена";

    btns.appendChild(saveBtn);
    btns.appendChild(cancelBtn);
    wrap.appendChild(textarea);
    wrap.appendChild(btns);

    textDiv.replaceWith(wrap);
    textarea.focus();
    textarea.selectionStart = textarea.value.length;

    const cancel = (): void => { wrap.replaceWith(textDiv); };

    cancelBtn.addEventListener("click", cancel);
    textarea.addEventListener("keydown", (e) => {
        if (e.key === "Escape") { cancel(); }
        if (e.key === "Enter" && (e.ctrlKey || e.metaKey)) { e.preventDefault(); saveBtn.click(); }
    });

    saveBtn.addEventListener("click", async () => {
        const newText = textarea.value.trim();
        if (!newText || newText === originalText || !state.currentConvId || !state.myPrivateKey) {
            cancel();
            return;
        }

        saveBtn.disabled = true;
        try {
            const partnerKey = await getPartnerPublicKey(state.currentPartnerId!);
            const aesKey = await Crypto.deriveAesKey(state.myPrivateKey, partnerKey);
            const { iv, ciphertext } = await Crypto.encrypt(aesKey, newText);
            const payload = JSON.stringify({ iv, ciphertext });

            const result = await patch<{ success: boolean; edited_at: string }>(
                `/chat/${state.currentConvId}/messages/${msgId}`,
                { encrypted_payload: payload },
            );

            if (result.success) {
                bubble.dataset.encryptedPayload = payload;
                const newTextDiv = document.createElement("div");
                newTextDiv.className = "bubble-text";
                newTextDiv.textContent = newText;
                wrap.replaceWith(newTextDiv);

                const meta = bubble.querySelector<HTMLElement>(".message-meta");
                if (meta && !meta.querySelector(".edited-badge")) {
                    const badge = document.createElement("span");
                    badge.className = "edited-badge";
                    badge.textContent = "изм.";
                    badge.addEventListener("click", (ev) => { ev.stopPropagation(); openEditHistory(msgId); });
                    const statusSpan = meta.querySelector(".status-icon");
                    if (statusSpan) {
                        meta.insertBefore(badge, statusSpan);
                    } else {
                        meta.appendChild(badge);
                    }
                }
            } else {
                cancel();
            }
        } catch {
            cancel();
        } finally {
            saveBtn.disabled = false;
        }
    });
}

async function openEditHistory(msgId: number): Promise<void> {
    if (!state.currentConvId || !state.myPrivateKey || !state.currentPartnerId) { return; }

    try {
        const data = await fetchJson<{
            success: boolean;
            data: Array<{ encrypted_payload: string; created_at: string }>;
        }>(`/chat/${state.currentConvId}/messages/${msgId}/edits`);

        if (!data.success || !data.data.length) { return; }

        const partnerKey = await getPartnerPublicKey(state.currentPartnerId);
        const aesKey = await Crypto.deriveAesKey(state.myPrivateKey, partnerKey);

        const items = await Promise.all(
            data.data.map(async (e) => {
                try {
                    const p = JSON.parse(e.encrypted_payload) as { iv: string; ciphertext: string };
                    return { text: await Crypto.decrypt(aesKey, p.iv, p.ciphertext), created_at: e.created_at };
                } catch {
                    return { text: "[не удалось расшифровать]", created_at: e.created_at };
                }
            }),
        );

        showEditHistoryModal(items);
    } catch { }
}

function showEditHistoryModal(items: Array<{ text: string; created_at: string }>): void {
    document.querySelector(".edit-history-overlay")?.remove();

    const overlay = document.createElement("div");
    overlay.className = "edit-history-overlay";
    overlay.addEventListener("click", (e) => { if (e.target === overlay) { overlay.remove(); } });

    const modal = document.createElement("div");
    modal.className = "edit-history-modal";

    const header = document.createElement("div");
    header.className = "edit-history-header";
    header.innerHTML = "<span>История правок</span>";
    const closeBtn = document.createElement("button");
    closeBtn.className = "edit-history-close";
    closeBtn.textContent = "×";
    closeBtn.addEventListener("click", () => overlay.remove());
    header.appendChild(closeBtn);

    const list = document.createElement("div");
    list.className = "edit-history-list";

    items.forEach((item) => {
        const entry = document.createElement("div");
        entry.className = "edit-history-entry";
        const timeStr = new Date(item.created_at).toLocaleString("ru", {
            day: "2-digit", month: "2-digit", year: "2-digit",
            hour: "2-digit", minute: "2-digit",
        });
        const timeEl = document.createElement("div");
        timeEl.className = "edit-history-time";
        timeEl.textContent = timeStr;
        const textEl = document.createElement("div");
        textEl.className = "edit-history-text";
        textEl.textContent = item.text;
        entry.appendChild(timeEl);
        entry.appendChild(textEl);
        list.appendChild(entry);
    });

    modal.appendChild(header);
    modal.appendChild(list);
    overlay.appendChild(modal);
    document.body.appendChild(overlay);
}

export function removeMessageFromDom(msgId: number): void {
    document.getElementById("msg-" + msgId)?.remove();
    removePinFromBar(msgId);
}

function showDeleteModal(msgId: number, isOwn: boolean): void {
    document.querySelector(".delete-msg-overlay")?.remove();

    const overlay = document.createElement("div");
    overlay.className = "delete-msg-overlay";
    overlay.addEventListener("click", (e) => { if (e.target === overlay) { overlay.remove(); } });

    const modal = document.createElement("div");
    modal.className = "delete-msg-modal";

    const title = document.createElement("p");
    title.className = "delete-msg-title";
    title.textContent = "Удалить сообщение?";

    const btns = document.createElement("div");
    btns.className = "delete-msg-btns";

    const doDelete = async (scope: "all" | "me"): Promise<void> => {
        overlay.remove();
        if (!state.currentConvId) { return; }
        try {
            await delWithBody(`/chat/${state.currentConvId}/messages/${msgId}`, { scope });
            removeMessageFromDom(msgId);
        } catch { }
    };

    const forAllBtn = document.createElement("button");
    forAllBtn.className = "delete-msg-btn delete-msg-btn--all";
    forAllBtn.textContent = isOwn ? "Удалить у всех" : "Удалить у всех";
    forAllBtn.addEventListener("click", () => doDelete("all"));

    const forMeBtn = document.createElement("button");
    forMeBtn.className = "delete-msg-btn delete-msg-btn--me";
    forMeBtn.textContent = "Удалить у себя";
    forMeBtn.addEventListener("click", () => doDelete("me"));

    const cancelBtn = document.createElement("button");
    cancelBtn.className = "delete-msg-btn delete-msg-btn--cancel";
    cancelBtn.textContent = "Отмена";
    cancelBtn.addEventListener("click", () => overlay.remove());

    btns.appendChild(forAllBtn);
    btns.appendChild(forMeBtn);
    btns.appendChild(cancelBtn);
    modal.appendChild(title);
    modal.appendChild(btns);
    overlay.appendChild(modal);
    document.body.appendChild(overlay);
}

function resetSelectMode(): void {
    isSelectMode = false;
    selectedIds.clear();
    document.querySelectorAll(".msg-selected").forEach((el) => el.classList.remove("msg-selected"));
    document.getElementById("selectToolbar")?.remove();
}

function exitSelectMode(): void {
    resetSelectMode();
    if (state.currentConvId) {
        (document.getElementById("inputArea") as HTMLElement).style.display = "block";
    }
}

function toggleSelectMsg(msgId: number): void {
    if (!msgId) { return; }
    const el = document.getElementById("msg-" + msgId);
    if (selectedIds.has(msgId)) {
        selectedIds.delete(msgId);
        el?.classList.remove("msg-selected");
    } else {
        selectedIds.add(msgId);
        el?.classList.add("msg-selected");
    }
    updateSelectToolbar();
}

function enterSelectMode(initialMsgId: number): void {
    isSelectMode = true;
    selectedIds.clear();
    selectedIds.add(initialMsgId);
    document.getElementById("msg-" + initialMsgId)?.classList.add("msg-selected");
    (document.getElementById("inputArea") as HTMLElement).style.display = "none";
    showSelectToolbar();
}

function showSelectToolbar(): void {
    document.getElementById("selectToolbar")?.remove();

    const toolbar = document.createElement("div");
    toolbar.id = "selectToolbar";
    toolbar.className = "select-toolbar";

    const cancelBtn = document.createElement("button");
    cancelBtn.className = "select-toolbar-btn";
    cancelBtn.textContent = "✕";
    cancelBtn.title = "Отмена";
    cancelBtn.addEventListener("click", exitSelectMode);

    const countEl = document.createElement("span");
    countEl.className = "select-toolbar-count";
    countEl.id = "selectCount";
    countEl.textContent = selectedCount();

    const replyBtn = document.createElement("button");
    replyBtn.className = "select-toolbar-btn";
    replyBtn.id = "selectReplyBtn";
    replyBtn.textContent = "Ответить";
    replyBtn.style.display = selectedIds.size === 1 ? "" : "none";
    replyBtn.addEventListener("click", () => {
        if (selectedIds.size !== 1) { return; }
        const msgId = [...selectedIds][0];
        const bubble = document.getElementById("msg-" + msgId);
        const snip = bubble?.querySelector<HTMLElement>(".bubble-text")?.textContent?.slice(0, 60) ?? "";
        const isOwn = bubble?.dataset.own === "1";
        const alias = isOwn ? window.Laravel.pseudonym : (state.currentPartnerLogin ?? "");
        exitSelectMode();
        startReply(msgId, snip, alias);
    });

    const copyBtn = document.createElement("button");
    copyBtn.className = "select-toolbar-btn";
    copyBtn.id = "selectCopyBtn";
    copyBtn.textContent = "Копировать";
    copyBtn.style.display = allSelectedAreText() ? "" : "none";
    copyBtn.addEventListener("click", () => {
        const sorted = [...selectedIds]
            .map((id) => document.getElementById("msg-" + id))
            .filter((el): el is HTMLElement => el !== null)
            .sort((a, b) => (a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1));

        const lines = sorted.map((bubble) => {
            const isOwn = bubble.dataset.own === "1";
            const author = isOwn ? window.Laravel.pseudonym : (state.currentPartnerLogin ?? "");
            const time = bubble.querySelector<HTMLElement>(".message-meta span")?.textContent ?? "";
            const text = bubble.querySelector<HTMLElement>(".bubble-text")?.textContent ?? "";
            return `>${author} (${time}):\n${text}`;
        });

        navigator.clipboard.writeText(lines.join("\n\n")).catch(() => {});
        exitSelectMode();
    });

    const forwardBtn = document.createElement("button");
    forwardBtn.className = "select-toolbar-btn";
    forwardBtn.textContent = "Переслать";
    forwardBtn.addEventListener("click", () => forwardMessages([...selectedIds]));

    const deleteBtn = document.createElement("button");
    deleteBtn.className = "select-toolbar-btn select-toolbar-btn--danger";
    deleteBtn.textContent = "Удалить";
    deleteBtn.addEventListener("click", () => showMultiDeleteModal([...selectedIds]));

    toolbar.appendChild(cancelBtn);
    toolbar.appendChild(countEl);
    toolbar.appendChild(replyBtn);
    toolbar.appendChild(copyBtn);
    toolbar.appendChild(forwardBtn);
    toolbar.appendChild(deleteBtn);

    document.getElementById("chatPane")?.appendChild(toolbar);
}

function updateSelectToolbar(): void {
    const countEl = document.getElementById("selectCount");
    if (countEl) { countEl.textContent = selectedCount(); }

    const replyBtn = document.getElementById("selectReplyBtn") as HTMLButtonElement | null;
    if (replyBtn) { replyBtn.style.display = selectedIds.size === 1 ? "" : "none"; }

    const copyBtn = document.getElementById("selectCopyBtn") as HTMLButtonElement | null;
    if (copyBtn) { copyBtn.style.display = allSelectedAreText() ? "" : "none"; }
}

function selectedCount(): string {
    return selectedIds.size === 0 ? "Выберите сообщения" : `${selectedIds.size} выбрано`;
}

function allSelectedAreText(): boolean {
    return selectedIds.size > 0 && [...selectedIds].every(
        (id) => document.getElementById("msg-" + id)?.dataset.type === "text",
    );
}

function showMultiDeleteModal(msgIds: number[]): void {
    document.querySelector(".delete-msg-overlay")?.remove();

    const overlay = document.createElement("div");
    overlay.className = "delete-msg-overlay";
    overlay.addEventListener("click", (e) => { if (e.target === overlay) { overlay.remove(); } });

    const modal = document.createElement("div");
    modal.className = "delete-msg-modal";

    const title = document.createElement("p");
    title.className = "delete-msg-title";
    title.textContent = `Удалить ${msgIds.length} сообщ.?`;

    const btns = document.createElement("div");
    btns.className = "delete-msg-btns";

    const doDelete = async (scope: "all" | "me"): Promise<void> => {
        overlay.remove();
        if (!state.currentConvId) { return; }
        for (const id of msgIds) {
            try {
                await delWithBody(`/chat/${state.currentConvId}/messages/${id}`, { scope });
                removeMessageFromDom(id);
            } catch { }
        }
        exitSelectMode();
    };

    const forAllBtn = document.createElement("button");
    forAllBtn.className = "delete-msg-btn delete-msg-btn--all";
    forAllBtn.textContent = "Удалить у всех";
    forAllBtn.addEventListener("click", () => doDelete("all"));

    const forMeBtn = document.createElement("button");
    forMeBtn.className = "delete-msg-btn delete-msg-btn--me";
    forMeBtn.textContent = "Удалить у себя";
    forMeBtn.addEventListener("click", () => doDelete("me"));

    const cancelBtn = document.createElement("button");
    cancelBtn.className = "delete-msg-btn delete-msg-btn--cancel";
    cancelBtn.textContent = "Отмена";
    cancelBtn.addEventListener("click", () => overlay.remove());

    btns.appendChild(forAllBtn);
    btns.appendChild(forMeBtn);
    btns.appendChild(cancelBtn);
    modal.appendChild(title);
    modal.appendChild(btns);
    overlay.appendChild(modal);
    document.body.appendChild(overlay);
}

async function forwardMessages(msgIds: number[]): Promise<void> {
    const items = msgIds
        .map((id) => {
            const bubble = document.getElementById("msg-" + id);
            if (!bubble) { return null; }
            return {
                encryptedPayload: bubble.dataset.encryptedPayload ?? "",
                isOwn: bubble.dataset.own === "1",
            };
        })
        .filter((item): item is { encryptedPayload: string; isOwn: boolean } =>
            item !== null && item.encryptedPayload !== "",
        );

    if (!items.length || !state.myPrivateKey || !state.currentPartnerId) { return; }

    const conversations = [...document.querySelectorAll<HTMLElement>(".conversation-item")]
        .map((el) => ({
            convId: parseInt(el.dataset.convId ?? "0", 10),
            partnerId: parseInt(el.dataset.partnerId ?? "0", 10),
            partnerLogin: el.dataset.partnerLogin ?? "",
        }))
        .filter((c) => c.convId && c.partnerId);

    if (!conversations.length) {
        showNotification("Нет доступных чатов для пересылки");
        return;
    }

    showForwardModal(items, conversations);
}

function showForwardModal(
    items: Array<{ encryptedPayload: string; isOwn: boolean }>,
    conversations: Array<{ convId: number; partnerId: number; partnerLogin: string }>,
): void {
    document.querySelector(".forward-overlay")?.remove();

    const overlay = document.createElement("div");
    overlay.className = "forward-overlay";
    overlay.addEventListener("click", (e) => { if (e.target === overlay) { overlay.remove(); } });

    const modal = document.createElement("div");
    modal.className = "forward-modal";

    const header = document.createElement("div");
    header.className = "forward-modal-header";
    const title = document.createElement("span");
    title.textContent = "Переслать в…";
    const closeBtn = document.createElement("button");
    closeBtn.className = "forward-modal-close";
    closeBtn.textContent = "×";
    closeBtn.addEventListener("click", () => overlay.remove());
    header.appendChild(title);
    header.appendChild(closeBtn);

    const list = document.createElement("div");
    list.className = "forward-conv-list";

    const checkedIds = new Set<number>();

    conversations.forEach((conv) => {
        const item = document.createElement("label");
        item.className = "forward-conv-item";

        const checkbox = document.createElement("input");
        checkbox.type = "checkbox";
        checkbox.addEventListener("change", () => {
            if (checkbox.checked) { checkedIds.add(conv.convId); }
            else { checkedIds.delete(conv.convId); }
            sendBtn.textContent = checkedIds.size
                ? `Переслать (${checkedIds.size})`
                : "Переслать";
            sendBtn.disabled = checkedIds.size === 0;
        });

        const nameEl = document.createElement("span");
        nameEl.className = "forward-conv-name";
        nameEl.textContent = conv.partnerLogin;

        item.appendChild(checkbox);
        item.appendChild(nameEl);
        list.appendChild(item);
    });

    const footer = document.createElement("div");
    footer.className = "forward-modal-footer";

    const cancelBtn = document.createElement("button");
    cancelBtn.className = "forward-btn forward-btn--cancel";
    cancelBtn.textContent = "Отмена";
    cancelBtn.addEventListener("click", () => overlay.remove());

    const sendBtn = document.createElement("button");
    sendBtn.className = "forward-btn forward-btn--send";
    sendBtn.textContent = "Переслать";
    sendBtn.disabled = true;
    sendBtn.addEventListener("click", async () => {
        if (!checkedIds.size || !state.myPrivateKey || !state.currentPartnerId) { return; }
        sendBtn.disabled = true;
        sendBtn.textContent = "Отправка…";

        // Decrypt all messages once with current conversation key
        const srcPartnerKey = await getPartnerPublicKey(state.currentPartnerId);
        const decryptKey = await Crypto.deriveAesKey(state.myPrivateKey, srcPartnerKey);

        const decrypted: Array<{ text: string; alias: string }> = [];
        for (const item of items) {
            let textToForward = "";
            const alias = item.isOwn
                ? window.Laravel.pseudonym
                : (state.currentPartnerLogin ?? "");
            try {
                const p = JSON.parse(item.encryptedPayload) as { iv: string; ciphertext: string };
                const raw = await Crypto.decrypt(decryptKey, p.iv, p.ciphertext);
                try {
                    const parsed = JSON.parse(raw) as { type: string; text?: string; file?: { name: string } };
                    if (parsed.type === "text") { textToForward = parsed.text ?? ""; }
                    else if (parsed.type === "file") { textToForward = "📎 " + (parsed.file?.name ?? "Файл"); }
                    else if (parsed.type === "photo") { textToForward = "🖼 Фото"; }
                    else if (parsed.type === "location" || parsed.type === "location_live") { textToForward = "📍 Геолокация"; }
                    else { textToForward = raw; }
                } catch { textToForward = raw; }
            } catch { continue; }
            decrypted.push({ text: textToForward, alias });
        }

        // Re-encrypt and send to each target
        for (const convId of checkedIds) {
            const conv = conversations.find((c) => c.convId === convId);
            if (!conv) { continue; }
            try {
                const targetKey = await getPartnerPublicKey(conv.partnerId);
                const encryptKey = await Crypto.deriveAesKey(state.myPrivateKey!, targetKey);
                for (const { text, alias } of decrypted) {
                    const content = JSON.stringify({ type: "text", text, forwarded_from: alias });
                    const { iv, ciphertext } = await Crypto.encrypt(encryptKey, content);
                    await post(`/chat/${convId}/messages`, {
                        encrypted_payload: JSON.stringify({ iv, ciphertext }),
                    });
                }
            } catch { }
        }

        overlay.remove();
        if (isSelectMode) { exitSelectMode(); }
        showNotification(
            checkedIds.size === 1 ? "Сообщение переслано" : `Переслано в ${checkedIds.size} чата`,
        );
    });

    footer.appendChild(cancelBtn);
    footer.appendChild(sendBtn);
    modal.appendChild(header);
    modal.appendChild(list);
    modal.appendChild(footer);
    overlay.appendChild(modal);
    document.body.appendChild(overlay);
}

export async function startChatWithFriend(
    partnerId: number,
    partnerLogin: string,
): Promise<void> {
    const data = await post<ConversationResponse>("/chat/conversation", {
        user_id: partnerId,
    });
    if (!data.success) {
        return;
    }
    const convId = data.conversation_id;
    if (!document.querySelector(`[data-conv-id="${convId}"]`)) {
        const list = document.getElementById("conversationList")!;
        document.getElementById("emptyState")?.remove();
        const div = document.createElement("div");
        div.className = "conversation-item";
        div.id = "conv-" + convId;
        div.dataset.convId = String(convId);
        div.dataset.partnerId = String(partnerId);
        div.dataset.partnerLogin = partnerLogin;
        const avatarUrl = window.Laravel.avatars?.[partnerId] ?? null;
        div.innerHTML = `
            <div class="conv-avatar">${avatarUrl ? `<img src="${avatarUrl}" alt="" class="avatar-img">` : escapeHtml(partnerLogin.charAt(0).toUpperCase())}<span class="online-dot${state.onlineUsers.has(partnerId) ? " online" : ""}"></span></div>
            <div class="conv-info">
                <div class="conv-name">${escapeHtml(partnerLogin)}</div>
                <div class="conv-preview" id="preview-${convId}">зашифровано</div>
            </div>
            <div class="conv-time" id="time-${convId}"></div>`;
        list.prepend(div);
    }
    openConversation(convId, partnerId, partnerLogin);
}
