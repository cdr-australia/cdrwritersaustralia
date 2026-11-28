/* Enquiry form: checks the fields, sends them to the form's address and shows the result.
   Loaded by the section itself. Safe to load more than once on a page.
   The address is the form's data-endpoint. The special word "demo" pretends to succeed. */
(function () {
  'use strict';

  if (window.cwaEnquiry) { window.cwaEnquiry.init(); return; }

  var EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
  var PHONE = /^[0-9+()\-.\s]{6,20}$/;

  function setup(form) {
    if (form.getAttribute('data-ready') === 'true') return;
    form.setAttribute('data-ready', 'true');
    form.noValidate = true; /* our own messages replace the browser's, only when JavaScript runs */

    var endpoint = form.getAttribute('data-endpoint') || '';
    var fields = form.querySelector('.enquiry__fields');
    var status = form.querySelector('.enquiry__status');
    var fallback = form.querySelector('.enquiry__fallback');
    var done = form.querySelector('.enquiry__done');
    var button = form.querySelector('button[type="submit"]');
    if (!fields || !status || !button) return;
    var buttonLabel = button.textContent;
    var sending = false;

    function holder(input) {
      return input.closest('.field') || input.closest('.check') || input.parentNode;
    }
    function clearError(input) {
      var p = document.getElementById(input.id + '-err');
      if (p) p.parentNode.removeChild(p);
      input.removeAttribute('aria-invalid');
      input.removeAttribute('aria-describedby');
    }
    function showError(input, message) {
      var id = input.id + '-err';
      var p = document.getElementById(id);
      if (!p) {
        p = document.createElement('p');
        p.className = 'field__error';
        p.id = id;
        holder(input).appendChild(p);
      }
      p.textContent = message;
      input.setAttribute('aria-invalid', 'true');
      input.setAttribute('aria-describedby', id);
    }

    function check(input) {
      var value = input.type === 'checkbox' ? input.checked : input.value.trim();
      var message = '';
      switch (input.name) {
        case 'name': if (!value) message = 'Please enter your name.'; break;
        case 'email':
          if (!value) message = 'Please enter your email.';
          else if (!EMAIL.test(value)) message = 'Please enter a valid email address.';
          break;
        case 'phone': if (value && !PHONE.test(value)) message = 'Please enter a valid phone number.'; break;
        case 'topic': if (!value) message = 'Please choose what you need help with.'; break;
        case 'message':
          if (!value) message = 'Please write a short message.';
          else if (value.length < 10) message = 'Please add a little more detail.';
          break;
        case 'consent': if (!value) message = 'Please tick the box so we can contact you.'; break;
      }
      if (message) showError(input, message); else clearError(input);
      return !message;
    }

    function inputs() {
      return Array.prototype.slice.call(form.querySelectorAll('input[name], select[name], textarea[name]'))
        .filter(function (el) { return el.name !== 'website'; });
    }

    function setSending(on) {
      sending = on;
      button.disabled = on;
      button.textContent = on ? 'Sending\u2026' : buttonLabel;
    }
    function succeed() {
      setSending(false);
      status.textContent = '';
      if (fallback) fallback.hidden = true;
      fields.hidden = true;
      if (done) { done.hidden = false; done.focus(); }
    }
    function fail() {
      setSending(false);
      status.textContent = 'Sorry, we could not send your message just now.';
      if (fallback) fallback.hidden = false;
    }

    /* fix a field's message as soon as it is corrected */
    form.addEventListener('input', function (e) {
      if (e.target.getAttribute && e.target.getAttribute('aria-invalid') === 'true') check(e.target);
    });
    form.addEventListener('change', function (e) {
      if (e.target.getAttribute && e.target.getAttribute('aria-invalid') === 'true') check(e.target);
    });

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (sending) return;

      var first = null;
      var bad = 0;
      inputs().forEach(function (el) {
        if (!check(el)) { bad++; if (!first) first = el; }
      });
      if (bad) {
        status.textContent = bad === 1 ? 'Please check the highlighted field.' : 'Please check the highlighted fields.';
        if (first) first.focus();
        return;
      }
      status.textContent = '';
      if (fallback) fallback.hidden = true;

      /* hidden trap field: a person never fills it in */
      if (form.elements.website && form.elements.website.value) { succeed(); return; }

      setSending(true);

      if (endpoint === 'demo') {
        setTimeout(succeed, 700);
        return;
      }
      if (!endpoint || !window.fetch) { fail(); return; }

      var controller = 'AbortController' in window ? new AbortController() : null;
      var timer = controller ? setTimeout(function () { controller.abort(); }, 15000) : null;
      fetch(endpoint, {
        method: 'POST',
        body: new FormData(form),
        headers: { 'Accept': 'application/json' },
        signal: controller ? controller.signal : undefined
      }).then(function (res) {
        if (timer) clearTimeout(timer);
        if (res.ok) succeed(); else fail();
      }).catch(function () {
        if (timer) clearTimeout(timer);
        fail();
      });
    });
  }

  function initAll() {
    var forms = document.querySelectorAll('.enquiry');
    for (var i = 0; i < forms.length; i++) {
      try { setup(forms[i]); } catch (e) { /* one failing never affects the page */ }
    }
  }

  window.cwaEnquiry = { init: initAll };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initAll);
  else initAll();
})();