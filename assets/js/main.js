/* Links Broadband — site interactions */
(function () {
  'use strict';

  /* ---------------- Plan data (from official plan sheet) ---------------- */
  const PLANS = {
    unlimited: [
      { speed: 35,  monthly: 400 },
      { speed: 50,  monthly: 500 },
      { speed: 60,  monthly: 550 },
      { speed: 75,  monthly: 650 },
      { speed: 100, monthly: 750, tag: 'Family favourite' },
      { speed: 200, monthly: 900, tag: 'Best value' }
    ],
    limited: [
      { speed: 75,  monthly: 500,  fup: '600 GB',  post: '3 Mbps' },
      { speed: 150, monthly: 750,  fup: '1000 GB', post: '5 Mbps' },
      { speed: 300, monthly: 1100, fup: '2000 GB', post: '10 Mbps', tag: 'Power users' },
      { speed: 500, monthly: 1500, fup: '3000 GB', post: '15 Mbps', tag: 'Fastest' }
    ]
  };
  const DURATION_LABEL = { 1: 'Monthly', 3: '3 Months', 6: '6 Months', 12: '12 Months' };
  const DURATION_FORM_VALUE = {
    1: 'Monthly',
    3: '3 Months (Free installation)',
    6: '6 Months (Free router)',
    12: '12 Months'
  };

  const inr = (n) => '₹' + n.toLocaleString('en-IN');
  const planName = (type, p) => `${p.speed} Mbps ${type === 'unlimited' ? 'Unlimited' : 'FUP'}`;

  const state = { type: 'unlimited', duration: 1 };

  /* ---------------- Render plan cards ---------------- */
  const grid = document.getElementById('plans-grid');
  const note = document.getElementById('plans-note');

  const check = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4z"/></svg>';

  function perksFor(type, p, d) {
    const list = [];
    if (type === 'unlimited') {
      list.push('Truly unlimited data');
    } else {
      list.push(`${p.fup} high-speed data`);
      list.push(`${p.post} after FUP`);
    }
    list.push('Optical fiber connection');
    if (d >= 6) list.push('<strong>Free router &amp; installation</strong>');
    else if (d === 3) list.push('<strong>Free installation</strong>');
    else list.push('Installation charges apply');
    list.push('Doorstep customer service');
    return list;
  }

  function renderPlans() {
    const d = state.duration;
    const plans = PLANS[state.type];
    note.textContent = state.type === 'unlimited'
      ? 'Truly unlimited data — no caps, no speed drops.'
      : 'Higher speeds with a generous monthly high-speed data limit (FUP), then continued access at the post-FUP speed.';

    grid.dataset.cols = plans.length % 3 === 0 ? '3' : '4';
    grid.style.setProperty('--cols', grid.dataset.cols);
    grid.innerHTML = plans.map((p, i) => {
      const total = p.monthly * d;
      const featured = !!p.tag;
      const perks = perksFor(state.type, p, d).map((t) => `<li>${check}<span>${t}</span></li>`).join('');
      return `
        <article class="plan${featured ? ' plan--featured' : ''}" style="--i:${i}">
          ${p.tag ? `<span class="plan__tag">${p.tag}</span>` : ''}
          <div class="plan__speed"><span>${p.speed}</span><small>Mbps</small></div>
          <p class="plan__kind">${state.type === 'unlimited' ? 'Unlimited' : 'High-Speed FUP'}</p>
          <div class="plan__price">
            <span class="plan__amount">${inr(p.monthly)}</span><span class="plan__per">/month</span>
          </div>
          <p class="plan__total">${d === 1 ? 'Billed monthly, paid in advance' : `${inr(total)} for ${DURATION_LABEL[d].toLowerCase()}`}</p>
          <ul class="plan__perks">${perks}</ul>
          <button class="btn ${featured ? 'btn--lime' : 'btn--primary'} btn--block" data-choose-plan="${i}">Get this plan</button>
        </article>`;
    }).join('');
  }

  document.querySelectorAll('[data-plan-type]').forEach((btn) => {
    btn.addEventListener('click', () => {
      state.type = btn.dataset.planType;
      document.querySelectorAll('[data-plan-type]').forEach((b) => {
        const on = b === btn;
        b.classList.toggle('is-active', on);
        b.setAttribute('aria-selected', String(on));
      });
      renderPlans();
    });
  });

  document.querySelectorAll('[data-duration]').forEach((btn) => {
    btn.addEventListener('click', () => {
      state.duration = Number(btn.dataset.duration);
      document.querySelectorAll('[data-duration]').forEach((b) => {
        const on = b === btn;
        b.classList.toggle('is-active', on);
        b.setAttribute('aria-checked', String(on));
      });
      renderPlans();
    });
  });

  /* Fill plan dropdowns */
  document.querySelectorAll('[data-plan-select]').forEach((select) => {
    ['unlimited', 'limited'].forEach((type) => {
      const group = document.createElement('optgroup');
      group.label = type === 'unlimited' ? 'Unlimited Plans' : 'High-Speed FUP Plans';
      PLANS[type].forEach((p) => {
        const opt = document.createElement('option');
        opt.value = planName(type, p);
        opt.textContent = `${planName(type, p)} — ${inr(p.monthly)}/mo`;
        group.appendChild(opt);
      });
      select.appendChild(group);
    });
  });

  renderPlans();

  /* ---------------- Modals ---------------- */
  const planModal = document.getElementById('plan-modal');
  const flyerModal = document.getElementById('flyer-modal');

  function openModal(dlg) {
    if (typeof dlg.showModal === 'function') dlg.showModal();
    else dlg.setAttribute('open', '');
  }
  function closeModal(dlg) {
    if (typeof dlg.close === 'function') dlg.close();
    else dlg.removeAttribute('open');
  }

  grid.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-choose-plan]');
    if (!btn) return;
    const p = PLANS[state.type][Number(btn.dataset.choosePlan)];
    const d = state.duration;
    const name = planName(state.type, p);
    planModal.querySelector('[data-modal-plan]').textContent = name;
    planModal.querySelector('[data-modal-price]').textContent =
      d === 1 ? `${inr(p.monthly)}/month` : `${DURATION_LABEL[d]} · ${inr(p.monthly * d)} total`;
    planModal.querySelector('[data-modal-plan-input]').value = name;
    planModal.querySelector('[data-modal-duration-input]').value = DURATION_FORM_VALUE[d];
    resetForm(planModal.querySelector('form'), true);
    openModal(planModal);
    setTimeout(() => planModal.querySelector('input[name="name"]').focus(), 50);
  });

  document.querySelector('[data-open-flyer]')?.addEventListener('click', () => openModal(flyerModal));

  document.querySelectorAll('.modal').forEach((dlg) => {
    dlg.querySelector('[data-close-modal]').addEventListener('click', () => closeModal(dlg));
    dlg.addEventListener('click', (e) => { if (e.target === dlg) closeModal(dlg); });
  });

  /* ---------------- Lead forms ---------------- */
  const forms = document.querySelectorAll('[data-lead-form]');

  function stamp(form) {
    const f = form.querySelector('input[name="started_at"]');
    if (f) f.value = String(Date.now());
  }

  function resetForm(form, keepHidden) {
    form.classList.remove('is-success');
    const status = form.querySelector('.form-status');
    status.textContent = '';
    status.className = 'form-status';
    form.querySelectorAll('.field').forEach((fl) => fl.classList.remove('has-error'));
    if (!keepHidden) form.reset();
    stamp(form);
  }

  function validPhone(v) {
    let digits = v.replace(/\D/g, '');
    if (digits.length === 12 && digits.startsWith('91')) digits = digits.slice(2);
    if (digits.length === 11 && digits.startsWith('0')) digits = digits.slice(1);
    return /^[6-9]\d{9}$/.test(digits) ? digits : null;
  }

  function markError(input, bad) {
    input.closest('.field').classList.toggle('has-error', bad);
  }

  forms.forEach((form) => {
    stamp(form);

    const phone = form.querySelector('input[name="phone"]');
    phone.addEventListener('input', () => {
      phone.value = phone.value.replace(/[^\d]/g, '').slice(0, 11);
      if (form.querySelector('.field.has-error')) markError(phone, !validPhone(phone.value));
    });

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const status = form.querySelector('.form-status');
      const btn = form.querySelector('button[type="submit"]');
      const name = form.querySelector('input[name="name"]');
      const email = form.querySelector('input[name="email"]');

      const nameBad = name.value.trim().length < 2;
      const phoneBad = !validPhone(phone.value);
      const emailBad = !!(email && email.value.trim() && !email.checkValidity());
      markError(name, nameBad);
      markError(phone, phoneBad);
      if (email) markError(email, emailBad);

      if (nameBad || phoneBad || emailBad) {
        status.className = 'form-status is-error';
        status.textContent = phoneBad && !nameBad
          ? 'Please enter a valid 10-digit mobile number.'
          : 'Please check the highlighted fields.';
        (nameBad ? name : phoneBad ? phone : email).focus();
        return;
      }

      btn.disabled = true;
      btn.classList.add('is-loading');
      status.className = 'form-status';
      status.textContent = '';

      try {
        const res = await fetch(form.getAttribute('action') || 'send-mail.php', {
          method: 'POST',
          body: new FormData(form),
          headers: { Accept: 'application/json' }
        });
        let data;
        try { data = await res.json(); } catch (_) { data = { ok: false }; }

        if (res.ok && data.ok) {
          form.classList.add('is-success');
          status.className = 'form-status is-success';
          status.textContent = data.message || 'Thank you! Our team will call you shortly.';
          form.reset();
          if (window.gtag) window.gtag('event', 'generate_lead', { form: form.querySelector('[name="form_source"]').value });
          if (window.fbq) window.fbq('track', 'Lead');
        } else {
          status.className = 'form-status is-error';
          status.textContent = data.message || 'Something went wrong. Please call us on 93930 50511.';
        }
      } catch (err) {
        status.className = 'form-status is-error';
        status.textContent = 'Network error. Please call or WhatsApp us on 93930 50511.';
      } finally {
        btn.disabled = false;
        btn.classList.remove('is-loading');
        stamp(form);
      }
    });
  });

  /* ---------------- Header: sticky shadow + mobile menu ---------------- */
  const header = document.getElementById('header');
  const burger = document.getElementById('burger');
  const nav = document.getElementById('nav');

  const onScroll = () => header.classList.toggle('is-scrolled', window.scrollY > 10);
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });

  function toggleMenu(open) {
    const isOpen = open ?? !nav.classList.contains('is-open');
    nav.classList.toggle('is-open', isOpen);
    burger.classList.toggle('is-open', isOpen);
    burger.setAttribute('aria-expanded', String(isOpen));
    document.body.classList.toggle('menu-open', isOpen);
  }
  burger.addEventListener('click', () => toggleMenu());
  nav.querySelectorAll('a').forEach((a) => a.addEventListener('click', () => toggleMenu(false)));

  /* ---------------- Reveal on scroll ---------------- */
  const revealEls = document.querySelectorAll('.section-head, .feature, .step, .guide__item, .perk, .contact__card, .accordion details');
  revealEls.forEach((el) => el.classList.add('reveal'));
  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach((en) => {
        if (en.isIntersecting) { en.target.classList.add('is-visible'); io.unobserve(en.target); }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    revealEls.forEach((el) => io.observe(el));
  } else {
    revealEls.forEach((el) => el.classList.add('is-visible'));
  }

  document.getElementById('year').textContent = new Date().getFullYear();
})();
