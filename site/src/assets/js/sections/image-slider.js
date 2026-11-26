/* Image slider: endless loop, largest card in the centre, autoplay with controls.
   The section loads this file itself. Safe to load more than once on a page.
   Speed: data-interval="4000" (milliseconds), or the class slider--slow / slider--fast. */
(function () {
  'use strict';

  if (window.cwaImageSlider) { window.cwaImageSlider.init(); return; }

  var reduceMotion = false;
  try { reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches; } catch (e) {}

  function make(tag, cls, text) {
    var node = document.createElement(tag);
    if (cls) node.className = cls;
    if (text) node.textContent = text;
    return node;
  }

  function setup(root) {
    if (root.getAttribute('data-ready') === 'true') return;
    var stage = root.querySelector('.slider__stage');
    var slides = Array.prototype.slice.call(root.querySelectorAll('.slider__slide'));
    var n = slides.length;
    if (!stage || n < 2) return;

    var interval = parseInt(root.getAttribute('data-interval'), 10);
    if (!interval || interval < 1500) {
      interval = root.classList.contains('slider--slow') ? 6000
        : root.classList.contains('slider--fast') ? 2500 : 4000;
    }

    var active = 0;
    var timer = null;
    var userPaused = reduceMotion;
    var hovering = false, focused = false, inView = true;
    var startX = 0, tracking = false, dragged = false;

    root.setAttribute('data-ready', 'true');
    root.classList.add('slider--js');

    /* carousel labels (blocks in WordPress cannot carry these, so they are added here) */
    if (!root.getAttribute('role')) root.setAttribute('role', 'region');
    if (!root.getAttribute('aria-roledescription')) root.setAttribute('aria-roledescription', 'carousel');
    if (!root.getAttribute('aria-label')) {
      var heading = root.parentNode ? root.parentNode.querySelector('h2') : null;
      root.setAttribute('aria-label', heading ? heading.textContent : 'Image slider');
    }
    stage.setAttribute('tabindex', '0');
    stage.setAttribute('aria-label', 'Use the left and right arrow keys to change slide');

    /* controls: previous, dots, next, pause/play */
    var controls = make('div', 'slider__controls');
    var prev = make('button', 'slider__btn', '\u2039');
    prev.type = 'button';
    prev.setAttribute('aria-label', 'Previous slide');
    var next = make('button', 'slider__btn', '\u203A');
    next.type = 'button';
    next.setAttribute('aria-label', 'Next slide');
    var toggle = make('button', 'slider__btn slider__toggle');
    toggle.type = 'button';
    var dotsWrap = make('div', 'slider__dots');
    var dots = slides.map(function (s, i) {
      var d = make('button', 'slider__dot');
      d.type = 'button';
      d.setAttribute('aria-label', 'Go to slide ' + (i + 1));
      d.addEventListener('click', function () { goTo(i); });
      dotsWrap.appendChild(d);
      return d;
    });
    controls.appendChild(prev);
    controls.appendChild(dotsWrap);
    controls.appendChild(next);
    controls.appendChild(toggle);
    root.appendChild(controls);

    slides.forEach(function (s, i) {
      if (!s.getAttribute('role')) s.setAttribute('role', 'group');
      if (!s.getAttribute('aria-roledescription')) s.setAttribute('aria-roledescription', 'slide');
      s.setAttribute('aria-label', (i + 1) + ' of ' + n);
      var imgs = s.getElementsByTagName('img');
      for (var k = 0; k < imgs.length; k++) imgs[k].setAttribute('draggable', 'false');
      s.addEventListener('click', function (e) {
        if (dragged) { e.preventDefault(); return; }
        if (i !== active) { e.preventDefault(); goTo(i); }
      });
    });

    function render() {
      var half = Math.floor(n / 2);
      slides.forEach(function (s, i) {
        var o = ((i - active + n + half) % n) - half;
        var a = Math.abs(o);
        s.setAttribute('data-a', String(a > 3 ? 3 : a));
        s.setAttribute('data-side', o < 0 ? 'l' : 'r');
        s.setAttribute('aria-hidden', a <= 2 ? 'false' : 'true');
        var links = s.getElementsByTagName('a');
        for (var k = 0; k < links.length; k++) links[k].setAttribute('tabindex', a === 0 ? '0' : '-1');
      });
      dots.forEach(function (d, i) {
        if (i === active) d.setAttribute('aria-current', 'true');
        else d.removeAttribute('aria-current');
      });
    }

    function shouldPlay() {
      return !userPaused && !hovering && !focused && inView && !document.hidden;
    }
    function stop() {
      if (timer) { clearTimeout(timer); timer = null; }
    }
    function restart() {
      stop();
      if (shouldPlay()) timer = setTimeout(function () { go(1); }, interval);
    }
    function goTo(i) {
      active = ((i % n) + n) % n;
      render();
      restart();
    }
    function go(d) { goTo(active + d); }

    function syncToggle() {
      toggle.textContent = userPaused ? '\u25B6' : '\u275A\u275A';
      toggle.setAttribute('aria-label', userPaused ? 'Start autoplay' : 'Pause autoplay');
    }

    prev.addEventListener('click', function () { go(-1); });
    next.addEventListener('click', function () { go(1); });
    toggle.addEventListener('click', function () {
      userPaused = !userPaused;
      syncToggle();
      restart();
    });

    /* pause while the visitor is using the slider */
    root.addEventListener('mouseenter', function () { hovering = true; stop(); });
    root.addEventListener('mouseleave', function () { hovering = false; restart(); });
    root.addEventListener('focusin', function (e) {
      var keyboard = true;
      try { keyboard = e.target.matches(':focus-visible'); } catch (err) {}
      if (keyboard) { focused = true; stop(); }
    });
    root.addEventListener('focusout', function () { focused = false; restart(); });

    /* keyboard */
    root.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft') { e.preventDefault(); go(-1); }
      else if (e.key === 'ArrowRight') { e.preventDefault(); go(1); }
    });

    /* swipe or drag */
    stage.addEventListener('pointerdown', function (e) {
      if (e.pointerType === 'mouse' && e.button !== 0) return;
      tracking = true;
      dragged = false;
      startX = e.clientX;
    });
    stage.addEventListener('pointermove', function (e) {
      if (tracking && Math.abs(e.clientX - startX) > 8) dragged = true;
    });
    stage.addEventListener('pointerup', function (e) {
      if (!tracking) return;
      tracking = false;
      var dx = e.clientX - startX;
      if (Math.abs(dx) > 45) go(dx < 0 ? 1 : -1);
      setTimeout(function () { dragged = false; }, 50);
    });
    stage.addEventListener('pointercancel', function () { tracking = false; });

    /* only play while visible on screen and in an active tab */
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (entries) {
        inView = entries[0].isIntersecting;
        restart();
      }, { threshold: 0.2 }).observe(root);
    }
    document.addEventListener('visibilitychange', restart);

    syncToggle();
    render();
    restart();
  }

  function initAll() {
    var roots = document.querySelectorAll('.slider');
    for (var i = 0; i < roots.length; i++) {
      try { setup(roots[i]); } catch (e) { /* one slider failing never affects the page */ }
    }
  }

  window.cwaImageSlider = { init: initAll };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initAll);
  else initAll();
})();