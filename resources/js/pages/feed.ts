import "../../css/pages/feed.scss";
import "../app";

type AttachmentPreview = {
    file: File;
    objectUrl: string | null;
};

type LivewireComponent = {
    call: (method: string, ...params: unknown[]) => void;
};

type LivewireWindow = Window & {
    Livewire?: {
        find: (id: string) => LivewireComponent | undefined;
    };
};

type GalleryViewerItem = {
    kind: "image" | "video";
    mime: string;
    name: string;
    url: string;
};

const attachmentPreviews: AttachmentPreview[] = [];
let galleryViewerItems: GalleryViewerItem[] = [];
let galleryViewerIndex = 0;

document.addEventListener("input", (event) => {
    if (event.target instanceof HTMLTextAreaElement && event.target.matches("[data-feed-textarea]")) {
        resizeTextarea(event.target);
    }

    updateSubmitState();
});

document.addEventListener("change", (event) => {
    if (!(event.target instanceof HTMLInputElement) || !event.target.matches("[data-feed-attachment]")) {
        return;
    }

    appendAttachmentPreviews(event.target);
    updateSubmitState();
});

document.addEventListener("livewire-upload-start", (event) => {
    if (isAttachmentUploadEvent(event)) {
        updateAttachmentUploadProgress(0, "Загрузка файлов");
    }
});

document.addEventListener("livewire-upload-progress", (event) => {
    if (!isAttachmentUploadEvent(event) || !(event instanceof CustomEvent)) {
        return;
    }

    updateAttachmentUploadProgress(Number(event.detail.progress ?? 0), "Загрузка файлов");
});

document.addEventListener("livewire-upload-finish", (event) => {
    if (isAttachmentUploadEvent(event)) {
        updateAttachmentUploadProgress(100, "Файлы загружены", "done");
    }
});

document.addEventListener("livewire-upload-error", (event) => {
    if (isAttachmentUploadEvent(event)) {
        updateAttachmentUploadProgress(0, "Ошибка загрузки", "error");
    }
});

document.addEventListener("livewire-upload-cancel", (event) => {
    if (isAttachmentUploadEvent(event)) {
        resetAttachmentUploadProgress();
    }
});

