/* ÍSIS - aplica o estado salvo do menu lateral antes de desenhar a página (evita o "pulo" ao carregar). */
(function () {
  'use strict';
  try {
    if (window.localStorage.getItem('isis.sidebar') === 'collapsed') {
      document.documentElement.classList.add('sidebar-collapsed');
    }
  } catch (e) { /* armazenamento indisponível: menu aberto */ }
})();
