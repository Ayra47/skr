export const CSRF = (
    document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement
).content;

export async function fetchJson<T = unknown>(url: string): Promise<T> {
    const r = await fetch(url, {
        headers: { "X-CSRF-TOKEN": CSRF, Accept: "application/json" },
    });
    return r.json() as Promise<T>;
}

export async function post<T = unknown>(
    url: string,
    body: Record<string, unknown>,
): Promise<T> {
    const r = await fetch(url, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            "X-CSRF-TOKEN": CSRF,
        },
        body: JSON.stringify(body),
    });
    return r.json() as Promise<T>;
}

export async function del<T = unknown>(url: string): Promise<T> {
    const r = await fetch(url, {
        method: "DELETE",
        headers: { Accept: "application/json", "X-CSRF-TOKEN": CSRF },
    });
    return r.json() as Promise<T>;
}

export async function delWithBody<T = unknown>(
    url: string,
    body: Record<string, unknown>,
): Promise<T> {
    const r = await fetch(url, {
        method: "DELETE",
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            "X-CSRF-TOKEN": CSRF,
        },
        body: JSON.stringify(body),
    });
    return r.json() as Promise<T>;
}

export async function patch<T = unknown>(
    url: string,
    body: Record<string, unknown>,
): Promise<T> {
    const r = await fetch(url, {
        method: "PATCH",
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            "X-CSRF-TOKEN": CSRF,
        },
        body: JSON.stringify(body),
    });
    return r.json() as Promise<T>;
}
