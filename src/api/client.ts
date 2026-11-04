declare global {
  interface Window {
    bckfcToolsConfig: { root: string; nonce: string };
  }
}

type ApiRequestOptions = {
  method: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  body?: unknown;
};

export async function requestToolsApi<T>(
  path: string,
  { method, body }: ApiRequestOptions,
): Promise<T> {
  const response = await fetch(window.bckfcToolsConfig.root + path, {
    method,
    headers: { 'X-WP-Nonce': window.bckfcToolsConfig.nonce, 'Content-Type': 'application/json' },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const data = await response.json();

  if (!response.ok) throw new Error(data.message || 'The request failed. Please try again.');

  return data;
}
