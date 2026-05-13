import "./pusher";
import Toastify from "toastify-js";
import "toastify-js/src/toastify.css";

const userId = window.Laravel?.userId;
if (!userId) { throw new Error("global-notifications: no userId"); }

const CSRF = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const VAPID_PUBLIC_KEY = document.querySelector('meta[name="vapid-public-key"]')?.content ?? '';

// ─── Sound ───────────────────────────────────────────────────────────────────
let soundEnabled = true;
const pingAudio = new Audio("/sounds/ping-2.mp3");
pingAudio.volume = 0.4;

function playPing() {
    if (!soundEnabled) { return; }
    pingAudio.currentTime = 0;
    pingAudio.play().catch(() => {});
}

// ─── Crypto (mirrors chat.js) ─────────────────────────────────────────────────
const Crypto = {
    importPrivateJwk: (jwk) => crypto.subtle.importKey('jwk', jwk, { name: 'ECDH', namedCurve: 'P-256' }, true, ['deriveKey', 'deriveBits']),
    importPublicJwk:  (jwk) => crypto.subtle.importKey('jwk', jwk, { name: 'ECDH', namedCurve: 'P-256' }, true, []),
    async deriveAesKey(privateKey, publicKey) {
        const bits = await crypto.subtle.deriveBits({ name: 'ECDH', public: publicKey }, privateKey, 256);
        const hkdf = await crypto.subtle.importKey('raw', bits, 'HKDF', false, ['deriveKey']);
        return crypto.subtle.deriveKey(
            { name: 'HKDF', hash: 'SHA-256', salt: new Uint8Array(32), info: new TextEncoder().encode('skr-chat-v1') },
            hkdf,
            { name: 'AES-GCM', length: 256 },
            false,
            ['decrypt'],
        );
    },
    async decrypt(aesKey, ivB64, ctB64) {
        const iv = Uint8Array.from(atob(ivB64), c => c.charCodeAt(0));
        const ct = Uint8Array.from(atob(ctB64), c => c.charCodeAt(0));
        const plain = await crypto.subtle.decrypt({ name: 'AES-GCM', iv }, aesKey, ct);
        return new TextDecoder().decode(plain);
    },
};

// ─── IndexedDB (same DB as chat.js) ──────────────────────────────────────────
async function idbGet(store, key) {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open('skr_chat', 2);
        req.onupgradeneeded = (e) => {
            const db = e.target.result;
            if (!db.objectStoreNames.contains('keys')) {
                db.createObjectStore('keys');
            }
            if (!db.objectStoreNames.contains('messages')) {
                const ms = db.createObjectStore('messages', { keyPath: 'id' });
                ms.createIndex('by_conv', 'conversation_id');
            }
        };
        req.onsuccess = (e) => {
            const tx = e.target.result.transaction(store, 'readonly');
            const r = tx.objectStore(store).get(key);
            r.onsuccess = () => resolve(r.result);
            r.onerror   = () => reject(r.error);
        };
        req.onerror = () => reject(req.error);
    });
}

// ─── Private key (loaded once at init) ───────────────────────────────────────
let myPrivateKey = null;

async function loadPrivateKey() {
    try {
        const stored = await idbGet('keys', 'keypair_' + userId);
        if (stored?.privateJwk) {
            myPrivateKey = await Crypto.importPrivateJwk(stored.privateJwk);
        }
    } catch {}
}

// ─── Partner public key (cached per user) ────────────────────────────────────
const publicKeyCache = {};

async function getPartnerPublicKey(partnerId) {
    if (publicKeyCache[partnerId]) { return publicKeyCache[partnerId]; }
    const data = await fetch('/chat/keys/' + partnerId, { headers: { Accept: 'application/json' } }).then(r => r.json());
    
    if (!data.public_key_jwk) { throw new Error('no key'); }
    const jwk = JSON.parse(data.public_key_jwk);
    const key = await Crypto.importPublicJwk(jwk);
    publicKeyCache[partnerId] = key;
    return key;
}

