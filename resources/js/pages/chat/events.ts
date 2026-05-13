import {
    openConversation,
    startChatWithFriend,
    sendMessage,
    sendFileMessage,
    sendPhotoMessage,
    sendLocationMessage,
    sendLiveLocationMessage,
    handleInputKeydown,
    handleInputKeyup,
    refreshCurrentGroupPanel,
} from "./messages";
import { CSRF, del, patch, post } from "./api";
import { uploadFile, uploadPhoto } from "./file-upload";
import { sendOneTimeLocation, startLiveLocation, stopLiveLocation, clearLiveSession } from "./location";
import {
    updateStoragePreference,
    exportHistoryToFile,
    importHistoryFromFile,
} from "./storage";
import { setupKeyBackup } from "./keys";
import { initEmojiPicker } from "./emoji";
import { openContextMenu } from "./context-menu";
import { autoResize, showNotification, updateSendBtn } from "./ui";
import { state } from "./state";

type PinDialogElement = HTMLElement & {
    _onConfirm?: (pin: string) => Promise<boolean | undefined | void>;
    _onRecovery?: () => void | Promise<void>;
    _onCancel?: () => void | Promise<void>;
};

type PhraseModalElement = HTMLElement & {
    _onConfirm?: (phrase: string) => Promise<boolean | undefined | void>;
    _onCancel?: () => void | Promise<void>;
};

