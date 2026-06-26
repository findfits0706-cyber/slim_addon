(function () {
  function onReady(callback) {
    if (document.readyState === 'loading') {
      if (document.addEventListener) {
        document.addEventListener('DOMContentLoaded', callback, false);
      } else if (window.attachEvent) {
        window.attachEvent('onload', callback);
      }
    } else {
      callback();
    }
  }

  function getCurrentBodyPadding() {
    var body = document.body;
    var style = null;

    if (window.getComputedStyle) {
      style = window.getComputedStyle(body, null);
    } else if (body.currentStyle) {
      style = body.currentStyle;
    }

    return parseInt(style && style.paddingBottom, 10) || 0;
  }

  function isVisible(element) {
    if (!element) return false;

    if (window.getComputedStyle) {
      return window.getComputedStyle(element, null).display !== 'none';
    }

    return element.offsetHeight > 0 || element.offsetWidth > 0;
  }

  function openHashDetails() {
    if (!window.location.hash || !document.getElementById) return;

    var id = window.location.hash.replace(/^#/, '');
    if (!id) return;

    try {
      id = decodeURIComponent(id);
    } catch (error) {
      return;
    }

    var target = document.getElementById(id);
    if (!target || !target.tagName) return;

    if (target.tagName.toLowerCase() === 'details') {
      target.open = true;
    }
  }

  onReady(function () {
    var cta = document.getElementById('fpFloatingTrialCta');
    var mobileNav = document.querySelector ? document.querySelector('.fp-mobile-nav') : null;
    if (!document.body) return;

    var basePadding = parseInt(document.body.style.paddingBottom, 10) || 0;

    function updateFloatingCtaSpace() {
      if (isVisible(mobileNav)) {
        document.body.style.paddingBottom = '';
        return;
      }

      var reserve = isVisible(cta) ? (cta.offsetHeight || 0) + 30 : 0;
      var padding = basePadding + reserve;
      document.body.style.paddingBottom = padding ? padding + 'px' : '';
    }

    openHashDetails();
    updateFloatingCtaSpace();
    setTimeout(updateFloatingCtaSpace, 300);

    if (window.addEventListener) {
      window.addEventListener('resize', updateFloatingCtaSpace, false);
      window.addEventListener('orientationchange', function () {
        setTimeout(updateFloatingCtaSpace, 300);
      }, false);
    } else if (window.attachEvent) {
      window.attachEvent('onresize', updateFloatingCtaSpace);
    }

    if (window.addEventListener) {
      window.addEventListener('hashchange', openHashDetails, false);
    }
  });
})();

function setFormStartedAt(form) {
  const startedAt = form.querySelector('input[name="form_started_at"]');
  if (startedAt) {
    startedAt.value = Math.floor(Date.now() / 1000);
  }
}

function hasTooManyUrls(text) {
  const matches = String(text || '').match(/https?:\/\/|www\./gi);
  return matches && matches.length >= 2;
}

function validateBeforeSubmit(form, type) {
  const honeypot = form.querySelector('input[name="website"]');
  if (honeypot && honeypot.value.trim() !== '') {
    throw new Error('送信内容を確認してください。');
  }

  const startedAt = Number(form.querySelector('input[name="form_started_at"]')?.value || 0);
  const elapsed = Math.floor(Date.now() / 1000) - startedAt;
  if (!startedAt || elapsed < 3) {
    throw new Error('入力後、3秒ほど待ってから送信してください。');
  }

  if (type === 'contact') {
    const name = form.querySelector('input[name="name"]')?.value || '';
    const message = form.querySelector('textarea[name="message"]')?.value || '';
    if (/(https?:\/\/|www\.)/i.test(name)) {
      throw new Error('お名前欄にURLは入力できません。');
    }
    if (hasTooManyUrls(message)) {
      throw new Error('本文にURLを2つ以上含めることはできません。');
    }
  }
}

function handleSubmitError(button, originalLabel, error) {
  button.disabled = false;
  button.textContent = originalLabel;
  alert(error?.message || '送信中にエラーが発生しました。時間をおいて再度お試しください。');
}

function handleContact(e) {
  e.preventDefault();
  const form = document.getElementById('contactForm');
  const button = form.querySelector('button[type="submit"]');
  const originalLabel = button.textContent;

  try {
    validateBeforeSubmit(form, 'contact');
  } catch (error) {
    return handleSubmitError(button, originalLabel, error);
  }

  button.disabled = true;
  button.textContent = '送信中...';

  fetch('contact.php', { method: 'POST', body: new FormData(form) })
    .then((response) => response.json())
    .then((result) => {
      if (!result.ok) {
        throw new Error(result.message || '送信に失敗しました。');
      }

      form.style.display = 'none';
      const message = document.getElementById('contactSuccess');
      message.style.display = 'block';
      message.textContent = '✓ ' + result.message;

      if (typeof gtag === 'function') {
        gtag('event', 'generate_lead', {
          form_name: 'contact_form',
          method: 'contact'
        });
      }
    })
    .catch((error) => handleSubmitError(button, originalLabel, error));
}

document.addEventListener('DOMContentLoaded', () => {
  const contactForm = document.getElementById('contactForm');

  if (contactForm) {
    setFormStartedAt(contactForm);
  }
});
