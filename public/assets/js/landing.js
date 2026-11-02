/* ÍSIS · animações da página inicial (sem bibliotecas, compatível com a CSP: só CSSOM e canvas).
 *
 * 1. Topo: pontos de dados soltos se organizam no escudo da marca, que envolve o título.
 *    O mouse afasta os pontos e liga os mais próximos a ele; uma onda de verificação percorre o escudo
 *    de tempos em tempos; ao rolar a página, os pontos se dispersam.
 * 2. A tela do produto começa inclinada e se endireita conforme entra na tela.
 * 3. Seções, cartões e os pedaços de interface dos módulos aparecem em sequência ao rolar.
 *
 * Com "reduzir movimento" ligado no sistema, o escudo é desenhado parado e o conteúdo aparece sem animação.
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

  // ---------- Topo: escudo de dados ----------
  var canvas = document.createElement('canvas');
  canvas.className = 'lp-canvas';
  canvas.setAttribute('aria-hidden', 'true');
  hero.insertBefore(canvas, hero.firstChild);
  var ctx = canvas.getContext('2d');
  if (!ctx || typeof Path2D === 'undefined') return;

  var SHIELD = new Path2D('M32 3.5 55 11.6c1.2.4 2 1.5 2 2.8V30c0 14.5-10 24.8-25 30.5C17 54.8 7 44.5 7 30V14.4c0-1.3.8-2.4 2-2.8L32 3.5z');
  var probe = document.createElement('canvas').getContext('2d');

  // Tipos: 0 contorno do escudo, 1 interior, 4 dado solto (a fechadura do logo fica de fora: estaria atrás do texto)
  var STYLE = [
    { color: '#5fd4c6', alpha: 0.8, size: 2.2 },
    { color: '#4f7fc4', alpha: 0.24, size: 1.6 },
    null, null,
    { color: '#8ea4c8', alpha: 0.3, size: 1.5 }
  ];

  function inShield(x, y) { return probe.isPointInPath(SHIELD, x, y); }

  /** Pontos do escudo em unidades do desenho do logo (0 a 64): 65% no contorno, o resto espalhado dentro. */
  function sampleShield(total) {
    var want = [Math.round(total * 0.65), Math.round(total * 0.35)];
    var out = [];
    var counts = [0, 0];
    var guard = 0;
    while ((counts[0] < want[0] || counts[1] < want[1]) && guard++ < 200000) {
      var x = 7 + Math.random() * 50;
      var y = 3.5 + Math.random() * 57;
      if (!inShield(x, y)) continue;
      var type = (!inShield(x - 1.2, y) || !inShield(x + 1.2, y) || !inShield(x, y - 1.2) || !inShield(x, y + 1.2)) ? 0 : 1;
      if (counts[type] >= want[type]) continue;
      counts[type]++;
      out.push({ ux: x, uy: y, type: type });
    }
    return out;
  }

  var W = 0, H = 0, dpr = 1, cx = 0, cy = 0, scale = 1, maxR = 1;
  var particles = [];
  var mouse = { x: -9999, y: -9999, active: false };
  var started = performance.now();
  var lastPulse = started - 1200;
  var running = false, visible = true;

  function layout() {
    var rect = hero.getBoundingClientRect();
    dpr = Math.min(window.devicePixelRatio || 1, 2);
    W = rect.width; H = rect.height;
    canvas.width = Math.round(W * dpr); canvas.height = Math.round(H * dpr);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

    var inner = hero.querySelector('.lp-hero-inner');
    var ir = inner ? inner.getBoundingClientRect() : rect;
    var size = Math.min(ir.height * 1.3, W * 0.95, 780);
    scale = size / 57;
    cx = W / 2;
    cy = ir.top - rect.top + ir.height / 2;
    maxR = size * 0.75;
  }

  function build() {
    layout();
    var small = W < 700;
    var shieldCount = small ? 360 : 720;
    var looseCount = small ? 40 : 90;
    var pts = sampleShield(shieldCount);
    for (var i = 0; i < looseCount; i++) pts.push({ type: 4 });
    var old = particles;
    particles = pts.map(function (p, i) {
      var prev = old[i];
      var hx = p.type === 4 ? Math.random() * W : cx + (p.ux - 32) * scale;
      var hy = p.type === 4 ? Math.random() * H : cy + (p.uy - 32) * scale;
      var angle = Math.atan2(hy - cy, hx - cx) + (Math.random() - 0.5) * 0.8;
      var far = Math.max(W, H) * (0.55 + Math.random() * 0.4);
      return {
        type: p.type,
        hx: hx, hy: hy,
        sx: cx + Math.cos(angle) * far, sy: cy + Math.sin(angle) * far, // para onde vai ao rolar
        x: prev ? prev.x : Math.random() * W, y: prev ? prev.y : Math.random() * H,
        vx: 0, vy: 0,
        delay: prev ? 0 : Math.random() * 900,
        phase: Math.random() * Math.PI * 2,
        drift: 0.15 + Math.random() * 0.25,
        glow: 0,
        dist: Math.hypot(hx - cx, hy - cy)
      };
    });
  }

  function scrollProgress() {
    var p = window.scrollY / (H * 0.7 || 1);
    p = Math.max(0, Math.min(1, p));
    return p * p * (3 - 2 * p);
  }

  function frameDraw(now) {
    var t = now - started;
    var scatter = reduce ? 0 : scrollProgress();
    if (!reduce && now - lastPulse > 3400) lastPulse = now;
    var wave = reduce ? -1 : ((now - lastPulse) / 1700) * maxR;

    ctx.clearRect(0, 0, W, H);
    var near = [];

    for (var i = 0; i < particles.length; i++) {
      var p = particles[i];
      var tx, ty;
      if (p.type === 4) {
        // Dados soltos: atravessam o topo devagar e voltam pelo outro lado
        p.hx += p.drift; p.hy += Math.sin(t * 0.0004 + p.phase) * 0.12;
        if (p.hx > W + 10) p.hx = -10;
        tx = p.hx; ty = p.hy;
      } else {
        tx = p.hx + Math.sin(t * 0.0007 + p.phase) * 1.4;
        ty = p.hy + Math.cos(t * 0.0006 + p.phase) * 1.4;
        tx += (p.sx - tx) * scatter;
        ty += (p.sy - ty) * scatter;
      }

      if (reduce) {
        p.x = tx; p.y = ty;
      } else if (t > p.delay) {
        p.vx += (tx - p.x) * 0.014;
        p.vy += (ty - p.y) * 0.014;
        if (mouse.active) {
          var dx = p.x - mouse.x, dy = p.y - mouse.y, d = Math.hypot(dx, dy);
          if (d < 120 && d > 0.1) {
            var f = (1 - d / 120);
            p.vx += (dx / d) * f * f * 3.2;
            p.vy += (dy / d) * f * f * 3.2;
            if (d < 150 && near.length < 16 && p.type !== 4) near.push(p);
          }
        }
        p.vx *= 0.84; p.vy *= 0.84;
        p.x += p.vx; p.y += p.vy;
      }

      if (wave >= 0 && p.type !== 4 && Math.abs(p.dist - wave) < 16) p.glow = 1;
      p.glow *= 0.94;
    }

    // Linhas do mouse até os pontos mais próximos
    if (near.length) {
      ctx.lineWidth = 1;
      ctx.strokeStyle = '#5fd4c6';
      near.forEach(function (p) {
        var d = Math.hypot(p.x - mouse.x, p.y - mouse.y);
        ctx.globalAlpha = Math.max(0, (1 - d / 150) * 0.45);
        ctx.beginPath(); ctx.moveTo(mouse.x, mouse.y); ctx.lineTo(p.x, p.y); ctx.stroke();
      });
    }

    for (var k = 0; k < particles.length; k++) {
      var q = particles[k];
      var s = STYLE[q.type];
      var fade = q.type === 4 ? 1 : 1 - scatter * 0.6;
      ctx.globalAlpha = Math.min(1, (s.alpha + q.glow * 0.5) * fade);
      ctx.fillStyle = q.glow > 0.35 && q.type === 1 ? '#7ce7d9' : s.color;
      var size = s.size + q.glow * 1.4;
      ctx.fillRect(q.x - size / 2, q.y - size / 2, size, size);
    }
    ctx.globalAlpha = 1;
  }

  function loop(now) {
    if (!running) return;
    frameDraw(now);
    requestAnimationFrame(loop);
  }

  function start() {
    if (reduce || running || !visible || document.hidden) return;
    running = true;
    requestAnimationFrame(loop);
  }
  function stop() { running = false; }

  build();
  if (reduce) {
    frameDraw(performance.now());
  } else {
    start();
    hero.addEventListener('pointermove', function (e) {
      var r = hero.getBoundingClientRect();
      mouse.x = e.clientX - r.left; mouse.y = e.clientY - r.top; mouse.active = e.pointerType === 'mouse';
    });
    hero.addEventListener('pointerleave', function () { mouse.active = false; });
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (entries) {
        visible = entries[0].isIntersecting;
        if (visible) start(); else stop();
      }).observe(hero);
    }
    document.addEventListener('visibilitychange', function () { if (document.hidden) stop(); else start(); });
  }

  var resizeTimer;
  window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () { build(); if (reduce) frameDraw(performance.now()); }, 150);
  });
  // A imagem da tela muda a altura do topo quando termina de carregar.
  var shot = hero.querySelector('.lp-frame img');
  if (shot && !shot.complete) shot.addEventListener('load', function () { build(); });
})();
