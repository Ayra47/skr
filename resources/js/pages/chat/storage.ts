import { state } from "./state";
import { IDB } from "./idb";
import { fetchJson, post } from "./api";
import { showNotification } from "./ui";
import type { Message } from "./types";

export async function loadStoragePreference(): Promise<void> {
    const data = await fetchJson<{ success: boolean; storage_preference: string }>(
        "/chat/settings",
    );
    if (data.success) {
        (document.getElementById("storageSelect") as HTMLSelectElement).value =
            data.storage_preference;
        toggleDeviceExport(data.storage_preference);
    }
}

export function toggleDeviceExport(pref: string): void {
    (document.getElementById("deviceExportRow") as HTMLElement).style.display =
        pref === "device" ? "flex" : "none";
}

export async function updateStoragePreference(value: string): Promise<void> {
    toggleDeviceExport(value);
    await post("/chat/settings", { storage_preference: value });
    showNotification("настройки сохранены");
}

async function deriveExportKey(usage: "encrypt" | "decrypt"): Promise<CryptoKey> {
    const privJwk = await crypto.subtle.exportKey("jwk", state.myPrivateKey!);
    const dBytes = Uint8Array.from(
        atob(privJwk.d!.replace(/-/g, "+").replace(/_/g, "/")),
        (c) => c.charCodeAt(0),
    );
    const hkdfKey = await crypto.subtle.importKey("raw", dBytes, "HKDF", false, [
        "deriveKey",
    ]);
    return crypto.subtle.deriveKey(
        {
            name: "HKDF",
            hash: "SHA-256",
            salt: new Uint8Array(32),
            info: new TextEncoder().encode("skr-export-v1"),
        },
        hkdfKey,
        { name: "AES-GCM", length: 256 },
        false,
        [usage],
    );
}

export async function exportHistoryToFile(): Promise<void> {
    const all = await IDB.getByIndex<Message & { conversation_id: number }>(
        "messages",
        "by_conv",
        state.currentConvId ?? IDBKeyRange.lowerBound(0),
    );
    const json = JSON.stringify(all);
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const keyMat = await deriveExportKey("encrypt");
    const enc = await crypto.subtle.encrypt(
        { name: "AES-GCM", iv },
        keyMat,
        new TextEncoder().encode(json),
    );
    const blob = new Blob([iv, new Uint8Array(enc)], {
        type: "application/octet-stream",
    });
    const a = document.createElement("a");
    a.href = URL.createObjectURL(blob);
    a.download = "chat-history-" + Date.now() + ".enc";
    a.click();
    showNotification("история экспортирована");
}

export async function importHistoryFromFile(event: Event): Promise<void> {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) {
        return;
    }
    const buf = await file.arrayBuffer();
    const iv = new Uint8Array(buf, 0, 12);
    const ct = new Uint8Array(buf, 12);
    try {
        const keyMat = await deriveExportKey("decrypt");
        const dec = await crypto.subtle.decrypt({ name: "AES-GCM", iv }, keyMat, ct);
        const messages = JSON.parse(new TextDecoder().decode(dec)) as (Message & {
            conversation_id: number;
        })[];
        for (const msg of messages) {
            await IDB.putMessage(msg);
        }
        showNotification("импортировано " + messages.length + " сообщений");
    } catch {
        showNotification("ошибка импорта файла");
    }
}
