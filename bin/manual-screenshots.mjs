#!/usr/bin/env node
/*
 * ÍSIS - gera as imagens das telas usadas no manual (public/assets/img/manual/*.webp).
 *
 * Uso (com o ÍSIS rodando LOCALMENTE com os dados demonstrativos e MAIL_DRIVER=log):
 *   node bin/manual-screenshots.mjs [http://localhost:8091]
 *
 * Abre o Chrome/Edge instalado em modo headless e o controla pelo protocolo DevTools (sem dependências).
 * Usa só as contas FICTÍCIAS de demonstração. Nunca rode contra o servidor de produção:
 * o script cadastra uma empresa de exemplo para fotografar o cadastro e a configuração inicial.
 */

import { spawn } from 'node:child_process';
import { existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const BASE = (process.argv[2] || 'http://localhost:8091').replace(/\/$/, '');
const OUT = join(ROOT, 'public', 'assets', 'img', 'manual');
const PASSWORD = 'Demo@Isis2026';
const PORT = 9333;

if (!/^http:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/.test(BASE)) {
  console.error('Por segurança, rode apenas contra um ÍSIS local (http://localhost:porta).');
  process.exit(1);
}

const browserPath = [
  'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
  'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
  'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
  '/usr/bin/google-chrome', '/usr/bin/chromium',
].find(existsSync);
if (!browserPath) {
  console.error('Chrome ou Edge não encontrado.');
  process.exit(1);
}

const profile = mkdtempSync(join(tmpdir(), 'isis-shots-'));
const browser = spawn(browserPath, [
  '--headless=new', `--remote-debugging-port=${PORT}`, `--user-data-dir=${profile}`,
  '--no-first-run', '--no-default-browser-check', '--hide-scrollbars', '--lang=pt-BR', 'about:blank',
], { stdio: 'ignore' });

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function pageSocketUrl() {
  for (let i = 0; i < 50; i++) {
    try {
      const list = await (await fetch(`http://127.0.0.1:${PORT}/json/list`)).json();
      const page = list.find((t) => t.type === 'page');
      if (page) return page.webSocketDebuggerUrl;
    } catch { /* ainda iniciando */ }
    await sleep(200);
  }
  throw new Error('O navegador não respondeu.');
}

const ws = new WebSocket(await pageSocketUrl());
await new Promise((resolve, reject) => { ws.onopen = resolve; ws.onerror = reject; });
let nextId = 1;
const pending = new Map();
const waiters = [];
ws.onmessage = (event) => {
  const msg = JSON.parse(event.data);
  if (msg.id && pending.has(msg.id)) {
    const { resolve, reject } = pending.get(msg.id);
    pending.delete(msg.id);
    msg.error ? reject(new Error(msg.error.message)) : resolve(msg.result);
  } else if (msg.method) {
    for (const w of [...waiters]) {
      if (w.method === msg.method) {
        waiters.splice(waiters.indexOf(w), 1);
        w.resolve(msg.params);
      }
    }
  }
};
const send = (method, params = {}) => new Promise((resolve, reject) => {
  const id = nextId++;
  pending.set(id, { resolve, reject });
  ws.send(JSON.stringify({ id, method, params }));
});
const once = (method, timeout = 15000) => new Promise((resolve, reject) => {
  const waiter = { method, resolve };
  waiters.push(waiter);
  setTimeout(() => { const i = waiters.indexOf(waiter); if (i >= 0) { waiters.splice(i, 1); reject(new Error(`timeout ${method}`)); } }, timeout);
});

async function evaluate(expression) {
  const result = await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
  if (result.exceptionDetails) throw new Error(result.exceptionDetails.text + ' em: ' + expression.slice(0, 80));
  return result.result.value;
}

async function go(path) {
  const loaded = once('Page.loadEventFired');
  await send('Page.navigate', { url: BASE + path });
  await loaded;
  await sleep(250);
}

/** Envia um formulário via JS e espera a próxima página carregar. */
async function submit(js) {
  const loaded = once('Page.loadEventFired');
  await evaluate(js);
  await loaded;
  await sleep(250);
}

let screen = { width: 1366, height: 768, mobile: false };

async function viewport(width, height, mobile = false) {
  screen = { width, height, mobile };
  await send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: mobile ? 2 : 1, mobile });
}

