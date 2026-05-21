import { AUTH_USER_ID } from "./constants";
import { state } from "./state";
import { post } from "./api";
import {
    updateOnlineIndicator,
    updateAllOnlineIndicators,
    updateChatHeaderStatus,
} from "./ui";
import {
    appendMessage,
    applyMessageEdit,
    applyPinUpdate,
    removeMessageFromDom,
    markVisibleMessagesRead,
    updateConvPreview,
} from "./messages";
import { getPartnerPublicKey } from "./keys";
import { Crypto } from "./crypto";
import type { Message } from "./types";
import { updateMapMarker, freezeSession, updateFullscreenMarker } from "./location-map";

interface IncomingMessage extends Message {
    sender_login?: string;
    conversation_id: number;
    conversation_type?: "direct" | "group";
    conversation_title?: string | null;
}

interface PresenceUser {
    id: number;
}

export function initWebSocket(): void {
    window.Echo.private("chat." + AUTH_USER_ID)
        .listen(".chat.message", async (e: IncomingMessage) => {
            if (e.conversation_id === state.currentConvId) {
                await appendMessage(
                    {
                        id: e.id,
                        type: e.type,
                        sender_id: e.sender_id,
                        encrypted_payload: e.encrypted_payload,
                        system_payload: e.system_payload,
                        created_at: e.created_at,
                        delivered_at: null,
                        read_at: null,
                    },
                    "append",
                );
                const area = document.getElementById("messagesArea")!;
                area.scrollTop = area.scrollHeight;
                markVisibleMessagesRead();
            }
            if (e.conversation_id !== state.currentConvId && state.myPrivateKey && e.type !== "system") {
                try {
                    const partnerKey = await getPartnerPublicKey(e.sender_id);
                    const aesKey = await Crypto.deriveAesKey(state.myPrivateKey, partnerKey);
                    const payload = JSON.parse(e.encrypted_payload) as {
                        iv: string;
                        ciphertext: string;
                    };
                    const text = await Crypto.decrypt(aesKey, payload.iv, payload.ciphertext);
                    window.dispatchEvent(
                        new CustomEvent("skr:incoming", {
                            detail: {
                                msgId: e.id,
                                senderLogin: e.sender_login ?? "",
                                senderId: e.sender_id,
                                conversationId: e.conversation_id,
                                conversationType: e.conversation_type ?? "direct",
                                conversationTitle: e.conversation_title ?? null,
                                text,
                            },
                        }),
                    );
                } catch {
                    // ignore decryption errors for background messages
                }
            }
            void updateConvPreview(e.conversation_id, e.created_at, e.encrypted_payload, e.sender_id);
            post("/chat/messages/delivered", {
                message_ids: [e.id],
                conversation_id: e.conversation_id,
            }).catch(() => {});
        })
        .listen(".chat.delivered", (e: { message_ids: number[] }) => {
            e.message_ids.forEach((id) => {
                const status = document.getElementById("status-" + id);
                if (status) {
                    status.innerHTML = '<span style="color:#5b606d">✓✓</span>';
                }
            });
        })
        .listen(".chat.read", (e: { message_ids: number[] }) => {
            e.message_ids.forEach((id) => {
                const status = document.getElementById("status-" + id);
                if (status) {
                    status.innerHTML = '<span style="color:#E8A656">✓✓</span>';
                }
            });
        })
        .listen(".chat.location", async (e: {
            session_id: string;
            conversation_id: number;
            encrypted_payload: string;
            stopped: boolean;
        }) => {
            if (!state.myPrivateKey) { return; }
            try {
                const senderId = document.querySelector<HTMLElement>(`[data-session-id="${e.session_id}"]`)
                    ? AUTH_USER_ID  // own bubble — use partner key
                    : e.conversation_id; // fallback: find sender from conversation
                // Decrypt using conversation partner key
                const partnerKey = await getPartnerPublicKey(state.currentPartnerId ?? senderId);
                const aesKey = await Crypto.deriveAesKey(state.myPrivateKey, partnerKey);
                const payload = JSON.parse(e.encrypted_payload) as { iv: string; ciphertext: string };
                const text = await Crypto.decrypt(aesKey, payload.iv, payload.ciphertext);
                const { lat, lng } = JSON.parse(text) as { lat: number; lng: number };
                updateMapMarker(e.session_id, lat, lng);
                updateFullscreenMarker(lat, lng);
                if (e.stopped) { freezeSession(e.session_id); }
            } catch {
                // ignore decrypt errors
            }
        })
        .listen(".chat.message.deleted", (e: { id: number; conversation_id: number }) => {
            if (e.conversation_id === state.currentConvId) {
                removeMessageFromDom(e.id);
            }
        })
        .listen(".chat.message.edited", async (e: {
            id: number;
            conversation_id: number;
            encrypted_payload: string;
            edited_at: string;
        }) => {
            if (e.conversation_id === state.currentConvId) {
                await applyMessageEdit(e.id, e.encrypted_payload, e.edited_at);
            }
        })
        .listen(".chat.message.pinned", async (e: {
            id: number;
            conversation_id: number;
            encrypted_payload: string;
            pinned: boolean;
        }) => {
            await applyPinUpdate(e.id, e.conversation_id, e.encrypted_payload, e.pinned);
        })
        .listen(".chat.typing", (e: { conversation_id: number; sender_id?: number }) => {
            if (e.conversation_id !== state.currentConvId) {
                return;
            }
            const el = document.getElementById("typingIndicator")!;
            const sender = state.currentConversationType === "group"
                ? state.currentParticipants.find((participant) => participant.id === e.sender_id)?.login
                : state.currentPartnerLogin;
            el.textContent = (sender ?? "кто-то") + " печатает…";
            clearTimeout(state.typingTimeout ?? undefined);
            state.typingTimeout = setTimeout(() => {
                el.textContent = "";
            }, 3000);
        });

    window.Echo.join("presence-chat")
        .here((users: PresenceUser[]) => {
            users.forEach((u) => state.onlineUsers.add(u.id));
            updateAllOnlineIndicators(state.onlineUsers);
            if (state.currentPartnerId) {
                updateChatHeaderStatus(state.onlineUsers.has(state.currentPartnerId));
            }
        })
        .joining((user: PresenceUser) => {
            state.onlineUsers.add(user.id);
            updateOnlineIndicator(user.id, true);
            if (state.currentPartnerId === user.id) {
                updateChatHeaderStatus(true);
            }
        })
        .leaving((user: PresenceUser) => {
            state.onlineUsers.delete(user.id);
            updateOnlineIndicator(user.id, false);
            if (state.currentPartnerId === user.id) {
                updateChatHeaderStatus(false);
            }
        });
}
