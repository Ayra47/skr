import { AUTH_USER_ID, HAS_PUBLIC_KEY, HAS_KEY_BACKUP } from "./constants";
import { state } from "./state";
import { IDB } from "./idb";
import { Crypto } from "./crypto";
import { fetchJson, post } from "./api";
import { showNotification } from "./ui";
import {
    showPinDialog,
    hidePinDialog,
    showRecoveryPhraseDialog,
    hideRecoveryPhraseDialog,
    showRecoveryPhrase,
} from "./dialogs";

interface StoredKeypair {
    privateJwk: JsonWebKey;
    publicJwk: JsonWebKey;
}

interface KeyBackupResponse {
    success: boolean;
    key_backup: string | null;
}

interface PublicKeyResponse {
    success: boolean;
    public_key_jwk: string;
    key_warn: boolean;
    key_changed_days_ago: number;
}

interface KeyWarnInfo {
    warn: boolean;
    daysAgo: number;
}

const partnerKeyWarnCache: Record<number, KeyWarnInfo> = {};

export async function loadOrGenerateKeyPair(): Promise<void> {
    const stored = await IDB.get<StoredKeypair>("keys", "keypair_" + AUTH_USER_ID);
    if (stored) {
        state.myPrivateKey = await Crypto.importPrivateJwk(stored.privateJwk);
        state.myPublicKeyJwk = stored.publicJwk;
        // Always sync IDB key → server so server always has the key from this browser.
        // If they already match, server does a no-op update; if they diverged (key rotation
        // on another device), this re-establishes the correct key for this session.
        await uploadPublicKey(null);
        if (!HAS_KEY_BACKUP) {
            offerPinBackupSetup();
        }
    } else {
        const backupData = await fetchJson<KeyBackupResponse>("/chat/keys/backup");
        if (backupData.success && backupData.key_backup) {
            await promptPinRestore(backupData.key_backup);
        } else {
            await generateFreshKeyPair();
            offerPinBackupSetup();
        }
    }
    const fp = await Crypto.fingerprint(state.myPublicKeyJwk!);
    document.getElementById("keyFingerprint")!.textContent =
        "отпечаток: " + fp.slice(0, 23) + "…";
}

export async function generateFreshKeyPair(): Promise<void> {
    const pair = await Crypto.generateKeyPair();
    state.myPrivateKey = pair.privateKey;
    state.myPublicKeyJwk = await Crypto.exportPublicJwk(pair.publicKey);
    const privateJwk = await Crypto.exportPrivateJwk(pair.privateKey);
    await IDB.put(
        "keys",
        { privateJwk, publicJwk: state.myPublicKeyJwk },
        "keypair_" + AUTH_USER_ID,
    );
    await uploadPublicKey();
}

export async function promptPinRestore(backupJson: string): Promise<boolean> {
    return new Promise((resolve) => {
        showPinDialog({
            title: "Восстановление ключа",
            subtitle: "Введите PIN-код, чтобы восстановить ключ шифрования на этом устройстве",
            confirmLabel: "Восстановить",
            showRecovery: true,
            onConfirm: async (pin) => {
                try {
                    const privateJwk = await Crypto.decryptPrivateKey(backupJson, pin);
                    const pubJwk: JsonWebKey = {
                        kty: privateJwk.kty,
                        crv: privateJwk.crv,
                        x: privateJwk.x,
                        y: privateJwk.y,
                    };
                    state.myPrivateKey = await Crypto.importPrivateJwk(privateJwk);
                    state.myPublicKeyJwk = pubJwk;
                    await IDB.put(
                        "keys",
                        { privateJwk, publicJwk: pubJwk },
                        "keypair_" + AUTH_USER_ID,
                    );
                    await uploadPublicKey(null);
                    hidePinDialog();
                    showNotification("ключ восстановлен");
                    resolve(true);
                } catch {
                    return false;
                }
            },
            onRecovery: async () => {
                hidePinDialog();
                await promptPhraseRestore();
                resolve(true);
            },
            onCancel: async () => {
                hidePinDialog();
                await generateFreshKeyPair();
                offerPinBackupSetup();
                resolve(false);
            },
        });
    });
}

