interface EncryptedPayload {
    iv: string;
    ciphertext: string;
}

interface PrivateKeyBackup {
    salt: string;
    iv: string;
    ciphertext: string;
}

export const Crypto = {
    async generateKeyPair(): Promise<CryptoKeyPair> {
        return crypto.subtle.generateKey(
            { name: "ECDH", namedCurve: "P-256" },
            true,
            ["deriveKey", "deriveBits"],
        );
    },

    async exportPublicJwk(key: CryptoKey): Promise<JsonWebKey> {
        return crypto.subtle.exportKey("jwk", key);
    },

    async exportPrivateJwk(key: CryptoKey): Promise<JsonWebKey> {
        return crypto.subtle.exportKey("jwk", key);
    },

    async importPublicJwk(jwk: JsonWebKey): Promise<CryptoKey> {
        return crypto.subtle.importKey(
            "jwk",
            jwk,
            { name: "ECDH", namedCurve: "P-256" },
            true,
            [],
        );
    },

    async importPrivateJwk(jwk: JsonWebKey): Promise<CryptoKey> {
        return crypto.subtle.importKey(
            "jwk",
            jwk,
            { name: "ECDH", namedCurve: "P-256" },
            true,
            ["deriveKey", "deriveBits"],
        );
    },

    async deriveAesKey(
        privateKey: CryptoKey,
        partnerPublicKey: CryptoKey,
    ): Promise<CryptoKey> {
        const bits = await crypto.subtle.deriveBits(
            { name: "ECDH", public: partnerPublicKey },
            privateKey,
            256,
        );
        const hkdfKey = await crypto.subtle.importKey(
            "raw",
            bits,
            "HKDF",
            false,
            ["deriveKey"],
        );
        return crypto.subtle.deriveKey(
            {
                name: "HKDF",
                hash: "SHA-256",
                salt: new Uint8Array(32),
                info: new TextEncoder().encode("skr-chat-v1"),
            },
            hkdfKey,
            { name: "AES-GCM", length: 256 },
            false,
            ["encrypt", "decrypt"],
        );
    },

    async encrypt(aesKey: CryptoKey, plaintext: string): Promise<EncryptedPayload> {
        const iv = crypto.getRandomValues(new Uint8Array(12));
        const ct = await crypto.subtle.encrypt(
            { name: "AES-GCM", iv },
            aesKey,
            new TextEncoder().encode(plaintext),
        );
        return {
            iv: btoa(String.fromCharCode(...iv)),
            ciphertext: btoa(String.fromCharCode(...new Uint8Array(ct))),
        };
    },

    async decrypt(
        aesKey: CryptoKey,
        ivB64: string,
        ctB64: string,
    ): Promise<string> {
        const iv = Uint8Array.from(atob(ivB64), (c) => c.charCodeAt(0));
        const ct = Uint8Array.from(atob(ctB64), (c) => c.charCodeAt(0));
        const plain = await crypto.subtle.decrypt({ name: "AES-GCM", iv }, aesKey, ct);
        return new TextDecoder().decode(plain);
    },

    async fingerprint(publicJwk: JsonWebKey): Promise<string> {
        const encoded = new TextEncoder().encode(JSON.stringify(publicJwk));
        const hash = await crypto.subtle.digest("SHA-256", encoded);
        return Array.from(new Uint8Array(hash))
            .map((b) => b.toString(16).padStart(2, "0"))
            .join("")
            .toUpperCase()
            .match(/.{4}/g)!
            .join(" ");
    },

    async deriveKeyFromPin(pin: string, saltB64: string): Promise<CryptoKey> {
        const salt = Uint8Array.from(atob(saltB64), (c) => c.charCodeAt(0));
        const keyMat = await crypto.subtle.importKey(
            "raw",
            new TextEncoder().encode(pin),
            "PBKDF2",
            false,
            ["deriveKey"],
        );
        return crypto.subtle.deriveKey(
            { name: "PBKDF2", hash: "SHA-256", salt, iterations: 100000 },
            keyMat,
            { name: "AES-GCM", length: 256 },
            false,
            ["encrypt", "decrypt"],
        );
    },

    async encryptPrivateKey(privateJwk: JsonWebKey, pin: string): Promise<string> {
        const salt = crypto.getRandomValues(new Uint8Array(16));
        const iv = crypto.getRandomValues(new Uint8Array(12));
        const saltB64 = btoa(String.fromCharCode(...salt));
        const aesKey = await this.deriveKeyFromPin(pin, saltB64);
        const ct = await crypto.subtle.encrypt(
            { name: "AES-GCM", iv },
            aesKey,
            new TextEncoder().encode(JSON.stringify(privateJwk)),
        );
        const backup: PrivateKeyBackup = {
            salt: saltB64,
            iv: btoa(String.fromCharCode(...iv)),
            ciphertext: btoa(String.fromCharCode(...new Uint8Array(ct))),
        };
        return JSON.stringify(backup);
    },

    async decryptPrivateKey(backupJson: string, pin: string): Promise<JsonWebKey> {
        const { salt, iv, ciphertext } = JSON.parse(backupJson) as PrivateKeyBackup;
        const aesKey = await this.deriveKeyFromPin(pin, salt);
        const ivBytes = Uint8Array.from(atob(iv), (c) => c.charCodeAt(0));
        const ctBytes = Uint8Array.from(atob(ciphertext), (c) => c.charCodeAt(0));
        const plain = await crypto.subtle.decrypt(
            { name: "AES-GCM", iv: ivBytes },
            aesKey,
            ctBytes,
        );
        return JSON.parse(new TextDecoder().decode(plain)) as JsonWebKey;
    },
};