/** Captura do topo da página até `height`. A janela é esticada para a altura da imagem, assim o menu fixo acompanha. */
async function shot(name, height = 820) {
  const full = await evaluate('Math.max(document.documentElement.scrollHeight, document.body.scrollHeight)');
  const target = Math.max(screen.height, Math.min(height, full));
  await send('Emulation.setDeviceMetricsOverride', { width: screen.width, height: target, deviceScaleFactor: screen.mobile ? 2 : 1, mobile: screen.mobile });
  await evaluate('window.scrollTo(0, 0)');
  await sleep(300);
  const { data } = await send('Page.captureScreenshot', { format: 'webp', quality: 82 });
  writeFileSync(join(OUT, `${name}.webp`), Buffer.from(data, 'base64'));
  await viewport(screen.width, screen.height, screen.mobile);
  console.log(`  ${name}.webp`);
}

async function login(email) {
  await send('Network.clearBrowserCookies');
  await go('/entrar');
  await submit(`(() => { const f = document.querySelector('form[action="/entrar"]'); f.email.value = ${JSON.stringify(email)}; f.password.value = ${JSON.stringify(PASSWORD)}; f.requestSubmit(); })()`);
  const path = await evaluate('location.pathname');
  if (path === '/entrar') throw new Error(`Não foi possível entrar com ${email}. Os dados demonstrativos estão instalados?`);
}

/** Caminho do primeiro link cujo texto contém o trecho. */
const hrefOf = (text) => evaluate(`[...document.querySelectorAll('a.title-link')].find((a) => a.textContent.includes(${JSON.stringify(text)}))?.getAttribute('href')`);

function cnpj() {
  const base = Array.from({ length: 12 }, () => Math.floor(Math.random() * 10));
  for (const len of [12, 13]) {
    let sum = 0;
    let weight = len - 7;
    for (let i = 0; i < len; i++) { sum += base[i] * weight; weight = weight === 2 ? 9 : weight - 1; }
    base.push(sum % 11 < 2 ? 0 : 11 - (sum % 11));
  }
  return base.join('');
}

