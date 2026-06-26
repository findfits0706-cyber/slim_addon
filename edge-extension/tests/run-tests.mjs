import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { detectSlimPage, PAGE_TYPES } from '../core/page-detector.js';
import { sanitizeInspectionResult } from '../core/inspection-sanitizer.js';
import { buildDryRunPlan } from '../core/dry-run.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const extensionRoot = path.resolve(__dirname, '..');

function testManifest() {
  const manifest = JSON.parse(fs.readFileSync(path.join(extensionRoot, 'manifest.json'), 'utf8'));
  assert.equal(manifest.manifest_version, 3, 'manifest v3');
  assert.ok(manifest.permissions.includes('sidePanel'), 'sidePanel permission');
  assert.ok(manifest.permissions.includes('scripting'), 'scripting permission');
  assert.ok(manifest.permissions.includes('storage'), 'storage permission');
  assert.ok(!manifest.permissions.includes('downloads'), 'downloads permission is not used in Prompt4');
  assert.ok(!JSON.stringify(manifest.host_permissions).includes('<all_urls>'), '<all_urls> is not used');
  assert.ok(manifest.host_permissions.includes('https://www.slim-sng.jp/*'), 'SLIM host permission');
  assert.ok(manifest.host_permissions.includes('https://findpilates.jp/*'), 'API host permission');
}

function testPageDetector() {
  const cases = [
    ['/slim/web/m/sng/login/', 'ログイン', PAGE_TYPES.LOGIN],
    ['/slim/web/m/sng/front/admission_procedure/', '入会受付', PAGE_TYPES.ADMISSION_PROCEDURE],
    ['/slim/web/m/sng/front/view_basic_user/', '会員情報', PAGE_TYPES.VIEW_BASIC_USER],
    ['/slim/web/m/sng/front/view_image_survey/', '画像アンケート', PAGE_TYPES.VIEW_IMAGE_SURVEY],
    ['/slim/web/m/sng/front/addition_notification/', '追加届', PAGE_TYPES.ADDITION_NOTIFICATION]
  ];

  for (const [path, title, pageType] of cases) {
    const result = detectSlimPage({
      url: 'https://www.slim-sng.jp' + path,
      title,
      headings: [title]
    });
    assert.equal(result.pageType, pageType, `${pageType} detected`);
  }

  assert.equal(
    detectSlimPage({
      url: 'https://www.slim-sng.jp/slim/web/m/sng/front/admission_procedure/',
      title: '追加届',
      headings: ['追加届']
    }).pageType,
    PAGE_TYPES.UNKNOWN,
    'URL and heading conflict becomes unknown'
  );

  assert.equal(
    detectSlimPage({ url: 'https://example.test/slim/web/m/sng/login/', title: 'ログイン' }).pageType,
    PAGE_TYPES.UNKNOWN,
    'non-SLIM origin is unknown'
  );
}

function testInspectionSanitizer() {
  const sanitized = sanitizeInspectionResult({
    url: 'https://www.slim-sng.jp/slim/web/m/sng/front/admission_procedure/',
    title: '入会受付',
    headings: ['入会受付', '090-1234-5678'],
    controls: [
      {
        tag: 'input',
        type: 'text',
        id: 'sei_name',
        name: 'sei_name',
        labels: ['姓'],
        classList: ['form-control', 'data-v-abcdef', 'css-a1b2c3d4'],
        value: '山田'
      },
      {
        tag: 'input',
        type: 'password',
        id: 'password',
        name: 'password',
        labels: ['password']
      },
      {
        tag: 'input',
        type: 'hidden',
        id: 'csrf_token',
        name: 'csrf_token'
      },
      {
        tag: 'input',
        type: 'file',
        id: 'identity_front',
        labels: ['公的身分証明書 表'],
        fileContext: ['公的身分証明書']
      },
      {
        tag: 'select',
        type: 'select',
        id: 'member_select',
        options: ['FP', '09012345678', 'sample@example.test']
      }
    ]
  });

  assert.equal(sanitized.controls.length, 3, 'password and hidden controls are removed');
  assert.equal(sanitized.controls[0].id, 'sei_name');
  assert.deepEqual(sanitized.controls[0].stableClasses, ['form-control'], 'data-v and hash classes are removed');
  assert.ok(!JSON.stringify(sanitized).includes('山田'), 'input value is not included');
  assert.ok(!JSON.stringify(sanitized).includes('09012345678'), 'phone-like option text is removed');
  assert.ok(!JSON.stringify(sanitized).includes('sample@example.test'), 'email-like option text is removed');
  assert.deepEqual(sanitized.controls[1].fileContext, ['公的身分証明書'], 'file input context is preserved');
}

function testDryRun() {
  const transfer = {
    readiness: { errors: [], warnings: [] },
    operations: [
      {
        id: 10,
        sequence_no: 1,
        operation_type: 'admission_procedure',
        page_type: 'admission_procedure',
        course_id: 151,
        course_code: 'FP',
        business_label: 'Find Pilates basic standalone',
        slim_option_texts: ['FP'],
        status: 'ready',
        readiness_errors: []
      }
    ]
  };
  const inspection = sanitizeInspectionResult({
    url: 'https://www.slim-sng.jp/slim/web/m/sng/front/admission_procedure/',
    title: '入会受付',
    headings: ['入会受付'],
    controls: [
      { tag: 'input', type: 'text', id: 'sei_name', name: 'sei_name', labels: ['姓'] },
      { tag: 'input', type: 'text', id: 'mei_name', name: 'mei_name', labels: ['名'] },
      { tag: 'input', type: 'text', id: 'kana_sei', name: 'kana_sei', labels: ['セイ'] },
      { tag: 'input', type: 'text', id: 'kana_mei', name: 'kana_mei', labels: ['メイ'] },
      { tag: 'input', type: 'text', id: 'birthday', name: 'birthday', labels: ['生年月日'] },
      { tag: 'input', type: 'text', id: 'entry_member_no', name: 'entry_member_no', labels: ['登録会員No.'] },
      { tag: 'select', type: 'select', id: 'course', name: 'course', labels: ['コース'], options: ['FP'] }
    ]
  });

  const plan = buildDryRunPlan({
    transfer,
    inspection,
    pageDetection: detectSlimPage({ url: inspection.url, title: inspection.title, headings: inspection.headings })
  });
  assert.equal(plan.status, 'warning', 'entry member number unresolved keeps dry-run warning');
  assert.ok(plan.items.some((item) => item.field === 'course' && item.status === 'ready'), 'course candidate matches once');

  const mismatch = buildDryRunPlan({
    transfer,
    inspection,
    pageDetection: { pageType: 'addition_notification' }
  });
  assert.equal(mismatch.status, 'blocked', 'page mismatch blocks dry-run');
  assert.ok(mismatch.blockers.includes('page_type_mismatch'));
}

testManifest();
testPageDetector();
testInspectionSanitizer();
testDryRun();

console.log('edge-extension tests: OK');
