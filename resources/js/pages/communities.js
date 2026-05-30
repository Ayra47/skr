import "../../css/pages/communities.scss";
import "../app";
import { initAccentOnLoad } from "../utils/accent.js";
import { initThemeOnLoad } from "../utils/theme.js";

initThemeOnLoad();
initAccentOnLoad();

document.addEventListener("DOMContentLoaded", () => {
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? "";

    // ── Create community wizard ───────────────────────────────────────────────
    const createModal = document.getElementById("createCommunityModal");

    if (createModal) {
        // Open / close
        document.querySelectorAll("[data-cm-open-create]").forEach((btn) => {
            btn.addEventListener("click", () => {
                wizReset();
                createModal.classList.add("is-open");
            });
        });
        const closeWizard = () => createModal.classList.remove("is-open");
        document.querySelectorAll("[data-cm-close-create]").forEach((btn) => {
            btn.addEventListener("click", closeWizard);
        });
        createModal.addEventListener("click", (e) => {
            if (e.target === createModal) closeWizard();
        });
        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape" && createModal.classList.contains("is-open")) closeWizard();
        });

        // ── Wizard state ─────────────────────────────────────────────────────
        const GLYPHS = ["◐", "◇", "☾", "✦", "✸", "◈", "❋", "✱", "✣", "◉", "◆", "✺"];
        const TINTS  = [30, 90, 150, 200, 260, 320];
        const STEP_TITLES = ["Идентичность", "Приватность", "Готово"];

        let wizStep = 0;
        let selGlyph = GLYPHS[0];
        let selTint  = TINTS[0];
        let selVis   = "private";
        let selInvitePolicy  = "moderators_only";
        let selPostingPolicy = "everyone";
        let selTtl   = "86400";
        let selLimit = "50";
        let countdownTimer = null;
        let createdCommunityId = null;

        // DOM refs
        const stepNumEl  = document.getElementById("cmWizStepNum");
        const titleEl    = document.getElementById("cmWizTitle");
        const panes      = createModal.querySelectorAll(".cm-wiz-pane");
        const bars       = createModal.querySelectorAll(".cm-wiz-bar");

        const nameInput  = /** @type {HTMLInputElement} */ (document.getElementById("cmWizName"));
        const descInput  = /** @type {HTMLTextAreaElement} */ (document.getElementById("cmWizDesc"));
        const descCount  = document.getElementById("cmWizDescCount");
        const avatarEl   = document.getElementById("cmWizAvatarPreview");
        const namePreview = document.getElementById("cmWizNamePreview");
        const descPreview = document.getElementById("cmWizDescPreview");
        const nextBtn = /** @type {HTMLButtonElement|null} */ (createModal.querySelector("[data-cm-wiz-next]"));

        const error0 = document.getElementById("cmWizError0");
        const error1 = document.getElementById("cmWizError1");

        // Step 2 elements
        const doneAvatar = document.getElementById("cmWizDoneAvatar");
        const doneName   = document.getElementById("cmWizDoneName");
        const doneDesc   = document.getElementById("cmWizDoneDesc");
        const inviteCode = document.getElementById("cmWizInviteCode");
        const ringFill   = createModal.querySelector(".cm-wiz-ring-fill");
        const ringTime   = document.getElementById("cmWizRingTime");
        const copyBtn    = document.getElementById("cmWizCopyBtn");
        const shareBtn   = document.getElementById("cmWizShareBtn");
        const refreshBtn = /** @type {HTMLButtonElement|null} */ (document.getElementById("cmWizRefreshBtn"));
        const gotoBtn    = document.getElementById("cmWizGoto");
        const limitRange = /** @type {HTMLInputElement|null} */ (document.getElementById("cmWizLimitRange"));
        const limitValue = document.getElementById("cmWizLimitValue");

        // ── Navigation ───────────────────────────────────────────────────────
        function goToStep(n) {
            wizStep = n;
            panes.forEach((p, i) => p.classList.toggle("is-active", i === n));
            bars.forEach((b, i) => b.classList.toggle("is-active", i <= n));
            if (stepNumEl) stepNumEl.textContent = `шаг ${n + 1} из 3`;
            if (titleEl)   titleEl.textContent = STEP_TITLES[n];
        }

        nextBtn?.addEventListener("click", () => {
            const name = nameInput?.value.trim() ?? "";
            if (!name) {
                showError(error0, "Введите название сообщества");
                nameInput?.focus();
                return;
            }
            hideError(error0);
            goToStep(1);
        });

        createModal.querySelector("[data-cm-wiz-back]")?.addEventListener("click", () => goToStep(0));

        createModal.querySelector("[data-cm-wiz-create]")?.addEventListener("click", submitWizard);

        // ── Step 0 interactions ──────────────────────────────────────────────
        descInput?.addEventListener("input", () => {
            const val = descInput.value.trim();
            if (descCount) descCount.textContent = String(descInput.value.length);
            if (descPreview) descPreview.textContent = val || "описание появится здесь";
        });

        nameInput?.addEventListener("input", () => {
            const val = nameInput.value.trim();
            if (namePreview) namePreview.textContent = val || "без названия";
            if (nextBtn) nextBtn.disabled = !val;
        });

        createModal.querySelectorAll(".cm-wiz-glyph").forEach((btn) => {
            btn.addEventListener("click", () => {
                createModal.querySelectorAll(".cm-wiz-glyph").forEach((b) => b.classList.remove("is-active"));
                btn.classList.add("is-active");
                selGlyph = btn.dataset.glyph ?? GLYPHS[0];
                updateAvatarPreview();
            });
        });

        createModal.querySelectorAll(".cm-wiz-tint-btn").forEach((btn) => {
            btn.addEventListener("click", () => {
                createModal.querySelectorAll(".cm-wiz-tint-btn").forEach((b) => {
                    b.classList.remove("is-active");
                    b.innerHTML = "";
                });
                btn.classList.add("is-active");
                btn.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L20 6"/></svg>';
                selTint = parseInt(btn.dataset.tint ?? "30", 10);
                updateAvatarPreview();
            });
        });

        function updateAvatarPreview() {
            if (!avatarEl) return;
            const tintEnd = (selTint + 60) % 360;
            avatarEl.style.background = `linear-gradient(135deg, oklch(0.32 0.07 ${selTint}), oklch(0.18 0.05 ${tintEnd}))`;
            avatarEl.textContent = selGlyph;
        }

        // ── Step 1 interactions ──────────────────────────────────────────────
        function initRadioCards(containerId, onChange) {
            const container = document.getElementById(containerId);
            if (!container) return;
            container.querySelectorAll(".cm-wiz-radio-card").forEach((card) => {
                card.addEventListener("click", () => {
                    container.querySelectorAll(".cm-wiz-radio-card").forEach((c) => {
                        c.classList.remove("is-active");
                        c.querySelector(".cm-wiz-radio-dot")?.classList.remove("is-active");
                    });
                    card.classList.add("is-active");
                    card.querySelector(".cm-wiz-radio-dot")?.classList.add("is-active");
                    onChange(card.dataset.value ?? "");
                });
            });
        }

        function initBtnGroup(containerId, onChange) {
            const container = document.getElementById(containerId);
            if (!container) return;
            container.querySelectorAll(".cm-wiz-group-btn").forEach((btn) => {
                btn.addEventListener("click", () => {
                    container.querySelectorAll(".cm-wiz-group-btn").forEach((b) => b.classList.remove("is-active"));
                    btn.classList.add("is-active");
                    onChange(btn.dataset.value ?? "");
                });
            });
        }

        initRadioCards("cmWizVisibility",    (v) => { selVis = v; });
        initRadioCards("cmWizInvitePolicy",  (v) => { selInvitePolicy = v; });
        initRadioCards("cmWizPostingPolicy", (v) => { selPostingPolicy = v; });
        initBtnGroup("cmWizTtl",   (v) => { selTtl = v; });

        limitRange?.addEventListener("input", () => {
            selLimit = limitRange.value;
            if (limitValue) limitValue.textContent = selLimit;
        });

        // ── Submit wizard ────────────────────────────────────────────────────
        async function submitWizard() {
            const name = nameInput?.value.trim() ?? "";
            if (!name) {
                goToStep(0);
                showError(error0, "Введите название сообщества");
                return;
            }
            hideError(error1);

            const createBtn = createModal.querySelector("[data-cm-wiz-create]");
            if (createBtn) { createBtn.disabled = true; createBtn.textContent = "…"; }

            try {
                // 1 — create community
                const storeRes = await fetch("/communities", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Accept": "application/json",
                        "X-CSRF-TOKEN": csrf(),
                    },
                    body: JSON.stringify({
                        name,
                        description: descInput?.value.trim() ?? "",
                        visibility: selVis,
                        join_mode: selVis === "public" ? "open" : "invite_only",
                        invite_policy: selInvitePolicy,
                        posting_policy: selPostingPolicy,
                        default_post_ttl_seconds: selTtl === "" ? null : parseInt(selTtl, 10),
                        member_limit: selLimit === "" ? null : parseInt(selLimit, 10),
                        allow_posts_in_member_feed: 1,
                        show_key_fingerprints: 1,
                        hide_real_names: 0,
                        anonymous_reactions_enabled: 0,
                    }),
                });

                const storeData = await storeRes.json().catch(() => ({}));

                if (!storeRes.ok || !storeData.success) {
                    const msg = storeData.message
                        ?? (storeData.errors ? Object.values(storeData.errors).flat().join(" ") : null)
                        ?? "Ошибка создания сообщества";
                    showError(error1, msg);
                    if (createBtn) {
                        createBtn.disabled = false;
                        createBtn.innerHTML = 'Создать <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg>';
                    }
                    return;
                }

                const { id, slug } = storeData.community;
                createdCommunityId = id;

                // 2 — generate invite code
                const code = await generateInviteCode(id);

                // 3 — fill step 2 UI
                fillDoneStep(name, descInput?.value.trim() ?? "", code, slug);
                goToStep(2);

            } catch {
                showError(error1, "Ошибка соединения");
                if (createBtn) {
                    createBtn.disabled = false;
                    createBtn.innerHTML = 'Создать <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M13 6l6 6-6 6"/></svg>';
                }
            }
        }

        async function generateInviteCode(communityId) {
            const expiresAt = new Date(Date.now() + 5 * 60 * 1000).toISOString();
            const invRes = await fetch(`/communities/${communityId}/invites`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "Accept": "application/json",
                    "X-CSRF-TOKEN": csrf(),
                },
                body: JSON.stringify({ expires_at: expiresAt, max_uses: 50 }),
            });

            const invData = await invRes.json().catch(() => ({}));
            return invData.invite?.code ?? invData.code ?? "";
        }

        function fillDoneStep(name, desc, code, slug) {
            const tintEnd = (selTint + 60) % 360;
            const gradient = `linear-gradient(135deg, oklch(0.32 0.07 ${selTint}), oklch(0.18 0.05 ${tintEnd}))`;

            if (doneAvatar) {
                doneAvatar.style.background = gradient;
                doneAvatar.textContent = selGlyph;
            }
            if (doneName) doneName.textContent = name;
            if (doneDesc) doneDesc.textContent = desc || "";
            if (inviteCode) inviteCode.textContent = code;
            if (gotoBtn) gotoBtn.href = `/communities/${slug}`;

            startCountdown(300);
        }

        function startCountdown(seconds) {
            if (countdownTimer) clearInterval(countdownTimer);
            const circumference = 2 * Math.PI * 27;
            if (ringFill) {
                ringFill.style.strokeDasharray = String(circumference);
                ringFill.style.strokeDashoffset = "0";
            }
            let remaining = seconds;

            const tick = () => {
                const frac = remaining / seconds;
                if (ringFill) {
                    ringFill.style.strokeDashoffset = String(circumference * (1 - frac));
                    ringFill.style.stroke = remaining < 30 ? "var(--danger, #ff6b6b)" : "var(--accent)";
                }
                const m = String(Math.floor(remaining / 60)).padStart(2, "0");
                const s = String(remaining % 60).padStart(2, "0");
                if (ringTime) ringTime.textContent = `${m}:${s}`;
                if (remaining <= 0) {
                    clearInterval(countdownTimer);
                    return;
                }
                remaining--;
            };
            tick();
            countdownTimer = setInterval(tick, 1000);
        }

        // ── Copy invite code ─────────────────────────────────────────────────
        function copyInviteCode() {
            const code = inviteCode?.textContent ?? "";
            if (!code || code === "—") return;
            navigator.clipboard?.writeText(code).then(() => {
                copyBtn?.classList.add("is-copied");
                if (copyBtn) copyBtn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L20 6"/></svg> Скопировано';
                setTimeout(() => {
                    copyBtn?.classList.remove("is-copied");
                    if (copyBtn) copyBtn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg> Скопировать';
                }, 1800);
            });
        }

        copyBtn?.addEventListener("click", copyInviteCode);

        shareBtn?.addEventListener("click", async () => {
            const code = inviteCode?.textContent ?? "";
            if (!code || code === "—") return;

            const shareText = `Код приглашения в сообщество: ${code}`;
            if (navigator.share) {
                try {
                    await navigator.share({ title: "Приглашение в skr", text: shareText });
                    return;
                } catch {
                    // User cancelled native share; keep the modal unchanged.
                }
            }

            copyInviteCode();
        });

        refreshBtn?.addEventListener("click", async () => {
            if (!createdCommunityId) return;

            refreshBtn.disabled = true;
            try {
                const code = await generateInviteCode(createdCommunityId);
                if (inviteCode) inviteCode.textContent = code;
                startCountdown(300);
            } finally {
                refreshBtn.disabled = false;
            }
        });

        // ── Reset wizard ─────────────────────────────────────────────────────
        function wizReset() {
            goToStep(0);
            if (nameInput) nameInput.value = "";
            if (descInput) descInput.value = "";
            if (descCount) descCount.textContent = "0";
            if (namePreview) namePreview.textContent = "без названия";
            if (descPreview) descPreview.textContent = "описание появится здесь";
            if (nextBtn) nextBtn.disabled = true;
            hideError(error0);
            hideError(error1);

            // Reset glyph/tint to defaults
            selGlyph = GLYPHS[0];
            selTint  = TINTS[0];
            createModal.querySelectorAll(".cm-wiz-glyph").forEach((b, i) => b.classList.toggle("is-active", i === 0));
            createModal.querySelectorAll(".cm-wiz-tint-btn").forEach((b, i) => {
                b.classList.toggle("is-active", i === 0);
                b.innerHTML = i === 0 ? '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L20 6"/></svg>' : "";
            });
            updateAvatarPreview();

            // Reset step 1 selections
            selVis = "private";
            selInvitePolicy  = "moderators_only";
            selPostingPolicy = "everyone";
            selTtl   = "86400";
            selLimit = "50";
            createdCommunityId = null;

            resetRadioCards("cmWizVisibility",    "private");
            resetRadioCards("cmWizInvitePolicy",  "moderators_only");
            resetRadioCards("cmWizPostingPolicy", "everyone");
            resetBtnGroup("cmWizTtl",   "86400");
            if (limitRange) limitRange.value = selLimit;
            if (limitValue) limitValue.textContent = selLimit;

            // Stop countdown
            if (countdownTimer) { clearInterval(countdownTimer); countdownTimer = null; }
        }

        function resetRadioCards(containerId, activeValue) {
            const container = document.getElementById(containerId);
            if (!container) return;
            container.querySelectorAll(".cm-wiz-radio-card").forEach((card) => {
                const active = activeValue !== null && card.dataset.value === activeValue;
                card.classList.toggle("is-active", active);
                card.querySelector(".cm-wiz-radio-dot")?.classList.toggle("is-active", active);
            });
        }

        function resetBtnGroup(containerId, activeValue) {
            const container = document.getElementById(containerId);
            if (!container) return;
            container.querySelectorAll(".cm-wiz-group-btn").forEach((btn) => {
                btn.classList.toggle("is-active", btn.dataset.value === activeValue);
            });
        }
    }

    // ── Join by invite code ───────────────────────────────────────────────────
    const joinPanel  = document.getElementById("cm-join-panel");
    const joinToggle = /** @type {HTMLButtonElement|null} */ (document.querySelector("[data-cm-toggle-join]"));
    const joinInput  = /** @type {HTMLInputElement|null} */ (document.getElementById("cm-join-input"));
    const joinSubmit = /** @type {HTMLButtonElement|null} */ (document.querySelector("[data-cm-join-submit]"));
    const joinError  = document.getElementById("cm-join-error");

    joinToggle?.addEventListener("click", () => {
        if (!joinPanel) return;
        const opening = joinPanel.hidden;
        joinPanel.hidden = !opening;
        joinToggle.classList.toggle("is-open", opening);
        if (opening) joinInput?.focus();
    });

    joinInput?.addEventListener("keydown", (e) => {
        if (e.key === "Enter") joinSubmit?.click();
    });

    joinSubmit?.addEventListener("click", async () => {
        const code = joinInput?.value.trim() ?? "";
        if (!code) { joinInput?.focus(); return; }

        joinSubmit.disabled = true;
        joinSubmit.textContent = "…";
        if (joinError) joinError.hidden = true;

        try {
            const res = await fetch("/communities/join-by-invite", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.content ?? "",
                },
                body: JSON.stringify({ code }),
            });

            if (res.ok) { window.location.reload(); return; }

            const data = await res.json().catch(() => ({}));
            if (joinError) {
                joinError.textContent = data.message ?? "Неверный код приглашения";
                joinError.hidden = false;
            }
        } catch {
            if (joinError) {
                joinError.textContent = "Ошибка соединения";
                joinError.hidden = false;
            }
        }

        joinSubmit.disabled = false;
        joinSubmit.textContent = "Войти";
    });
});

function showError(el, msg) {
    if (!el) return;
    el.textContent = msg;
    el.hidden = false;
}

function hideError(el) {
    if (!el) return;
    el.hidden = true;
    el.textContent = "";
}
