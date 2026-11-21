/* Service worker, snackbar, online/offline notice, install prompt. */
(function () {
  'use strict';

  var snack = document.getElementById('snackbar');
  var snackTimer = null;

  function toast(msg, ms) {
    if (!snack) return;
    snack.textContent = msg;
    snack.classList.add('is-show');
    clearTimeout(snackTimer);
    snackTimer = setTimeout(function () { snack.classList.remove('is-show'); }, ms || 3000);
  }
  window.cwaToast = toast; /* other sections can call this later */

  /* service worker (needs HTTPS or localhost) */
  try {
    var secure = location.protocol === 'https:' || location.hostname === 'localhost';
    if ('serviceWorker' in navigator && secure) {
      window.addEventListener('load', function () {
        navigator.serviceWorker.register('/sw.js').catch(function () {});
      });
    }
  } catch (e) {}

  /* connection status */
  try {
    window.addEventListener('offline', function () { toast('You are offline 📡', 4000); });
    window.addEventListener('online', function () { toast('Back online ✅', 2500); });
  } catch (e) {}

  /* install prompt: any element with [data-install] is shown when installable */
  try {
    var deferred = null;
    window.addEventListener('beforeinstallprompt', function (e) {
      e.preventDefault();
      deferred = e;
      var btns = document.querySelectorAll('[data-install]');
      for (var i = 0; i < btns.length; i++) btns[i].hidden = false;
    });
    document.addEventListener('click', function (e) {
      if (!deferred || !e.target.closest || !e.target.closest('[data-install]')) return;
      deferred.prompt();
      deferred.userChoice.then(function () { deferred = null; });
    });
    window.addEventListener('appinstalled', function () { toast('App installed ✅'); });
  } catch (e) {}
})();