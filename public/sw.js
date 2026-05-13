// ─── Crypto (mirrors chat.js) ─────────────────────────────────────────────────
const Crypto = {
    importPrivateJwk: (jwk) =>
        crypto.subtle.importKey(
            "jwk",
            jwk,
            { name: "ECDH", namedCurve: "P-256" },
            true,
            ["deriveKey", "deriveBits"],
        ),
    importPublicJwk: (jwk) =>
        crypto.subtle.importKey(
            "jwk",
            jwk,
            { name: "ECDH", namedCurve: "P-256" },
            true,
            [],
        ),
    async deriveAesKey(privateKey, publicKey) {
        const bits = await crypto.subtle.deriveBits(
            { name: "ECDH", public: publicKey },
            privateKey,
            256,
        );
        const hkdf = await crypto.subtle.importKey("raw", bits, "HKDF", false, [
            "deriveKey",
        ]);
        return crypto.subtle.deriveKey(
            {
                name: "HKDF",
                hash: "SHA-256",
                salt: new Uint8Array(32),
                info: new TextEncoder().encode("skr-chat-v1"),
            },
            hkdf,
            { name: "AES-GCM", length: 256 },
            false,
            ["decrypt"],
        );
    },
    async decrypt(aesKey, ivB64, ctB64) {
        const iv = Uint8Array.from(atob(ivB64), (c) => c.charCodeAt(0));
        const ct = Uint8Array.from(atob(ctB64), (c) => c.charCodeAt(0));
        return new TextDecoder().decode(
            await crypto.subtle.decrypt({ name: "AES-GCM", iv }, aesKey, ct),
        );
    },
};

// ─── IndexedDB ────────────────────────────────────────────────────────────────
function idbGet(store, key) {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open("skr_chat", 2);
        req.onupgradeneeded = (e) => {
            const db = e.target.result;
            if (!db.objectStoreNames.contains("keys")) {
                db.createObjectStore("keys");
            }
            if (!db.objectStoreNames.contains("messages")) {
                const ms = db.createObjectStore("messages", { keyPath: "id" });
                ms.createIndex("by_conv", "conversation_id");
            }
        };
        req.onsuccess = (e) => {
            const tx = e.target.result.transaction(store, "readonly");
            const r = tx.objectStore(store).get(key);
            r.onsuccess = () => resolve(r.result);
            r.onerror = () => reject(r.error);
        };
        req.onerror = () => reject(req.error);
    });
}

// ─── Decrypt push payload ─────────────────────────────────────────────────────
const pubKeyCache = {};

async function decryptPushPayload(encryptedPayload, senderId, recipientId) {
    const stored = await idbGet("keys", "keypair_" + recipientId);
    if (!stored?.privateJwk) {
        return null;
    }

    const privateKey = await Crypto.importPrivateJwk(stored.privateJwk);

    if (!pubKeyCache[senderId]) {
        const res = await fetch("/chat/keys/" + senderId, {
            credentials: "include",
            headers: { Accept: "application/json" },
        });
        const data = await res.json();
        if (!data.public_key_jwk) {
            return null;
        }
        pubKeyCache[senderId] = await Crypto.importPublicJwk(
            JSON.parse(data.public_key_jwk),
        );
    }

    const aesKey = await Crypto.deriveAesKey(privateKey, pubKeyCache[senderId]);
    const { iv, ciphertext } = JSON.parse(encryptedPayload);
    return Crypto.decrypt(aesKey, iv, ciphertext);
}

// ─── Push handler ─────────────────────────────────────────────────────────────
self.addEventListener("push", (event) => {
    if (!event.data) {
        return;
    }
    const data = event.data.json();

    event.waitUntil(
        (async () => {
            const clientList = await clients.matchAll({
                type: "window",
                includeUncontrolled: true,
            });
            const hasFocused = clientList.some(
                (c) => c.focused && c.visibilityState === "visible",
            );
            if (hasFocused && data.conversation_id) {
                return;
            }

            let body = data.body;
            if (data.encrypted_payload && data.sender_id && data.recipient_id) {
                try {
                    const text = await decryptPushPayload(
                        data.encrypted_payload,
                        data.sender_id,
                        data.recipient_id,
                    );
                    if (text) {
                        body = data.sender_login + ": " + text;
                    }
                } catch (e) {
                    console.log("omg err", e);
                }
            }

            try {
                await self.registration.showNotification("skr", {
                    body,
                    icon: "/logo.svg",
                    tag: data.tag,
                    data: { url: data.url },
                });

                console.log("NOTIFICATION SHOWN");
            } catch (e) {
                console.error("SHOW NOTIFICATION ERROR", e);
            }

            return 1;
        })(),
    );
});

// ─── Notification click ───────────────────────────────────────────────────────
self.addEventListener("notificationclick", (event) => {
    event.notification.close();
    const url = event.notification.data?.url ?? "/";

    event.waitUntil(
        clients
            .matchAll({ type: "window", includeUncontrolled: true })
            .then((clientList) => {
                for (const client of clientList) {
                    if (
                        client.url.includes(self.location.origin) &&
                        "focus" in client
                    ) {
                        client.navigate(url);
                        return client.focus();
                    }
                }
                return clients.openWindow(url);
            }),
    );
});
