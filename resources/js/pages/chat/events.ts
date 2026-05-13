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
} from "./messages";
import { uploadFile, uploadPhoto } from "./file-upload";
import { sendOneTimeLocation, startLiveLocation, stopLiveLocation, clearLiveSession } from "./location";
import {
    updateStoragePreference,
    exportHistoryToFile,
    importHistoryFromFile,
} from "./storage";
import { setupKeyBackup } from "./keys";
import { initEmojiPicker } from "./emoji";
import { autoResize, updateSendBtn } from "./ui";

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
    document.getElementById("conversationList")!.addEventListener("click", (e) => {
        const item = (e.target as HTMLElement).closest<HTMLElement>(".conversation-item");
        if (!item) {
            return;
        }
        const convId = item.dataset.convId;
        const partnerId = parseInt(item.dataset.partnerId!);
        const partnerLogin = item.dataset.partnerLogin!;
        if (convId) {
            openConversation(parseInt(convId), partnerId, partnerLogin);
        } else {
            startChatWithFriend(partnerId, partnerLogin);
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
