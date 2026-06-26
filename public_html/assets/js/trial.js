(function () {
  var dateCards = toArray(document.querySelectorAll('[data-date-card]'));
  var menuCards = toArray(document.querySelectorAll('[data-genre-card]'));
  var slotCards = toArray(document.querySelectorAll('.slot-card'));
  var stepPanels = toArray(document.querySelectorAll('[data-step-panel]'));
  var emptyMessage = document.getElementById('emptyMessage');
  var selectedDateText = document.getElementById('selectedDateText');
  var trialHistoryLabel = document.getElementById('trialHistoryLabel');
  var trialHistorySelect = document.getElementById('trial_history');
  var progressSteps = toArray(document.querySelectorAll('[data-progress-step]'));
  var stepOneNext = document.getElementById('stepOneNext');
  var stepTwoNext = document.getElementById('stepTwoNext');
  var stepOneSummary = document.getElementById('stepOneSummary');
  var selectedSummaryDate = document.getElementById('selectedSummaryDate');
  var selectedSummaryMeta = document.getElementById('selectedSummaryMeta');
  var confirmSummaryDate = document.getElementById('confirmSummaryDate');
  var confirmSummaryMeta = document.getElementById('confirmSummaryMeta');
  var backButtons = toArray(document.querySelectorAll('[data-step-back]'));
  var customerTitle = document.getElementById('customer-title');
  var noticeTitle = document.getElementById('notice-title');
  var calendarMonths = toArray(document.querySelectorAll('[data-calendar-month]'));
  var calendarStrip = document.querySelector('[data-calendar-strip]');
  var calendarPrev = document.querySelector('[data-calendar-prev]');
  var calendarNext = document.querySelector('[data-calendar-next]');
  var calendarCurrentMonth = document.getElementById('calendarCurrentMonth');
  var memberGuideToggle = document.querySelector('[data-member-guide-toggle]');
  var memberGuidePanel = document.querySelector('[data-member-guide-panel]');
  var requiredFieldIds = ['customer_name', 'customer_kana', 'phone', 'email', 'contact_method', 'trial_history'];
  var customerRequiredFields = [];
  var selectedDate = '';
  var selectedGenre = '';
  var currentCalendarMonth = 0;
  var i;

  for (i = 0; i < requiredFieldIds.length; i += 1) {
    var field = document.getElementById(requiredFieldIds[i]);
    if (field) {
      customerRequiredFields.push(field);
    }
  }

  function toArray(list) {
    return Array.prototype.slice.call(list || []);
  }

  function addEvent(element, type, handler) {
    if (!element) {
      return;
    }

    if (element.addEventListener) {
      element.addEventListener(type, handler, false);
    } else if (element.attachEvent) {
      element.attachEvent('on' + type, handler);
    }
  }

  function getData(element, name) {
    if (!element) {
      return '';
    }

    return element.getAttribute('data-' + name) || '';
  }

  function trimText(value) {
    return String(value || '').replace(/^\s+|\s+$/g, '');
  }

  function textOf(element) {
    if (!element) {
      return '';
    }

    return trimText(element.textContent || element.innerText || '');
  }

  function hasClass(element, className) {
    if (!element) {
      return false;
    }

    if (element.classList) {
      return element.classList.contains(className);
    }

    return (' ' + element.className + ' ').indexOf(' ' + className + ' ') !== -1;
  }

  function addClass(element, className) {
    if (!element || hasClass(element, className)) {
      return;
    }

    if (element.classList) {
      element.classList.add(className);
    } else {
      element.className = trimText(element.className + ' ' + className);
    }
  }

  function removeClass(element, className) {
    if (!element) {
      return;
    }

    if (element.classList) {
      element.classList.remove(className);
    } else {
      element.className = trimText((' ' + element.className + ' ').replace(' ' + className + ' ', ' '));
    }
  }

  function toggleClass(element, className, enabled) {
    if (enabled) {
      addClass(element, className);
    } else {
      removeClass(element, className);
    }
  }

  function setHidden(element, hidden, displayValue) {
    if (!element) {
      return;
    }

    if (hidden) {
      element.setAttribute('hidden', 'hidden');
      element.style.display = 'none';
    } else {
      element.removeAttribute('hidden');
      element.style.display = displayValue || '';
    }
  }

  function indexOfValue(values, value) {
    var index;

    for (index = 0; index < values.length; index += 1) {
      if (values[index] === value) {
        return index;
      }
    }

    return -1;
  }

  function closestByClass(element, className) {
    var current = element;

    while (current && current.nodeType === 1) {
      if (hasClass(current, className)) {
        return current;
      }
      current = current.parentNode;
    }

    return null;
  }

  function firstByClass(root, className) {
    if (!root || !root.getElementsByClassName) {
      return null;
    }

    return root.getElementsByClassName(className)[0] || null;
  }

  function safeScrollTo(element) {
    var raf;

    if (!element) {
      return;
    }

    raf = window.requestAnimationFrame || function (callback) {
      window.setTimeout(callback, 16);
    };

    raf(function () {
      try {
        element.scrollIntoView({ behavior: 'smooth', block: 'start' });
      } catch (error) {
        element.scrollIntoView(true);
      }
    });
  }

  function setTransform(element, value) {
    if (!element) {
      return;
    }

    element.style.webkitTransform = value;
    element.style.msTransform = value;
    element.style.transform = value;
  }

  function findCalendarMonthIndexByDate(date) {
    var index;

    for (index = 0; index < dateCards.length; index += 1) {
      if (getData(dateCards[index], 'date-card') === date) {
        var month = closestByClass(dateCards[index], 'calendar-month');
        var monthIndex;
        for (monthIndex = 0; monthIndex < calendarMonths.length; monthIndex += 1) {
          if (calendarMonths[monthIndex] === month) {
            return monthIndex;
          }
        }
      }
    }

    return 0;
  }

  function updateCalendarSlider(nextIndex) {
    var index;
    var monthLabel;

    if (calendarMonths.length === 0) {
      return;
    }

    if (nextIndex < 0) {
      nextIndex = 0;
    }
    if (nextIndex >= calendarMonths.length) {
      nextIndex = calendarMonths.length - 1;
    }

    currentCalendarMonth = nextIndex;
    setTransform(calendarStrip, 'translateX(-' + (currentCalendarMonth * 100) + '%)');

    for (index = 0; index < calendarMonths.length; index += 1) {
      toggleClass(calendarMonths[index], 'is-calendar-active', index === currentCalendarMonth);
      calendarMonths[index].setAttribute('aria-hidden', index === currentCalendarMonth ? 'false' : 'true');
    }

    if (calendarPrev) {
      calendarPrev.disabled = currentCalendarMonth === 0;
    }
    if (calendarNext) {
      calendarNext.disabled = currentCalendarMonth >= calendarMonths.length - 1;
    }
    if (calendarCurrentMonth) {
      monthLabel = getData(calendarMonths[currentCalendarMonth], 'month-label') || textOf(firstByClass(calendarMonths[currentCalendarMonth], 'calendar-month-title'));
      calendarCurrentMonth.textContent = monthLabel;
    }
  }

  function clearSelectedSlots() {
    var index;

    for (index = 0; index < slotCards.length; index += 1) {
      var slotCard = slotCards[index];
      var input = slotCard.querySelector('input[type="radio"]');
      removeClass(slotCard, 'is-active');
      if (input) {
        input.checked = false;
      }
    }
  }

  function availableGenresForDate(date) {
    var genres = [];
    var index;

    for (index = 0; index < slotCards.length; index += 1) {
      var slotCard = slotCards[index];
      var genre = getData(slotCard, 'genre');
      if (getData(slotCard, 'date') === date && indexOfValue(genres, genre) === -1) {
        genres.push(genre);
      }
    }

    return genres;
  }

  function getSelectedSlotCard() {
    var index;

    for (index = 0; index < slotCards.length; index += 1) {
      var radio = slotCards[index].querySelector('input[type="radio"]');
      if (radio && radio.checked) {
        return slotCards[index];
      }
    }

    return null;
  }

  function getSelectedDateSummary() {
    var activeCard = null;
    var index;

    for (index = 0; index < dateCards.length; index += 1) {
      if (getData(dateCards[index], 'date-card') === selectedDate) {
        activeCard = dateCards[index];
        break;
      }
    }

    if (!activeCard) {
      return '';
    }

    var day = textOf(firstByClass(activeCard, 'calendar-date-day'));
    var monthNode = closestByClass(activeCard, 'calendar-month');
    var month = textOf(firstByClass(monthNode, 'calendar-month-title'));
    return trimText(month + (day ? ' ' + day + '日' : ''));
  }

  function setText(element, value) {
    if (element) {
      element.textContent = value;
    }
  }

  function updateSelectedSummaries() {
    var selectedSlot = getSelectedSlotCard();
    var summaryDateText;
    var time;
    var meta;

    if (!selectedSlot) {
      setText(stepOneSummary, '日時を選択すると次へ進めます。');
      setText(selectedSummaryDate, '未選択');
      setText(selectedSummaryMeta, '日時を選択してください');
      setText(confirmSummaryDate, '未選択');
      setText(confirmSummaryMeta, '日時を選択してください');
      return;
    }

    time = textOf(selectedSlot.querySelector('.slot-date'));
    meta = textOf(selectedSlot.querySelector('.slot-meta'));
    summaryDateText = getSelectedDateSummary();

    setText(stepOneSummary, summaryDateText + ' / ' + time);
    setText(selectedSummaryDate, trimText(summaryDateText + ' ' + time));
    setText(selectedSummaryMeta, meta);
    setText(confirmSummaryDate, trimText(summaryDateText + ' ' + time));
    setText(confirmSummaryMeta, meta);
  }

  function updateGenreCards() {
    var availableGenres = availableGenresForDate(selectedDate);
    var index;

    if (indexOfValue(availableGenres, selectedGenre) === -1) {
      selectedGenre = indexOfValue(availableGenres, 'pilates') !== -1 ? 'pilates' : (availableGenres[0] || '');
    }

    for (index = 0; index < menuCards.length; index += 1) {
      var card = menuCards[index];
      var genre = getData(card, 'genre-card');
      var isVisible = indexOfValue(availableGenres, genre) !== -1;
      var isActive = genre === selectedGenre;
      var input = card.querySelector('input[type="radio"]');

      setHidden(card, !isVisible, 'block');
      toggleClass(card, 'is-active', isActive);

      if (input) {
        input.checked = isActive;
        input.disabled = !isVisible;
      }
    }
  }

  function appendOption(select, value, label) {
    var option = document.createElement('option');
    option.value = value;
    option.appendChild(document.createTextNode(label));
    select.appendChild(option);
  }

  function updateTrialHistoryOptions() {
    var optionSets;
    var selectedValue;
    var options;
    var hasSelectedValue = false;
    var index;

    if (!trialHistoryLabel || !trialHistorySelect) {
      return;
    }

    optionSets = {
      pilates: [
        ['', '選択してください'],
        ['初めて', 'マシンピラティス体験は初めて'],
        ['過去にマシンピラティス体験を利用したことがある', '過去にマシンピラティス体験を利用したことがある']
      ],
      self_esthe: [
        ['', '選択してください'],
        ['初めて', 'セルフエステ体験は初めて'],
        ['過去にセルフエステ体験を利用したことがある', '過去にセルフエステ体験を利用したことがある']
      ],
      visit: [
        ['', '選択してください'],
        ['初めて', '施設見学は初めて'],
        ['過去に施設見学を利用したことがある', '過去に施設見学を利用したことがある']
      ]
    };

    selectedValue = trialHistorySelect.value;
    options = optionSets[selectedGenre] || optionSets.pilates;

    for (index = 0; index < options.length; index += 1) {
      if (options[index][0] === selectedValue) {
        hasSelectedValue = true;
      }
    }

    trialHistoryLabel.innerHTML = '体験歴<span class="required">必須</span>';
    while (trialHistorySelect.firstChild) {
      trialHistorySelect.removeChild(trialHistorySelect.firstChild);
    }

    for (index = 0; index < options.length; index += 1) {
      appendOption(trialHistorySelect, options[index][0], options[index][1]);
    }

    trialHistorySelect.value = hasSelectedValue ? selectedValue : '';
  }

  function updateDateCards() {
    var index;

    for (index = 0; index < dateCards.length; index += 1) {
      var card = dateCards[index];
      var isActive = getData(card, 'date-card') === selectedDate;
      toggleClass(card, 'is-active', isActive);
      card.setAttribute('aria-pressed', isActive ? 'true' : 'false');
    }

    if (selectedDateText) {
      var summary = getSelectedDateSummary();
      selectedDateText.textContent = summary ? summary + ' の受付内容と時間をお選びください。' : 'ご希望の日付を選択してください。';
    }
  }

  function updateSlotCards() {
    var visibleCount = 0;
    var index;

    for (index = 0; index < slotCards.length; index += 1) {
      var slotCard = slotCards[index];
      var isVisible = getData(slotCard, 'date') === selectedDate && getData(slotCard, 'genre') === selectedGenre;
      setHidden(slotCard, !isVisible, 'flex');
      if (isVisible) {
        visibleCount += 1;
      }
    }

    setHidden(emptyMessage, visibleCount !== 0, 'block');
    clearSelectedSlots();

    if (stepOneNext) {
      stepOneNext.disabled = true;
    }

    updateSelectedSummaries();
  }

  function isCustomerStepComplete() {
    var index;

    for (index = 0; index < customerRequiredFields.length; index += 1) {
      if (trimText(customerRequiredFields[index].value) === '') {
        return false;
      }
    }

    return true;
  }

  function setCurrentStep(step) {
    var index;
    var target;

    for (index = 0; index < stepPanels.length; index += 1) {
      toggleClass(stepPanels[index], 'is-active', Number(getData(stepPanels[index], 'step-panel')) === step);
    }

    for (index = 0; index < progressSteps.length; index += 1) {
      var itemStep = Number(getData(progressSteps[index], 'progress-step'));
      toggleClass(progressSteps[index], 'is-active', itemStep === step);
      toggleClass(progressSteps[index], 'is-done', itemStep < step);
    }

    target = step === 2 ? customerTitle : (step === 3 ? noticeTitle : null);
    safeScrollTo(target);
  }

  function applySelection() {
    updateDateCards();
    updateGenreCards();
    updateTrialHistoryOptions();
    updateSlotCards();
  }

  for (i = 0; i < dateCards.length; i += 1) {
    addEvent(dateCards[i], 'click', (function (card) {
      return function () {
        selectedDate = getData(card, 'date-card');
        updateCalendarSlider(findCalendarMonthIndexByDate(selectedDate));
        applySelection();
      };
    }(dateCards[i])));
  }

  addEvent(calendarPrev, 'click', function () {
    updateCalendarSlider(currentCalendarMonth - 1);
  });

  addEvent(calendarNext, 'click', function () {
    updateCalendarSlider(currentCalendarMonth + 1);
  });

  for (i = 0; i < menuCards.length; i += 1) {
    addEvent(menuCards[i], 'click', (function (card) {
      return function () {
        if (card.getAttribute('hidden') === 'hidden' || card.style.display === 'none') {
          return;
        }

        selectedGenre = getData(card, 'genre-card');
        updateGenreCards();
        updateTrialHistoryOptions();
        updateSlotCards();
      };
    }(menuCards[i])));
  }

  for (i = 0; i < slotCards.length; i += 1) {
    addEvent(slotCards[i], 'click', (function (slotCard) {
      return function (event) {
        var e = event || window.event;
        var radio;
        var index;

        if (hasClass(slotCard, 'is-full') || slotCard.getAttribute('hidden') === 'hidden' || slotCard.style.display === 'none') {
          if (e && e.preventDefault) {
            e.preventDefault();
          } else if (e) {
            e.returnValue = false;
          }
          return false;
        }

        for (index = 0; index < slotCards.length; index += 1) {
          removeClass(slotCards[index], 'is-active');
        }

        addClass(slotCard, 'is-active');
        radio = slotCard.querySelector('input[type="radio"]');
        if (radio && !radio.disabled) {
          radio.checked = true;
        }

        if (stepOneNext) {
          stepOneNext.disabled = false;
        }
        updateSelectedSummaries();
        return true;
      };
    }(slotCards[i])));
  }

  function updateCustomerNextButton() {
    if (stepTwoNext) {
      stepTwoNext.disabled = !isCustomerStepComplete();
    }
  }

  for (i = 0; i < customerRequiredFields.length; i += 1) {
    addEvent(customerRequiredFields[i], 'input', updateCustomerNextButton);
    addEvent(customerRequiredFields[i], 'change', updateCustomerNextButton);
  }

  addEvent(stepOneNext, 'click', function () {
    if (!stepOneNext.disabled) {
      setCurrentStep(2);
    }
  });

  addEvent(stepTwoNext, 'click', function () {
    if (!stepTwoNext.disabled) {
      setCurrentStep(3);
    }
  });

  for (i = 0; i < backButtons.length; i += 1) {
    addEvent(backButtons[i], 'click', (function (button) {
      return function () {
        var step = Number(getData(button, 'step-back'));
        if (step >= 1) {
          setCurrentStep(step);
        }
      };
    }(backButtons[i])));
  }

  addEvent(memberGuideToggle, 'click', function () {
    var isExpanded = memberGuideToggle.getAttribute('aria-expanded') === 'true';
    memberGuideToggle.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
    setHidden(memberGuidePanel, isExpanded, 'block');
  });

  for (i = 0; i < dateCards.length; i += 1) {
    if (hasClass(dateCards[i], 'is-active')) {
      selectedDate = getData(dateCards[i], 'date-card');
      break;
    }
  }

  if (selectedDate === '' && dateCards.length > 0) {
    selectedDate = getData(dateCards[0], 'date-card');
  }

  for (i = 0; i < menuCards.length; i += 1) {
    if (hasClass(menuCards[i], 'is-active')) {
      selectedGenre = getData(menuCards[i], 'genre-card');
      break;
    }
  }

  if (selectedGenre === '') {
    selectedGenre = 'pilates';
  }

  if (selectedDate !== '') {
    applySelection();
  }

  updateCustomerNextButton();
  setHidden(emptyMessage, true, 'block');
  setHidden(memberGuidePanel, true, 'block');
  updateCalendarSlider(findCalendarMonthIndexByDate(selectedDate));
  setCurrentStep(1);
}());
