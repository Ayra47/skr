import { autoResize, updateSendBtn } from "./ui";
import { state } from "./state";

type SidePanelTab = "info" | "emoji";

export function initEmojiPicker(): void {
    const btn = document.getElementById("emojiBtn") as HTMLButtonElement;
    const input = document.getElementById(
        "messageInput",
    ) as HTMLTextAreaElement;
    const panel = document.getElementById("chatSidePanel") as HTMLElement;
    const pickerHost = document.getElementById("emojiPickerHost") as HTMLElement;
    const groupPanel = document.getElementById("groupPanel") as HTMLElement;
    const directInfoPanel = document.getElementById("directInfoPanel") as HTMLElement;
    let built = false;
    let isOpen = false;
    const STORAGE_KEY = "emoji_panel_open";
    const TAB_STORAGE_KEY = "chat_side_panel_tab";

    const isMobile = () => window.matchMedia("(max-width: 768px)").matches;

    const backdrop = document.createElement("div");
    backdrop.className = "emoji-backdrop";
    document.body.appendChild(backdrop);

    function open(): void {
        panel.style.overflow = "hidden";
        panel.classList.add("emoji-panel--open");
        if (isMobile()) {
            backdrop.classList.add("emoji-backdrop--open");
        }
        panel.addEventListener(
            "transitionend",
            () => {
                panel.style.overflow = "visible";
            },
            { once: true },
        );
        isOpen = true;
        sessionStorage.setItem(STORAGE_KEY, "1");
    }

    function close(): void {
        panel.style.overflow = "hidden";
        panel.classList.remove("emoji-panel--open");
        backdrop.classList.remove("emoji-backdrop--open");
        isOpen = false;
        sessionStorage.removeItem(STORAGE_KEY);
    }

    function syncInfoPanel(): void {
        const isGroup = state.currentConversationType === "group";
        directInfoPanel.style.display = isGroup ? "none" : "";
        groupPanel.style.display = isGroup ? "" : "none";
    }

    function setActiveTab(tab: SidePanelTab): void {
        syncInfoPanel();
        panel.dataset.activeTab = tab;
        panel.querySelectorAll<HTMLElement>("[data-side-panel-tab]").forEach((tabButton) => {
            tabButton.classList.toggle("is-active", tabButton.dataset.sidePanelTab === tab);
        });
        panel.querySelectorAll<HTMLElement>("[data-side-panel-content]").forEach((content) => {
            content.classList.toggle("is-active", content.dataset.sidePanelContent === tab);
        });
        sessionStorage.setItem(TAB_STORAGE_KEY, tab);
    }

    async function openTab(tab: SidePanelTab): Promise<void> {
        if (tab === "emoji") {
            await build();
        }
        setActiveTab(tab);
        open();
    }

    async function build(): Promise<void> {
        if (built) {
            return;
        }
        built = true;
        const [{ Picker }, { default: data }] = await Promise.all([
            import("emoji-mart"),
            import("@emoji-mart/data"),
        ]);
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const picker = new (Picker as any)({
            data,
            locale: "ru",
            theme: "dark",
            previewPosition: "none",
            skinTonePosition: "none",
            onEmojiSelect: (emoji: { native: string }) => {
                const start = input.selectionStart ?? input.value.length;
                const end = input.selectionEnd ?? input.value.length;
                const val = input.value;
                input.value =
                    val.slice(0, start) + emoji.native + val.slice(end);
                const pos = start + emoji.native.length;
                input.setSelectionRange(pos, pos);
                input.focus();
                autoResize(input);
                updateSendBtn();
            },
        });
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        pickerHost.appendChild((picker as any).el ?? picker);
    }

    function initSidePanelTabs(): void {
        panel.querySelectorAll<HTMLElement>("[data-side-panel-tab]").forEach((tabButton) => {
            tabButton.addEventListener("click", async () => {
                const tab = tabButton.dataset.sidePanelTab as SidePanelTab;
                await openTab(tab);
            });
        });

        setActiveTab((sessionStorage.getItem(TAB_STORAGE_KEY) as SidePanelTab | null) ?? "emoji");
    }

    window.emojiPanelOnChatOpen = async (): Promise<void> => {
        syncInfoPanel();
        if (sessionStorage.getItem(STORAGE_KEY)) {
            await openTab((sessionStorage.getItem(TAB_STORAGE_KEY) as SidePanelTab | null) ?? "emoji");
        }
    };

    window.chatSidePanelOnConversationChange = (): void => {
        syncInfoPanel();
    };

    window.openChatSidePanel = async (tab: SidePanelTab = "info"): Promise<void> => {
        await openTab(tab);
    };

    window.closeChatSidePanel = close;

    btn.addEventListener("click", async (e) => {
        e.stopPropagation();
        if (isOpen && panel.dataset.activeTab === "emoji") {
            close();
            return;
        }
        await openTab("emoji");
    });

    backdrop.addEventListener("click", close);
    initSidePanelTabs();
}
