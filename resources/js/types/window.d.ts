import Echo from "laravel-echo";
import Pusher from "pusher-js";
import type { AxiosInstance } from "axios";

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo: InstanceType<typeof Echo>;
        axios: AxiosInstance;
        Laravel: {
            userId: number | null;
            activeCodeExpiresAt: string | null;
            hasPublicKey: string | null;
        };
    }
}

export {};
