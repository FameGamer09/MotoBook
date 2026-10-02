declare global {
  interface Window {
    __RIDER_BOOT__?: {
      riderId: number;
      riderName: string;
      riderEmail: string;
      riderStatus: any;
      riderCode: string;
      vehiclePlate: string | null;
      unifiedLogoutUrl: string;
      apiBase: string;
      buildHash: string;
    };
  }
}

type ApiRequestOptions = RequestInit & {
  query?: Record<string, string | number | boolean | undefined | null>;
};

export const API_BASE: string =
  window.__RIDER_BOOT__?.apiBase ||
  (window.location.origin + '/IM-101/motobook/A-rider/api');

export function apiUrl(p: string, qs?: Record<string, string | number | boolean | undefined | null>): string {
  let url = API_BASE + (p.startsWith('/') ? p : `/${p}`);
  if (qs) {
    const params = new URLSearchParams();
    for (const [k, v] of Object.entries(qs)) {
      if (v === undefined || v === null) continue;
      params.set(k, String(v));
    }
    const str = params.toString();
    if (str) url += (url.includes('?') ? '&' : '?') + str;
  }
  return url;
}

export async function fetchJson<T = unknown>(
  path: string,
  init: RequestInit & { query?: Record<string, string | number | boolean | undefined | null> } = {},
): Promise<{ data: T; status: number; ok: boolean }> {
  const { query, ...rest } = init;
  const url = apiUrl(path, query);
  const headers = new Headers(init.headers ?? {});
  if (
    rest.body &&
    typeof rest.body === 'string' &&
    (rest.method ?? 'GET').toUpperCase() !== 'GET' &&
    !headers.has('Content-Type')
  ) {
    headers.set('Content-Type', 'application/json');
  }
  const resp = await fetch(url, {
    credentials: 'same-origin',
    ...rest,
    headers,
  });
  const text = await resp.text();
  let data: any = null;
  try { data = text ? JSON.parse(text) : null; } catch { data = text; }
  if (resp.status === 401 && typeof data?.redirect === 'string') {
    window.location.href = data.redirect;
  }
  return { data: data as T, status: resp.status, ok: resp.ok };
}
