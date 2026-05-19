type GalleryViewerItem = {
    kind: "image" | "video";
    mime: string;
    name: string;
    url: string;
};

let galleryViewerItems: GalleryViewerItem[] = [];
let galleryViewerIndex = 0;

function activateFeedGalleryItem(thumb: HTMLButtonElement): void {
    const gallery = thumb.closest("[data-feed-gallery]");
    const index = thumb.dataset.galleryIndex;

    if (!(gallery instanceof HTMLElement) || index === undefined) {
        return;
    }

    gallery.querySelectorAll("[data-feed-gallery-main-item]").forEach((item) => {
        if (!(item instanceof HTMLElement)) {
            return;
        }

        const isActive = item.dataset.galleryIndex === index;
        item.hidden = !isActive;
        item.classList.toggle("active", isActive);
    });

    gallery.querySelectorAll("[data-feed-gallery-thumb]").forEach((item) => {
        if (item instanceof HTMLElement) {
            item.classList.toggle("active", item.dataset.galleryIndex === index);
        }
    });

    thumb.scrollIntoView({
        behavior: "smooth",
        block: "nearest",
        inline: "nearest",
    });
}

function scrollFeedGalleryThumbs(control: HTMLButtonElement, direction: number): void {
    const gallery = control.closest("[data-feed-gallery]");
    const track = gallery?.querySelector("[data-feed-gallery-thumbs-track]");

    if (!(track instanceof HTMLElement)) {
        return;
    }

    track.scrollBy({
        left: direction * Math.max(track.clientWidth * 0.75, 220),
        behavior: "smooth",
    });
}

function updateFeedGalleryThumbControls(track: HTMLElement): void {
    const gallery = track.closest("[data-feed-gallery]");
    const prevButton = gallery?.querySelector("[data-feed-gallery-thumbs-prev]");
    const nextButton = gallery?.querySelector("[data-feed-gallery-thumbs-next]");
    const hasOverflow = track.scrollWidth > track.clientWidth + 1;

    if (!(prevButton instanceof HTMLButtonElement) || !(nextButton instanceof HTMLButtonElement)) {
        return;
    }

    prevButton.hidden = !hasOverflow || track.scrollLeft <= 1;
    nextButton.hidden = !hasOverflow || track.scrollLeft + track.clientWidth >= track.scrollWidth - 1;
}

function openFeedGalleryViewer(trigger: HTMLElement): void {
    const gallery = trigger.closest("[data-feed-gallery]");
    const activeItem = trigger.closest("[data-feed-gallery-main-item]");

    if (!(gallery instanceof HTMLElement) || !(activeItem instanceof HTMLElement) || gallery.dataset.galleryModal !== "1") {
        return;
    }

    galleryViewerItems = Array.from(gallery.querySelectorAll("[data-feed-gallery-main-item][data-gallery-media='1']"))
        .filter((item): item is HTMLElement => item instanceof HTMLElement)
        .map((item) => ({
            kind: item.dataset.galleryKind === "video" ? "video" : "image",
            mime: item.dataset.galleryMime ?? "",
            name: item.dataset.galleryName ?? "",
            url: item.dataset.galleryUrl ?? "",
        }))
        .filter((item) => item.url.length > 0);

    const activeMediaItems = Array.from(gallery.querySelectorAll("[data-feed-gallery-main-item][data-gallery-media='1']"));
    galleryViewerIndex = Math.max(0, activeMediaItems.indexOf(activeItem));

    if (galleryViewerItems.length === 0) {
        return;
    }

    let viewer = document.querySelector("[data-feed-gallery-viewer]");

    if (!(viewer instanceof HTMLElement)) {
        viewer = document.createElement("div");
        viewer.className = "feed-gallery-viewer";
        viewer.dataset.feedGalleryViewer = "";
        viewer.innerHTML = `
            <button type="button" data-feed-gallery-viewer-close aria-label="Закрыть">×</button>
            <button type="button" data-feed-gallery-viewer-prev aria-label="Предыдущее вложение">‹</button>
            <div class="feed-gallery-viewer-stage" data-feed-gallery-viewer-stage></div>
            <button type="button" data-feed-gallery-viewer-next aria-label="Следующее вложение">›</button>
        `;
        document.body.append(viewer);
    }

    renderFeedGalleryViewer();
}

