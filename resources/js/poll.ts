type EchoChannel = {
    listen: (event: string, callback: (data: PollBroadcastPayload) => void) => EchoChannel;
};

type EchoInstance = {
    channel: (name: string) => EchoChannel;
    leaveChannel: (name: string) => void;
};

type EchoWindow = Window & { Echo?: EchoInstance };

type PollOptionPayload = {
    id: number;
    votes_count: number;
    percentage: number;
};

type PollBroadcastPayload = {
    poll_id: number;
    total_votes: number;
    options: PollOptionPayload[];
};

type VoteResponsePayload = {
    poll_id: number;
    total_votes: number;
    voted_option_ids: number[];
    options: PollOptionPayload[];
    error?: string;
};

const subscribedChannels = new Set<string>();
const debounceTimers = new Map<number, ReturnType<typeof setTimeout>>();

export function initPoll(): void {
    document.addEventListener("click", handlePollClick);
    document.addEventListener("livewire:navigated", onNavigated);
    window.addEventListener("feed-post-created", onNavigated);
    subscribeAllPolls();
}

function onNavigated(): void {
    subscribeAllPolls();
}

function handlePollClick(event: MouseEvent): void {
    if (!(event.target instanceof Element)) {
        return;
    }

    const cancelBtn = event.target.closest("[data-poll-cancel]");
    if (cancelBtn instanceof HTMLElement) {
        const pollEl = cancelBtn.closest("[data-poll]");
        if (pollEl instanceof HTMLElement) {
            event.stopPropagation();
            cancelVotes(pollEl);
        }
        return;
    }

    const optionBtn = event.target.closest("[data-poll-option]");
    if (optionBtn instanceof HTMLButtonElement) {
        const pollEl = optionBtn.closest("[data-poll]");
        if (pollEl instanceof HTMLElement) {
            event.stopPropagation();
            handleOptionClick(pollEl, optionBtn);
        }
    }
}

function handleOptionClick(pollEl: HTMLElement, optionBtn: HTMLButtonElement): void {
    if (pollEl.dataset.pollExpired === "true" || optionBtn.disabled) {
        return;
    }

    const optionId = Number(optionBtn.dataset.optionId);
    if (!optionId) {
        return;
    }

    const mode = pollEl.dataset.pollMode ?? "single";
    const maxChoices = pollEl.dataset.pollMaxChoices ? Number(pollEl.dataset.pollMaxChoices) : null;
    const currentVotedIds = getVotedIds(pollEl);

    let nextVotedIds: number[];

    if (mode === "single") {
        // Toggle: same option deselects, different option replaces
        nextVotedIds = currentVotedIds.includes(optionId) ? [] : [optionId];
    } else {
        if (currentVotedIds.includes(optionId)) {
            nextVotedIds = currentVotedIds.filter((id) => id !== optionId);
        } else {
            if (maxChoices !== null && currentVotedIds.length >= maxChoices) {
                return;
            }
            nextVotedIds = [...currentVotedIds, optionId];
        }
    }

    applyOptimisticVote(pollEl, nextVotedIds);

    if (nextVotedIds.length === 0) {
        sendCancelRequest(pollEl);
    } else {
        sendVoteRequest(pollEl, nextVotedIds);
    }
}

function cancelVotes(pollEl: HTMLElement): void {
    applyOptimisticVote(pollEl, []);
    sendCancelRequest(pollEl);
}

function sendVoteRequest(pollEl: HTMLElement, optionIds: number[]): void {
    const url = pollEl.dataset.voteUrl;
    if (!url) {
        return;
    }

    const csrfToken = getCsrfToken();

    fetch(url, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "Accept": "application/json",
            "X-CSRF-TOKEN": csrfToken,
        },
        body: JSON.stringify({ option_ids: optionIds }),
    })
        .then((res) => res.json())
        .then((data: VoteResponsePayload) => {
            if (data.error) {
                return;
            }
            applyAuthoritativeResult(pollEl, data);
        })
        .catch(() => {
            // Revert optimistic on network failure
            const prevIds = getVotedIds(pollEl);
            applyOptimisticVote(pollEl, prevIds);
        });
}

function sendCancelRequest(pollEl: HTMLElement): void {
    const url = pollEl.dataset.cancelUrl;
    if (!url) {
        return;
    }

    const csrfToken = getCsrfToken();

    fetch(url, {
        method: "DELETE",
        headers: {
            "Accept": "application/json",
            "X-CSRF-TOKEN": csrfToken,
        },
    })
        .then((res) => res.json())
        .then((data: VoteResponsePayload) => {
            applyAuthoritativeResult(pollEl, data);
        })
        .catch(() => {});
}

function applyOptimisticVote(pollEl: HTMLElement, votedIds: number[]): void {
    setVotedIds(pollEl, votedIds);
    const showResults = votedIds.length > 0 || pollEl.dataset.pollExpired === "true";

    pollEl.querySelectorAll("[data-poll-option]").forEach((el) => {
        if (!(el instanceof HTMLButtonElement)) {
            return;
        }

        const id = Number(el.dataset.optionId);
        const isSelected = votedIds.includes(id);

        el.classList.toggle("selected", isSelected);
        el.classList.toggle("show-results", showResults);
        el.setAttribute("aria-pressed", isSelected ? "true" : "false");

        const pct = showResults ? Number(el.dataset.pct ?? 0) : 0;
        const fill = el.querySelector<HTMLElement>(".feed-poll-option-fill");
        const stat = el.querySelector<HTMLElement>("[data-poll-option-stat]");

        if (fill) {
            fill.style.width = `${pct}%`;
        }
        if (stat) {
            stat.style.display = showResults ? "" : "none";
        }
    });

    updateCancelButton(pollEl, votedIds);
}

