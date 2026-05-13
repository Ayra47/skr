import { escapeHtml } from "./ui";
import { openLightbox } from "./lightbox";

const IMAGE_INLINE_LIMIT = 20 * 1024 * 1024;
const AUDIO_INLINE_LIMIT = 50 * 1024 * 1024;
const VIDEO_INLINE_LIMIT = 300 * 1024 * 1024;

export interface FilePayload {
    id: string;
    key: string;
    chunks: number;
    chunk_size: number;
    name: string;
    mime: string;
    size: number;
}

async function importFileKey(keyB64: string): Promise<CryptoKey> {
    const raw = Uint8Array.from(atob(keyB64), (c) => c.charCodeAt(0));
    return crypto.subtle.importKey("raw", raw, { name: "AES-GCM" }, false, ["decrypt"]);
}

async function decryptChunk(key: CryptoKey, data: Uint8Array<ArrayBuffer>): Promise<Uint8Array<ArrayBuffer>> {
    const iv = data.slice(0, 12) as Uint8Array<ArrayBuffer>;
    const ct = data.slice(12) as Uint8Array<ArrayBuffer>;
    const plain = await crypto.subtle.decrypt({ name: "AES-GCM", iv }, key, ct);
    return new Uint8Array(plain) as Uint8Array<ArrayBuffer>;
}

async function downloadAndDecrypt(payload: FilePayload): Promise<Uint8Array> {
    const resp = await fetch(`/chat/files/${payload.id}`, {
        headers: { Accept: "application/octet-stream" },
    });

    if (!resp.ok) {
        throw new Error(resp.status === 410 ? "Файл истёк" : "Ошибка загрузки файла");
    }

    const encrypted = new Uint8Array(await resp.arrayBuffer()) as Uint8Array<ArrayBuffer>;
    const key = await importFileKey(payload.key);

    // Each encrypted chunk = 12 (IV) + chunk_size plaintext + 16 (GCM tag)
    const encChunkSize = 12 + payload.chunk_size + 16;
    const parts: Uint8Array<ArrayBuffer>[] = [];
    let offset = 0;

    for (let i = 0; i < payload.chunks; i++) {
        const isLast = i === payload.chunks - 1;
        const end = isLast ? encrypted.length : offset + encChunkSize;
        const chunk = encrypted.slice(offset, end) as Uint8Array<ArrayBuffer>;
        parts.push(await decryptChunk(key, chunk));
        offset = end;
    }

    const totalSize = parts.reduce((sum, p) => sum + p.byteLength, 0);
    const result = new Uint8Array(totalSize) as Uint8Array<ArrayBuffer>;
    let pos = 0;
    for (const part of parts) { result.set(part, pos); pos += part.byteLength; }
    return result;
}

function makeBlobUrl(data: Uint8Array, mime: string): string {
    return URL.createObjectURL(new Blob([data.buffer as ArrayBuffer], { type: mime }));
}

function formatBytes(bytes: number): string {
    if (bytes < 1024) { return bytes + " Б"; }
    if (bytes < 1024 * 1024) { return (bytes / 1024).toFixed(1) + " КБ"; }
    if (bytes < 1024 * 1024 * 1024) { return (bytes / (1024 * 1024)).toFixed(1) + " МБ"; }
    return (bytes / (1024 * 1024 * 1024)).toFixed(2) + " ГБ";
}

function fileExt(name: string): string {
    const dot = name.lastIndexOf(".");
    return dot > 0 ? name.slice(dot + 1).toUpperCase().slice(0, 8) : "FILE";
}

function expiryWarning(expiresAt: string): string {
    const hoursLeft = Math.round((new Date(expiresAt).getTime() - Date.now()) / 3600_000);
    return hoursLeft <= 48
        ? `<div class="file-expiry-warn">⏱ истекает через ~${hoursLeft} ч</div>`
        : "";
}

