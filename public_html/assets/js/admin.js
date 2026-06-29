(() => {
  'use strict';

  document.documentElement.classList.add('js');

  const drawer = document.querySelector('[data-schedule-drawer]');
  const drawerBackdrop = document.querySelector('[data-drawer-backdrop]');
  const form = document.querySelector('[data-schedule-form]');
  const repeatSelect = document.querySelector('[data-repeat-select]');
  const kindSelect = document.querySelector('[data-kind-select]');
  const filterPanel = document.querySelector('[data-filter-panel]');
  const filterToggle = document.querySelector('[data-toggle-filters]');
  const moreRoot = document.querySelector('[data-more-menu-root]');
  const moreButton = document.querySelector('[data-more-menu-button]');
  const moreMenu = document.querySelector('[data-more-menu]');
  const autoDateForm = document.querySelector('[data-auto-date-form]');
  const networkStatus = document.createElement('div');
  let drawerTrigger = null;

  networkStatus.className = 'network-status';
  networkStatus.setAttribute('role', 'status');
  networkStatus.hidden = true;
  document.body.prepend(networkStatus);

  const updateNetworkStatus = () => {
    if (navigator.onLine) {
      networkStatus.hidden = true;
      networkStatus.textContent = '';
      return;
    }

    networkStatus.hidden = false;
    networkStatus.textContent = '通信が切断されています。保存操作は通信復旧後に実行してください。';
  };

  window.addEventListener('online', updateNetworkStatus);
  window.addEventListener('offline', updateNetworkStatus);
  updateNetworkStatus();

  const setHidden = (elements, hidden) => {
    elements.forEach((element) => {
      element.hidden = hidden;
    });
  };

  const addMinutes = (time, minutes) => {
    const [hour, minute] = time.split(':').map((value) => Number.parseInt(value, 10));
    const total = Math.min((hour * 60) + minute + minutes, (23 * 60) + 59);
    return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
  };

  const updateDrawerFields = () => {
    if (!form || !repeatSelect || !kindSelect) return;

    const mode = repeatSelect.value;
    const kind = kindSelect.value;
    const isRange = mode !== 'single';
    const isBulk = mode === 'self_esthe_bulk';

    if (isBulk && kindSelect.value !== 'self_esthe') {
      kindSelect.value = 'self_esthe';
    }

    setHidden(form.querySelectorAll('[data-single-field]'), isRange);
    setHidden(form.querySelectorAll('[data-range-field]'), !isRange);
    setHidden(form.querySelectorAll('[data-bulk-field]'), !isBulk);
    setHidden(form.querySelectorAll('[data-normal-time]'), isBulk);
    setHidden(form.querySelectorAll('[data-closed-genre]'), kind !== 'closed');
  };

  const openDrawer = (button) => {
    if (!drawer || !form) return;

    drawerTrigger = button || document.activeElement;
    const date = button?.getAttribute('data-date');
    const time = button?.getAttribute('data-time');

    if (date) {
      const singleDate = form.querySelector('[name="single_date"]');
      const rangeStart = form.querySelector('[name="repeat_start_date"]');
      const rangeEnd = form.querySelector('[name="repeat_end_date"]');
      if (singleDate) singleDate.value = date;
      if (rangeStart) rangeStart.value = date;
      if (rangeEnd && rangeEnd.value < date) rangeEnd.value = date;
    }

    if (time) {
      const start = form.querySelector('[name="start_time"]');
      const end = form.querySelector('[name="end_time"]');
      if (start) start.value = time;
      if (end) end.value = addMinutes(time, 50);
    }

    drawer.hidden = false;
    drawer.classList.add('is-open');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.classList.add('has-schedule-drawer');

    if (drawerBackdrop) {
      drawerBackdrop.hidden = false;
      window.requestAnimationFrame(() => drawerBackdrop.classList.add('is-open'));
    }

    window.setTimeout(() => {
      drawer.querySelector('input:not([type="hidden"]), select, textarea, button')?.focus();
    }, 50);
  };

  const closeDrawer = () => {
    if (!drawer) return;

    drawer.classList.remove('is-open');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('has-schedule-drawer');

    if (drawerBackdrop) {
      drawerBackdrop.classList.remove('is-open');
      window.setTimeout(() => {
        drawerBackdrop.hidden = true;
      }, 180);
    }

    drawerTrigger?.focus?.();
  };

  document.querySelectorAll('[data-open-drawer]').forEach((button) => {
    button.addEventListener('click', () => openDrawer(button));
  });

  document.querySelectorAll('[data-close-drawer]').forEach((button) => {
    button.addEventListener('click', closeDrawer);
  });

  drawerBackdrop?.addEventListener('click', closeDrawer);
  repeatSelect?.addEventListener('change', updateDrawerFields);
  kindSelect?.addEventListener('change', updateDrawerFields);
  updateDrawerFields();

  filterToggle?.addEventListener('click', () => {
    if (!filterPanel) return;
    const willOpen = filterPanel.hidden;
    filterPanel.hidden = !willOpen;
    filterToggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    if (willOpen) {
      filterPanel.querySelector('select, input')?.focus();
    }
  });

  const closeMoreMenu = () => {
    if (!moreMenu || !moreButton) return;
    moreMenu.hidden = true;
    moreButton.setAttribute('aria-expanded', 'false');
  };

  moreButton?.addEventListener('click', (event) => {
    event.stopPropagation();
    if (!moreMenu) return;
    const willOpen = moreMenu.hidden;
    moreMenu.hidden = !willOpen;
    moreButton.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
  });

  document.addEventListener('click', (event) => {
    if (moreRoot && !moreRoot.contains(event.target)) {
      closeMoreMenu();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    if (drawer?.classList.contains('is-open')) {
      closeDrawer();
      return;
    }
    closeMoreMenu();
  });

  autoDateForm?.querySelector('input[type="date"]')?.addEventListener('change', () => {
    if (typeof autoDateForm.requestSubmit === 'function') {
      autoDateForm.requestSubmit();
    } else {
      autoDateForm.submit();
    }
  });

  const admissionSearch = document.querySelector('[data-admission-search]');
  const admissionStatusFilter = document.querySelector('[data-admission-status-filter]');
  const admissionSlimStatusFilter = document.querySelector('[data-admission-slim-status-filter]');
  const admissionItems = Array.from(document.querySelectorAll('[data-admission-item]'));
  const admissionEmpty = document.querySelector('[data-admission-empty]');
  const admissionVisibleCounts = Array.from(document.querySelectorAll('[data-admission-visible-count]'));

  const normalizeSearch = (value) => String(value || '').toLowerCase().replace(/\s+/g, '');
  const updateAdmissionList = () => {
    if (admissionItems.length === 0) return;

    const query = normalizeSearch(admissionSearch?.value || '');
    const status = admissionStatusFilter?.value || '';
    const slimStatus = admissionSlimStatusFilter?.value || '';
    let visibleCount = 0;

    admissionItems.forEach((item) => {
      const text = normalizeSearch(item.getAttribute('data-search') || '');
      const itemStatus = item.getAttribute('data-status') || '';
      const itemSlimStatus = item.getAttribute('data-slim-status') || '';
      const visible = (query === '' || text.includes(query))
        && (status === '' || itemStatus === status)
        && (slimStatus === '' || itemSlimStatus === slimStatus);
      item.hidden = !visible;
      if (visible) visibleCount += 1;
    });

    admissionVisibleCounts.forEach((target) => {
      target.textContent = String(visibleCount);
    });
    if (admissionEmpty) {
      admissionEmpty.hidden = visibleCount !== 0;
    }
  };

  admissionSearch?.addEventListener('input', updateAdmissionList);
  admissionStatusFilter?.addEventListener('change', updateAdmissionList);
  admissionSlimStatusFilter?.addEventListener('change', updateAdmissionList);
  updateAdmissionList();

  const detailTarget = document.getElementById('admissionDetail');
  if (detailTarget && window.matchMedia('(max-width: 860px)').matches && window.location.search.includes('id=')) {
    window.setTimeout(() => {
      detailTarget.scrollIntoView({ block: 'start' });
      detailTarget.focus({ preventScroll: true });
    }, 80);
  }

  document.querySelectorAll('[data-toggle-slim]').forEach((button) => {
    const targetId = button.getAttribute('aria-controls');
    const target = targetId ? document.getElementById(targetId) : null;
    if (!target) return;

    button.addEventListener('click', () => {
      const expanded = target.classList.toggle('is-expanded');
      button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      button.textContent = expanded ? '内容を閉じる' : '内容を表示';
    });
  });

  document.querySelectorAll('[data-copy-target]').forEach((button) => {
    button.addEventListener('click', () => {
      const targetId = button.getAttribute('data-copy-target');
      const target = targetId ? document.getElementById(targetId) : null;
      const status = button.parentElement?.querySelector('[data-copy-status]');
      if (!target) return;

      const reset = () => {
        if (status) status.textContent = '';
        button.textContent = 'コピー';
      };
      const done = () => {
        if (status) status.textContent = 'コピーしました';
        button.textContent = 'コピー済み';
        window.setTimeout(reset, 1600);
      };
      const fallback = () => {
        target.focus();
        target.select();
        try {
          document.execCommand('copy');
          done();
        } catch (error) {
          if (status) status.textContent = 'コピーできない場合は選択してコピーしてください';
        }
      };

      if (navigator.clipboard?.writeText) {
        navigator.clipboard.writeText(target.value).then(done).catch(fallback);
      } else {
        fallback();
      }
    });
  });

  document.querySelectorAll('[data-birth-card]').forEach((card) => {
    const button = card.querySelector('[data-birth-toggle]');
    const value = card.querySelector('.admin-birth-value');
    if (!button || !value) return;

    button.addEventListener('click', () => {
      const mode = card.getAttribute('data-mode') || 'seireki';
      if (mode === 'seireki') {
        value.textContent = card.getAttribute('data-wareki') || '未入力';
        card.setAttribute('data-mode', 'wareki');
        button.textContent = '西暦表示へ切替';
        return;
      }

      value.textContent = card.getAttribute('data-seireki') || '未入力';
      card.setAttribute('data-mode', 'seireki');
      button.textContent = '和暦表示へ切替';
    });
  });

  const updateCampaignCard = (card) => {
    const mode = card.querySelector('[data-campaign-mode]')?.value || 'amount';
    const autoApply = Boolean(card.querySelector('[data-campaign-auto]')?.checked);
    const codeInput = card.querySelector('[data-campaign-code-field] input');

    card.classList.toggle('is-amount-mode', mode === 'amount');
    card.classList.toggle('is-percent-mode', mode === 'percent');
    card.classList.toggle('is-target-mode', mode === 'target_total');
    card.classList.toggle('is-rules-mode', mode === 'rules');
    card.classList.toggle('is-auto-apply', autoApply);
    if (codeInput) {
      codeInput.disabled = autoApply;
    }

    const deleteInput = card.querySelector('[data-campaign-delete]');
    const deleteNote = card.querySelector('[data-campaign-delete-note]');
    const deletePending = Boolean(deleteInput?.checked);
    card.classList.toggle('is-delete-pending', deletePending);
    if (deleteNote) deleteNote.hidden = !deletePending;
  };

  document.querySelectorAll('[data-campaign-card]').forEach((card) => {
    updateCampaignCard(card);
    card.querySelectorAll('[data-campaign-mode], [data-campaign-auto], [data-campaign-delete]').forEach((input) => {
      input.addEventListener('change', () => updateCampaignCard(card));
    });
  });

  document.querySelectorAll('[data-dirty-form]').forEach((dirtyForm) => {
    let dirty = false;
    let submittingForm = false;
    const indicator = dirtyForm.querySelector('[data-dirty-indicator]');
    const markDirty = () => {
      if (submittingForm) return;
      dirty = true;
      if (indicator) indicator.hidden = false;
    };

    dirtyForm.addEventListener('input', markDirty);
    dirtyForm.addEventListener('change', markDirty);
    dirtyForm.addEventListener('submit', () => {
      submittingForm = true;
      dirty = false;
      if (indicator) indicator.hidden = true;
    });

    window.addEventListener('beforeunload', (event) => {
      if (!dirty || submittingForm) return;
      event.preventDefault();
      event.returnValue = '';
    });
  });

  document.querySelectorAll('[data-prune-empty-get]').forEach((targetForm) => {
    targetForm.addEventListener('submit', () => {
      targetForm.querySelectorAll('input[name], select[name], textarea[name]').forEach((field) => {
        if (!field.disabled && String(field.value || '').trim() === '') {
          field.disabled = true;
        }
      });
    });
  });

  document.querySelectorAll('form').forEach((targetForm) => {
    targetForm.addEventListener('submit', (event) => {
      const method = (targetForm.getAttribute('method') || 'get').toLowerCase();
      if (method !== 'post') {
        return;
      }

      if (!navigator.onLine) {
        event.preventDefault();
        updateNetworkStatus();
        return;
      }

      targetForm.querySelectorAll('button[type="submit"]').forEach((button) => {
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
      });
    });
  });
})();
