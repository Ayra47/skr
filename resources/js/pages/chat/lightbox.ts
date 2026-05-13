let overlay: HTMLElement | null = null;
let img: HTMLImageElement | null = null;
let imgWrap: HTMLElement | null = null;

let scale = 1;
let panX = 0;
let panY = 0;

// Mouse drag state
let dragging = false;
let dragStartX = 0;
let dragStartY = 0;
let dragPanX = 0;
let dragPanY = 0;
let didDrag = false;

// Touch state
let lastTouchDist = 0;
let lastMidX = 0;
let lastMidY = 0;

let downloadCallback: (() => Promise<void>) | null = null;

const MIN_SCALE = 1;
const MAX_SCALE = 8;

function applyTransform(): void {
    img!.style.transform = `translate(${panX}px, ${panY}px) scale(${scale})`;
}

function resetView(): void {
    scale = 1;
    panX = 0;
    panY = 0;
    applyTransform();
}

function clamp(v: number, min: number, max: number): number {
    return Math.max(min, Math.min(max, v));
}

function wrapCenter(): { x: number; y: number } {
    const r = imgWrap!.getBoundingClientRect();
    return { x: r.left + r.width / 2, y: r.top + r.height / 2 };
}

function zoomAt(cx: number, cy: number, factor: number): void {
    const center = wrapCenter();
    const relX = cx - center.x;
    const relY = cy - center.y;
    const newScale = clamp(scale * factor, MIN_SCALE, MAX_SCALE);
    const ratio = newScale / scale;
    panX = relX * (1 - ratio) + panX * ratio;
    panY = relY * (1 - ratio) + panY * ratio;
    scale = newScale;
    if (scale <= MIN_SCALE) { scale = MIN_SCALE; panX = 0; panY = 0; }
    applyTransform();
    updateCursor();
}

function updateCursor(): void {
    img!.style.cursor = scale > 1 ? (dragging ? "grabbing" : "grab") : "zoom-in";
}

export function openLightbox(src: string, onDownload: (() => Promise<void>) | null): void {
    if (!overlay) {
        overlay = document.createElement("div");
        overlay.className = "lightbox-overlay";
        overlay.innerHTML = `
            <button class="lightbox-close" aria-label="закрыть">✕</button>
            <div class="lightbox-img-wrap">
                <img class="lightbox-img" src="" alt="фото">
            </div>
            <button class="lightbox-dl-btn" type="button">↓ скачать оригинал</button>
        `;
        document.body.appendChild(overlay);

        imgWrap = overlay.querySelector<HTMLElement>(".lightbox-img-wrap")!;
        img = overlay.querySelector<HTMLImageElement>(".lightbox-img")!;

        // Close
        overlay.addEventListener("click", (e) => {
            if (e.target === overlay || e.target === imgWrap) closeLightbox();
        });
        overlay.querySelector(".lightbox-close")!.addEventListener("click", closeLightbox);
        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape" && overlay?.style.display !== "none") closeLightbox();
        });

        // Double-click to reset zoom
        img.addEventListener("dblclick", () => resetView());

        // Wheel zoom toward cursor — proportional to deltaY so trackpad feels smooth
        imgWrap.addEventListener("wheel", (e) => {
            e.preventDefault();
            zoomAt(e.clientX, e.clientY, Math.exp(-e.deltaY * 0.02));
        }, { passive: false });

        // Mouse drag
        img.addEventListener("mousedown", (e) => {
            if (e.button !== 0) { return; }
            dragging = true;
            didDrag = false;
            dragStartX = e.clientX;
            dragStartY = e.clientY;
            dragPanX = panX;
            dragPanY = panY;
            img!.style.cursor = "grabbing";
            e.preventDefault();
        });
        document.addEventListener("mousemove", (e) => {
            if (!dragging) { return; }
            const dx = e.clientX - dragStartX;
            const dy = e.clientY - dragStartY;
            if (Math.abs(dx) + Math.abs(dy) > 2) { didDrag = true; }
            panX = dragPanX + dx;
            panY = dragPanY + dy;
            applyTransform();
        });
        document.addEventListener("mouseup", () => {
            if (!dragging) { return; }
            dragging = false;
            updateCursor();
        });

        // Touch: pinch-zoom + single-finger pan
        imgWrap.addEventListener("touchstart", (e) => {
            if (e.touches.length === 2) {
                dragging = false;
                lastTouchDist = touchDist(e.touches);
                const m = touchMid(e.touches);
                lastMidX = m.x;
                lastMidY = m.y;
            } else if (e.touches.length === 1) {
                dragging = true;
                dragStartX = e.touches[0].clientX;
                dragStartY = e.touches[0].clientY;
                dragPanX = panX;
                dragPanY = panY;
            }
        }, { passive: true });
        imgWrap.addEventListener("touchmove", (e) => {
            if (e.touches.length === 2) {
                e.preventDefault();
                const d = touchDist(e.touches);
                const m = touchMid(e.touches);
                const center = wrapCenter();
                const relX = m.x - center.x;
                const relY = m.y - center.y;
                const factor = d / lastTouchDist;
                const newScale = clamp(scale * factor, MIN_SCALE, MAX_SCALE);
                const ratio = newScale / scale;
                panX = relX * (1 - ratio) + panX * ratio + (m.x - lastMidX);
                panY = relY * (1 - ratio) + panY * ratio + (m.y - lastMidY);
                scale = newScale;
                if (scale <= MIN_SCALE) { scale = MIN_SCALE; panX = 0; panY = 0; }
                lastTouchDist = d;
                lastMidX = m.x;
                lastMidY = m.y;
                applyTransform();
            } else if (e.touches.length === 1 && dragging && scale > 1) {
                e.preventDefault();
                panX = dragPanX + (e.touches[0].clientX - dragStartX);
                panY = dragPanY + (e.touches[0].clientY - dragStartY);
                applyTransform();
            }
        }, { passive: false });
        imgWrap.addEventListener("touchend", () => {
            dragging = false;
        });

        // Download
        const dlBtn = overlay.querySelector<HTMLButtonElement>(".lightbox-dl-btn")!;
        dlBtn.addEventListener("click", async () => {
            if (!downloadCallback) { return; }
            dlBtn.disabled = true;
            dlBtn.textContent = "загрузка…";
            try {
                await downloadCallback();
                dlBtn.textContent = "↓ скачать оригинал";
            } catch {
                dlBtn.textContent = "ошибка — повторить";
            }
            dlBtn.disabled = false;
        });
    }

    img!.src = src;
    resetView();
    updateCursor();

    downloadCallback = onDownload;
    const dlBtn = overlay.querySelector<HTMLButtonElement>(".lightbox-dl-btn")!;
    dlBtn.style.display = onDownload ? "" : "none";
    dlBtn.textContent = "↓ скачать оригинал";
    dlBtn.disabled = false;

    overlay.style.display = "flex";
    document.body.style.overflow = "hidden";
}

function closeLightbox(): void {
    if (overlay) {
        overlay.style.display = "none";
        document.body.style.overflow = "";
        dragging = false;
    }
}

function touchDist(touches: TouchList): number {
    return Math.hypot(
        touches[0].clientX - touches[1].clientX,
        touches[0].clientY - touches[1].clientY,
    );
}

function touchMid(touches: TouchList): { x: number; y: number } {
    return {
        x: (touches[0].clientX + touches[1].clientX) / 2,
        y: (touches[0].clientY + touches[1].clientY) / 2,
    };
}