try {
  mkdirSync(OUT, { recursive: true });
  await send('Page.enable');
  await send('Runtime.enable');
  await send('Network.enable');
  await viewport(1366, 768);
  console.log('Gerando imagens em public/assets/img/manual:');

  // Páginas públicas
  await send('Network.clearBrowserCookies');
  await go('/'); await shot('inicio', 768);
  await go('/cadastro'); await shot('cadastro', 900);
  await go('/entrar'); await shot('login', 768);

  // Cadastro de uma empresa de exemplo -> confirmação -> configuração inicial
  const email = `lucia.andrade${Date.now() % 1000}@escolaexemplo.example`;
  await go('/cadastro');
  await submit(`(() => { const f = document.querySelector('form[action="/cadastro"]');
    f.legal_name.value = 'Escola Exemplo Ensino Ltda.'; f.trade_name.value = 'Escola Exemplo'; f.cnpj.value = '${cnpj()}';
    f.segment_code.value = 'educacao'; f.name.value = 'Lúcia Andrade'; f.email.value = '${email}';
    f.password.value = '${PASSWORD}'; f.password_confirmation.value = '${PASSWORD}'; f.accept_terms.checked = true; f.requestSubmit(); })()`);
  const mailLog = join(ROOT, 'storage', 'logs', 'mail.log');
  const links = existsSync(mailLog) ? [...readFileSync(mailLog, 'utf8').matchAll(/verificar-email\/([A-Za-z0-9_-]+)/g)] : [];
  if (links.length > 0) {
    await go('/verificar-email/' + links[links.length - 1][1]);
    await login(email);
    await go('/configuracao-inicial'); await shot('configuracao-inicial', 1150);
  } else {
    console.log('  (configuração inicial ignorada: e-mail de confirmação não encontrado em storage/logs/mail.log)');
  }

  // Administradora da clínica fictícia
  await login('mariana.costa@vidaplena.example');
  await evaluate("localStorage.setItem('isis.sidebar', 'expanded')");
  await go('/painel'); await shot('painel', 1250);
  await go('/inventario'); await shot('inventario', 900);
  const op = await hrefOf('Prontuário eletrônico');
  await go('/inventario/nova'); await shot('inventario-form', 1200);
  if (op) { await go(op); await shot('inventario-detalhe', 1100); }
  await go('/inventario/catalogos'); await shot('catalogos', 820);
  await go('/riscos'); await shot('riscos', 1000);
  const risk = await hrefOf('Acesso indevido');
  if (risk) { await go(risk); await shot('risco-detalhe', 1000); }
  await go('/riscos/novo'); await shot('risco-form', 1100);
  await go('/planos'); await shot('planos', 900);
  const action = await hrefOf('Revisar perfis');
  if (action) { await go(action); await shot('acao-detalhe', 1100); }
  await go('/titulares'); await shot('titulares', 1000);
  const request = await evaluate(`(() => { const tr = [...document.querySelectorAll('tbody tr')].find((r) => r.textContent.includes('João Pereira'));
    const a = tr && tr.querySelector('a'); return a ? { href: a.getAttribute('href'), protocol: a.textContent.trim() } : null; })()`);
  if (request) { await go(request.href); await shot('titular-detalhe', 1150); }
  await go('/incidentes'); await shot('incidentes', 820);
  const incident = await hrefOf('Planilha de pacientes');
  if (incident) { await go(incident); await shot('incidente-detalhe', 1150); }
  await go('/titular/clinica-vida-plena'); await shot('canal-titular', 1050);
  await go('/relatorios'); await shot('relatorio', 1250);
  await go('/fornecedores'); await shot('fornecedores', 900);
  const vendor = await hrefOf('Laboratório');
  if (vendor) { await go(vendor); await shot('fornecedor-detalhe', 1300); }
  await go('/politicas'); await shot('politicas', 760);
  const policy = await hrefOf('Política de Privacidade');
  if (policy) { await go(policy); await shot('politica-detalhe', 1000); }
  await go('/treinamentos'); await shot('treinamentos', 900);
  const trainingHref = await evaluate(`document.querySelector('a.title-link[href^="/treinamentos/"]')?.getAttribute('href') ?? null`);
  if (trainingHref) {
    await go(trainingHref);
    const cert = await evaluate(`document.querySelector('a[href^="/treinamentos/certificado/"]')?.getAttribute('href') ?? null`);
    if (cert) { await go(cert); await shot('certificado', 860); }
  }
  await go('/usuarios'); await shot('usuarios', 900);
  await go('/configuracoes'); await shot('configuracoes', 820);
  await go('/auditoria'); await shot('auditoria', 900);
  await go('/perfil'); await shot('perfil', 768);
  await evaluate("localStorage.setItem('isis.sidebar', 'collapsed')");
  await go('/painel'); await shot('menu-recolhido', 768);
  await evaluate("localStorage.setItem('isis.sidebar', 'expanded')");

  // Colaborador: só as próprias tarefas
  await login('carlos.souza@vidaplena.example');
  await go('/planos'); await shot('minhas-tarefas', 768);
  await go('/incidentes/novo'); await shot('incidente-comunicar', 860);

  // Auditora: avaliação do treinamento (ela ainda pode refazer)
  if (trainingHref) {
    await login('helena.prado@vidaplena.example');
    await go(trainingHref + '/avaliacao'); await shot('treinamento-avaliacao', 1100);
  }

  // Celular
  await viewport(390, 844, true);
  await login('mariana.costa@vidaplena.example');
  await go('/painel'); await shot('celular-painel', 844);
  await evaluate("document.querySelector('[data-toggle-sidebar]').click()");
  await sleep(400);
  await shot('celular-menu', 844);
  if (request) {
    await go('/titular/clinica-vida-plena/acompanhar');
    await submit(`(() => { const f = document.querySelector('form[action$="/acompanhar"]'); f.protocol.value = ${JSON.stringify(request.protocol)};
      f.email.value = 'joao.pereira@exemplo.example'; f.requestSubmit(); })()`);
    await shot('canal-acompanhar', 1400);
  }

  console.log('Pronto.');
} catch (error) {
  console.error('Erro:', error.message);
  process.exitCode = 1;
} finally {
  try { await send('Browser.close'); } catch { /* já fechado */ }
  ws.close();
  browser.kill();
  await sleep(500);
  try { rmSync(profile, { recursive: true, force: true }); } catch { /* perfil temporário em uso */ }
}
