function csrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

export interface JsonResult<T> {
    ok: boolean;
    status: number;
    data: T;
}

export async function postJson<T>(
    url: string,
    body?: unknown,
): Promise<JsonResult<T>> {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: body ? JSON.stringify(body) : undefined,
    });

    const data = (await response.json().catch(() => ({}))) as T;

    return { ok: response.ok, status: response.status, data };
}
