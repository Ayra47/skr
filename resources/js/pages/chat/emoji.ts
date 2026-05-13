import { autoResize, updateSendBtn } from "./ui";

export function initEmojiPicker(): void {
    const btn = document.getElementById("emojiBtn") as HTMLButtonElement;
    const input = document.getElementById(
        "messageInput",
    ) as HTMLTextAreaElement;
    const panel = document.getElementById("emojiPanel") as HTMLElement;
    let built = false;
    let isOpen = false;
    const STORAGE_KEY = "emoji_panel_open";

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
        panel.appendChild((picker as any).el ?? picker);
    }

    window.emojiPanelOnChatOpen = async (): Promise<void> => {
        if (sessionStorage.getItem(STORAGE_KEY)) {
            await build();
            open();
        }
    };

    btn.addEventListener("click", async (e) => {
        e.stopPropagation();
        await build();
        isOpen ? close() : open();
    });

    backdrop.addEventListener("click", close);
}
