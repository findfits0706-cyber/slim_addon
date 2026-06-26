import { EXTENSION_VERSION, normalizeApiBaseUrl } from './config.js';

export class ApiError extends Error {
  constructor(status, code, message, requestId = '') {
    super(message || code || 'API error');
    this.name = 'ApiError';
    this.status = status;
    this.code = code;
    this.requestId = requestId;
  }
}

function requestId() {
  const bytes = new Uint8Array(12);
  crypto.getRandomValues(bytes);
  return 'edge-' + Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
}

function routeUrl(baseUrl, route, query = null) {
  const url = new URL(normalizeApiBaseUrl(baseUrl).replace(/\/+$/, '') + '/' + String(route).replace(/^\/+/, ''));
  if (query) {
    for (const [key, value] of Object.entries(query)) {
      if (value !== undefined && value !== null && value !== '') {
        url.searchParams.set(key, String(value));
      }
    }
  }
  return url.toString();
}

export class ExtensionApiClient {
  constructor({ baseUrl, accessToken = '', installationId = '' }) {
    this.baseUrl = normalizeApiBaseUrl(baseUrl);
    this.accessToken = accessToken;
    this.installationId = installationId;
  }

  withAuth(auth) {
    return new ExtensionApiClient({
      baseUrl: this.baseUrl,
      accessToken: auth?.accessToken || '',
      installationId: auth?.installationId || this.installationId
    });
  }

  async request(route, { method = 'GET', body = null, query = null, auth = true } = {}) {
    const headers = {
      'Accept': 'application/json',
      'X-Request-ID': requestId()
    };
    if (body !== null) {
      headers['Content-Type'] = 'application/json';
    }
    if (auth) {
      headers.Authorization = 'Bearer ' + this.accessToken;
      headers['X-Extension-Installation-Id'] = this.installationId;
    }

    const response = await fetch(routeUrl(this.baseUrl, route, query), {
      method,
      headers,
      body: body === null ? null : JSON.stringify(body),
      cache: 'no-store'
    });

    let payload = null;
    const text = await response.text();
    if (text) {
      try {
        payload = JSON.parse(text);
      } catch {
        throw new ApiError(response.status, 'invalid_json_response', 'API returned invalid JSON.');
      }
    }

    if (!response.ok || !payload?.ok) {
      const error = payload?.error || {};
      throw new ApiError(response.status, error.code || 'api_error', error.message || response.statusText, payload?.request_id || '');
    }

    return payload.data;
  }

  pair({ pairingCode, installationId }) {
    return this.request('pair', {
      method: 'POST',
      auth: false,
      body: {
        pairing_code: pairingCode,
        installation_id: installationId,
        extension_version: EXTENSION_VERSION
      }
    });
  }

  me() {
    return this.request('me');
  }

  admissions({ scope, q = '', page = 1, limit = 25 }) {
    return this.request('admissions', {
      query: { scope, q, page, limit }
    });
  }

  transfer(applicationId) {
    return this.request(`admissions/${encodeURIComponent(applicationId)}/transfer`);
  }

  lock(applicationId, version) {
    return this.request(`admissions/${encodeURIComponent(applicationId)}/lock`, {
      method: 'POST',
      body: { version }
    });
  }

  heartbeat(applicationId) {
    return this.request(`admissions/${encodeURIComponent(applicationId)}/heartbeat`, {
      method: 'POST',
      body: {}
    });
  }

  releaseLock(applicationId) {
    return this.request(`admissions/${encodeURIComponent(applicationId)}/lock`, {
      method: 'DELETE'
    });
  }
}