function applyAuthoritativeResult(pollEl: HTMLElement, data: VoteResponsePayload): void {
    const { voted_option_ids: votedIds, total_votes: total, options } = data;

    setVotedIds(pollEl, votedIds);
    const showResults = votedIds.length > 0 || pollEl.dataset.pollExpired === "true";

    const optionMap = new Map(options.map((o) => [o.id, o]));

    pollEl.querySelectorAll("[data-poll-option]").forEach((el) => {
        if (!(el instanceof HTMLButtonElement)) {
            return;
        }

        const id = Number(el.dataset.optionId);
        const payload = optionMap.get(id);
        if (!payload) {
            return;
        }

        const pct = payload.percentage;
        el.dataset.votes = String(payload.votes_count);
        el.dataset.pct = String(pct);

        const isSelected = votedIds.includes(id);
        el.classList.toggle("selected", isSelected);
        el.classList.toggle("show-results", showResults);
        el.setAttribute("aria-pressed", isSelected ? "true" : "false");

        const fill = el.querySelector<HTMLElement>(".feed-poll-option-fill");
        const stat = el.querySelector<HTMLElement>("[data-poll-option-stat]");

        if (fill) {
            fill.style.width = `${pct}%`;
        }
        if (stat) {
            stat.textContent = `${pct}%`;
            stat.style.display = showResults ? "" : "none";
        }
    });

    const totalEl = pollEl.querySelector("[data-poll-total]");
    if (totalEl) {
        totalEl.textContent = `${total} ${votesLabel(total)}`;
    }

    updateCancelButton(pollEl, votedIds);
}

function applyBroadcastUpdate(pollEl: HTMLElement, data: PollBroadcastPayload): void {
    const votedIds = getVotedIds(pollEl);
    const showResults = votedIds.length > 0 || pollEl.dataset.pollExpired === "true";
    const optionMap = new Map(data.options.map((o) => [o.id, o]));

    pollEl.querySelectorAll("[data-poll-option]").forEach((el) => {
        if (!(el instanceof HTMLButtonElement)) {
            return;
        }

        const id = Number(el.dataset.optionId);
        const payload = optionMap.get(id);
        if (!payload) {
            return;
        }

        el.dataset.votes = String(payload.votes_count);
        el.dataset.pct = String(payload.percentage);

        if (showResults) {
            const fill = el.querySelector<HTMLElement>(".feed-poll-option-fill");
            const stat = el.querySelector<HTMLElement>("[data-poll-option-stat]");

            if (fill) {
                fill.style.width = `${payload.percentage}%`;
            }
            if (stat) {
                stat.textContent = `${payload.percentage}%`;
            }
        }
    });

    const totalEl = pollEl.querySelector("[data-poll-total]");
    if (totalEl) {
        totalEl.textContent = `${data.total_votes} ${votesLabel(data.total_votes)}`;
    }
}

function subscribeAllPolls(): void {
    const echo = (window as EchoWindow).Echo;
    if (!echo) {
        return;
    }

    document.querySelectorAll("[data-poll]").forEach((el) => {
        if (!(el instanceof HTMLElement)) {
            return;
        }

        const pollId = Number(el.dataset.pollId);
        if (!pollId) {
            return;
        }

        const channelName = `poll.${pollId}`;
        if (subscribedChannels.has(channelName)) {
            return;
        }

        subscribedChannels.add(channelName);

        echo.channel(channelName).listen(".poll.updated", (data: PollBroadcastPayload) => {
            const timer = debounceTimers.get(pollId);
            if (timer !== undefined) {
                clearTimeout(timer);
            }

            debounceTimers.set(
                pollId,
                setTimeout(() => {
                    debounceTimers.delete(pollId);
                    document.querySelectorAll(`[data-poll][data-poll-id="${pollId}"]`).forEach((pollEl) => {
                        if (pollEl instanceof HTMLElement) {
                            applyBroadcastUpdate(pollEl, data);
                        }
                    });
                }, 800),
            );
        });
    });
}

function updateCancelButton(pollEl: HTMLElement, votedIds: number[]): void {
    const footer = pollEl.querySelector("[data-poll-footer]");
    if (!(footer instanceof HTMLElement)) {
        return;
    }

    const isExpired = pollEl.dataset.pollExpired === "true";
    const existing = footer.querySelector("[data-poll-cancel]");

    if (votedIds.length > 0 && !isExpired) {
        if (!existing) {
            const btn = document.createElement("button");
            btn.className = "feed-poll-cancel";
            btn.type = "button";
            btn.dataset.pollCancel = "";
            btn.textContent = "отменить голос";
            footer.append(btn);
        }
    } else {
        existing?.remove();
    }
}

function getVotedIds(pollEl: HTMLElement): number[] {
    const raw = pollEl.dataset.pollVotedIds ?? "";
    return raw
        .split(",")
        .map(Number)
        .filter((n) => n > 0);
}

function setVotedIds(pollEl: HTMLElement, ids: number[]): void {
    pollEl.dataset.pollVotedIds = ids.join(",");
}

function getCsrfToken(): string {
    return (document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content) ?? "";
}

function votesLabel(count: number): string {
    const mod10 = count % 10;
    const mod100 = count % 100;

    if (mod10 === 1 && mod100 !== 11) {
        return "голос";
    }
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) {
        return "голоса";
    }
    return "голосов";
}
