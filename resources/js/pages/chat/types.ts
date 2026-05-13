export interface Message {
    id: number;
    type?: "message" | "system";
    sender_id: number;
    encrypted_payload: string;
    system_payload?: {
        event?: string;
        actor?: string;
        target?: string;
    } | null;
    created_at: string;
    delivered_at: string | null;
    read_at: string | null;
    edited_at?: string | null;
    reply_to_id?: number | null;
    conversation_id?: number;
}

export interface LaravelData {
    userId: number;
    pseudonym: string;
    hasPublicKey: boolean;
    hasKeyBackup: boolean;
    avatars: Record<number, string | null>;
}

export interface ChatParticipant {
    id: number;
    login: string;
    role: "owner" | "admin" | "member";
    avatar: string | null;
    public_key_jwk: string | null;
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
