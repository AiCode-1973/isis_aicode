/* ÍSIS · animações da página inicial (sem bibliotecas, compatível com a CSP: só CSSOM, sem estilo inline).
 *
 * 1. A tela do produto começa inclinada e se endireita conforme entra na tela.
 * 2. Seções, cartões e os pedaços de interface dos módulos aparecem em sequência ao rolar.
 *
 * Com "reduzir movimento" ligado no sistema, o conteúdo aparece sem animação.
 */
(function () {
  'use strict';

  var hero = document.querySelector('.lp-hero');
  if (!hero) return;
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // ---------- Revelação ao rolar ----------
  var groups = [
    '.lp-audience li', '.lp-head', '.bento-card', '#seguranca .lp-split > div:first-child', '.lp-checks li',
    '.lp-steps li', '#planos .section-head', '#planos .pricing-card', '#duvidas details', '.lp-final .container > *'
  ];

  function inView(el) {
    var r = el.getBoundingClientRect();
    return r.top < window.innerHeight * 0.95 && r.bottom > 0;
  }

  if (!reduce && 'IntersectionObserver' in window) {
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          reveal(entry.target);
          observer.unobserve(entry.target);
        }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.12 });

    groups.forEach(function (selector) {
      var items = document.querySelectorAll(selector);
      Array.prototype.forEach.call(items, function (el, i) {
        if (el.hasAttribute('data-reveal')) return;
        el.setAttribute('data-reveal', '');
        el.style.setProperty('--d', (Math.min(i, 6) * 80) + 'ms');
        if (inView(el)) {
          el.classList.add('is-in'); // já visível: não esconde para não piscar
        } else {
          observer.observe(el);
        }
      });
    });

    // Pedaços de interface dentro dos cartões entram um depois do outro.
    Array.prototype.forEach.call(document.querySelectorAll('.bento-card'), function (card) {
      var parts = card.querySelectorAll('.mini-table > div, .mini-audit > *, .mini-cards > div, .mini-steps li, .mini-heat i, .mini-doc > *');
      Array.prototype.forEach.call(parts, function (part, i) {
        part.style.setProperty('--i', String(i));
      });
    });
    document.documentElement.classList.add('lp-anim');
  }

  function reveal(el) {
    el.classList.add('is-in');
    var timer = el.querySelector('.mini-timer b');
    if (timer) countUp(timer, parseInt(timer.textContent, 10) || 0, 1100);
  }

  function countUp(el, to, ms) {
    var start = null;
    function step(now) {
      if (start === null) start = now;
      var p = Math.min(1, (now - start) / ms);
      el.textContent = String(Math.round(to * (1 - Math.pow(1 - p, 3))));
      if (p < 1) requestAnimationFrame(step);
    }
    el.textContent = '0';
    requestAnimationFrame(step);
  }

  // ---------- Tela do produto: endireita ao rolar ----------
  var frame = document.querySelector('.lp-frame');
  if (frame && !reduce) {
    frame.classList.add('lp-tilt');
    var tiltQueued = false;
    var updateTilt = function () {
      tiltQueued = false;
      var r = frame.getBoundingClientRect();
      var vh = window.innerHeight;
      var t = Math.max(0, Math.min(1, (r.top - vh * 0.2) / (vh * 0.55)));
      frame.style.setProperty('--tilt', t.toFixed(3));
    };
    window.addEventListener('scroll', function () {
      if (!tiltQueued) { tiltQueued = true; requestAnimationFrame(updateTilt); }
    }, { passive: true });
    window.addEventListener('resize', updateTilt);
    updateTilt();
  }

})();
