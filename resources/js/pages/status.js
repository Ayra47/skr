import "../../css/pages/status.scss";
import "../app";

document.addEventListener('DOMContentLoaded', () => {
    // Copy canary signature
    const copyBtn = document.getElementById('canaryCopyBtn');
    if (copyBtn) {
        copyBtn.addEventListener('click', () => {
            navigator.clipboard.writeText(copyBtn.dataset.sig).then(() => {
                copyBtn.classList.add('copied');
                copyBtn.querySelector('span').textContent = 'скопировано';
                setTimeout(() => {
                    copyBtn.classList.remove('copied');
                    copyBtn.querySelector('span').textContent = 'скопировать';
                }, 1800);
            });
        });
    }

    // Load more incidents
    const loadMoreBtn = document.getElementById('incidentsLoadMoreBtn');
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', async () => {
            const offset = parseInt(loadMoreBtn.dataset.offset, 10);
            loadMoreBtn.disabled = true;
            loadMoreBtn.textContent = 'Загрузка...';

            try {
                const res = await fetch(`/status/incidents/more?offset=${offset}`);
                const data = await res.json();

                const list = document.getElementById('incidentsList');
                data.incidents.forEach(inc => list.appendChild(buildIncident(inc)));

                if (data.has_more) {
                    loadMoreBtn.dataset.offset = offset + 5;
                    loadMoreBtn.disabled = false;
                    loadMoreBtn.textContent = 'Загрузить ещё';
                } else {
                    document.getElementById('incidentsLoadMore').remove();
                }
            } catch {
                loadMoreBtn.disabled = false;
                loadMoreBtn.textContent = 'Загрузить ещё';
            }
        });
    }

    // Load more canary history
    const canaryLoadMoreBtn = document.getElementById('canaryLoadMoreBtn');
    if (canaryLoadMoreBtn) {
        canaryLoadMoreBtn.addEventListener('click', async () => {
            const offset = parseInt(canaryLoadMoreBtn.dataset.offset, 10);
            canaryLoadMoreBtn.disabled = true;
            canaryLoadMoreBtn.textContent = 'Загрузка...';

            try {
                const res = await fetch(`/status/canary/more?offset=${offset}`);
                const data = await res.json();

                const list = document.getElementById('canaryHistoryList');
                data.items.forEach(h => list.appendChild(buildCanaryRow(h)));

                if (data.has_more) {
                    canaryLoadMoreBtn.dataset.offset = offset + 10;
                    canaryLoadMoreBtn.disabled = false;
                    canaryLoadMoreBtn.textContent = 'Загрузить ещё';
                } else {
                    document.getElementById('canaryLoadMore').remove();
                }
            } catch {
                canaryLoadMoreBtn.disabled = false;
                canaryLoadMoreBtn.textContent = 'Загрузить ещё';
            }
        });
    }

    // Auto-refresh every 30 seconds
    setTimeout(() => location.reload(), 30_000);
});

function buildIncident(inc) {
    const el = document.createElement('div');
    el.className = `st-incident st-incident--${inc.kind}`;

    const tagClass = inc.status === 'resolved' ? 'st-incident-tag--resolved' : 'st-incident-tag--ongoing';
    const tagText  = inc.status === 'resolved' ? 'устранено' : 'текущий';
    const meta     = inc.duration ? `${inc.date} · ${inc.duration}` : inc.date;

    el.innerHTML = `
        <span class="st-incident-bar"></span>
        <div class="st-incident-head">
            <span class="st-incident-title">${escHtml(inc.title)}</span>
            <span class="st-incident-tag ${tagClass}">${tagText}</span>
            <span class="st-incident-meta">${escHtml(meta)}</span>
        </div>
        ${inc.body ? `<p class="st-incident-body">${escHtml(inc.body)}</p>` : ''}
    `;

    return el;
}

function buildCanaryRow(h) {
    const el = document.createElement('div');
    el.className = `st-canary-history-row${h.is_current ? ' st-canary-history-row--current' : ''}`;
    el.innerHTML = `
        <span class="st-canary-history-dot${h.is_current ? ' st-canary-history-dot--current' : ''}"></span>
        <span class="st-canary-history-date">${escHtml(h.date)}</span>
        <span class="st-canary-history-sig">${escHtml(h.signature)}</span>
        ${h.is_current ? '<span class="st-canary-history-tag">текущая</span>' : ''}
    `;
    return el;
}

function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
