/* ÍSIS - aplica o estado salvo do menu lateral antes de desenhar a página (evita o "pulo" ao carregar):
 * menu recolhido e grupos fechados (classe nav-closed--<grupo> no <html>; o CSS esconde os itens). */
(function () {
  'use strict';
  try {
    var root = document.documentElement;
    if (window.localStorage.getItem('isis.sidebar') === 'collapsed') {
      root.classList.add('sidebar-collapsed');
    }
    var closed = JSON.parse(window.localStorage.getItem('isis.nav.closed') || '[]');
    if (Array.isArray(closed)) {
      closed.forEach(function (key) {
        if (/^[a-z0-9-]{1,40}$/.test(key)) root.classList.add('nav-closed--' + key);
      });
    }
  } catch (e) { /* armazenamento indisponível: menu aberto */ }
})();