async function decryptPayload(encryptedPayload, senderId) {
    if (!myPrivateKey) { return null; }
    const partnerKey = await getPartnerPublicKey(senderId);
    const aesKey = await Crypto.deriveAesKey(myPrivateKey, partnerKey);
    const { iv, ciphertext } = JSON.parse(encryptedPayload);
    return Crypto.decrypt(aesKey, iv, ciphertext);
}

// ─── Preferences ─────────────────────────────────────────────────────────────
let pushEnabled = false;

async function loadPrefs() {
    try {
        const data = await fetch('/settings/notifications', { headers: { Accept: 'application/json' } }).then(r => r.json());
        pushEnabled = !!data.notify_push;
        soundEnabled = data.notify_sound ?? true;
    } catch {}
}

async function savePushEnabled(value) {
    pushEnabled = value;
    await fetch('/settings/notifications', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ notify_push: value }),
    }).catch(() => {});
}

// ─── Push subscription ────────────────────────────────────────────────────────
function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = atob(base64);
    return Uint8Array.from([...raw].map(c => c.charCodeAt(0)));
}

async function saveSubscription(sub) {
    const json = sub.toJSON();
    await fetch('/push/subscription', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': CSRF },
        body: JSON.stringify({ endpoint: json.endpoint, p256dh: json.keys.p256dh, auth: json.keys.auth }),
    }).catch(() => {});
}

async function registerAndSubscribe() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !VAPID_PUBLIC_KEY) { return; }
    const registration = await navigator.serviceWorker.register('/sw.js');
    await navigator.serviceWorker.ready;
    let sub = await registration.pushManager.getSubscription();
    if (!sub) {
        sub = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY),
        });
    }
    await saveSubscription(sub);
}

// ─── Permission ───────────────────────────────────────────────────────────────
async function requestAndEnable() {
    if (Notification.permission === 'granted') {
        if (!pushEnabled) { await savePushEnabled(true); }
        await registerAndSubscribe();
        return;
    }
    if (Notification.permission === 'default') {
        const result = await Notification.requestPermission();
        if (result === 'granted') {
            await savePushEnabled(true);
            await registerAndSubscribe();
        }
    }
}

document.addEventListener('click', async () => {
    if (Notification.permission === 'default') { await requestAndEnable(); }
}, { once: true });

// ─── Toast ────────────────────────────────────────────────────────────────────
function showToast(senderLogin, senderId, text) {
    const body = text ? senderLogin + ': ' + text : 'Новое сообщение от ' + senderLogin;
    Toastify({
        text: body,
        duration: 4000,
        close: true,
        gravity: 'bottom',
        position: 'right',
        stopOnFocus: true,
        style: { background: 'var(--panel-2)', border: '1px solid var(--border-2)', color: 'var(--text)' },
        onClick: () => { window.location.href = '/chats?with=' + senderId + '&login=' + encodeURIComponent(senderLogin); },
    }).showToast();
}

// ─── Init ─────────────────────────────────────────────────────────────────────
Promise.all([loadPrefs(), loadPrivateKey()]).then(async () => {
    if (Notification.permission === 'granted') {
        await registerAndSubscribe();
    }

    // chat.js fires this after decrypting on the chat page — deduplicate
    const toastedIds = new Set();

    window.addEventListener('skr:incoming', (ev) => {
        const { msgId, senderLogin, senderId, text } = ev.detail;
        toastedIds.add(msgId);
        showToast(senderLogin, senderId, text);
    });

    window.Echo.private('chat.' + userId).listen('.chat.message', async (e) => {
        playPing();

        if (window.currentConvId && window.currentConvId === e.conversation_id) { return; }

        // Give chat.js a tick to fire skr:incoming first (it runs in the same Echo handler)
        await new Promise(r => setTimeout(r, 0));
        if (toastedIds.has(e.id)) { return; }

        // Decrypt here (works on any page as long as the key is in IndexedDB)
        let text = null;
        try {
            text = await decryptPayload(e.encrypted_payload, e.sender_id);
        } catch (e) {
            showToast('System', e.sender_id, 'Ошибка!');
        }

        toastedIds.add(e.id);

        showToast(e.sender_login ?? 'кто-то', e.sender_id, text);
    });
});
