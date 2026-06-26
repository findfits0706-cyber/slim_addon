(() => {
  const sensitiveKeyRe = /(password|passwd|token|csrf|session|cookie|secret|credential)/i;
  const emailRe = /[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i;
  const phoneRe = /(?:\+?\d{1,3}[-\s]?)?(?:0\d{1,4}[-\s]?\d{1,4}[-\s]?\d{3,4})/;
  const longNumberRe = /\d{6,}/;
  const buildHashRe = /(?:^|[-_])(?:[a-f0-9]{8,}|css-[a-z0-9]{6,}|v-[a-z0-9]{6,})(?:$|[-_])/i;

  function sanitizeText(value, maxLength = 80) {
    const text = String(value || '').normalize('NFKC').replace(/\s+/g, ' ').trim();
    if (!text || emailRe.test(text) || phoneRe.test(text) || longNumberRe.test(text)) {
      return '';
    }
    return text.length > maxLength ? text.slice(0, maxLength - 1) + '…' : text;
  }

  function unique(values, maxItems = 12) {
    return [...new Set(values.map((value) => sanitizeText(value)).filter(Boolean))].slice(0, maxItems);
  }

  function stableClasses(element) {
    return [...element.classList]
      .filter((name) => /^[A-Za-z0-9_-]{1,40}$/.test(name))
      .filter((name) => !name.startsWith('data-v-'))
      .filter((name) => !buildHashRe.test(name))
      .slice(0, 8);
  }

  function textFromSelector(selector, root = document) {
    return [...root.querySelectorAll(selector)].map((element) => sanitizeText(element.textContent, 100)).filter(Boolean);
  }

  function labelTexts(element) {
    const labels = [];
    const id = element.id || '';
    if (id) {
      labels.push(...[...document.querySelectorAll(`label[for="${CSS.escape(id)}"]`)].map((label) => label.textContent));
    }
    const closestLabel = element.closest('label');
    if (closestLabel) {
      labels.push(closestLabel.textContent);
    }
    const previous = element.previousElementSibling;
    if (previous && ['LABEL', 'SPAN', 'DIV', 'DT', 'TH'].includes(previous.tagName)) {
      labels.push(previous.textContent);
    }
    const parent = element.parentElement;
    if (parent) {
      const parentLabel = parent.querySelector(':scope > label, :scope > dt, :scope > th');
      if (parentLabel && parentLabel !== closestLabel) {
        labels.push(parentLabel.textContent);
      }
    }
    return unique(labels, 8);
  }

  function contextualHeadings(element) {
    const values = [];
    let current = element.parentElement;
    let depth = 0;
    while (current && depth < 4) {
      values.push(...textFromSelector(':scope > h1, :scope > h2, :scope > h3, :scope > h4, :scope > summary, :scope > legend', current));
      current = current.parentElement;
      depth += 1;
    }
    return unique(values, 8);
  }

  function fieldsetHeadings(element) {
    const values = [];
    const fieldset = element.closest('fieldset');
    if (fieldset) {
      values.push(...textFromSelector(':scope > legend', fieldset));
    }
    const details = element.closest('details');
    if (details) {
      values.push(...textFromSelector(':scope > summary', details));
    }
    const accordion = element.closest('[aria-expanded], [role="region"], .accordion, .collapse');
    if (accordion) {
      values.push(...textFromSelector(':scope h1, :scope h2, :scope h3, :scope h4', accordion));
    }
    return unique(values, 6);
  }

  function optionTexts(element) {
    if (element.tagName === 'SELECT') {
      return unique([...element.options].map((option) => option.textContent), 20);
    }
    const root = element.closest('[role="combobox"], [role="listbox"], .select, .dropdown') || document;
    return unique([...root.querySelectorAll('[role="option"], option')].map((option) => option.textContent), 20);
  }

  function isRegistrationButtonCandidate(element) {
    if (!['BUTTON', 'A', 'INPUT'].includes(element.tagName)) {
      return false;
    }
    const text = sanitizeText(element.textContent || element.getAttribute('aria-label') || element.getAttribute('title') || '', 40);
    return /(登録|保存|確定|submit|register|complete)/i.test(text);
  }

  function inspectControl(element) {
    const tag = element.tagName.toLowerCase();
    const type = tag === 'input' ? String(element.getAttribute('type') || 'text').toLowerCase() : tag;
    const id = String(element.getAttribute('id') || '');
    const name = String(element.getAttribute('name') || '');

    if (['hidden', 'password'].includes(type) || sensitiveKeyRe.test(id) || sensitiveKeyRe.test(name)) {
      return null;
    }

    return {
      tag,
      type,
      id: sanitizeText(id, 80),
      name: sanitizeText(name, 80),
      placeholder: sanitizeText(element.getAttribute('placeholder') || '', 80),
      readonly: element.hasAttribute('readonly') || element.getAttribute('aria-readonly') === 'true',
      disabled: element.disabled || element.getAttribute('aria-disabled') === 'true',
      required: element.required || element.getAttribute('aria-required') === 'true',
      autocomplete: sanitizeText(element.getAttribute('autocomplete') || '', 40),
      maxLength: element.maxLength && element.maxLength > 0 ? element.maxLength : null,
      stableClasses: stableClasses(element),
      labels: labelTexts(element),
      headings: contextualHeadings(element),
      fieldsetHeadings: fieldsetHeadings(element),
      options: optionTexts(element),
      fileContext: type === 'file' ? unique([...labelTexts(element), ...contextualHeadings(element)], 8) : [],
      registrationButtonCandidate: isRegistrationButtonCandidate(element)
    };
  }

  function inspect() {
    const controls = [...document.querySelectorAll('input, select, textarea, button')]
      .map(inspectControl)
      .filter(Boolean);

    const url = location.href;
    let path = '';
    try {
      path = new URL(url).pathname;
    } catch {
      path = location.pathname || '';
    }

    return {
      timestamp: new Date().toISOString(),
      url,
      path,
      title: sanitizeText(document.title, 100),
      headings: textFromSelector('h1, h2, h3').slice(0, 30),
      controls,
      frame: {
        url,
        path,
        title: sanitizeText(document.title, 100),
        inputCount: controls.length
      }
    };
  }

  return inspect();
})();
