/* Tabs: builds the tab buttons from the panel headings. Loaded by the section itself.
   Safe to load more than once on a page. Without JavaScript all panels stay visible. */
(function () {
  'use strict';

  if (window.cwaTabs) { window.cwaTabs.init(); return; }

  var counter = 0;

  function setup(root) {
    if (root.getAttribute('data-ready') === 'true') return;
    var panels = Array.prototype.slice.call(root.querySelectorAll('.tabs__panel'));
    if (panels.length < 2) return;

    root.setAttribute('data-ready', 'true');
    root.classList.add('tabs--js');
    var uid = 'tabs' + (++counter);

    var list = document.createElement('div');
    list.className = 'tabs__list';
    list.setAttribute('role', 'tablist');

    var buttons = panels.map(function (panel, i) {
      var heading = panel.querySelector('.tabs__title');
      var label = heading ? heading.textContent.replace(/\s+/g, ' ').trim() : 'Tab ' + (i + 1);
      var tab = document.createElement('button');
      tab.type = 'button';
      tab.className = 'tabs__tab';
      tab.id = uid + '-tab-' + i;
      tab.setAttribute('role', 'tab');
      tab.setAttribute('aria-controls', uid + '-panel-' + i);
      tab.textContent = label;
      tab.addEventListener('click', function () { select(i, false); });
      list.appendChild(tab);

      panel.id = uid + '-panel-' + i;
      panel.setAttribute('role', 'tabpanel');
      panel.setAttribute('aria-labelledby', tab.id);
      panel.setAttribute('tabindex', '0');
      return tab;
    });
    root.insertBefore(list, root.firstChild);

    var current = 0;
    function select(i, focus) {
      current = i;
      buttons.forEach(function (tab, k) {
        var on = k === i;
        tab.setAttribute('aria-selected', on ? 'true' : 'false');
        tab.setAttribute('tabindex', on ? '0' : '-1');
        panels[k].hidden = !on;
      });
      if (focus) buttons[i].focus();
    }

    list.addEventListener('keydown', function (e) {
      var next = null;
      if (e.key === 'ArrowRight') next = (current + 1) % buttons.length;
      else if (e.key === 'ArrowLeft') next = (current - 1 + buttons.length) % buttons.length;
      else if (e.key === 'Home') next = 0;
      else if (e.key === 'End') next = buttons.length - 1;
      if (next !== null) { e.preventDefault(); select(next, true); }
    });

    select(0, false);
  }

  function initAll() {
    var roots = document.querySelectorAll('.tabs');
    for (var i = 0; i < roots.length; i++) {
      try { setup(roots[i]); } catch (e) { /* one failing never affects the page */ }
    }
  }

  window.cwaTabs = { init: initAll };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initAll);
  else initAll();
})();