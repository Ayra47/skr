function closeProfileMenu(root: HTMLElement): void {
    const toggle = root.querySelector("[data-profile-menu-toggle]");
    const menu = root.querySelector("[data-profile-menu]");

    if (!(toggle instanceof HTMLButtonElement) || !(menu instanceof HTMLElement)) {
        return;
    }

    menu.hidden = true;
    toggle.setAttribute("aria-expanded", "false");
}

function toggleProfileMenu(root: HTMLElement): void {
    const toggle = root.querySelector("[data-profile-menu-toggle]");
    const menu = root.querySelector("[data-profile-menu]");

    if (!(toggle instanceof HTMLButtonElement) || !(menu instanceof HTMLElement)) {
        return;
    }

    const willOpen = menu.hidden;

    document.querySelectorAll("[data-profile-menu-root]").forEach((node) => {
        if (node instanceof HTMLElement) {
            closeProfileMenu(node);
        }
    });

    menu.hidden = !willOpen;
    toggle.setAttribute("aria-expanded", willOpen ? "true" : "false");
}

document.addEventListener("click", (event) => {
    if (!(event.target instanceof Element)) {
        return;
    }

    const toggle = event.target.closest("[data-profile-menu-toggle]");

    if (toggle instanceof HTMLButtonElement) {
        const root = toggle.closest("[data-profile-menu-root]");

        if (root instanceof HTMLElement) {
            toggleProfileMenu(root);
        }

        return;
    }

    document.querySelectorAll("[data-profile-menu-root]").forEach((root) => {
        if (root instanceof HTMLElement && !root.contains(event.target)) {
            closeProfileMenu(root);
        }
    });
});

document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
        document.querySelectorAll("[data-profile-menu-root]").forEach((root) => {
            if (root instanceof HTMLElement) {
                closeProfileMenu(root);
            }
        });
    }
});
