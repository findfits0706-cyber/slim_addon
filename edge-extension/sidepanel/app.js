import { ExtensionApiClient, ApiError } from '../core/api-client.js';
import { ADMISSION_SCOPES, EXTENSION_VERSION, isSlimUrl } from '../core/config.js';
import { buildDryRunPlan } from '../core/dry-run.js';
import { detectSlimPage } from '../core/page-detector.js';
import { sanitizeInspectionResult } from '../core/inspection-sanitizer.js';
import {
  clearAuth,
  getAuth,
  getInstallationId,
  getSelectedApplicationId,
  getSettings,
  saveAuth,
  saveSelectedApplicationId,
  saveSettings
} from '../core/storage.js';

const app = document.getElementById('app');

const state = {
  settings: null,
  installationId: '',
  auth: null,
  me: null,
  selectedApplicationId: '',
  transfer: null,
  admissions: [],
  scope: 'unregistered',
  searchQuery: '',
  pageDetection: null,
  inspection: null,
  dryRun: null,
  message: '',
  messageType: '',
  loading: false,
  heartbeatTimer: null
};

function h(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

function compactDate(value) {
  if (!value) {
    return '-';
  }
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return value;
  }
  return date.toLocaleString('ja-JP', { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}

function apiClient() {
  return new ExtensionApiClient({
    baseUrl: state.settings?.apiBaseUrl,
    accessToken: state.auth?.accessToken || '',
    installationId: state.installationId
  });
}

function statusClass(status) {
  if (status === 'ready' || status === 'completed') {
    return 'ok';
  }
  if (status === 'blocked' || status === 'needs_review') {
    return 'error';
  }
  if (status === 'warning' || status === 'preparing' || status === 'in_progress') {
    return 'warning';
  }
  return '';
}

function setMessage(message, type = '') {
  state.message = message;
  state.messageType = type;
}

function renderMessage() {
  if (!state.message) {
    return '';
  }
  return `<div class="message ${h(state.messageType)}">${h(state.message)}</div>`;
}

function renderHeader() {
  const connected = Boolean(state.auth);
  return `
    <header class="panel-header">
      <div>
        <h1 class="panel-title">Find Pilates SLIM</h1>
        <p class="panel-subtitle">Prompt4 dry-run only / v${h(EXTENSION_VERSION)}</p>
      </div>
      <span class="status ${connected ? 'ok' : 'warning'}">${connected ? 'connected' : 'not paired'}</span>
    </header>
  `;
}

function renderDisconnected() {
  return `
    ${renderHeader()}
    ${renderMessage()}
    <section class="card">
      <h2 class="section-title">Edgeを接続</h2>
      <p class="note">管理画面で発行した5分有効のコードを入力します。tokenはsession storageだけに保存します。</p>
      <form data-action="pair" class="grid">
        <div class="field">
          <label for="apiBaseUrl">API base URL</label>
          <input id="apiBaseUrl" class="input" name="apiBaseUrl" value="${h(state.settings?.apiBaseUrl || '')}">
        </div>
        <div class="field">
          <label for="pairingCode">Pairing code</label>
          <input id="pairingCode" class="input" name="pairingCode" autocomplete="one-time-code" placeholder="ABCD-EFGH">
        </div>
        <button class="btn" type="submit" ${state.loading ? 'disabled' : ''}>接続</button>
      </form>
      <p class="meta">installation: ${h(state.installationId.slice(-8).padStart(state.installationId.length ? 11 : 1, '.'))}</p>
    </section>
  `;
}

function renderTabs() {
  return `
    <div class="tabs">
      ${ADMISSION_SCOPES.map((scope) => `
        <button class="tab ${state.scope === scope.key ? 'is-active' : ''}" type="button" data-action="scope" data-scope="${h(scope.key)}">${h(scope.label)}</button>
      `).join('')}
    </div>
    ${state.scope === 'search' ? `
      <form class="button-row" data-action="search">
        <input class="input" name="q" value="${h(state.searchQuery)}" placeholder="申込ID・氏名・電話で検索">
        <button class="btn secondary" type="submit">検索</button>
      </form>
    ` : ''}
  `;
}

function renderAdmissionList() {
  if (state.admissions.length === 0) {
    return '<div class="message">表示できる申込はありません。</div>';
  }

  return `
    <div class="list">
      ${state.admissions.map((item) => {
        const progress = item.operation_progress || {};
        return `
          <button class="admission-item" type="button" data-action="select" data-application-id="${h(item.application_id)}">
            <strong>${h(item.application_id)}</strong>
            <span class="meta">申込日時 ${h(compactDate(item.submitted_at))}</span>
            <span class="meta">SLIM ${h(item.slim_status)} / ${h(progress.completed ?? 0)} / ${h(progress.total ?? 0)}</span>
          </button>
        `;
      }).join('')}
    </div>
  `;
}

function renderConnectedList() {
  return `
    ${renderHeader()}
    ${renderMessage()}
    <section class="card">
      <div class="panel-header">
        <div>
          <h2 class="section-title">接続済み</h2>
          <p class="note">${h(state.me?.staff?.display_name || state.auth?.staff?.display_name || '')}</p>
        </div>
        <button class="btn secondary" type="button" data-action="logout">解除</button>
      </div>
      <p class="meta">token期限 ${h(compactDate(state.me?.expires_at || state.auth?.expiresAt || ''))}</p>
      ${renderTabs()}
    </section>
    <section class="card">
      <h2 class="section-title">申込一覧</h2>
      ${renderAdmissionList()}
    </section>
  `;
}

function readinessHtml() {
  const readiness = state.transfer?.readiness || { errors: [], warnings: [] };
  const errors = readiness.errors || [];
  const warnings = readiness.warnings || [];
  if (errors.length === 0 && warnings.length === 0) {
    return '<span class="status ok">readiness ok</span>';
  }
  return `
    <div class="grid">
      ${errors.length ? `<div class="message error">不足: ${h(errors.join(', '))}</div>` : ''}
      ${warnings.length ? `<div class="message warning">警告: ${h(warnings.join(', '))}</div>` : ''}
    </div>
  `;
}

function renderOperations() {
  const operations = state.transfer?.operations || [];
  if (!operations.length) {
    return '<div class="message warning">operationがありません。</div>';
  }
  return `
    <div class="operations">
      ${operations.map((operation) => `
        <article class="operation">
          <div class="operation-head">
            <strong>${h(operation.sequence_no)}. ${h(operation.course_code)} / ${h(operation.course_id)}</strong>
            <span class="status ${h(statusClass(operation.status))}">${h(operation.status)}</span>
          </div>
          <div class="meta">${h(operation.page_type)} / ${h(operation.business_label)}</div>
        </article>
      `).join('')}
    </div>
  `;
}

function renderPageState() {
  if (!state.pageDetection) {
    return '<div class="message">現在のSLIM画面は未解析です。</div>';
  }
  const type = state.pageDetection.pageType;
  if (type === 'login') {
    return '<div class="message warning">SLIMログイン画面を検知しました。拡張はパスワード入力を行いません。SLIM側で再ログインしてください。</div>';
  }
  return `
    <div class="analysis-summary">
      <span class="status ${h(statusClass(type === 'unknown' ? 'blocked' : 'ready'))}">${h(type)}</span>
      <p class="meta">${h(state.pageDetection.path || '')} / ${h(state.pageDetection.confidence || '')}</p>
    </div>
  `;
}

function renderInspection() {
  if (!state.inspection) {
    return '';
  }
  const data = {
    ...state.inspection,
    controls: state.inspection.controls?.slice(0, 40) || []
  };
  return `
    <section class="card">
      <h2 class="section-title">解析結果</h2>
      <div class="analysis-summary">
        <span class="meta">frames: ${h(state.inspection.frames?.length || 0)}</span>
        <span class="meta">controls: ${h(state.inspection.controls?.length || 0)}</span>
        <span class="meta">headings: ${h((state.inspection.headings || []).join(' / '))}</span>
      </div>
      <div class="button-row">
        <button class="btn secondary" type="button" data-action="copy-inspection">JSONコピー</button>
        <button class="btn secondary" type="button" data-action="save-inspection">JSON保存</button>
      </div>
      <pre class="json-box">${h(JSON.stringify(data, null, 2))}</pre>
    </section>
  `;
}

function renderDryRun() {
  if (!state.dryRun) {
    return '';
  }
  return `
    <section class="card">
      <div class="panel-header">
        <h2 class="section-title">dry-run</h2>
        <span class="status ${h(statusClass(state.dryRun.status))}">${h(state.dryRun.status)}</span>
      </div>
      ${state.dryRun.blockers?.length ? `<div class="message error">blocked: ${h(state.dryRun.blockers.join(', '))}</div>` : ''}
      ${state.dryRun.warnings?.length ? `<div class="message warning">warning: ${h(state.dryRun.warnings.join(', '))}</div>` : ''}
      <div class="dryrun-list">
        ${(state.dryRun.items || []).map((item) => `
          <div class="dryrun-item">
            <strong>${h(item.label)} <span class="status ${h(statusClass(item.status))}">${h(item.status)}</span></strong>
            <span>${h(item.message)}</span>
          </div>
        `).join('')}
      </div>
    </section>
  `;
}

function renderSelected() {
  const transfer = state.transfer;
  return `
    ${renderHeader()}
    ${renderMessage()}
    <section class="card">
      <div class="panel-header">
        <div>
          <h2 class="section-title">対象固定中</h2>
          <p class="note">${h(transfer.display_name || '')}</p>
        </div>
        <button class="btn secondary" type="button" data-action="change-target">対象を変更</button>
      </div>
      <dl class="grid">
        <div class="kv"><dt>申込ID</dt><dd>${h(transfer.application_id)}</dd></div>
        <div class="kv"><dt>登録方式</dt><dd>${h(transfer.workflow_label)}</dd></div>
        <div class="kv"><dt>実手続日</dt><dd>${h(transfer.actual_procedure_date || '-')}</dd></div>
        <div class="kv"><dt>利用開始日</dt><dd>${h(transfer.start_date || '-')}</dd></div>
        <div class="kv"><dt>進捗</dt><dd>${h(transfer.operation_progress?.completed ?? 0)} / ${h(transfer.operation_progress?.total ?? 0)}</dd></div>
      </dl>
      ${readinessHtml()}
    </section>
    <section class="card">
      <h2 class="section-title">現在のSLIM画面</h2>
      ${renderPageState()}
      <div class="button-row">
        <button class="btn" type="button" data-action="inspect">現在のSLIM画面を解析</button>
        <button class="btn secondary" type="button" data-action="dry-run" ${state.inspection ? '' : 'disabled'}>dry-run</button>
      </div>
    </section>
    <section class="card">
      <h2 class="section-title">Operations</h2>
      ${renderOperations()}
    </section>
    ${renderDryRun()}
    ${renderInspection()}
  `;
}

function render() {
  if (!state.settings) {
    app.innerHTML = '<div class="panel-loading">Loading...</div>';
    return;
  }
  if (!state.auth) {
    app.innerHTML = renderDisconnected();
    return;
  }
  if (state.transfer) {
    app.innerHTML = renderSelected();
    return;
  }
  app.innerHTML = renderConnectedList();
}

async function withLoading(task) {
  state.loading = true;
  render();
  try {
    await task();
  } catch (error) {
    await handleError(error);
  } finally {
    state.loading = false;
    render();
  }
}

async function handleError(error) {
  if (error instanceof ApiError && error.status === 401) {
    await clearSensitiveState();
    setMessage('API tokenが期限切れまたは失効しました。再ペアリングしてください。', 'error');
    return;
  }
  if (error instanceof ApiError && error.status === 409 && error.code === 'lock_conflict') {
    setMessage('別スタッフがロック中です。期限を確認してから再試行してください。', 'warning');
    return;
  }
  setMessage(error?.message || '処理に失敗しました。', 'error');
}

async function clearSensitiveState() {
  stopHeartbeat();
  await clearAuth();
  state.auth = null;
  state.me = null;
  state.selectedApplicationId = '';
  state.transfer = null;
  state.inspection = null;
  state.dryRun = null;
}

async function loadMeAndAdmissions() {
  state.me = await apiClient().me();
  await loadAdmissions();
}

async function loadAdmissions() {
  const response = await apiClient().admissions({
    scope: state.scope,
    q: state.scope === 'search' ? state.searchQuery : '',
    page: 1,
    limit: 25
  });
  state.admissions = response.items || [];
}

async function pair(form) {
  const formData = new FormData(form);
  const apiBaseUrl = String(formData.get('apiBaseUrl') || '');
  const pairingCode = String(formData.get('pairingCode') || '');
  await saveSettings({ apiBaseUrl });
  state.settings = await getSettings();
  const response = await apiClient().pair({
    pairingCode,
    installationId: state.installationId
  });
  state.auth = {
    accessToken: response.access_token,
    expiresAt: response.expires_at,
    staff: response.staff || {},
    installationId: state.installationId
  };
  await saveAuth(state.auth);
  setMessage('接続しました。', '');
  await loadMeAndAdmissions();
}

async function selectAdmission(applicationId) {
  const transfer = await apiClient().transfer(applicationId);
  await apiClient().lock(applicationId, transfer.version);
  state.selectedApplicationId = applicationId;
  state.transfer = await apiClient().transfer(applicationId);
  state.inspection = null;
  state.dryRun = null;
  await saveSelectedApplicationId(applicationId);
  startHeartbeat();
  setMessage('対象を固定しました。', '');
}

async function changeTarget() {
  const applicationId = state.transfer?.application_id;
  if (applicationId) {
    try {
      await apiClient().releaseLock(applicationId);
    } catch {
      // Expired locks are allowed to disappear silently.
    }
  }
  state.selectedApplicationId = '';
  state.transfer = null;
  state.inspection = null;
  state.pageDetection = null;
  state.dryRun = null;
  await saveSelectedApplicationId('');
  stopHeartbeat();
  await loadAdmissions();
}

function startHeartbeat() {
  stopHeartbeat();
  state.heartbeatTimer = setInterval(async () => {
    if (!state.transfer?.application_id || !state.auth) {
      return;
    }
    try {
      await apiClient().heartbeat(state.transfer.application_id);
    } catch (error) {
      handleError(error);
      render();
    }
  }, 4 * 60 * 1000);
}

function stopHeartbeat() {
  if (state.heartbeatTimer) {
    clearInterval(state.heartbeatTimer);
    state.heartbeatTimer = null;
  }
}

async function activeTab() {
  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
  return tab || null;
}

function aggregateInspection(results) {
  const frameResults = results
    .map((item) => ({ frameId: item.frameId, ...(item.result || {}) }))
    .filter((item) => item.url && isSlimUrl(item.url));
  const main = frameResults.find((item) => item.frameId === 0) || frameResults[0];
  if (!main) {
    return null;
  }
  const frames = frameResults.map((item) => ({
    frameId: item.frameId,
    url: item.url,
    path: item.path,
    title: item.title,
    inputCount: item.controls?.length || item.frame?.inputCount || 0
  }));
  const controls = frameResults.flatMap((item) =>
    (item.controls || []).map((control) => ({ ...control, frameId: item.frameId }))
  );
  return sanitizeInspectionResult({
    timestamp: new Date().toISOString(),
    extension_version: EXTENSION_VERSION,
    url: main.url,
    path: main.path,
    title: main.title,
    headings: main.headings,
    frames,
    controls
  });
}

async function inspectCurrentSlimPage() {
  const tab = await activeTab();
  if (!tab?.id || !isSlimUrl(tab.url || '')) {
    state.inspection = null;
    state.pageDetection = detectSlimPage({ url: tab?.url || '' });
    state.dryRun = null;
    setMessage('現在のアクティブタブはSLIMではありません。', 'warning');
    return;
  }

  let results = [];
  try {
    results = await chrome.scripting.executeScript({
      target: { tabId: tab.id, allFrames: true },
      files: ['content/inspect-page.js']
    });
  } catch {
    results = await chrome.scripting.executeScript({
      target: { tabId: tab.id },
      files: ['content/inspect-page.js']
    });
  }

  const inspection = aggregateInspection(results);
  if (!inspection) {
    state.inspection = null;
    state.pageDetection = detectSlimPage({ url: tab.url || '' });
    state.dryRun = null;
    setMessage('解析できるSLIMフレームが見つかりませんでした。', 'warning');
    return;
  }

  state.inspection = inspection;
  state.pageDetection = detectSlimPage({
    url: inspection.url,
    title: inspection.title,
    headings: inspection.headings
  });
  state.dryRun = null;
  setMessage('解析しました。valueやhidden tokenは収集していません。', '');
}

function runDryRun() {
  state.dryRun = buildDryRunPlan({
    transfer: state.transfer,
    inspection: state.inspection,
    pageDetection: state.pageDetection
  });
  setMessage('dry-runを作成しました。DOMへ値は書き込んでいません。', '');
}

async function copyInspection() {
  if (!state.inspection) {
    return;
  }
  await navigator.clipboard.writeText(JSON.stringify(state.inspection, null, 2));
  setMessage('解析JSONをクリップボードへコピーしました。', '');
}

function inspectionFilename() {
  const pageType = state.pageDetection?.pageType || 'unknown';
  const timestamp = new Date().toISOString().replace(/[-:]/g, '').replace(/\.\d+Z$/, 'Z');
  return `slim-inspection-${pageType}-${timestamp}.json`;
}

function saveInspection() {
  if (!state.inspection) {
    return;
  }
  const blob = new Blob([JSON.stringify(state.inspection, null, 2)], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = inspectionFilename();
  anchor.click();
  URL.revokeObjectURL(url);
  setMessage('解析JSONを保存しました。', '');
}

app.addEventListener('submit', (event) => {
  const form = event.target.closest('form');
  if (!form) {
    return;
  }
  event.preventDefault();
  const action = form.dataset.action;
  if (action === 'pair') {
    void withLoading(() => pair(form));
  }
  if (action === 'search') {
    state.searchQuery = String(new FormData(form).get('q') || '');
    void withLoading(loadAdmissions);
  }
});

app.addEventListener('click', (event) => {
  const button = event.target.closest('button[data-action]');
  if (!button) {
    return;
  }
  const action = button.dataset.action;
  if (action === 'scope') {
    state.scope = button.dataset.scope || 'unregistered';
    void withLoading(loadAdmissions);
  }
  if (action === 'select') {
    void withLoading(() => selectAdmission(button.dataset.applicationId || ''));
  }
  if (action === 'logout') {
    void withLoading(async () => {
      await clearSensitiveState();
      setMessage('接続を解除しました。', '');
    });
  }
  if (action === 'change-target') {
    void withLoading(changeTarget);
  }
  if (action === 'inspect') {
    void withLoading(inspectCurrentSlimPage);
  }
  if (action === 'dry-run') {
    runDryRun();
    render();
  }
  if (action === 'copy-inspection') {
    void withLoading(copyInspection);
  }
  if (action === 'save-inspection') {
    saveInspection();
    render();
  }
});

async function init() {
  state.settings = await getSettings();
  state.installationId = await getInstallationId();
  state.auth = await getAuth();
  state.selectedApplicationId = await getSelectedApplicationId();

  if (state.auth) {
    try {
      await loadMeAndAdmissions();
      if (state.selectedApplicationId) {
        state.transfer = await apiClient().transfer(state.selectedApplicationId);
        startHeartbeat();
      }
    } catch (error) {
      await handleError(error);
    }
  }
  render();
}

void init();
