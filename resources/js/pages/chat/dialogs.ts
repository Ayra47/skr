export interface PinDialogOptions {
    title: string;
    subtitle: string;
    confirmLabel: string;
    showRecovery: boolean;
    onConfirm: (pin: string) => Promise<boolean | undefined | void>;
    onRecovery?: () => void | Promise<void>;
    onCancel: () => void | Promise<void>;
}

export interface RecoveryPhraseDialogOptions {
    onConfirm: (phrase: string) => Promise<boolean | undefined | void>;
    onCancel: () => void | Promise<void>;
}

type PinDialogElement = HTMLElement & {
    _onConfirm?: (pin: string) => Promise<boolean | undefined | void>;
    _onRecovery?: () => void | Promise<void>;
    _onCancel?: () => void | Promise<void>;
};

type PhraseModalElement = HTMLElement & {
    _onConfirm?: (phrase: string) => Promise<boolean | undefined | void>;
    _onCancel?: () => void | Promise<void>;
};

export function showPinDialog({
    title,
    subtitle,
    confirmLabel,
    showRecovery,
    onConfirm,
    onRecovery,
    onCancel,
}: PinDialogOptions): void {
    const d = document.getElementById("pinDialog") as PinDialogElement;
    d.querySelector(".pin-dialog-title")!.textContent = title;
    d.querySelector(".pin-dialog-subtitle")!.textContent = subtitle;
    const confirmBtn = d.querySelector<HTMLButtonElement>(".pin-dialog-confirm")!;
    confirmBtn.textContent = confirmLabel;
    confirmBtn.disabled = false;
    (d.querySelector(".pin-dialog-recovery") as HTMLElement).style.display = showRecovery
        ? ""
        : "none";
    d.querySelector(".pin-dialog-error")!.textContent = "";
    const input = d.querySelector<HTMLInputElement>(".pin-input")!;
    input.value = "";
    d.style.display = "flex";
    setTimeout(() => input.focus(), 50);
    d._onConfirm = onConfirm;
    d._onRecovery = onRecovery;
    d._onCancel = onCancel;
}

export function hidePinDialog(): void {
    (document.getElementById("pinDialog") as HTMLElement).style.display = "none";
}

export function showRecoveryPhraseDialog({
    onConfirm,
    onCancel,
}: RecoveryPhraseDialogOptions): void {
    const d = document.getElementById("recoveryPhraseRestoreModal") as PhraseModalElement;
    d.querySelector<HTMLTextAreaElement>(".phrase-input")!.value = "";
    d.querySelector(".phrase-dialog-error")!.textContent = "";
    d.querySelector<HTMLButtonElement>(".phrase-dialog-confirm")!.disabled = false;
    d.style.display = "flex";
    setTimeout(() => d.querySelector<HTMLTextAreaElement>(".phrase-input")!.focus(), 50);
    d._onConfirm = onConfirm;
    d._onCancel = onCancel;
}

export function hideRecoveryPhraseDialog(): void {
    (document.getElementById("recoveryPhraseRestoreModal") as HTMLElement).style.display = "none";
}

export function showRecoveryPhrase(phrase: string): void {
    const d = document.getElementById("recoveryPhraseModal") as HTMLElement;
    d.querySelector(".recovery-phrase-text")!.textContent = phrase;
    d.style.display = "flex";
}
