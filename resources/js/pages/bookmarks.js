import "../../css/pages/bookmarks.scss";
import "../app";
import { initBookmarkToggle } from "../bookmark-toggle";
import { initGallery } from "../gallery";

initBookmarkToggle();
initGallery();

let bmObserver = null;

function setupInfiniteScroll() {
    bmObserver?.disconnect();
    bmObserver = null;

    const sentinel = document.querySelector('[data-bm-sentinel]');
    if (!sentinel) { return; }

    bmObserver = new IntersectionObserver(([entry]) => {
        if (entry.isIntersecting) {
            sentinel.click();
        }
    }, { rootMargin: '300px' });

    bmObserver.observe(sentinel);
}

document.addEventListener('DOMContentLoaded', setupInfiniteScroll);
document.addEventListener('livewire:update', setupInfiniteScroll);
