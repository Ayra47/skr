export interface ContextMenuItem {
    label: string;
    action: () => void;
    danger?: boolean;
}

let menuEl: HTMLElement | null = null;

function getMenu(): HTMLElement {
    if (!menuEl) {
        menuEl = document.createElement("div");
        menuEl.className = "ctx-menu";
        menuEl.style.display = "none";
        document.body.appendChild(menuEl);
    }
    return menuEl;
}

export function openContextMenu(x: number, y: number, items: ContextMenuItem[]): void {
    if (!items.length) { return; }

    const menu = getMenu();
    menu.innerHTML = "";

    items.forEach((item) => {
        const btn = document.createElement("button");
        btn.className = "ctx-item" + (item.danger ? " ctx-item--danger" : "");
        btn.textContent = item.label;
        btn.addEventListener("click", (e) => {
            e.stopPropagation();
            closeContextMenu();
            item.action();
        });
        menu.appendChild(btn);
    });

    menu.style.display = "block";

    // Reset position for accurate measurement
    menu.style.left = "0px";
    menu.style.top = "0px";

    const mw = menu.offsetWidth || 160;
    const mh = menu.offsetHeight || items.length * 38 + 8;
    const vw = window.innerWidth;
    const vh = window.innerHeight;

    const left = x + mw > vw - 8 ? vw - mw - 8 : x;
    const top = y + mh > vh - 8 ? y - mh : y;

    menu.style.left = Math.max(8, left) + "px";
    menu.style.top = Math.max(8, top) + "px";
}

export function closeContextMenu(): void {
    if (menuEl) { menuEl.style.display = "none"; }
}

document.addEventListener("click", closeContextMenu);
document.addEventListener("scroll", closeContextMenu, { capture: true, passive: true });
document.addEventListener("keydown", (e) => { if (e.key === "Escape") { closeContextMenu(); } });
