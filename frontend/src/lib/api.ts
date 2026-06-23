// Thin fetch wrapper for the Laravel API (Sanctum stateful cookies).
// Same-origin by default (nginx proxies /api + /sanctum to Laravel);
// override with NEXT_PUBLIC_API_URL for split-origin dev.

const BASE = process.env.NEXT_PUBLIC_API_URL ?? "";

export class ApiError extends Error {
  status: number;
  errors?: Record<string, string[]>;

  constructor(status: number, message: string, errors?: Record<string, string[]>) {
    super(message);
    this.status = status;
    this.errors = errors;
  }
}

function readCookie(name: string): string | null {
  const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
  return match ? decodeURIComponent(match[1]) : null;
}

let csrfReady = false;

async function ensureCsrf(): Promise<void> {
  if (csrfReady && readCookie("XSRF-TOKEN")) return;
  await fetch(`${BASE}/sanctum/csrf-cookie`, { credentials: "include" });
  csrfReady = true;
}

async function request<T>(method: string, path: string, body?: unknown): Promise<T> {
  const headers: Record<string, string> = { Accept: "application/json" };
  // FormData → let the browser set multipart Content-Type (with boundary).
  const isForm = typeof FormData !== "undefined" && body instanceof FormData;

  if (method !== "GET") {
    await ensureCsrf();
    const token = readCookie("XSRF-TOKEN");
    if (token) headers["X-XSRF-TOKEN"] = token;
    if (body !== undefined && !isForm) headers["Content-Type"] = "application/json";
  }

  const res = await fetch(`${BASE}/api/v1${path}`, {
    method,
    headers,
    credentials: "include",
    body: body !== undefined ? (isForm ? (body as FormData) : JSON.stringify(body)) : undefined,
  });

  if (res.status === 204) return undefined as T;

  const payload = await res.json().catch(() => ({}));

  if (!res.ok) {
    if (res.status === 419) csrfReady = false; // CSRF token rotated — refetch next time
    throw new ApiError(res.status, payload.message ?? res.statusText, payload.errors);
  }

  return payload as T;
}

export const api = {
  get: <T>(path: string) => request<T>("GET", path),
  post: <T>(path: string, body?: unknown) => request<T>("POST", path, body),
  put: <T>(path: string, body?: unknown) => request<T>("PUT", path, body),
  del: <T>(path: string) => request<T>("DELETE", path),
};

/** Build a query string, dropping empty values. */
export function qs(params: Record<string, string | number | undefined | null>): string {
  const pairs = Object.entries(params).filter(
    ([, v]) => v !== undefined && v !== null && v !== ""
  );
  if (!pairs.length) return "";
  return "?" + pairs.map(([k, v]) => `${k}=${encodeURIComponent(String(v))}`).join("&");
}