function renderFeedGalleryViewer(): void {
    const viewer = document.querySelector("[data-feed-gallery-viewer]");
    const stage = viewer?.querySelector("[data-feed-gallery-viewer-stage]");

    if (!(viewer instanceof HTMLElement) || !(stage instanceof HTMLElement) || galleryViewerItems.length === 0) {
        return;
    }

    const item = galleryViewerItems[galleryViewerIndex];
    stage.replaceChildren();

    if (item.kind === "image") {
        const image = document.createElement("img");
        image.src = item.url;
        image.alt = item.name;
        stage.append(image);
    } else {
        const video = document.createElement("video");
        video.controls = true;
        video.autoplay = true;
        video.preload = "metadata";

        const source = document.createElement("source");
        source.src = item.url;
        source.type = item.mime;
        video.append(source);
        stage.append(video);
    }

    viewer.querySelector("[data-feed-gallery-viewer-prev]")?.toggleAttribute("hidden", galleryViewerItems.length < 2);
    viewer.querySelector("[data-feed-gallery-viewer-next]")?.toggleAttribute("hidden", galleryViewerItems.length < 2);
}

function shiftFeedGalleryViewer(direction: number): void {
    if (galleryViewerItems.length < 2) {
        return;
    }

    galleryViewerIndex = (galleryViewerIndex + direction + galleryViewerItems.length) % galleryViewerItems.length;
    renderFeedGalleryViewer();
}

function closeFeedGalleryViewer(): void {
    document.querySelector("[data-feed-gallery-viewer]")?.remove();
    galleryViewerItems = [];
    galleryViewerIndex = 0;
}

export function initGallery(): void {
    document.addEventListener("click", (event) => {
        if (!(event.target instanceof Element)) {
            return;
        }

        const galleryThumb = event.target.closest("[data-feed-gallery-thumb]");
        if (galleryThumb instanceof HTMLButtonElement) {
            activateFeedGalleryItem(galleryThumb);
            return;
        }

        const thumbsPrevButton = event.target.closest("[data-feed-gallery-thumbs-prev]");
        if (thumbsPrevButton instanceof HTMLButtonElement) {
            scrollFeedGalleryThumbs(thumbsPrevButton, -1);
            return;
        }

        const thumbsNextButton = event.target.closest("[data-feed-gallery-thumbs-next]");
        if (thumbsNextButton instanceof HTMLButtonElement) {
            scrollFeedGalleryThumbs(thumbsNextButton, 1);
            return;
        }

        const galleryViewerTrigger = event.target.closest("[data-feed-gallery-open-viewer]");
        if (galleryViewerTrigger instanceof HTMLElement) {
            openFeedGalleryViewer(galleryViewerTrigger);
            return;
        }

        const galleryViewerControl = event.target.closest("[data-feed-gallery-viewer-close], [data-feed-gallery-viewer-prev], [data-feed-gallery-viewer-next]");
        if (galleryViewerControl instanceof HTMLElement) {
            if (galleryViewerControl.matches("[data-feed-gallery-viewer-close]")) {
                closeFeedGalleryViewer();
            } else if (galleryViewerControl.matches("[data-feed-gallery-viewer-prev]")) {
                shiftFeedGalleryViewer(-1);
            } else if (galleryViewerControl.matches("[data-feed-gallery-viewer-next]")) {
                shiftFeedGalleryViewer(1);
            }
            return;
        }

        if (event.target.matches("[data-feed-gallery-viewer]")) {
            closeFeedGalleryViewer();
        }
    });

    document.addEventListener("scroll", (event) => {
        if (!(event.target instanceof HTMLElement) || !event.target.matches("[data-feed-gallery-thumbs-track]")) {
            return;
        }
        updateFeedGalleryThumbControls(event.target);
    }, true);
}