document.addEventListener("click", (event) => {
    if (!(event.target instanceof Element)) {
        return;
    }

    const galleryThumb = event.target.closest("[data-feed-gallery-thumb]");

    if (galleryThumb instanceof HTMLButtonElement) {
        activateFeedGalleryItem(galleryThumb);

        return;
    }

    const whisperToggle = event.target.closest("[data-feed-whisper-toggle]");

    if (whisperToggle instanceof HTMLButtonElement) {
        toggleWhisperComposerState(whisperToggle);

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

    const previewPrevButton = event.target.closest("[data-feed-attachment-previews-prev]");

    if (previewPrevButton instanceof HTMLButtonElement) {
        scrollAttachmentPreviews(previewPrevButton, -1);

        return;
    }

    const previewNextButton = event.target.closest("[data-feed-attachment-previews-next]");

    if (previewNextButton instanceof HTMLButtonElement) {
        scrollAttachmentPreviews(previewNextButton, 1);

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

        return;
    }

    const removeButton = event.target.closest("[data-feed-attachment-remove]");

    if (!(removeButton instanceof HTMLButtonElement)) {
        return;
    }

    const index = Number(removeButton.dataset.feedAttachmentRemove);

    if (!Number.isInteger(index)) {
        return;
    }

    removeAttachmentPreview(index);
    callLivewireMethod(removeButton, "removeAttachment", index);
    updateSubmitState();
});

document.addEventListener(
    "scroll",
    (event) => {
        if (!(event.target instanceof HTMLElement) || !event.target.matches("[data-feed-replies-panel]")) {
            return;
        }

        loadMoreRepliesIfNeeded(event.target);
    },
    true,
);

document.addEventListener(
    "scroll",
    (event) => {
        if (!(event.target instanceof HTMLElement) || !event.target.matches("[data-feed-gallery-thumbs-track]")) {
            return;
        }

        updateFeedGalleryThumbControls(event.target);
    },
    true,
);

document.addEventListener(
    "scroll",
    (event) => {
        if (!(event.target instanceof HTMLElement) || !event.target.matches("[data-feed-attachment-previews-track]")) {
            return;
        }

        updateAttachmentPreviewControls(event.target);
    },
    true,
);

window.addEventListener("resize", () => {
    document.querySelectorAll("[data-feed-gallery-thumbs-track]").forEach((track) => {
        if (track instanceof HTMLElement) {
            updateFeedGalleryThumbControls(track);
        }
    });

    document.querySelectorAll("[data-feed-attachment-previews-track]").forEach((track) => {
        if (track instanceof HTMLElement) {
            updateAttachmentPreviewControls(track);
        }
    });
});

document.addEventListener("keydown", (event) => {
    if (!document.querySelector("[data-feed-gallery-viewer]")) {
        return;
    }

    if (event.key === "Escape") {
        closeFeedGalleryViewer();
    } else if (event.key === "ArrowLeft") {
        shiftFeedGalleryViewer(-1);
    } else if (event.key === "ArrowRight") {
        shiftFeedGalleryViewer(1);
    }
});

window.addEventListener("feed-post-created", () => {
    clearAllAttachmentPreviews();
    initializeFeedControls();
});

document.addEventListener("livewire:navigated", initializeFeedControls);
document.addEventListener("DOMContentLoaded", initializeFeedControls);
initializeFeedControls();

function initializeFeedControls(): void {
    document.querySelectorAll("[data-feed-textarea]").forEach((textarea) => {
        if (textarea instanceof HTMLTextAreaElement) {
            resizeTextarea(textarea);
        }
    });

    renderAttachmentPreviews();
    document.querySelectorAll("[data-feed-gallery-thumbs-track]").forEach((track) => {
        if (track instanceof HTMLElement) {
            updateFeedGalleryThumbControls(track);
        }
    });
    document.querySelectorAll("[data-feed-attachment-previews-track]").forEach((track) => {
        if (track instanceof HTMLElement) {
            updateAttachmentPreviewControls(track);
        }
    });
    syncWhisperComposerState();
    updateSubmitState();
}

function toggleWhisperComposerState(toggle: HTMLButtonElement): void {
    const composer = toggle.closest("form");
    const whisperInput = composer?.querySelector("[data-feed-whisper-input]");

    if (!(whisperInput instanceof HTMLInputElement)) {
        return;
    }

    whisperInput.checked = !whisperInput.checked;
    whisperInput.dispatchEvent(new Event("input", { bubbles: true }));
    whisperInput.dispatchEvent(new Event("change", { bubbles: true }));
    syncWhisperComposerState(composer);
}

function syncWhisperComposerState(scope: ParentNode = document): void {
    const whisperInput = scope.querySelector("[data-feed-whisper-input]");
    const whisperToggle = scope.querySelector("[data-feed-whisper-toggle]");
    const whisperHint = scope.querySelector("[data-feed-whisper-hint]");
    const friendsVisibility = scope.querySelector("[data-feed-visibility-friends]");
    const publicVisibility = scope.querySelector("[data-feed-visibility-public]");

    if (
        !(whisperInput instanceof HTMLInputElement)
        || !(whisperToggle instanceof HTMLButtonElement)
        || !(whisperHint instanceof HTMLElement)
        || !(friendsVisibility instanceof HTMLInputElement)
        || !(publicVisibility instanceof HTMLInputElement)
    ) {
        return;
    }

    const isWhisper = whisperInput.checked;

    whisperToggle.classList.toggle("active", isWhisper);
    whisperToggle.setAttribute("aria-pressed", isWhisper ? "true" : "false");
    whisperHint.hidden = !isWhisper;
    friendsVisibility.disabled = isWhisper;

    if (!isWhisper) {
        return;
    }

    publicVisibility.checked = true;
    publicVisibility.dispatchEvent(new Event("input", { bubbles: true }));
    publicVisibility.dispatchEvent(new Event("change", { bubbles: true }));
}

function resizeTextarea(textarea: HTMLTextAreaElement): void {
    textarea.style.height = "auto";
    textarea.style.height = `${textarea.scrollHeight}px`;
}

function updateSubmitState(): void {
    const submitButton = document.querySelector("[data-feed-submit]");
    const textarea = document.querySelector("[data-feed-textarea]");

    if (!(submitButton instanceof HTMLButtonElement)) {
        return;
    }

    const hasText = textarea instanceof HTMLTextAreaElement && textarea.value.trim().length > 0;

    submitButton.disabled = !hasText && attachmentPreviews.length === 0;
}

function loadMoreRepliesIfNeeded(panel: HTMLElement): void {
    if (panel.dataset.loadingMoreReplies === "1") {
        return;
    }

    const distanceToBottom = panel.scrollHeight - panel.scrollTop - panel.clientHeight;

    if (distanceToBottom > 32) {
        return;
    }

    const trigger = panel.querySelector("[data-feed-replies-load-more]");

    if (!(trigger instanceof HTMLButtonElement)) {
        return;
    }

    panel.dataset.loadingMoreReplies = "1";
    trigger.click();

    window.setTimeout(() => {
        delete panel.dataset.loadingMoreReplies;
    }, 1000);
}

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

function appendAttachmentPreviews(input: HTMLInputElement): void {
    Array.from(input.files ?? []).forEach((file) => {
        attachmentPreviews.push({
            file,
            objectUrl: isMediaFile(file) ? URL.createObjectURL(file) : null,
        });
    });

    renderAttachmentPreviews();
}

function removeAttachmentPreview(index: number): void {
    const [preview] = attachmentPreviews.splice(index, 1);

    if (preview?.objectUrl) {
        URL.revokeObjectURL(preview.objectUrl);
    }

    renderAttachmentPreviews();
}

function clearAllAttachmentPreviews(): void {
    attachmentPreviews.splice(0).forEach((preview) => {
        if (preview.objectUrl) {
            URL.revokeObjectURL(preview.objectUrl);
        }
    });

    document.querySelectorAll("[data-feed-attachment]").forEach((input) => {
        if (input instanceof HTMLInputElement) {
            input.value = "";
        }
    });

    renderAttachmentPreviews();
    resetAttachmentUploadProgress();
}

function renderAttachmentPreviews(): void {
    const container = document.querySelector("[data-feed-attachment-previews]");

    if (!(container instanceof HTMLElement)) {
        return;
    }

    if (attachmentPreviews.length === 0) {
        container.replaceChildren();
        container.hidden = true;

        return;
    }

    const shell = document.createElement("div");
    shell.className = "feed-attachment-previews-shell";

    const prevButton = document.createElement("button");
    prevButton.className = "feed-attachment-previews-arrow prev";
    prevButton.type = "button";
    prevButton.dataset.feedAttachmentPreviewsPrev = "";
    prevButton.ariaLabel = "Прокрутить вложения назад";
    prevButton.hidden = true;
    prevButton.textContent = "‹";

    const track = document.createElement("div");
    track.className = "feed-attachment-previews-track";
    track.dataset.feedAttachmentPreviewsTrack = "";
    track.append(...attachmentPreviews.map(createAttachmentPreviewElement));

    const nextButton = document.createElement("button");
    nextButton.className = "feed-attachment-previews-arrow next";
    nextButton.type = "button";
    nextButton.dataset.feedAttachmentPreviewsNext = "";
    nextButton.ariaLabel = "Прокрутить вложения вперед";
    nextButton.hidden = true;
    nextButton.textContent = "›";

    shell.append(prevButton, track, nextButton);
    container.replaceChildren(shell);
    container.hidden = false;
    updateAttachmentPreviewControls(track);
}

function createAttachmentPreviewElement(preview: AttachmentPreview, index: number): HTMLElement {
    const item = document.createElement("div");
    item.className = `feed-attachment-preview ${isMediaFile(preview.file) ? "media" : "file"}`;
    item.title = preview.file.name;

    const media = document.createElement("div");
    media.className = "feed-attachment-media";

    if (preview.objectUrl && preview.file.type.startsWith("image/")) {
        const image = document.createElement("img");
        image.src = preview.objectUrl;
        image.alt = "";
        media.append(image);
    } else if (preview.objectUrl && preview.file.type.startsWith("video/")) {
        const video = document.createElement("video");
        video.src = preview.objectUrl;
        video.muted = true;
        video.controls = true;
        video.preload = "metadata";
        media.append(video);
    } else {
        const filePlaceholder = document.createElement("span");
        filePlaceholder.className = "feed-attachment-file-placeholder";
        filePlaceholder.ariaHidden = "true";
        media.append(filePlaceholder);
    }

    const removeButton = document.createElement("button");
    removeButton.type = "button";
    removeButton.dataset.feedAttachmentRemove = String(index);
    removeButton.ariaLabel = "Убрать вложение";
    removeButton.textContent = "×";

    item.append(media, removeButton);

    return item;
}

function callLivewireMethod(element: Element, method: string, ...params: unknown[]): void {
    const componentRoot = element.closest("[wire\\:id]");
    const componentId = componentRoot?.getAttribute("wire:id");

    if (!componentId) {
        return;
    }

    (window as LivewireWindow).Livewire?.find(componentId)?.call(method, ...params);
}

function isMediaFile(file: File): boolean {
    return file.type.startsWith("image/") || file.type.startsWith("video/");
}

function scrollAttachmentPreviews(control: HTMLButtonElement, direction: number): void {
    const shell = control.closest(".feed-attachment-previews-shell");
    const track = shell?.querySelector("[data-feed-attachment-previews-track]");

    if (!(track instanceof HTMLElement)) {
        return;
    }

    track.scrollBy({
        left: direction * Math.max(track.clientWidth * 0.75, 180),
        behavior: "smooth",
    });
}

function updateAttachmentPreviewControls(track: HTMLElement): void {
    const shell = track.closest(".feed-attachment-previews-shell");
    const prevButton = shell?.querySelector("[data-feed-attachment-previews-prev]");
    const nextButton = shell?.querySelector("[data-feed-attachment-previews-next]");
    const hasOverflow = track.scrollWidth > track.clientWidth + 1;

    if (!(prevButton instanceof HTMLButtonElement) || !(nextButton instanceof HTMLButtonElement)) {
        return;
    }

    prevButton.hidden = !hasOverflow || track.scrollLeft <= 1;
    nextButton.hidden = !hasOverflow || track.scrollLeft + track.clientWidth >= track.scrollWidth - 1;
}

function isAttachmentUploadEvent(event: Event): boolean {
    return event.target instanceof HTMLInputElement && event.target.matches("[data-feed-attachment]");
}

function updateAttachmentUploadProgress(progress: number, label: string, state: "done" | "error" | null = null): void {
    const container = document.querySelector("[data-feed-upload-progress]");
    const labelElement = container?.querySelector("[data-feed-upload-progress-label]");
    const valueElement = container?.querySelector("[data-feed-upload-progress-value]");
    const bar = container?.querySelector("[data-feed-upload-progress-bar]");

    if (
        !(container instanceof HTMLElement)
        || !(labelElement instanceof HTMLElement)
        || !(valueElement instanceof HTMLElement)
        || !(bar instanceof HTMLElement)
    ) {
        return;
    }

    const clampedProgress = Math.min(100, Math.max(0, Math.round(progress)));

    container.hidden = false;
    container.classList.toggle("done", state === "done");
    container.classList.toggle("error", state === "error");
    labelElement.textContent = label;
    valueElement.textContent = `${clampedProgress}%`;
    bar.style.width = `${clampedProgress}%`;
}

function resetAttachmentUploadProgress(): void {
    const container = document.querySelector("[data-feed-upload-progress]");
    const labelElement = container?.querySelector("[data-feed-upload-progress-label]");
    const valueElement = container?.querySelector("[data-feed-upload-progress-value]");
    const bar = container?.querySelector("[data-feed-upload-progress-bar]");

    if (
        !(container instanceof HTMLElement)
        || !(labelElement instanceof HTMLElement)
        || !(valueElement instanceof HTMLElement)
        || !(bar instanceof HTMLElement)
    ) {
        return;
    }

    container.hidden = true;
    container.classList.remove("done", "error");
    labelElement.textContent = "Загрузка файлов";
    valueElement.textContent = "0%";
    bar.style.width = "0%";
}
