import type { Message } from "./types";

export interface MessageRecord extends Message {
    conversation_id: number;
}

export const IDB = {
    db: null as IDBDatabase | null,

    async open(): Promise<void> {
        return new Promise((resolve, reject) => {
            const req = indexedDB.open("skr_chat", 2);
            req.onupgradeneeded = (e) => {
                const db = (e.target as IDBOpenDBRequest).result;
                if (!db.objectStoreNames.contains("keys")) {
                    db.createObjectStore("keys");
                }
                if (!db.objectStoreNames.contains("messages")) {
                    const ms = db.createObjectStore("messages", { keyPath: "id" });
                    ms.createIndex("by_conv", "conversation_id");
                }
            };
            req.onsuccess = (e) => {
                this.db = (e.target as IDBOpenDBRequest).result;
                resolve();
            };
            req.onerror = () => reject(req.error);
        });
    },

    async get<T = unknown>(store: string, key: IDBValidKey): Promise<T> {
        return new Promise((resolve, reject) => {
            const tx = this.db!.transaction(store, "readonly");
            const req = tx.objectStore(store).get(key);
            req.onsuccess = () => resolve(req.result as T);
            req.onerror = () => reject(req.error);
        });
    },

    async put<T>(store: string, value: T, key?: IDBValidKey): Promise<void> {
        return new Promise((resolve, reject) => {
            const tx = this.db!.transaction(store, "readwrite");
            const req =
                key !== undefined
                    ? tx.objectStore(store).put(value, key)
                    : tx.objectStore(store).put(value);
            req.onsuccess = () => resolve();
            req.onerror = () => reject(req.error);
        });
    },

    async getByIndex<T = unknown>(
        store: string,
        index: string,
        value: IDBValidKey | IDBKeyRange,
    ): Promise<T[]> {
        return new Promise((resolve, reject) => {
            const tx = this.db!.transaction(store, "readonly");
            const req = tx.objectStore(store).index(index).getAll(value);
            req.onsuccess = () => resolve(req.result as T[]);
            req.onerror = () => reject(req.error);
        });
    },

    async putMessage(msg: MessageRecord): Promise<void> {
        return this.put("messages", msg);
    },
};
