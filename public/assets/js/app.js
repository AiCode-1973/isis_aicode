/* ISIS - interações da interface (sem dependências; CSP: script-src 'self'). */
(function () {
  'use strict';

  // Menu lateral no celular
  document.querySelectorAll('[data-toggle-sidebar]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var open = document.body.classList.toggle('sidebar-open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  });
  document.querySelectorAll('[data-close-sidebar]').forEach(function (el) {
    el.addEventListener('click', function () { document.body.classList.remove('sidebar-open'); });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      document.body.classList.remove('sidebar-open');
      document.querySelectorAll('details.user-menu[open]').forEach(function (d) { d.removeAttribute('open'); });
    }
  });

  // Fecha o menu do usuário ao clicar fora
  document.addEventListener('click', function (e) {
    document.querySelectorAll('details.user-menu[open]').forEach(function (d) {
      if (!d.contains(e.target)) d.removeAttribute('open');
    });
  });

  // Confirmação antes de ações sensíveis: <form data-confirm="Mensagem">
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (!window.confirm(form.getAttribute('data-confirm'))) e.preventDefault();
    });
  });

  // Mostrar/ocultar senha
  document.querySelectorAll('[data-password-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = document.getElementById(btn.getAttribute('data-password-toggle'));
      if (!input) return;
      var show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.setAttribute('aria-pressed', show ? 'true' : 'false');
      btn.setAttribute('aria-label', show ? 'Ocultar senha' : 'Mostrar senha');
    });
  });

  // Evita duplo envio
  document.querySelectorAll('form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (e.defaultPrevented) return;
      var btn = form.querySelector('button[type=submit][data-loading]');
      if (btn) {
        btn.disabled = true;
        btn.textContent = btn.getAttribute('data-loading');
      }
    });
  });

  // Assistente: trocar o segmento recarrega as sugestões
  var segment = document.querySelector('[data-segment-reload]');
  if (segment) {
    segment.addEventListener('change', function () {
      window.location.href = '/configuracao-inicial?segmento=' + encodeURIComponent(segment.value);
    });
  }

  // Máscara leve de CNPJ (aceita o novo formato alfanumérico)
  document.querySelectorAll('[data-mask=cnpj]').forEach(function (input) {
    input.addEventListener('input', function () {
      var v = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 14);
      var out = v.slice(0, 2);
      if (v.length > 2) out += '.' + v.slice(2, 5);
      if (v.length > 5) out += '.' + v.slice(5, 8);
      if (v.length > 8) out += '/' + v.slice(8, 12);
      if (v.length > 12) out += '-' + v.slice(12, 14);
      input.value = out;
    });
  });
})();
