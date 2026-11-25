export async function api<T>(path: string, method = 'GET', data?: object, signal?: AbortSignal): Promise<T> {
  const response = await fetch('/api' + path, {
    method,
    signal,
    headers: data ? { 'Content-Type': 'application/json' } : undefined,
    body: data ? JSON.stringify(data) : undefined,
  });
  if (!response.ok) {
    const error = await response.json().catch(() => null);
    throw new Error(error?.error ?? 'HTTP ' + response.status);
  }
  return response.status === 204 ? (undefined as T) : response.json();
}
