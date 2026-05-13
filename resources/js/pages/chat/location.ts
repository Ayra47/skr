import { CSRF, post } from "./api";
import { state } from "./state";
import { Crypto } from "./crypto";
import { getPartnerPublicKey } from "./keys";

export interface LocationPayload {
    lat: number;
    lng: number;
    accuracy: number;
}

let liveSessionId: string | null = null;
let liveIntervalId: ReturnType<typeof setInterval> | null = null;

// ─── Geolocation helpers ──────────────────────────────────────────────────────

export function getCurrentPosition(): Promise<GeolocationPosition> {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error("Геолокация не поддерживается браузером"));
            return;
        }
        navigator.geolocation.getCurrentPosition(resolve, reject, {
            enableHighAccuracy: true,
            timeout: 10_000,
            maximumAge: 5_000,
        });
    });
}

// ─── Encrypt position with conversation ECDH key ──────────────────────────────

async function encryptPosition(payload: LocationPayload): Promise<string> {
    const partnerKey = await getPartnerPublicKey(state.currentPartnerId!);
    const aesKey = await Crypto.deriveAesKey(state.myPrivateKey!, partnerKey);
    const { iv, ciphertext } = await Crypto.encrypt(
        aesKey,
        JSON.stringify(payload),
    );
    return JSON.stringify({ iv, ciphertext });
}

// ─── One-time location ────────────────────────────────────────────────────────

export async function sendOneTimeLocation(): Promise<{
    lat: number;
    lng: number;
    accuracy: number;
}> {
    const pos = await getCurrentPosition();
    return {
        lat: pos.coords.latitude,
        lng: pos.coords.longitude,
        accuracy: Math.round(pos.coords.accuracy),
    };
}

// ─── Live location ────────────────────────────────────────────────────────────

export async function startLiveLocation(durationMinutes: number): Promise<{
    sessionId: string;
    expiresAt: string;
    lat: number;
    lng: number;
    accuracy: number;
}> {
    if (!state.currentConvId) {
        throw new Error("Нет активного чата");
    }

    // Get initial position first so we fail fast if permission denied
    const pos = await getCurrentPosition();
    const lat = pos.coords.latitude;
    const lng = pos.coords.longitude;
    const accuracy = Math.round(pos.coords.accuracy);

    // Create server session
    const data = await post<{
        success: boolean;
        session_id: string;
        expires_at: string;
        message?: string;
    }>(`/chat/${state.currentConvId}/location`, {
        duration_minutes: durationMinutes,
    });
    if (!data.success) {
        throw new Error(data.message ?? "Ошибка создания сессии");
    }

    liveSessionId = data.session_id;

    // Send first position update immediately
    await pushPositionUpdate(liveSessionId, { lat, lng, accuracy });

    // Start 5-second interval
    liveIntervalId = setInterval(async () => {
        if (!liveSessionId) {
            return;
        }
        try {
            const p = await getCurrentPosition();
            await pushPositionUpdate(liveSessionId, {
                lat: p.coords.latitude,
                lng: p.coords.longitude,
                accuracy: Math.round(p.coords.accuracy),
            });
        } catch {
            // silently skip failed updates
        }
    }, 5_000);

    return {
        sessionId: liveSessionId,
        expiresAt: data.expires_at,
        lat,
        lng,
        accuracy,
    };
}

async function pushPositionUpdate(
    sessionId: string,
    payload: LocationPayload,
): Promise<void> {
    if (!state.currentConvId) {
        return;
    }
    const encrypted = await encryptPosition(payload);
    await fetch(`/chat/${state.currentConvId}/location/${sessionId}/position`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            "X-CSRF-TOKEN": CSRF,
        },
        body: JSON.stringify({ encrypted_payload: encrypted }),
    });
}

export async function stopLiveLocation(): Promise<void> {
    if (!liveSessionId || !state.currentConvId) {
        return;
    }

    clearInterval(liveIntervalId!);
    liveIntervalId = null;

    const sessionToStop = liveSessionId;
    liveSessionId = null;

    await fetch(
        `/chat/${state.currentConvId}/location/${sessionToStop}`,
        {
            method: "DELETE",
            headers: { Accept: "application/json", "X-CSRF-TOKEN": CSRF },
        },
    );
}

export function getActiveLiveSessionId(): string | null {
    return liveSessionId;
}

export async function stopSessionByUuid(convId: number, sessionId: string): Promise<void> {
    if (liveSessionId === sessionId) {
        clearInterval(liveIntervalId!);
        liveIntervalId = null;
        liveSessionId = null;
    }
    await fetch(`/chat/${convId}/location/${sessionId}`, {
        method: "DELETE",
        headers: { Accept: "application/json", "X-CSRF-TOKEN": CSRF },
    });
}

// Auto-stop when session expires (called from timer countdown reaching zero)
export function clearLiveSession(): void {
    if (liveIntervalId) {
        clearInterval(liveIntervalId);
        liveIntervalId = null;
    }
    liveSessionId = null;
}
