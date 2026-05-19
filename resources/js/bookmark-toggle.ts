function csrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function svgActive(): string {
    return '<svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M6 3a2 2 0 0 0-2 2v16l8-5 8 5V5a2 2 0 0 0-2-2H6z"/></svg>';
}

function svgDefault(): string {
    return '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>';
}

async function addBookmark(postId: number, btn: HTMLButtonElement): Promise<void> {
    const res = await fetch('/bookmarks', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json',
        },
        body: JSON.stringify({
            bookmarkable_type: 'feed_post',
            bookmarkable_id: postId,
        }),
    });

    if (res.ok) {
        const data = await res.json() as { id: number };
        btn.dataset.bookmarked = 'true';
        btn.dataset.bookmarkId = String(data.id);
        btn.classList.add('feed-bookmark-btn--active');
        btn.setAttribute('title', 'Убрать из закладок');
        btn.innerHTML = svgActive();
    }
}

async function removeBookmark(bookmarkId: number, btn: HTMLButtonElement): Promise<void> {
    const res = await fetch(`/bookmarks/${bookmarkId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json',
        },
    });

    if (res.ok || res.status === 204) {
        btn.dataset.bookmarked = 'false';
        btn.dataset.bookmarkId = '';
        btn.classList.remove('feed-bookmark-btn--active');
        btn.setAttribute('title', 'Сохранить в закладки');
        btn.innerHTML = svgDefault();
    }
}

export function initBookmarkToggle(): void {
    document.addEventListener('click', async (e: MouseEvent) => {
        const btn = (e.target as Element).closest<HTMLButtonElement>('[data-bookmark-btn]');
        if (!btn) { return; }

        e.preventDefault();
        e.stopPropagation();

        const postId = parseInt(btn.dataset.postId ?? '0', 10);
        const isBookmarked = btn.dataset.bookmarked === 'true';

        btn.classList.add('feed-bookmark-btn--loading');

        try {
            if (isBookmarked) {
                await removeBookmark(parseInt(btn.dataset.bookmarkId ?? '0', 10), btn);
            } else {
                await addBookmark(postId, btn);
            }
        } catch {
            // state unchanged on error
        } finally {
            btn.classList.remove('feed-bookmark-btn--loading');
        }
    });
}
