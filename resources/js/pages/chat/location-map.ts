import L from "leaflet";
import "leaflet/dist/leaflet.css";

// Fix Leaflet default marker icon path broken by bundlers
import markerIcon2x from "leaflet/dist/images/marker-icon-2x.png";
import markerIcon from "leaflet/dist/images/marker-icon.png";
import markerShadow from "leaflet/dist/images/marker-shadow.png";

delete (L.Icon.Default.prototype as unknown as Record<string, unknown>)._getIconUrl;
L.Icon.Default.mergeOptions({
    iconUrl: markerIcon,
    iconRetinaUrl: markerIcon2x,
    shadowUrl: markerShadow,
});

interface MapInstance {
    map: L.Map;
    marker: L.Marker;
    timerEl: HTMLElement | null;
    timerInterval: ReturnType<typeof setInterval> | null;
}

// session_id → map instance (for live location updates)
const instances = new Map<string, MapInstance>();

export function renderLocationMapBubble(options: {
    lat: number;
    lng: number;
    sessionId: string | null;    // null = one-time
    isOwn: boolean;
    expiresAt: string | null;
    isActive?: boolean;          // false = render as frozen immediately
    onStop?: () => void;
}): HTMLElement {
    const { lat, lng, sessionId, isOwn, expiresAt, onStop } = options;
    const isActive = options.isActive !== false; // default true
    const isLive = sessionId !== null && isActive;

    const wrap = document.createElement("div");
    wrap.className = "location-bubble";
    if (sessionId) { wrap.dataset.sessionId = sessionId; }

    const mapEl = document.createElement("div");
    mapEl.className = "location-map-tile";
    wrap.appendChild(mapEl);

    // Controls row
    const controls = document.createElement("div");
    controls.className = "location-controls";

    if (isLive) {
        const liveRow = document.createElement("div");
        liveRow.className = "location-live-row";

        const dot = document.createElement("span");
        dot.className = "location-live-dot";
        liveRow.appendChild(dot);

        const timerEl = document.createElement("span");
        timerEl.className = "location-live-label";
        timerEl.textContent = expiresAt ? formatCountdown(expiresAt) : "Живая геолокация";
        liveRow.appendChild(timerEl);

        controls.appendChild(liveRow);

        if (isOwn && onStop) {
            const stopBtn = document.createElement("button");
            stopBtn.className = "location-stop-btn";
            stopBtn.type = "button";
            stopBtn.textContent = "Остановить";
            stopBtn.addEventListener("click", () => {
                stopBtn.disabled = true;
                onStop();
            });
            controls.appendChild(stopBtn);
        }

        // Store timer reference for update later
        wrap.dataset.timerEl = "1";
        setTimeout(() => {
            const inst = instances.get(sessionId!);
            if (inst) { inst.timerEl = timerEl; }
        }, 100);
    }

    if (isLive) {
        wrap.appendChild(controls);
    }

    // Init Leaflet after element is in DOM (rAF gives layout time)
    requestAnimationFrame(() => {
        const map = L.map(mapEl, {
            center: [lat, lng],
            zoom: 15,
            zoomControl: false,
            attributionControl: true,
            dragging: false,
            scrollWheelZoom: false,
            doubleClickZoom: false,
            touchZoom: false,
        });
        map.attributionControl.setPrefix(false);

        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19,
        }).addTo(map);

        const marker = L.marker([lat, lng]).addTo(map);

        if (sessionId && isActive) {
            const timerEl = wrap.querySelector<HTMLElement>(".location-live-label");
            let timerInterval: ReturnType<typeof setInterval> | null = null;

            if (expiresAt) {
                timerInterval = setInterval(() => {
                    if (!timerEl) { return; }
                    const remaining = formatCountdown(expiresAt);
                    timerEl.textContent = remaining;
                    if (remaining === "завершена") {
                        clearInterval(timerInterval!);
                        freezeSession(sessionId);
                    }
                }, 1000);
            }

            instances.set(sessionId, { map, marker, timerEl, timerInterval });
        }

        // Click on map tile opens fullscreen
        map.on("click", () => {
            const pos = marker.getLatLng();
            openFullscreen(pos.lat, pos.lng, marker);
        });
        mapEl.style.cursor = "pointer";
    });

    return wrap;
}

export function updateMapMarker(sessionId: string, lat: number, lng: number): void {
    const inst = instances.get(sessionId);
    if (!inst) { return; }
    inst.marker.setLatLng([lat, lng]);
    inst.map.panTo([lat, lng]);
}

export function freezeSession(sessionId: string): void {
    const inst = instances.get(sessionId);
    if (inst?.timerInterval) {
        clearInterval(inst.timerInterval);
        inst.timerInterval = null;
    }

    const bubble = document.querySelector<HTMLElement>(`[data-session-id="${sessionId}"]`);
    if (!bubble) { return; }

    bubble.querySelector(".location-live-row")?.remove();
    bubble.querySelector(".location-stop-btn")?.remove();

    const controls = bubble.querySelector<HTMLElement>(".location-controls");
    if (controls && !controls.hasChildNodes()) {
        controls.remove();
    }
}

// ─── Fullscreen overlay ───────────────────────────────────────────────────────

let fsOverlay: HTMLElement | null = null;
let fsMap: L.Map | null = null;
let fsMarker: L.Marker | null = null;

function openFullscreen(lat: number, lng: number, sourceMarker?: L.Marker): void {
    if (!fsOverlay) {
        fsOverlay = document.createElement("div");
        fsOverlay.className = "location-fs-overlay";
        fsOverlay.innerHTML = `
            <button class="location-fs-close" aria-label="закрыть">✕</button>
            <div class="location-fs-map"></div>
        `;
        document.body.appendChild(fsOverlay);

        fsOverlay.querySelector(".location-fs-close")!.addEventListener("click", closeFullscreen);
        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape" && fsOverlay?.style.display !== "none") { closeFullscreen(); }
        });

        const mapEl = fsOverlay.querySelector<HTMLElement>(".location-fs-map")!;
        fsMap = L.map(mapEl, { center: [lat, lng], zoom: 15 });
        fsMap.attributionControl.setPrefix(false);
        L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19,
        }).addTo(fsMap);
        fsMarker = L.marker([lat, lng]).addTo(fsMap);
    } else {
        fsMap!.setView([lat, lng], 15);
        fsMarker!.setLatLng([lat, lng]);
    }

    // If this is a live session, keep fsMarker in sync with sourceMarker via polling
    if (sourceMarker) {
        (fsOverlay as HTMLElement & { _srcMarker?: L.Marker })._srcMarker = sourceMarker;
    }

    fsOverlay.style.display = "flex";
    document.body.style.overflow = "hidden";
    setTimeout(() => fsMap?.invalidateSize(), 50);
}

function closeFullscreen(): void {
    if (fsOverlay) {
        fsOverlay.style.display = "none";
        document.body.style.overflow = "";
    }
}

// Called from websocket handler so fullscreen also tracks live updates
export function updateFullscreenMarker(lat: number, lng: number): void {
    if (fsOverlay?.style.display !== "none" && fsMap && fsMarker) {
        fsMarker.setLatLng([lat, lng]);
        fsMap.panTo([lat, lng]);
    }
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

function formatCountdown(expiresAtIso: string): string {
    const ms = new Date(expiresAtIso).getTime() - Date.now();
    if (ms <= 0) { return "завершена"; }
    const totalSec = Math.ceil(ms / 1000);
    const m = Math.floor(totalSec / 60);
    const s = totalSec % 60;
    return m > 0
        ? `${m} мин ${s.toString().padStart(2, "0")} сек`
        : `${s} сек`;
}
