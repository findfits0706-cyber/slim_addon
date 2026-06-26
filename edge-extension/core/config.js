export const EXTENSION_VERSION = '0.1.0';
export const DEFAULT_API_BASE_URL = 'https://findpilates.jp/api/v1/extension';
export const SLIM_ORIGIN = 'https://www.slim-sng.jp';

export const ADMISSION_SCOPES = [
  { key: 'today', label: '今日予定' },
  { key: 'unregistered', label: '未登録' },
  { key: 'in_progress', label: '登録中' },
  { key: 'search', label: '検索' }
];

export function normalizeApiBaseUrl(value) {
  const raw = String(value || DEFAULT_API_BASE_URL).trim();
  try {
    const parsed = new URL(raw);
    if (parsed.protocol !== 'https:') {
      return DEFAULT_API_BASE_URL;
    }
    return parsed.href.replace(/\/+$/, '');
  } catch {
    return DEFAULT_API_BASE_URL;
  }
}

export function isSlimUrl(url) {
  try {
    return new URL(url).origin === SLIM_ORIGIN;
  } catch {
    return false;
  }
}
