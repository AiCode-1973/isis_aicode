# Envio ao servidor · versões 1.1 a 1.10.0

Pacote: `deploy/isis-1.10.0.zip` (gerado em 04/11/2026, versão 1.10.0). Contém `app/`, `bin/isis`, `config/`, `database/`, `public/` (com as fontes Inter), `vendor/` só com as dependências de produção, `.htaccess`, `.env.example`, `composer.json`, `composer.lock` e `README.md`.

**Não contém** `.env`, `storage/`, testes nem documentos internos. Por isso, ao extrair por cima, a configuração, os uploads, os backups e as sessões do servidor ficam como estão.

O banco de produção está atualizado até a migration 009. A versão 1.7 traz a **migration 010** (`010_assistente_configuracao`), que só acrescenta a coluna `onboarding_answers` em `tenants`: ela é aplicada pelo `migrate` do passo 4, depois de extrair os arquivos. Os arquivos antigos continuam funcionando com ela.

Nos comandos abaixo, `PHP` é o PHP 8.3 do servidor, por exemplo `/usr/local/bin/ea-php83`, e a pasta do ÍSIS é `~/isis`. Ajuste se for outra.

## 1. Antes de enviar (5 minutos)

No Terminal do cPanel:

```bash
cd ~/isis && /usr/local/bin/ea-php83 bin/isis backup
```

Confira se apareceu "Backup criado". Se quiser uma garantia extra, baixe também uma cópia do banco pelo cPanel (*Backup* ou *phpMyAdmin › Exportar*).

## 2. Enviar e extrair

1. cPanel › *Gerenciador de Arquivos* › pasta `isis`.
2. *Carregar* › escolha `isis-1.10.0.zip`.
3. Clique com o botão direito no zip › *Extract* › destino: a própria pasta `isis` (ex.: `/home/USUARIO/isis`). Confirme a substituição dos arquivos.
4. Apague o `isis-1.10.0.zip` do servidor.

Arquivos de versões antigas que sobrarem não atrapalham.

## 3. Conferir o `.env` do servidor

Abra `~/isis/.env` no editor do cPanel e confirme:

| Chave | Valor em produção |
|---|---|
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://isis.aicode.dev.br` |
| `SESSION_SECURE_COOKIE` | `true` |
| `MAIL_DRIVER` e `MAIL_*` | `smtp` e os dados da conta de e-mail (ver README, seção "E-mail (SMTP)") |
| `INSTALL_TOKEN` | vazio |

Não troque a `APP_KEY`.

## 4. Rodar os comandos de atualização

```bash
cd ~/isis
/usr/local/bin/ea-php83 bin/isis migrate
/usr/local/bin/ea-php83 bin/isis audit:verify
/usr/local/bin/ea-php83 bin/isis verificar
```

- `migrate` deve responder "Migrations aplicadas: 010_assistente_configuracao". Se responder "Duplicate column onboarding_answers", a coluna já existe: avise antes de continuar.
- `audit:verify` deve mostrar todas as cadeias "íntegra".
- `verificar` lista o que ainda falta. Os pontos esperados nesta primeira vez são: identificação no aviso de privacidade (resolvida no passo 6), SMTP (se ainda não configurado) e backup recente (resolvido pelo cron).

## 5. Agendar a rotina diária (se ainda não estiver)

cPanel › *Cron Jobs* › "Uma vez por dia" (ex.: 02:30):

```bash
cd /home/USUARIO/isis && /usr/local/bin/ea-php83 bin/isis diario >> storage/logs/rotina.log 2>&1
```

## 6. Testar no navegador

1. `https://isis.aicode.dev.br`: página inicial nova, fonte Inter e seção de planos.
2. Rodapé: Termos de uso, Aviso de privacidade e Acordo de tratamento de dados abrem.
3. `https://isis.aicode.dev.br/plataforma/entrar`: entre com a senha. **O painel vai pedir para cadastrar o aplicativo autenticador.** Tenha o celular em mãos, leia o QR code, digite o código e **guarde os 10 códigos de recuperação** (imprima ou anote em papel ou num gerenciador de senhas).
4. Em *Configurações da plataforma › Documentos legais*, preencha razão social, CNPJ, endereço, e-mail de privacidade, encarregado e suboperadores (hospedagem e e-mail).
5. Entre com uma conta de empresa e confira o painel com o índice de maturidade, o RIPD e a verificação em duas etapas em *Meu perfil*.
6. Com o administrador de uma empresa, abra `https://isis.aicode.dev.br/configuracao-inicial?refazer=1`: o assistente em cinco etapas deve abrir. Empresas que já tinham concluído a configuração antiga não são obrigadas a refazê-lo. Só avance até o plano se quiser aplicar as respostas: ao concluir, os convites marcados são enviados de verdade.
7. No menu *Visão geral*, abra **O que preciso fazer?**: a lista deve mostrar as pendências da empresa agrupadas por prioridade.
8. Abra *Inventário › Nova operação* e clique em **Ajuda** (alto da tela): o painel deve abrir, e os campos devem mostrar "obrigatório"/"opcional" e o botão ?. Abra também *Políticas › Nova política*: o formulário deve aparecer (antes abria em branco).
9. Abra *Bibliotecas › Modelos prontos*, escolha um modelo de risco e clique em **Usar este modelo**: o formulário deve abrir preenchido (não salve, se for só um teste).
10. Rode `bin/isis verificar` de novo.

## Se algo der errado

- **Página em branco ou erro 500:** veja `~/isis/storage/logs/`. Confirme que a pasta `vendor/` foi extraída e que o PHP do domínio é o 8.3.
- **Perdeu o celular antes de guardar os códigos:** `cd ~/isis && /usr/local/bin/ea-php83 bin/isis plataforma:2fa-redefinir seu@email`.
- **Voltar à versão anterior:** extraia de novo os arquivos antigos. O banco continua compatível, porque as colunas novas são ignoradas pelos arquivos antigos. Para restaurar os dados, use o backup do passo 1 (`bin/isis backup:restaurar`; veja o README).
