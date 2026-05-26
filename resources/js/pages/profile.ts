import "../../css/pages/profile.scss";
import "../app";
import "./feed";
import { initAccentOnLoad } from "../utils/accent.js";
import { initThemeOnLoad } from "../utils/theme.js";

initThemeOnLoad();
initAccentOnLoad();

document.addEventListener('DOMContentLoaded', () => {
    document.querySelector<HTMLButtonElement>('.profile-share-btn')
        ?.addEventListener('click', async (e) => {
            const btn = e.currentTarget as HTMLButtonElement;
            await navigator.clipboard.writeText(window.location.href);
            const orig = btn.textContent;
            btn.textContent = 'Скопировано';
            setTimeout(() => { btn.textContent = orig; }, 1500);
        });

    const removeBtn = document.querySelector<HTMLButtonElement>('.profile-remove-friend-btn');
    const modal     = document.getElementById('removeFriendModal');
    const cancelBtn = document.getElementById('removeFriendCancel');
    const confirmBtn = document.getElementById('removeFriendConfirm') as HTMLButtonElement | null;

    removeBtn?.addEventListener('click', () => {
        if (modal) { modal.style.display = 'flex'; }
    });

    cancelBtn?.addEventListener('click', () => {
        if (modal) { modal.style.display = 'none'; }
    });

    modal?.addEventListener('click', (e) => {
        if (e.target === modal) { modal.style.display = 'none'; }
    });

    confirmBtn?.addEventListener('click', async () => {
        const friendId = removeBtn?.dataset.friendId;
        if (!friendId) { return; }

        confirmBtn.disabled = true;
        const res = await fetch('/friends/remove', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
            },
            body: JSON.stringify({ friend_id: parseInt(friendId) }),
        });

        if (res.ok) {
            window.location.reload();
        } else {
            confirmBtn.disabled = false;
        }
    });
});
