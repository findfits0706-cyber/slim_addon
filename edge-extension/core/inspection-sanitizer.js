const SENSITIVE_KEY_RE = /(password|passwd|token|csrf|session|cookie|secret|credential)/i;
const EMAIL_RE = /[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i;
const PHONE_RE = /(?:\+?\d{1,3}[-\s]?)?(?:0\d{1,4}[-\s]?\d{1,4}[-\s]?\d{3,4})/;
const LONG_NUMBER_RE = /\d{6,}/;
const BUILD_HASH_RE = /(?:^|[-_])(?:[a-f0-9]{8,}|css-[a-z0-9]{6,}|v-[a-z0-9]{6,})(?:$|[-_])/i;

export function isSensitiveKey(value) {
  return SENSITIVE_KEY_RE.test(String(value || ''));
}

export function textLooksSensitive(value) {
  const text = String(value || '');
  return EMAIL_RE.test(text) || PHONE_RE.test(text) || LONG_NUMBER_RE.test(text);
}

export function sanitizeText(value, maxLength = 80) {
  const text = String(value || '')
    .normalize('NFKC')
    .replace(/\s+/g, ' ')
    .trim();

  if (!text || textLooksSensitive(text)) {
    return '';
  }

  return text.length > maxLength ? text.slice(0, maxLength - 1) + '…' : text;
}

export function sanitizeClassList(classNames) {
  if (!Array.isArray(classNames)) {
    return [];
  }

  return [...new Set(classNames
    .map((name) => String(name || '').trim())
    .filter((name) => /^[A-Za-z0-9_-]{1,40}$/.test(name))
    .filter((name) => !name.startsWith('data-v-'))
    .filter((name) => !BUILD_HASH_RE.test(name))
  )].slice(0, 8);
}

function sanitizeStringArray(values, maxLength = 80, maxItems = 12) {
  if (!Array.isArray(values)) {
    return [];
  }

  return [...new Set(values.map((value) => sanitizeText(value, maxLength)).filter(Boolean))].slice(0, maxItems);
}

export function sanitizeControl(raw = {}) {
  const type = String(raw.type || '').toLowerCase();
  const id = String(raw.id || '');
  const name = String(raw.name || '');
  if (['hidden', 'password'].includes(type) || isSensitiveKey(id) || isSensitiveKey(name)) {
    return null;
  }

  return {
    frameId: Number.isInteger(raw.frameId) ? raw.frameId : 0,
    tag: String(raw.tag || '').toLowerCase(),
    type,
    id: sanitizeText(id, 80),
    name: sanitizeText(name, 80),
    placeholder: sanitizeText(raw.placeholder, 80),
    readonly: Boolean(raw.readonly),
    disabled: Boolean(raw.disabled),
    required: Boolean(raw.required),
    autocomplete: sanitizeText(raw.autocomplete, 40),
    maxLength: Number.isFinite(raw.maxLength) ? raw.maxLength : null,
    stableClasses: sanitizeClassList(raw.stableClasses || raw.classList || []),
    labels: sanitizeStringArray(raw.labels, 80, 8),
    headings: sanitizeStringArray(raw.headings, 80, 6),
    fieldsetHeadings: sanitizeStringArray(raw.fieldsetHeadings, 80, 6),
    options: sanitizeStringArray(raw.options, 60, 20),
    fileContext: sanitizeStringArray(raw.fileContext, 80, 6),
    registrationButtonCandidate: Boolean(raw.registrationButtonCandidate)
  };
}

export function sanitizeInspectionResult(raw = {}) {
  const controls = Array.isArray(raw.controls)
    ? raw.controls.map(sanitizeControl).filter(Boolean)
    : [];

  return {
    timestamp: String(raw.timestamp || new Date().toISOString()),
    extension_version: sanitizeText(raw.extension_version || raw.extensionVersion || '', 40),
    url: String(raw.url || ''),
    path: String(raw.path || ''),
    title: sanitizeText(raw.title, 100),
    headings: sanitizeStringArray(raw.headings, 100, 20),
    frames: Array.isArray(raw.frames) ? raw.frames.map((frame) => ({
      frameId: Number.isInteger(frame.frameId) ? frame.frameId : 0,
      url: String(frame.url || ''),
      path: String(frame.path || ''),
      title: sanitizeText(frame.title, 100),
      inputCount: Math.max(0, Number(frame.inputCount || 0))
    })) : [],
    controls
  };
}
