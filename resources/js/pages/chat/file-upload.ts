import { CSRF } from "./api";
import { state } from "./state";

const CHUNK_SIZE = 1.5 * 1024 * 1024; // 1.5MB plaintext — stays within PHP's 2M upload_max_filesize
const PHOTO_MAX_DIM = 1280;
const PHOTO_JPEG_QUALITY = 0.82;

export interface FileUploadResult {
    fileUuid: string;
    fileKey: string; // base64 raw AES-256-GCM key
    name: string;
    mime: string;
    size: number; // plaintext byte count
    chunks: number;
    chunkSize: number;
    expiresAt: string;
}

export interface PhotoUploadResult {
    preview: FileUploadResult;
    original: FileUploadResult;
}

async function generateFileKey(): Promise<CryptoKey> {
    return crypto.subtle.generateKey({ name: "AES-GCM", length: 256 }, true, [
        "encrypt",
        "decrypt",
    ]);
}

async function exportKeyB64(key: CryptoKey): Promise<string> {
    const raw = await crypto.subtle.exportKey("raw", key);
    return btoa(String.fromCharCode(...new Uint8Array(raw)));
}

async function encryptChunk(key: CryptoKey, chunk: Uint8Array<ArrayBuffer>): Promise<Uint8Array<ArrayBuffer>> {
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const ct = await crypto.subtle.encrypt({ name: "AES-GCM", iv }, key, chunk);
    // Wire format: [12B IV][AES-GCM ciphertext]
    const out = new Uint8Array(12 + ct.byteLength);
    out.set(iv, 0);
    out.set(new Uint8Array(ct), 12);
    return out as Uint8Array<ArrayBuffer>;
}

export async function uploadFile(
    file: File,
    onProgress: (percent: number) => void,
): Promise<FileUploadResult> {
    const convId = state.currentConvId;
    if (!convId) {
        throw new Error("Нет активного чата");
    }

    const uploadUuid = crypto.randomUUID();
    const fileKey = await generateFileKey();
    const fileKeyB64 = await exportKeyB64(fileKey);
    const totalChunks = Math.ceil(file.size / CHUNK_SIZE) || 1;

    let serverFileUuid = "";
    let expiresAt = "";

    for (let i = 0; i < totalChunks; i++) {
        const start = i * CHUNK_SIZE;
        const end = Math.min(start + CHUNK_SIZE, file.size);
        const plain = new Uint8Array(await file.slice(start, end).arrayBuffer()) as Uint8Array<ArrayBuffer>;
        const encrypted = await encryptChunk(fileKey, plain);

        const form = new FormData();
        form.append("upload_uuid", uploadUuid);
        form.append("chunk_index", String(i));
        form.append("total_chunks", String(totalChunks));
        form.append(
            "chunk",
            new Blob([encrypted.buffer as ArrayBuffer], { type: "application/octet-stream" }),
            "chunk",
        );

        const resp = await fetch(`/chat/${convId}/files`, {
            method: "POST",
            headers: { "X-CSRF-TOKEN": CSRF, Accept: "application/json" },
            body: form,
        });

        const data = await resp.json() as {
            success: boolean;
            status?: "partial" | "complete";
            file_uuid?: string;
            expires_at?: string;
            message?: string;
        };

        if (!resp.ok || !data.success) {
            throw new Error(data.message ?? "Ошибка загрузки");
        }

        onProgress(Math.round(((i + 1) / totalChunks) * 100));

        if (data.status === "complete") {
            serverFileUuid = data.file_uuid!;
            expiresAt = data.expires_at!;
        }
    }

    return {
        fileUuid: serverFileUuid,
        fileKey: fileKeyB64,
        name: file.name,
        mime: file.type || "application/octet-stream",
        size: file.size,
        chunks: totalChunks,
        chunkSize: CHUNK_SIZE,
        expiresAt,
    };
}

async function compressImage(file: File): Promise<File> {
    return new Promise((resolve, reject) => {
        const img = new Image();
        const blobUrl = URL.createObjectURL(file);
        img.onload = () => {
            URL.revokeObjectURL(blobUrl);
            let w = img.naturalWidth;
            let h = img.naturalHeight;
            if (w > PHOTO_MAX_DIM || h > PHOTO_MAX_DIM) {
                if (w >= h) { h = Math.round(h * PHOTO_MAX_DIM / w); w = PHOTO_MAX_DIM; }
                else { w = Math.round(w * PHOTO_MAX_DIM / h); h = PHOTO_MAX_DIM; }
            }
            const canvas = document.createElement("canvas");
            canvas.width = w;
            canvas.height = h;
            canvas.getContext("2d")!.drawImage(img, 0, 0, w, h);
            canvas.toBlob((blob) => {
                if (!blob) { reject(new Error("сжатие не удалось")); return; }
                resolve(new File([blob], file.name.replace(/\.[^.]+$/, ".jpg"), { type: "image/jpeg" }));
            }, "image/jpeg", PHOTO_JPEG_QUALITY);
        };
        img.onerror = () => { URL.revokeObjectURL(blobUrl); reject(new Error("не удалось загрузить изображение")); };
        img.src = blobUrl;
    });
}

export async function uploadPhoto(
    file: File,
    onProgress: (pct: number) => void,
): Promise<PhotoUploadResult> {
    const compressed = await compressImage(file);
    const preview = await uploadFile(compressed, (pct) => onProgress(Math.round(pct * 0.55)));
    const original = await uploadFile(file, (pct) => onProgress(55 + Math.round(pct * 0.45)));
    return { preview, original };
}