export async function promptPhraseRestore(): Promise<boolean> {
    return new Promise((resolve) => {
        showRecoveryPhraseDialog({
            onConfirm: async (phrase) => {
                try {
                    const privateJwk = JSON.parse(atob(phrase.trim())) as JsonWebKey;
                    const pubJwk: JsonWebKey = {
                        kty: privateJwk.kty,
                        crv: privateJwk.crv,
                        x: privateJwk.x,
                        y: privateJwk.y,
                    };
                    state.myPrivateKey = await Crypto.importPrivateJwk(privateJwk);
                    state.myPublicKeyJwk = pubJwk;
                    await IDB.put(
                        "keys",
                        { privateJwk, publicJwk: pubJwk },
                        "keypair_" + AUTH_USER_ID,
                    );
                    await uploadPublicKey(null);
                    hideRecoveryPhraseDialog();
                    showNotification("ключ восстановлен по фразе");
                    resolve(true);
                } catch {
                    return false;
                }
            },
            onCancel: async () => {
                hideRecoveryPhraseDialog();
                await generateFreshKeyPair();
                offerPinBackupSetup();
                resolve(false);
            },
        });
    });
}

export function offerPinBackupSetup(): void {
    if (document.getElementById("backupOfferBanner")) {
        return;
    }
    const banner = document.createElement("div");
    banner.id = "backupOfferBanner";
    banner.className = "key-warning";
    const btn = document.createElement("button");
    btn.style.cssText =
        "background:none;border:none;color:inherit;text-decoration:underline;cursor:pointer;font-size:inherit;";
    btn.textContent = "настроить бэкап с PIN";
    btn.addEventListener("click", setupKeyBackup);
    banner.append(
        "⚠ новый ключ шифрования. ",
        btn,
        " или история может стать недоступной при смене браузера.",
    );
    document.getElementById("chatPane")!.insertAdjacentElement("afterbegin", banner);
}

export async function setupKeyBackup(): Promise<void> {
    document.getElementById("backupOfferBanner")?.remove();
    const privateJwk = await Crypto.exportPrivateJwk(state.myPrivateKey!);
    const recoveryPhrase = btoa(JSON.stringify(privateJwk));
    showPinDialog({
        title: "Настройка бэкапа",
        subtitle: "Введите 6-значный PIN для защиты ключа на сервере",
        confirmLabel: "Сохранить бэкап",
        showRecovery: false,
        onConfirm: async (pin) => {
            try {
                const backupJson = await Crypto.encryptPrivateKey(privateJwk, pin);
                const res = await post<{ success: boolean }>("/chat/keys/backup", {
                    key_backup: backupJson,
                });
                if (!res.success) {
                    return false;
                }
                hidePinDialog();
                showRecoveryPhrase(recoveryPhrase);
                return true;
            } catch {
                return false;
            }
        },
        onCancel: () => hidePinDialog(),
    });
}

export async function uploadPublicKey(source: string | null = "fresh"): Promise<void> {
    const payload: Record<string, string> = {
        public_key_jwk: JSON.stringify(state.myPublicKeyJwk),
    };
    if (source) {
        payload.key_change_source = source;
    }
    await post("/chat/keys", payload);
}

export async function getPartnerPublicKey(partnerId: number): Promise<CryptoKey> {
    if (state.partnerPublicKeyCache[partnerId]) {
        return state.partnerPublicKeyCache[partnerId];
    }
    const data = await fetchJson<PublicKeyResponse>("/chat/keys/" + partnerId);
    if (!data.success) {
        throw new Error("Ключ партнёра не найден");
    }
    const jwk = JSON.parse(data.public_key_jwk) as JsonWebKey;
    const key = await Crypto.importPublicJwk(jwk);
    state.partnerPublicKeyCache[partnerId] = key;
    partnerKeyWarnCache[partnerId] = {
        warn: data.key_warn,
        daysAgo: data.key_changed_days_ago,
    };
    return key;
}

export function updateKeyChangeWarn(partnerId: number): void {
    const warn = document.getElementById("keyChangeWarn");
    const text = document.getElementById("keyChangeWarnText");
    if (!warn) {
        return;
    }
    const info = partnerKeyWarnCache[partnerId];
    if (info?.warn) {
        const label =
            info.daysAgo === 0
                ? "сегодня"
                : info.daysAgo === 1
                  ? "1 день назад"
                  : `${info.daysAgo} дн. назад`;
        text!.textContent = `ключ шифрования изменён ${label}`;
        warn.style.display = "flex";
    } else {
        warn.style.display = "none";
    }
}
