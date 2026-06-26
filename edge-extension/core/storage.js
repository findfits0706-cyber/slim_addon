import { DEFAULT_API_BASE_URL, normalizeApiBaseUrl } from './config.js';

const AUTH_KEY = 'findPilatesSlimAuth';
const SELECTED_KEY = 'findPilatesSlimSelectedApplicationId';
const INSTALLATION_KEY = 'findPilatesSlimInstallationId';
const SETTINGS_KEY = 'findPilatesSlimSettings';

let memorySession = {};

function storageArea(kind) {
  if (kind === 'session' && chrome.storage?.session) {
    return chrome.storage.session;
  }
  if (kind === 'local' && chrome.storage?.local) {
    return chrome.storage.local;
  }
  return null;
}

function storageGet(area, keys) {
  return new Promise((resolve) => area.get(keys, resolve));
}

function storageSet(area, values) {
  return new Promise((resolve) => area.set(values, resolve));
}

function storageRemove(area, keys) {
  return new Promise((resolve) => area.remove(keys, resolve));
}

async function sessionGet(key) {
  const area = storageArea('session');
  if (!area) {
    return memorySession[key];
  }
  return (await storageGet(area, [key]))[key];
}

async function sessionSet(key, value) {
  const area = storageArea('session');
  if (!area) {
    memorySession[key] = value;
    return;
  }
  await storageSet(area, { [key]: value });
}

async function sessionRemove(key) {
  const area = storageArea('session');
  if (!area) {
    delete memorySession[key];
    return;
  }
  await storageRemove(area, [key]);
}

async function localGet(key) {
  const area = storageArea('local');
  if (!area) {
    return null;
  }
  return (await storageGet(area, [key]))[key];
}

async function localSet(key, value) {
  const area = storageArea('local');
  if (!area) {
    return;
  }
  await storageSet(area, { [key]: value });
}

function randomId() {
  if (crypto.randomUUID) {
    return crypto.randomUUID();
  }
  const bytes = new Uint8Array(16);
  crypto.getRandomValues(bytes);
  return Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
}

export async function getInstallationId() {
  let installationId = await localGet(INSTALLATION_KEY);
  if (!installationId) {
    installationId = randomId();
    await localSet(INSTALLATION_KEY, installationId);
  }
  return installationId;
}

export async function getSettings() {
  const settings = await localGet(SETTINGS_KEY);
  return {
    apiBaseUrl: normalizeApiBaseUrl(settings?.apiBaseUrl || DEFAULT_API_BASE_URL)
  };
}

export async function saveSettings(settings) {
  await localSet(SETTINGS_KEY, {
    apiBaseUrl: normalizeApiBaseUrl(settings?.apiBaseUrl || DEFAULT_API_BASE_URL)
  });
}

export async function getAuth() {
  return await sessionGet(AUTH_KEY) || null;
}

export async function saveAuth(auth) {
  await sessionSet(AUTH_KEY, auth);
}

export async function clearAuth() {
  await sessionRemove(AUTH_KEY);
  await sessionRemove(SELECTED_KEY);
  memorySession = {};
}

export async function getSelectedApplicationId() {
  return await sessionGet(SELECTED_KEY) || '';
}

export async function saveSelectedApplicationId(applicationId) {
  if (!applicationId) {
    await sessionRemove(SELECTED_KEY);
    return;
  }
  await sessionSet(SELECTED_KEY, applicationId);
}
