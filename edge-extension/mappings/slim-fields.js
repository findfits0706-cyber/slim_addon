export const FIELD_STATUS = {
  READY: 'ready',
  WARNING: 'warning',
  BLOCKED: 'blocked'
};

export const ADMISSION_PROCEDURE_FIELDS = [
  { key: 'surname', label: '姓', selectorId: 'sei_name', required: true },
  { key: 'given_name', label: '名', selectorId: 'mei_name', required: true },
  { key: 'surname_kana', label: 'セイ', selectorId: 'kana_sei', required: true },
  { key: 'given_name_kana', label: 'メイ', selectorId: 'kana_mei', required: true },
  { key: 'birth', label: '生年月日', selectorId: 'birthday', required: true },
  { key: 'entry_member_no', label: '登録会員No.', selectorId: 'entry_member_no', required: false, unresolvedRule: true }
];

export const ADMISSION_PROCEDURE_LABEL_FIELDS = [
  { key: 'gender', label: '性別', keywords: ['性別', 'gender', 'sex'] },
  { key: 'actual_procedure_date', label: '実手続日', keywords: ['入会日', '手続日', '申込日'] },
  { key: 'start_date', label: '利用開始日', keywords: ['利用開始', '開始日'] },
  { key: 'course', label: 'コース', keywords: ['コース', 'course'] },
  { key: 'payment_cycle', label: '支払サイクル', keywords: ['支払', 'サイクル', '月払'] }
];

export const ADDITION_NOTIFICATION_FIELDS = [
  { key: 'actual_procedure_date', label: '申込日', keywords: ['申込日', '手続日'] },
  { key: 'reason', label: '理由', keywords: ['理由', 'reason', '9999'] },
  { key: 'start_date', label: '利用開始日', keywords: ['利用開始', '開始日'] },
  { key: 'course', label: 'コース', keywords: ['コース', 'course'] },
  { key: 'payment_cycle', label: '支払サイクル', keywords: ['支払', 'サイクル', '月払'] },
  { key: 'member_context', label: '対象会員', keywords: ['会員', 'member'] }
];

export function mappingForPage(pageType) {
  if (pageType === 'admission_procedure') {
    return {
      stableIdFields: ADMISSION_PROCEDURE_FIELDS,
      labelFields: ADMISSION_PROCEDURE_LABEL_FIELDS
    };
  }

  if (pageType === 'addition_notification') {
    return {
      stableIdFields: [],
      labelFields: ADDITION_NOTIFICATION_FIELDS
    };
  }

  return {
    stableIdFields: [],
    labelFields: []
  };
}