export function renderFileBubble(payload: FilePayload, expiresAt: string | undefined): HTMLElement {
    const wrap = document.createElement("div");
    wrap.className = "file-bubble";

    const isImage = payload.mime.startsWith("image/") && payload.size <= IMAGE_INLINE_LIMIT;
    const isAudio = payload.mime.startsWith("audio/") && payload.size <= AUDIO_INLINE_LIMIT;
    const isVideo = payload.mime.startsWith("video/") && payload.size <= VIDEO_INLINE_LIMIT;
    const warn = expiresAt ? expiryWarning(expiresAt) : "";

    if (isImage) {
        wrap.innerHTML = `
            <div class="file-preview file-preview--image">
                <div class="file-media file-media--loading"><span class="file-spinner"></span></div>
            </div>
            ${warn}
        `;
        autoLoad(wrap, payload, "image");
    } else if (isAudio) {
        wrap.innerHTML = `
            <div class="file-preview file-preview--audio">
                <div class="file-media file-media--loading"><span class="file-spinner"></span></div>
            </div>
            ${warn}
        `;
        autoLoad(wrap, payload, "audio");
    } else if (isVideo) {
        wrap.innerHTML = `
            <div class="file-preview file-preview--video">
                <div class="file-media file-media--loading"><span class="file-spinner"></span></div>
            </div>
            ${warn}
        `;
        autoLoad(wrap, payload, "video");
    } else {
        wrap.innerHTML = `
            <div class="file-preview file-preview--generic">
                <span class="file-ext-badge">${escapeHtml(fileExt(payload.name))}</span>
                <div class="file-info">
                    <span class="file-name">${escapeHtml(payload.name)}</span>
                    <span class="file-size">${formatBytes(payload.size)}</span>
                </div>
                <button class="file-download-btn" type="button">Скачать</button>
            </div>
            ${warn}
        `;
        bindDownloadBtn(wrap, payload);
    }

    return wrap;
}

function autoLoad(wrap: HTMLElement, payload: FilePayload, kind: "image" | "audio" | "video"): void {
    const mediaDiv = wrap.querySelector<HTMLElement>(".file-media")!;
    downloadAndDecrypt(payload)
        .then((data) => {
            const url = makeBlobUrl(data, payload.mime);
            mediaDiv.classList.remove("file-media--loading");
            mediaDiv.innerHTML = ""; // remove spinner
            let el: HTMLElement;
            if (kind === "image") {
                const img = document.createElement("img");
                img.src = url;
                img.className = "file-img photo-clickable";
                img.alt = payload.name;
                img.addEventListener("click", () => openLightbox(url, null));
                el = img;
            } else if (kind === "video") {
                const video = document.createElement("video");
                video.src = url;
                video.controls = true;
                video.className = "file-video";
                el = video;
            } else {
                const audio = document.createElement("audio");
                audio.src = url;
                audio.controls = true;
                audio.className = "file-audio";
                el = audio;
            }
            mediaDiv.appendChild(el);
        })
        .catch(() => {
            mediaDiv.classList.remove("file-media--loading");
            mediaDiv.innerHTML = `<span class="file-error">не удалось загрузить</span>`;
        });
}


export function renderPhotoBubble(
    preview: FilePayload,
    original: FilePayload,
    expiresAt: string | undefined,
): HTMLElement {
    const wrap = document.createElement("div");
    wrap.className = "file-bubble";
    const warn = expiresAt ? expiryWarning(expiresAt) : "";
    wrap.innerHTML = `
        <div class="file-preview file-preview--image">
            <div class="file-media file-media--loading"><span class="file-spinner"></span></div>
        </div>
        ${warn}
    `;

    const mediaDiv = wrap.querySelector<HTMLElement>(".file-media")!;
    downloadAndDecrypt(preview)
        .then((data) => {
            const url = makeBlobUrl(data, preview.mime);
            mediaDiv.classList.remove("file-media--loading");
            mediaDiv.innerHTML = "";
            const img = document.createElement("img");
            img.src = url;
            img.className = "file-img photo-clickable";
            img.alt = preview.name;
            img.addEventListener("click", () => {
                openLightbox(url, async () => {
                    const origData = await downloadAndDecrypt(original);
                    const origUrl = makeBlobUrl(origData, original.mime);
                    const a = document.createElement("a");
                    a.href = origUrl;
                    a.download = original.name;
                    a.click();
                    setTimeout(() => URL.revokeObjectURL(origUrl), 10_000);
                });
            });
            mediaDiv.appendChild(img);
        })
        .catch(() => {
            mediaDiv.classList.remove("file-media--loading");
            mediaDiv.innerHTML = `<span class="file-error">не удалось загрузить</span>`;
        });

    return wrap;
}

function bindDownloadBtn(wrap: HTMLElement, payload: FilePayload): void {
    const btn = wrap.querySelector<HTMLButtonElement>(".file-download-btn")!;
    btn.addEventListener("click", async () => {
        btn.disabled = true;
        btn.textContent = "Загрузка…";
        try {
            const data = await downloadAndDecrypt(payload);
            const url = makeBlobUrl(data, payload.mime);
            const a = document.createElement("a");
            a.href = url;
            a.download = payload.name;
            a.click();
            setTimeout(() => URL.revokeObjectURL(url), 10_000);
            btn.disabled = false;
            btn.textContent = "Скачать";
        } catch (e) {
            btn.disabled = false;
            btn.textContent = "Ошибка — повторить";
            console.error("File download error:", e);
        }
    });
}
