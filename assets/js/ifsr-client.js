(function () {
  const forms = document.querySelectorAll('form');
  if (!forms.length || typeof IFSRConfig === 'undefined') {
    return;
  }

  const stateCache = new Map();
  const saveTimers = new Map();

  function fingerprint(form) {
    if (form.dataset.ifsrFingerprint) {
      return form.dataset.ifsrFingerprint;
    }
    const id = form.getAttribute('id') || form.getAttribute('name') || 'form';
    const fp = `${window.location.pathname}::${id}`;
    form.dataset.ifsrFingerprint = fp;
    return fp;
  }

  function extractState(form) {
    const data = {};
    [...form.elements].forEach((el) => {
      if (!el.name || el.type === 'password') return;
      if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) return;
      data[el.name] = el.value;
    });
    return data;
  }

  function applyState(form, state) {
    Object.keys(state || {}).forEach((key) => {
      const field = form.elements.namedItem(key);
      if (!field) return;
      if (field instanceof RadioNodeList) {
        [...field].forEach((item) => {
          item.checked = item.value === state[key];
        });
      } else if (field.type === 'checkbox') {
        field.checked = !!state[key];
      } else {
        field.value = state[key];
      }
    });
  }

  async function save(form) {
    const emailField = form.querySelector('input[type="email"][name], input[name*="email" i]');
    if (!emailField || !emailField.value) return;

    if (IFSRConfig.requireConsent) {
      const consent = form.querySelector('input[name="ifsr_consent"], input[data-ifsr-consent="1"]');
      if (!consent || !consent.checked) {
        return;
      }
    }

    const payload = {
      formFingerprint: fingerprint(form),
      source: form.hasAttribute('data-wp-block-form') ? 'gutenberg' : 'native',
      email: emailField.value,
      consent: 1,
      state: extractState(form),
    };

    stateCache.set(payload.formFingerprint, payload.state);

    await fetch(`${IFSRConfig.endpoint}/save`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': IFSRConfig.nonce,
      },
      body: JSON.stringify(payload),
      credentials: 'same-origin',
    });
  }

  function queueSave(form) {
    const key = fingerprint(form);
    if (saveTimers.has(key)) {
      clearTimeout(saveTimers.get(key));
    }
    saveTimers.set(
      key,
      setTimeout(() => {
        save(form).catch(() => {
          // Intentionally silent to keep UX invisible.
        });
      }, IFSRConfig.autosaveFrequency || 8000)
    );
  }

  async function restoreFromToken(form) {
    if (!IFSRConfig.resumeToken) return;
    const url = new URL(`${IFSRConfig.endpoint}/restore`, window.location.origin);
    url.searchParams.set('token', IFSRConfig.resumeToken);
    const res = await fetch(url.toString(), { credentials: 'same-origin' });
    if (!res.ok) return;
    const data = await res.json();
    if (data && data.state && data.formFingerprint === fingerprint(form)) {
      applyState(form, data.state);
    }
  }

  forms.forEach((form) => {
    restoreFromToken(form).catch(() => {});
    ['input', 'blur', 'change'].forEach((eventName) => {
      form.addEventListener(eventName, () => queueSave(form), true);
    });
  });
})();
