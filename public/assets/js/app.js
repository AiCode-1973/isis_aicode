/* ÍSIS - interações da interface (sem dependências; CSP: script-src 'self'). */
(function () {
  'use strict';

  // Menu lateral: no computador recolhe/expande (estado salvo); no celular abre por cima do conteúdo.
  var mobile = window.matchMedia('(max-width: 960px)');
  var root = document.documentElement;
  function syncToggle(btn) {
    var expanded = mobile.matches ? document.body.classList.contains('sidebar-open') : !root.classList.contains('sidebar-collapsed');
    btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    btn.setAttribute('aria-label', mobile.matches ? (expanded ? 'Fechar menu' : 'Abrir menu') : (expanded ? 'Recolher menu' : 'Expandir menu'));
  }
  document.querySelectorAll('[data-toggle-sidebar]').forEach(function (btn) {
    syncToggle(btn);
    btn.addEventListener('click', function () {
      if (mobile.matches) {
        document.body.classList.toggle('sidebar-open');
      } else {
        var collapsed = root.classList.toggle('sidebar-collapsed');
        try { window.localStorage.setItem('isis.sidebar', collapsed ? 'collapsed' : 'expanded'); } catch (e) { /* sem armazenamento */ }
      }
      syncToggle(btn);
    });
    mobile.addEventListener('change', function () { syncToggle(btn); });
  });
  // Grupos do menu: o título abre e fecha os itens; os fechados ficam salvos neste navegador.
  function closedGroups() {
    try {
      var list = JSON.parse(window.localStorage.getItem('isis.nav.closed') || '[]');
      return Array.isArray(list) ? list : [];
    } catch (e) { return []; }
  }
  document.querySelectorAll('[data-nav-group]').forEach(function (group) {
    var btn = group.querySelector('.nav-section');
    var list = group.querySelector('.nav');
    var key = group.getAttribute('data-nav-group');
    if (!btn || !list) return;
    var isOpen = function () {
      return group.classList.contains('is-open') || (!group.classList.contains('is-closed')
        && (group.classList.contains('has-active') || !root.classList.contains('nav-closed--' + key)));
    };
    btn.setAttribute('aria-expanded', isOpen() ? 'true' : 'false');
    btn.addEventListener('click', function () {
      var open = !isOpen();
      group.classList.toggle('is-open', open);
      group.classList.toggle('is-closed', !open);
      root.classList.remove('nav-closed--' + key);
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      var closed = closedGroups().filter(function (k) { return k !== key; });
      if (!open) closed.push(key);
      try { window.localStorage.setItem('isis.nav.closed', JSON.stringify(closed)); } catch (e) { /* sem armazenamento */ }
    });
  });

  // Ajuda da tela: fecha pelo X; nos formulários, marca obrigatório/opcional e põe o "?" com explicação e exemplo.
  document.querySelectorAll('[data-help-close]').forEach(function (btn) {
    btn.addEventListener('click', function () { var d = btn.closest('details'); if (d) d.removeAttribute('open'); });
  });
  (function decorateFields() {
    var main = document.getElementById('main');
    if (!main) return;
    var seq = 0;
    function labelFor(control) {
      if (control.id) {
        var byFor = main.querySelector('label[for="' + control.id + '"]');
        if (byFor) return byFor;
      }
      var name = control.getAttribute('name') || '';
      if (control.type === 'checkbox' && name.slice(-2) !== '[]' && control.closest('label')) return control.closest('label');
      var fieldset = control.closest('fieldset');
      if (fieldset && fieldset.querySelector('legend')) return fieldset.querySelector('legend');
      var field = control.closest('.field');
      if (!field) return null;
      for (var i = 0; i < field.children.length; i++) {
        var child = field.children[i];
        if (child.matches('label:not(.check), .label, legend')) return child;
      }
      return null;
    }
    document.querySelectorAll('[data-help-field]').forEach(function (item) {
      var name = item.getAttribute('data-help-field');
      var required = item.getAttribute('data-help-required') === '1';
      var dd = item.querySelector('dd');
      main.querySelectorAll('[name="' + name + '"]').forEach(function (control) {
        if (control.type === 'hidden' || control.closest('.filters')) return;
        var label = labelFor(control);
        if (!label || label.getAttribute('data-helped')) return;
        label.setAttribute('data-helped', '1');
        var host = label.matches('.check') ? (label.querySelector('span') || label) : label;
        if (required) {
          control.setAttribute('aria-required', 'true');
          var form = control.closest('form');
          if (form) form.setAttribute('data-has-required', '1');
        }
        if (required || label.textContent.toLowerCase().indexOf('opcional') === -1) {
          var tag = document.createElement('span');
          tag.className = 'field-tag' + (required ? ' req' : '');
          tag.textContent = required ? 'obrigatório' : 'opcional';
          host.appendChild(tag);
        }
        if (!dd || dd.textContent.trim() === '') return;
        var id = 'fh-' + (++seq);
        var bubble = document.createElement('div');
        bubble.className = 'field-help';
        bubble.id = id;
        bubble.hidden = true;
        Array.prototype.forEach.call(dd.children, function (part) { bubble.appendChild(part.cloneNode(true)); });
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'field-help-btn';
        btn.textContent = '?';
        btn.setAttribute('aria-expanded', 'false');
        btn.setAttribute('aria-controls', id);
        btn.setAttribute('aria-label', 'Ajuda: ' + (item.querySelector('.help-label') || item).textContent.trim());
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          bubble.hidden = !bubble.hidden;
          btn.setAttribute('aria-expanded', bubble.hidden ? 'false' : 'true');
        });
        host.appendChild(btn);
        label.insertAdjacentElement('afterend', bubble);
      });
    });
    main.querySelectorAll('form[data-has-required]').forEach(function (form) {
      if (form.querySelectorAll('.field-tag.req').length === 0 || form.querySelector('.form-legend')) return;
      var note = document.createElement('p');
      note.className = 'form-legend';
      note.textContent = 'Campos marcados como "obrigatório" precisam ser preenchidos para salvar. Clique em ? ao lado de um campo para ver uma explicação e um exemplo.';
      var first = Array.prototype.find.call(form.children, function (c) { return !(c.matches('input[type=hidden]')); });
      form.insertBefore(note, first || null);
    });
  })();

  document.querySelectorAll('[data-close-sidebar]').forEach(function (el) {
    el.addEventListener('click', function () { document.body.classList.remove('sidebar-open'); });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      document.body.classList.remove('sidebar-open');
      document.querySelectorAll('details.user-menu[open], details.dropdown[open], details.help[open]').forEach(function (d) { d.removeAttribute('open'); });
    }
  });

  // Fecha o menu do usuário ao clicar fora
  document.addEventListener('click', function (e) {
    document.querySelectorAll('details.user-menu[open], details.dropdown[open], details.help[open]').forEach(function (d) {
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

  // Campos só de leitura que se selecionam inteiros ao clicar (ex.: link do canal do titular)
  document.querySelectorAll('[data-select-on-focus]').forEach(function (input) {
    input.addEventListener('focus', function () { input.select(); });
  });

  // Selects que aplicam o filtro ao mudar (ex.: período do relatório)
  document.querySelectorAll('select[data-autosubmit]').forEach(function (select) {
    select.addEventListener('change', function () { if (select.form) select.form.submit(); });
  });

  // Imprimir (ou salvar em PDF) — usado no manual
  document.querySelectorAll('[data-print]').forEach(function (btn) {
    btn.addEventListener('click', function () { window.print(); });
  });

  // Linha de tabela inteira clicável: leva ao link principal da linha (o título)
  document.querySelectorAll('tbody tr').forEach(function (tr) {
    var link = tr.querySelector('a.title-link');
    if (!link) return;
    tr.classList.add('row-link');
    tr.addEventListener('click', function (e) {
      if (e.target.closest('a, button, input, select, textarea, label, form, summary')) return;
      if (window.getSelection && String(window.getSelection()).length > 0) return;
      if (e.ctrlKey || e.metaKey) { window.open(link.href, '_blank'); return; }
      window.location.href = link.href;
    });
  });

  // Filtros recolhidos no celular, com a quantidade de filtros em uso
  var small = window.matchMedia('(max-width: 720px)');
  document.querySelectorAll('form.filters').forEach(function (form) {
    var active = Array.prototype.filter.call(form.elements, function (el) {
      return el.name && ((el.type === 'checkbox' && el.checked) || (el.type !== 'checkbox' && el.type !== 'submit' && el.value !== ''));
    }).length;
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-ghost filters-toggle';
    btn.setAttribute('aria-expanded', 'false');
    btn.textContent = active ? 'Filtros (' + active + ')' : 'Filtros';
    form.parentNode.insertBefore(btn, form);
    function sync() { form.classList.toggle('is-collapsed', small.matches && btn.getAttribute('aria-expanded') !== 'true'); }
    btn.addEventListener('click', function () {
      btn.setAttribute('aria-expanded', btn.getAttribute('aria-expanded') === 'true' ? 'false' : 'true');
      sync();
    });
    small.addEventListener('change', sync);
    sync();
  });

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
