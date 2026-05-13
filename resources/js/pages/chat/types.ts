export interface Message {
    id: number;
    sender_id: number;
    encrypted_payload: string;
    created_at: string;
    delivered_at: string | null;
    read_at: string | null;
    edited_at?: string | null;
    conversation_id?: number;
}

export interface LaravelData {
    userId: number;
    hasPublicKey: boolean;
    hasKeyBackup: boolean;
    avatars: Record<number, string | null>;
}

declare global {
    interface Window {
        Laravel: LaravelData;
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        Echo: any;
        emojiPanelOnChatOpen?: () => Promise<void>;
        currentConvId: number | null;
    }
}
