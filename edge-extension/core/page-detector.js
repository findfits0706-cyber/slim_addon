import { SLIM_ORIGIN } from './config.js';

export const PAGE_TYPES = {
  LOGIN: 'login',
  ADMISSION_PROCEDURE: 'admission_procedure',
  VIEW_BASIC_USER: 'view_basic_user',
  VIEW_IMAGE_SURVEY: 'view_image_survey',
  ADDITION_NOTIFICATION: 'addition_notification',
  UNKNOWN: 'unknown'
};

export const PAGE_PROFILES = [
  {
    pageType: PAGE_TYPES.LOGIN,
    pathPrefix: '/slim/web/m/sng/login/',
    tokens: ['login', 'ログイン']
  },
  {
    pageType: PAGE_TYPES.ADMISSION_PROCEDURE,
    pathPrefix: '/slim/web/m/sng/front/admission_procedure/',
    tokens: ['admission', '入会', '受付']
  },
  {
    pageType: PAGE_TYPES.VIEW_BASIC_USER,
    pathPrefix: '/slim/web/m/sng/front/view_basic_user/',
    tokens: ['basic user', '会員情報', '基本情報']
  },
  {
    pageType: PAGE_TYPES.VIEW_IMAGE_SURVEY,
    pathPrefix: '/slim/web/m/sng/front/view_image_survey/',
    tokens: ['image survey', '画像', 'アンケート']
  },
  {
    pageType: PAGE_TYPES.ADDITION_NOTIFICATION,
    pathPrefix: '/slim/web/m/sng/front/addition_notification/',
    tokens: ['addition', '追加', '届']
  }
];

export function normalizeText(value) {
  return String(value || '')
    .normalize('NFKC')
    .toLowerCase()
    .replace(/\s+/g, ' ')
    .trim();
}

function parseUrl(url) {
  try {
    return new URL(url);
  } catch {
    return null;
  }
}

function headingCorpus(input) {
  return normalizeText([
    input.title || '',
    ...(Array.isArray(input.headings) ? input.headings : [])
  ].join(' '));
}

function profileForPath(pathname) {
  return PAGE_PROFILES.find((profile) => pathname.startsWith(profile.pathPrefix)) || null;
}

function profilesForText(corpus) {
  if (!corpus) {
    return [];
  }

  return PAGE_PROFILES.filter((profile) =>
    profile.tokens.some((token) => corpus.includes(normalizeText(token)))
  );
}

export function detectSlimPage(input = {}) {
  const parsed = parseUrl(input.url || '');
  if (!parsed || parsed.origin !== SLIM_ORIGIN) {
    return {
      pageType: PAGE_TYPES.UNKNOWN,
      path: parsed?.pathname || '',
      confidence: 'none',
      reasons: ['not_slim_origin']
    };
  }

  const pathProfile = profileForPath(parsed.pathname);
  const corpus = headingCorpus(input);
  const textProfiles = profilesForText(corpus);

  if (!pathProfile) {
    const loginByText = textProfiles.find((profile) => profile.pageType === PAGE_TYPES.LOGIN);
    return {
      pageType: loginByText ? PAGE_TYPES.LOGIN : PAGE_TYPES.UNKNOWN,
      path: parsed.pathname,
      confidence: loginByText ? 'heading' : 'none',
      reasons: loginByText ? ['login_heading_without_known_path'] : ['unknown_path']
    };
  }

  const contradictions = textProfiles.filter((profile) => profile.pageType !== pathProfile.pageType);
  if (contradictions.length > 0) {
    return {
      pageType: PAGE_TYPES.UNKNOWN,
      path: parsed.pathname,
      confidence: 'conflict',
      reasons: ['path_heading_conflict'],
      expectedPageType: pathProfile.pageType,
      conflictingPageTypes: contradictions.map((profile) => profile.pageType)
    };
  }

  return {
    pageType: pathProfile.pageType,
    path: parsed.pathname,
    confidence: textProfiles.length > 0 ? 'path_and_heading' : 'path',
    reasons: textProfiles.length > 0 ? ['path_and_heading_match'] : ['path_match_heading_unavailable']
  };
}
