/* Drawer, active-link marking. Plain script, no imports. */
(function () {
  'use strict';

  try { markActive(); } catch (e) { /* page still works */ }
  try { initDrawer(); } catch (e) { /* page still works */ }

  function markActive() {
    var path = location.pathname.replace(/index\.html$/, '');
    if (path.charAt(path.length - 1) !== '/') path += '/';
    var links = document.querySelectorAll('.nav-desktop a, .drawer__nav a, .bottom-nav a');
    for (var i = 0; i < links.length; i++) {
      var href = links[i].getAttribute('href');
      if (!href || href.charAt(0) !== '/') continue;
      var on = href === '/' ? path === '/' : path.indexOf(href) === 0;
      if (on) links[i].setAttribute('aria-current', 'page');
      else links[i].removeAttribute('aria-current');
    }
  }

  function initDrawer() {
    var drawer = document.getElementById('drawer');
    var overlay = document.querySelector('.drawer-overlay');
    var opener = document.querySelector('[data-drawer-open]');
    if (!drawer || !overlay || !opener) return;

    var lastFocus = null;
    var timer = null;

    function isOpen() { return drawer.classList.contains('is-open'); }

    function open() {
      lastFocus = document.activeElement;
      clearTimeout(timer);
      overlay.hidden = false;
      void overlay.offsetWidth; /* force reflow so the fade runs */
      overlay.classList.add('is-open');
      drawer.classList.add('is-open');
      drawer.setAttribute('aria-hidden', 'false');
      opener.setAttribute('aria-expanded', 'true');
      document.documentElement.style.overflow = 'hidden';
      var first = drawer.querySelector('button, a');
      if (first) first.focus();
    }

    function close() {
      if (!isOpen()) return;
      overlay.classList.remove('is-open');
      drawer.classList.remove('is-open');
      drawer.setAttribute('aria-hidden', 'true');
      opener.setAttribute('aria-expanded', 'false');
      document.documentElement.style.overflow = '';
      timer = setTimeout(function () { overlay.hidden = true; }, 260);
      if (lastFocus && lastFocus.focus) lastFocus.focus();
    }

    opener.addEventListener('click', open);

    document.addEventListener('click', function (e) {
      if (e.target.closest && e.target.closest('[data-drawer-close]')) close();
    });

    document.addEventListener('keydown', function (e) {
      if (!isOpen()) return;
      if (e.key === 'Escape') { close(); return; }
      if (e.key === 'Tab') { /* keep focus inside the drawer */
        var f = drawer.querySelectorAll('a[href], button');
        if (!f.length) return;
        var first = f[0], last = f[f.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    });

    if (window.matchMedia) {
      var mq = window.matchMedia('(min-width: 900px)');
      var onChange = function (m) { if (m.matches) close(); };
      if (mq.addEventListener) mq.addEventListener('change', onChange);
      else if (mq.addListener) mq.addListener(onChange);
    }
  }
})();