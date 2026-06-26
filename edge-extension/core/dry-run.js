import { FIELD_STATUS, mappingForPage } from '../mappings/slim-fields.js';

function currentOperation(transfer) {
  const operations = Array.isArray(transfer?.operations) ? transfer.operations : [];
  return operations.find((operation) => operation.status !== 'completed') || operations[0] || null;
}

function allControls(inspection) {
  return Array.isArray(inspection?.controls) ? inspection.controls : [];
}

function controlById(inspection, id) {
  return allControls(inspection).find((control) => control.id === id) || null;
}

function controlByKeywords(inspection, keywords) {
  const terms = keywords.map((keyword) => String(keyword).toLowerCase());
  return allControls(inspection).find((control) => {
    const haystack = [
      control.id,
      control.name,
      control.placeholder,
      ...(control.labels || []),
      ...(control.headings || []),
      ...(control.fieldsetHeadings || []),
      ...(control.options || [])
    ].join(' ').toLowerCase();
    return terms.some((term) => haystack.includes(term));
  }) || null;
}

function optionMatches(control, expectedTexts) {
  if (!control || !Array.isArray(control.options) || !Array.isArray(expectedTexts)) {
    return 0;
  }
  return control.options.filter((option) => expectedTexts.includes(option)).length;
}

function resultItem(field, status, message, extra = {}) {
  return {
    field: field.key,
    label: field.label,
    status,
    message,
    ...extra
  };
}

export function buildDryRunPlan({ transfer, inspection, pageDetection }) {
  const operation = currentOperation(transfer);
  const readiness = transfer?.readiness || { errors: [], warnings: [] };
  const items = [];
  const blockers = [];
  const warnings = [];

  if (!transfer) {
    return {
      status: FIELD_STATUS.BLOCKED,
      blockers: ['admission_not_selected'],
      warnings: [],
      operation: null,
      items: []
    };
  }

  if (!operation) {
    return {
      status: FIELD_STATUS.BLOCKED,
      blockers: ['operation_missing'],
      warnings: [],
      operation: null,
      items: []
    };
  }

  if (!inspection || !pageDetection || pageDetection.pageType === 'unknown') {
    blockers.push('slim_page_unknown');
  } else if (pageDetection.pageType !== operation.page_type) {
    blockers.push('page_type_mismatch');
  }

  for (const code of readiness.errors || []) {
    blockers.push(code);
  }
  for (const code of readiness.warnings || []) {
    warnings.push(code);
  }
  for (const code of operation.readiness_errors || []) {
    blockers.push(code);
  }

  const mapping = mappingForPage(operation.page_type);
  for (const field of mapping.stableIdFields) {
    const control = controlById(inspection, field.selectorId);
    if (field.unresolvedRule) {
      const status = control ? FIELD_STATUS.WARNING : FIELD_STATUS.BLOCKED;
      const message = control ? '欄は検出しましたが、入力ルール未確定です。' : '欄を検出できません。';
      items.push(resultItem(field, status, message, { selector: '#' + field.selectorId }));
      (status === FIELD_STATUS.WARNING ? warnings : blockers).push(field.key + '_' + status);
      continue;
    }

    if (!control) {
      items.push(resultItem(field, FIELD_STATUS.BLOCKED, '対象欄を検出できません。', { selector: '#' + field.selectorId }));
      blockers.push(field.key + '_missing_target');
      continue;
    }
    if (control.disabled || control.readonly) {
      items.push(resultItem(field, FIELD_STATUS.BLOCKED, '対象欄はreadonlyまたはdisabledです。', { selector: '#' + field.selectorId }));
      blockers.push(field.key + '_readonly_target');
      continue;
    }
    items.push(resultItem(field, FIELD_STATUS.READY, '対象欄検出', { selector: '#' + field.selectorId }));
  }

  for (const field of mapping.labelFields) {
    const control = controlByKeywords(inspection, field.keywords);
    if (!control) {
      items.push(resultItem(field, FIELD_STATUS.WARNING, 'ラベルまたは候補欄を検出できません。実画面解析で確認が必要です。'));
      warnings.push(field.key + '_unverified_target');
      continue;
    }

    if (field.key === 'course') {
      const matches = optionMatches(control, operation.slim_option_texts || []);
      if (matches === 1) {
        items.push(resultItem(field, FIELD_STATUS.READY, 'コース候補一致1件'));
      } else {
        const status = matches === 0 ? FIELD_STATUS.WARNING : FIELD_STATUS.BLOCKED;
        items.push(resultItem(field, status, matches === 0 ? 'コース候補は未検証です。' : 'コース候補が複数一致しています。'));
        (status === FIELD_STATUS.WARNING ? warnings : blockers).push(field.key + '_candidate_' + matches);
      }
      continue;
    }

    items.push(resultItem(field, FIELD_STATUS.WARNING, '候補欄は検出しました。次工程で入力アダプタ検証が必要です。'));
    warnings.push(field.key + '_adapter_unverified');
  }

  const uniqueBlockers = [...new Set(blockers)];
  const uniqueWarnings = [...new Set(warnings)];
  const status = uniqueBlockers.length > 0
    ? FIELD_STATUS.BLOCKED
    : (uniqueWarnings.length > 0 ? FIELD_STATUS.WARNING : FIELD_STATUS.READY);

  return {
    status,
    blockers: uniqueBlockers,
    warnings: uniqueWarnings,
    operation: {
      id: operation.id,
      sequence_no: operation.sequence_no,
      operation_type: operation.operation_type,
      page_type: operation.page_type,
      course_id: operation.course_id,
      course_code: operation.course_code,
      business_label: operation.business_label
    },
    items
  };
}