export function bindEvents(): void {
    async function copyText(text: string): Promise<void> {
        try {
            await navigator.clipboard.writeText(text);
            showNotification("скопировано");
        } catch {
            showNotification("не удалось скопировать");
        }
    }

    function showGroupActionModal(options: {
        title: string;
        subtitle: string;
        confirmText: string;
        confirmClass: string;
        onConfirm: () => Promise<void>;
    }): void {
        document.querySelector(".delete-msg-overlay")?.remove();

        const overlay = document.createElement("div");
        overlay.className = "delete-msg-overlay";
        overlay.addEventListener("click", (e) => {
            if (e.target === overlay) {
                overlay.remove();
            }
        });

        const modal = document.createElement("div");
        modal.className = "delete-msg-modal";

        const title = document.createElement("p");
        title.className = "delete-msg-title";
        title.textContent = options.title;

        const subtitle = document.createElement("p");
        subtitle.className = "delete-group-subtitle";
        subtitle.textContent = options.subtitle;

        const btns = document.createElement("div");
        btns.className = "delete-msg-btns";

        const confirmBtn = document.createElement("button");
        confirmBtn.className = `delete-msg-btn ${options.confirmClass}`;
        confirmBtn.textContent = options.confirmText;
        confirmBtn.addEventListener("click", async () => {
            confirmBtn.disabled = true;
            await options.onConfirm();
        });

        const cancelBtn = document.createElement("button");
        cancelBtn.className = "delete-msg-btn delete-msg-btn--cancel";
        cancelBtn.textContent = "Отмена";
        cancelBtn.addEventListener("click", () => overlay.remove());

        btns.appendChild(confirmBtn);
        btns.appendChild(cancelBtn);
        modal.appendChild(title);
        modal.appendChild(subtitle);
        modal.appendChild(btns);
        overlay.appendChild(modal);
        document.body.appendChild(overlay);
    }

    function showDeleteGroupModal(convId: string, name: string): void {
        showGroupActionModal({
            title: "Удалить группу?",
            subtitle: `«${name}» будет удалена для всех участников.`,
            confirmText: "Удалить",
            confirmClass: "delete-msg-btn--all",
            onConfirm: async () => {
                await del(`/chat/${convId}/group`);
                window.location.href = "/chats";
            },
        });
    }

    function showLeaveGroupModal(convId: string, name: string): void {
        showGroupActionModal({
            title: "Выйти из группы?",
            subtitle: `Вы покинете «${name}». История останется в группе для остальных участников.`,
            confirmText: "Выйти",
            confirmClass: "delete-msg-btn--all",
            onConfirm: async () => {
                await del(`/chat/${convId}/members/me`);
                window.location.href = "/chats";
            },
        });
    }

    function applyConversationSearch(query: string): void {
        const normalizedQuery = query.trim().toLowerCase();
        const labels = document.querySelectorAll<HTMLElement>("#conversationList .conv-section-label");

        labels.forEach((label) => {
            label.style.display = normalizedQuery ? "none" : "";
        });

        document
            .querySelectorAll<HTMLElement>("#conversationList .conversation-item")
            .forEach((item) => {
                const title = (item.dataset.partnerLogin ?? "").toLowerCase();
                item.style.display = !normalizedQuery || title.includes(normalizedQuery) ? "" : "none";
            });
    }

    document.querySelector<HTMLInputElement>(".sidebar-search input")?.addEventListener("input", (e) => {
        applyConversationSearch((e.target as HTMLInputElement).value);
    });

    document.getElementById("conversationList")!.addEventListener("click", (e) => {
        const item = (e.target as HTMLElement).closest<HTMLElement>(".conversation-item");
        if (!item) {
            return;
        }
        const convId = item.dataset.convId;
        const conversationType = (item.dataset.convType ?? "direct") as "direct" | "group";
        const partnerId = parseInt(item.dataset.partnerId ?? "0");
        const partnerLogin = item.dataset.partnerLogin!;
        if (convId) {
            openConversation(parseInt(convId), partnerId, partnerLogin, conversationType);
        } else {
            startChatWithFriend(partnerId, partnerLogin);
        }
    });

    document.getElementById("conversationList")!.addEventListener("contextmenu", (e) => {
        const item = (e.target as HTMLElement).closest<HTMLElement>(".conversation-item");
        if (!item) {
            return;
        }

        e.preventDefault();
        e.stopPropagation();

        const conversationType = item.dataset.convType ?? "direct";
        const convId = item.dataset.convId;
        const name = item.dataset.partnerLogin ?? "";
        const items: Array<{ label: string; action: () => void; danger?: boolean }> = [];

        if (conversationType === "group" && convId) {
            items.push({
                label: "Выйти",
                action: () => {
                    showLeaveGroupModal(convId, name);
                },
            });

            if (item.dataset.userRole === "owner") {
                items.push({
                    label: "Удалить группу",
                    danger: true,
                    action: () => {
                        showDeleteGroupModal(convId, name);
                    },
                });
            }
        } else if (name) {
            items.push({
                label: "Скопировать имя",
                action: () => { copyText(name); },
            });
        }

        openContextMenu(e.clientX, e.clientY, items);
    });

    document.getElementById("newGroupBtn")?.addEventListener("click", () => {
        (document.getElementById("groupCreateModal") as HTMLElement).style.display = "flex";
    });

    document.getElementById("groupRequestsBtn")?.addEventListener("click", () => {
        (document.getElementById("groupRequestsModal") as HTMLElement).style.display = "flex";
    });

    document.getElementById("groupRequestsClose")?.addEventListener("click", () => {
        (document.getElementById("groupRequestsModal") as HTMLElement).style.display = "none";
    });

    document.getElementById("groupRequestsModal")?.addEventListener("click", async (e) => {
        const target = e.target as HTMLElement;
        const row = target.closest<HTMLElement>("[data-request-id]");
        if (!row?.dataset.requestId) {
            return;
        }

        if (target.dataset.action === "accept-group-request") {
            const data = await post<{ success: boolean; conversation_id: number }>(
                `/chat/group-requests/${row.dataset.requestId}/accept`,
                {},
            );
            if (data.success) {
                window.location.href = `/chats?conversation=${data.conversation_id}`;
            }
            return;
        }

        if (target.dataset.action === "decline-group-request") {
            const data = await del<{ success: boolean }>(`/chat/group-requests/${row.dataset.requestId}`);
            if (data.success) {
                row.remove();
                if (!document.querySelector("[data-request-id]")) {
                    window.location.href = "/chats";
                }
            }
        }
    });

    document.getElementById("groupCreateCancel")?.addEventListener("click", () => {
        (document.getElementById("groupCreateModal") as HTMLElement).style.display = "none";
    });

    document.getElementById("groupCreateForm")?.addEventListener("submit", async (e) => {
        e.preventDefault();
        const form = e.currentTarget as HTMLFormElement;
        const title = (form.querySelector("[name='title']") as HTMLInputElement).value.trim();
        const userIds = [...form.querySelectorAll<HTMLInputElement>("[name='user_ids[]']:checked")]
            .map((input) => parseInt(input.value));

        const data = await post<{ success: boolean; conversation_id: number; message?: string }>("/chat/groups", {
            title,
            user_ids: userIds,
        });

        if (!data.success) {
            return;
        }

        (document.getElementById("groupCreateModal") as HTMLElement).style.display = "none";
        form.reset();

        const list = document.getElementById("conversationList")!;
        const item = document.createElement("div");
        item.className = "conversation-item";
        item.dataset.convId = String(data.conversation_id);
        item.dataset.convType = "group";
        item.dataset.partnerLogin = title;
        item.dataset.userRole = "owner";
        item.dataset.avatarUrl = "";
        item.innerHTML = `
            <div class="conv-avatar">${title.charAt(0).toUpperCase()}</div>
            <div class="conv-info">
                <div class="conv-name"></div>
                <div class="conv-preview" id="preview-${data.conversation_id}">группа</div>
            </div>
            <div class="conv-time" id="time-${data.conversation_id}"></div>`;
        item.querySelector(".conv-name")!.textContent = title;
        list.prepend(item);
        openConversation(data.conversation_id, 0, title, "group");
    });

    document.getElementById("groupManageBtn")?.addEventListener("click", async () => {
        const panel = document.getElementById("groupPanel") as HTMLElement;
        panel.style.display = panel.style.display === "block" ? "none" : "block";
        await refreshCurrentGroupPanel();
    });

    document.getElementById("chatAvatar")?.addEventListener("click", () => {
        if (
            state.currentConversationType !== "group" ||
            !["owner", "admin"].includes(state.currentUserRole ?? "")
        ) {
            return;
        }

        (document.getElementById("groupAvatarInput") as HTMLInputElement | null)?.click();
    });

    document.getElementById("groupPanel")?.addEventListener("click", async (e) => {
        const target = e.target as HTMLElement;

        if (target.id === "groupPanelClose") {
            (document.getElementById("groupPanel") as HTMLElement).style.display = "none";
            return;
        }

        if (target.id === "groupAddFriendBtn" && state.currentConvId) {
            const select = document.getElementById("groupFriendSelect") as HTMLSelectElement;
            if (select.value) {
                await post(`/chat/${state.currentConvId}/members`, { user_ids: [parseInt(select.value)] });
                showNotification("приглашение отправлено");
                await refreshCurrentGroupPanel();
            }
            return;
        }

        if (target.id === "groupRenameBtn" && state.currentConvId) {
            const input = document.getElementById("groupTitleInput") as HTMLInputElement;
            const title = input.value.trim();
            if (!title) {
                return;
            }
            const data = await patch<{ success: boolean; title: string }>(`/chat/${state.currentConvId}/group`, { title });
            if (data.success) {
                state.currentPartnerLogin = data.title;
                document.getElementById("chatPartnerName")!.textContent = data.title;
                const item = document.querySelector<HTMLElement>(`[data-conv-id="${state.currentConvId}"]`);
                if (item) {
                    item.dataset.partnerLogin = data.title;
                    item.querySelector(".conv-name")!.textContent = data.title;
                }
                await refreshCurrentGroupPanel();
            }
            return;
        }

        if (target.id === "groupAvatarBtn") {
            (document.getElementById("groupAvatarInput") as HTMLInputElement | null)?.click();
            return;
        }

        const inviteType = target.dataset.inviteType;
        if (inviteType && state.currentConvId) {
            const data = await post<{
                success: boolean;
                invite?: { url: string };
            }>(`/chat/${state.currentConvId}/invites`, { type: inviteType });
            if (data.success && data.invite?.url) {
                await copyText(data.invite.url);
            }
            await refreshCurrentGroupPanel();
            return;
        }

        const row = target.closest<HTMLElement>("[data-user-id], [data-invite-id]");
        if (target.dataset.action === "promote" && row?.dataset.userId && state.currentConvId) {
            await post(`/chat/${state.currentConvId}/members/${row.dataset.userId}/admin`, {});
            await refreshCurrentGroupPanel();
            return;
        }

        if (target.dataset.action === "demote" && row?.dataset.userId && state.currentConvId) {
            await del(`/chat/${state.currentConvId}/members/${row.dataset.userId}/admin`);
            await refreshCurrentGroupPanel();
            return;
        }

        if (target.dataset.action === "remove" && row?.dataset.userId && state.currentConvId) {
            await del(`/chat/${state.currentConvId}/members/${row.dataset.userId}`);
            await refreshCurrentGroupPanel();
            return;
        }

        if (target.dataset.action === "revoke-invite" && row?.dataset.inviteId && state.currentConvId) {
            await del(`/chat/${state.currentConvId}/invites/${row.dataset.inviteId}`);
            await refreshCurrentGroupPanel();
            return;
        }

        if (target.dataset.action === "copy-invite") {
            const input = row?.querySelector<HTMLInputElement>("input");
            if (input?.value) {
                await copyText(input.value);
            }
            return;
        }

        if (target.id === "groupLeaveBtn" && state.currentConvId) {
            showLeaveGroupModal(String(state.currentConvId), state.currentPartnerLogin);
        }
    });

    document.getElementById("groupPanel")?.addEventListener("change", async (e) => {
        const target = e.target as HTMLInputElement;
        if (target.id !== "groupAvatarInput" || !state.currentConvId || !target.files?.[0]) {
            return;
        }

        const form = new FormData();
        form.append("avatar", target.files[0]);
        target.value = "";

        const response = await fetch(`/chat/${state.currentConvId}/group/avatar`, {
            method: "POST",
            headers: {
                Accept: "application/json",
                "X-CSRF-TOKEN": CSRF,
            },
            body: form,
        });
        const data = await response.json() as { success: boolean; avatar_url?: string };
        if (data.success && data.avatar_url) {
            const item = document.querySelector<HTMLElement>(`[data-conv-id="${state.currentConvId}"]`);
            if (item) {
                item.dataset.avatarUrl = data.avatar_url;
                const avatar = item.querySelector<HTMLElement>(".conv-avatar");
                if (avatar) {
                    avatar.innerHTML = `<img src="${data.avatar_url}" alt="" class="avatar-img">`;
                }
            }
            const headerAvatar = document.getElementById("chatAvatar");
            if (headerAvatar) {
                headerAvatar.innerHTML = `<img src="${data.avatar_url}" alt="" class="avatar-img">`;
            }
            showNotification("фото группы обновлено");
        }
    });

    (document.getElementById("storageSelect") as HTMLSelectElement).addEventListener(
        "change",
        (e) => updateStoragePreference((e.target as HTMLSelectElement).value),
    );

    document.getElementById("exportHistoryBtn")!.addEventListener("click", exportHistoryToFile);
    document.getElementById("importFileInput")!.addEventListener("change", importHistoryFromFile);
    document.getElementById("importTriggerBtn")!.addEventListener("click", () => {
        (document.getElementById("importFileInput") as HTMLInputElement).click();
    });

    const input = document.getElementById("messageInput") as HTMLTextAreaElement;
    input.addEventListener("keydown", handleInputKeydown);
    input.addEventListener("keyup", (e) => {
        handleInputKeyup();
        autoResize(e.target as HTMLTextAreaElement);
        updateSendBtn();
    });
    input.addEventListener("input", updateSendBtn);

    document.getElementById("sendBtn")!.addEventListener("click", sendMessage);
    document.getElementById("setupBackupBtn")!.addEventListener("click", setupKeyBackup);

    // Attach menu (Фото / Файл)
    const attachBtn = document.getElementById("attachBtn") as HTMLButtonElement;
    const attachMenu = document.getElementById("attachMenu") as HTMLElement;

    attachBtn?.addEventListener("click", (e) => {
        e.stopPropagation();
        attachMenu.classList.toggle("attach-menu--open");
    });
    document.addEventListener("click", () => {
        attachMenu?.classList.remove("attach-menu--open");
    });

    function getUploadUiRefs() {
        return {
            progress: document.getElementById("uploadProgress") as HTMLElement,
            bar: document.getElementById("uploadProgressBar") as HTMLElement,
            label: document.getElementById("uploadProgressLabel") as HTMLElement,
            input: document.getElementById("messageInput") as HTMLTextAreaElement,
        };
    }

    // Photo upload
    document.getElementById("attachPhotoBtn")?.addEventListener("click", () => {
        attachMenu.classList.remove("attach-menu--open");
        (document.getElementById("photoAttachInput") as HTMLInputElement).click();
    });
    document.getElementById("photoAttachInput")?.addEventListener("change", async (e) => {
        const file = (e.target as HTMLInputElement).files?.[0];
        if (!file) { return; }
        (e.target as HTMLInputElement).value = "";

        const { progress, bar, label, input } = getUploadUiRefs();
        const caption = input.value.trim();
        input.value = "";
        autoResize(input);
        updateSendBtn();

        progress.hidden = false;
        attachBtn.disabled = true;

        try {
            label.textContent = "сжатие…";
            const result = await uploadPhoto(file, (pct) => {
                bar.style.width = pct + "%";
                label.textContent = `загрузка… ${pct}%`;
            });
            label.textContent = "шифрование…";
            await sendPhotoMessage(result.preview, result.original, caption);
        } catch (err) {
            label.textContent = "ошибка: " + (err as Error).message;
            setTimeout(() => { progress.hidden = true; }, 3000);
            attachBtn.disabled = false;
            return;
        }

        progress.hidden = true;
        bar.style.width = "0%";
        attachBtn.disabled = false;
    });

    // File upload
    document.getElementById("attachFileBtn")?.addEventListener("click", () => {
        attachMenu.classList.remove("attach-menu--open");
        (document.getElementById("fileAttachInput") as HTMLInputElement).click();
    });
    document.getElementById("fileAttachInput")?.addEventListener("change", async (e) => {
        const file = (e.target as HTMLInputElement).files?.[0];
        if (!file) { return; }
        (e.target as HTMLInputElement).value = "";

        const { progress, bar, label, input } = getUploadUiRefs();
        const caption = input.value.trim();
        input.value = "";
        autoResize(input);
        updateSendBtn();

        progress.hidden = false;
        attachBtn.disabled = true;

        try {
            const result = await uploadFile(file, (pct) => {
                bar.style.width = pct + "%";
                label.textContent = `загрузка… ${pct}%`;
            });
            label.textContent = "шифрование…";
            await sendFileMessage(
                result.fileUuid, result.fileKey, result.name, result.mime,
                result.size, result.chunks, result.chunkSize, result.expiresAt, caption,
            );
        } catch (err) {
            label.textContent = "ошибка: " + (err as Error).message;
            setTimeout(() => { progress.hidden = true; }, 3000);
            attachBtn.disabled = false;
            return;
        }

        progress.hidden = true;
        bar.style.width = "0%";
        attachBtn.disabled = false;
    });

    // Location
    document.getElementById("attachLocationBtn")?.addEventListener("click", () => {
        attachMenu.classList.remove("attach-menu--open");
        openLocationModal();
    });

    // One-time location
    document.getElementById("locationOnceBtn")?.addEventListener("click", async () => {
        closeLocationModal();
        const progress = document.getElementById("uploadProgress") as HTMLElement;
        const label = document.getElementById("uploadProgressLabel") as HTMLElement;
        progress.hidden = false;
        label.textContent = "определение позиции…";
        try {
            const { lat, lng, accuracy } = await sendOneTimeLocation();
            label.textContent = "отправка…";
            await sendLocationMessage(lat, lng, accuracy);
        } catch (err) {
            label.textContent = "ошибка: " + (err as Error).message;
            setTimeout(() => { progress.hidden = true; }, 3000);
            return;
        }
        progress.hidden = true;
    });

    // Live location duration buttons
    document.getElementById("locationDurationBtns")?.addEventListener("click", async (e) => {
        const btn = (e.target as HTMLElement).closest<HTMLButtonElement>("[data-duration]");
        if (!btn) { return; }
        const minutes = parseInt(btn.dataset.duration!);
        closeLocationModal();

        const progress = document.getElementById("uploadProgress") as HTMLElement;
        const label = document.getElementById("uploadProgressLabel") as HTMLElement;
        progress.hidden = false;
        label.textContent = "определение позиции…";
        try {
            const result = await startLiveLocation(minutes);
            label.textContent = "отправка…";
            await sendLiveLocationMessage(
                result.sessionId, result.lat, result.lng,
                result.accuracy, minutes, result.expiresAt,
            );
        } catch (err) {
            label.textContent = "ошибка: " + (err as Error).message;
            await stopLiveLocation();
            clearLiveSession();
            setTimeout(() => { progress.hidden = true; }, 3000);
            return;
        }
        progress.hidden = true;
    });

    document.getElementById("locationModalCancel")?.addEventListener("click", closeLocationModal);

    initEmojiPicker();

    // PIN dialog
    const pinDialog = document.getElementById("pinDialog") as PinDialogElement;
    pinDialog.querySelector(".pin-dialog-confirm")!.addEventListener("click", async () => {
        const pin = pinDialog
            .querySelector<HTMLInputElement>(".pin-input")!
            .value.trim();
        if (pin.length !== 6) {
            pinDialog.querySelector(".pin-dialog-error")!.textContent =
                "введите 6-значный PIN";
            return;
        }
        const btn = pinDialog.querySelector<HTMLButtonElement>(".pin-dialog-confirm")!;
        btn.disabled = true;
        pinDialog.querySelector(".pin-dialog-error")!.textContent = "";
        const ok = await pinDialog._onConfirm?.(pin);
        if (ok === false) {
            pinDialog.querySelector(".pin-dialog-error")!.textContent = "неверный PIN";
            btn.disabled = false;
        }
    });
    pinDialog
        .querySelector<HTMLInputElement>(".pin-input")!
        .addEventListener("keydown", (e) => {
            if (e.key === "Enter") {
                pinDialog
                    .querySelector<HTMLButtonElement>(".pin-dialog-confirm")!
                    .click();
            }
        });
    pinDialog
        .querySelector(".pin-dialog-cancel")!
        .addEventListener("click", () => pinDialog._onCancel?.());
    pinDialog
        .querySelector(".pin-dialog-recovery")!
        .addEventListener("click", () => pinDialog._onRecovery?.());

    // Recovery phrase restore dialog
    const phraseModal = document.getElementById(
        "recoveryPhraseRestoreModal",
    ) as PhraseModalElement;
    phraseModal
        .querySelector(".phrase-dialog-confirm")!
        .addEventListener("click", async () => {
            const phrase = phraseModal.querySelector<HTMLTextAreaElement>(".phrase-input")!.value;
            const btn = phraseModal.querySelector<HTMLButtonElement>(".phrase-dialog-confirm")!;
            btn.disabled = true;
            phraseModal.querySelector(".phrase-dialog-error")!.textContent = "";
            const ok = await phraseModal._onConfirm?.(phrase);
            if (ok === false) {
                phraseModal.querySelector(".phrase-dialog-error")!.textContent =
                    "неверная фраза восстановления";
                btn.disabled = false;
            }
        });
    phraseModal
        .querySelector(".phrase-dialog-cancel")!
        .addEventListener("click", () => phraseModal._onCancel?.());

    // Recovery phrase display modal
    document.getElementById("recoveryPhraseDoneBtn")!.addEventListener("click", () => {
        (document.getElementById("recoveryPhraseModal") as HTMLElement).style.display =
            "none";
    });
}

function openLocationModal(): void {
    (document.getElementById("locationModal") as HTMLElement).style.display = "flex";
}

function closeLocationModal(): void {
    (document.getElementById("locationModal") as HTMLElement).style.display = "none";
}
