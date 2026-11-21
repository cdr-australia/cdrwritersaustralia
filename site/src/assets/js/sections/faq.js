/* FAQ: opening one item closes the others. Works without JS too (native <details>). */
(function () {
  'use strict';
  try {
    var groups = document.querySelectorAll('[data-faq]');
    for (var i = 0; i < groups.length; i++) {
      (function (group) {
        /* "toggle" does not bubble, so listen in the capture phase */
        group.addEventListener('toggle', function (e) {
          var t = e.target;
          if (!t || !t.open) return;
          var items = group.querySelectorAll('details[open]');
          for (var j = 0; j < items.length; j++) {
            if (items[j] !== t) items[j].open = false;
          }
        }, true);
      })(groups[i]);
    }
  } catch (e) { /* page still works */ }
})();