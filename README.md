# ÍSIS · Gestão da Privacidade e Proteção de Dados

**LGPD • Governança • Segurança da Informação • Conformidade**

SaaS multiempresa para implantar e gerir a LGPD em organizações de qualquer segmento.
Endereço de produção: **https://isis.aicode.dev.br**. Todas as empresas usam o mesmo domínio, sem subdomínios.

> O ÍSIS apoia a organização da conformidade com a LGPD e **não substitui análise jurídica especializada nem garante conformidade legal**.

## Situação: fase 1 de 5 (núcleo)

| Fase | Conteúdo | Situação |
|---|---|---|
| 1 | Cadastro da empresa, confirmação de e-mail, login, recuperação de acesso, convites, perfis e permissões, configuração inicial por segmento, usuários, setores, painel, perfil, trilha de auditoria com verificação de integridade | **pronta** |
| 2 | Inventário de operações de tratamento, riscos, planos de ação e painel completo | próxima |
| 3 | Solicitações de titulares (com canal público) e incidentes de segurança | — |
| 4 | Fornecedores, políticas e documentos, treinamentos com certificado | — |
| 5 | Backup automatizado, relatórios e refinamentos | — |

## Requisitos

- PHP **8.3+** com as extensões `pdo_mysql`, `sodium`, `mbstring`, `openssl` e `fileinfo`
- MySQL 8.0+ ou MariaDB 10.4+
- Apache com `mod_rewrite` (ou Nginx, veja abaixo) e HTTPS
- Composer (recomendado: instala o PHPMailer para SMTP e o PHPUnit para os testes). Sem Composer o sistema roda, mas os e-mails só vão para o log.

## Instalação no servidor (isis.aicode.dev.br)

1. **Envie os arquivos** para uma pasta fora da raiz pública, por exemplo `/home/usuario/isis`.
2. **Aponte o domínio** `isis.aicode.dev.br` para a pasta `public/` (DocumentRoot = `/home/usuario/isis/public`).
   Se a hospedagem não permitir, o `.htaccess` da raiz redireciona tudo para `public/`, mas o ideal é o DocumentRoot.
3. **Crie o banco e um usuário exclusivo**:
   ```sql
   CREATE DATABASE isis CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'isis'@'localhost' IDENTIFIED BY 'troque-esta-senha';
   GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES, DROP ON isis.* TO 'isis'@'localhost';
   ```
4. **Configure o ambiente**: copie `.env.example` para `.env` e preencha `DB_*`, `MAIL_*` e `APP_URL`.
5. **Instale**. Com acesso SSH:
   ```bash
   composer install --no-dev --optimize-autoloader
   php bin/isis key:generate
   php bin/isis install --demo   # sem --demo em produção com clientes reais
   ```
   **Sem SSH**: gere a chave localmente (`php bin/isis key:generate` ou o valor sugerido pela tela de instalação), coloque `APP_KEY` e um `INSTALL_TOKEN` longo no `.env`, acesse `https://isis.aicode.dev.br/instalar`, informe o token e conclua. O instalador se bloqueia sozinho (`storage/install.lock`). Depois **apague o `INSTALL_TOKEN`** do `.env`.
6. **Permissões**: a pasta `storage/` (logs, sessões, uploads, backups) precisa ser gravável pelo PHP; o restante, só leitura.
7. **Guarde a `APP_KEY`** num cofre fora do servidor. Sem ela os dados criptografados ficam ilegíveis, inclusive nos backups.

### Nginx (alternativa ao Apache)

```nginx
server {
    server_name isis.aicode.dev.br;
    root /home/usuario/isis/public;
    index index.php;
    location / { try_files $uri /index.php?$query_string; }
    location ~ \.php$ { include fastcgi_params; fastcgi_pass unix:/run/php/php8.3-fpm.sock; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; }
    location ~ /\. { deny all; }
}
```

### Atualizações

```bash
php bin/isis migrate        # aplica migrations novas e atualiza o catálogo de segmentos
php bin/isis audit:verify   # confere a integridade da trilha de auditoria de todas as empresas
```

## Contas de demonstração (fictícias)

Criadas com `--demo`. Senha de todas: `Demo@Isis2026`.

| Empresa | Perfil | E-mail |
|---|---|---|
| Clínica Vida Plena (Saúde) | Administrador | mariana.costa@vidaplena.example |
| | Encarregado | rafael.nunes@vidaplena.example |
| | Gestor | beatriz.lima@vidaplena.example |
| | Colaborador | carlos.souza@vidaplena.example |
| | Auditor | helena.prado@vidaplena.example |
| Loja Bom Preço (Comércio) | Administrador | paulo.mendes@bompreco.example |
| | Encarregado | juliana.rocha@bompreco.example |
| | Gestor | andre.martins@bompreco.example |
| | Colaborador | fernanda.alves@bompreco.example |
| | Auditor | ricardo.teles@bompreco.example |

Não use as contas de demonstração num ambiente com dados reais.

## Desenvolvimento local

```bash
cp .env.example .env          # APP_ENV=local, APP_DEBUG=true, SESSION_SECURE_COOKIE=false, MAIL_DRIVER=log
php bin/isis key:generate
php bin/isis install --demo
php -S localhost:8090 -t public bin/dev-router.php
```

Com `MAIL_DRIVER=log`, os e-mails (confirmação, convites, recuperação) ficam em `storage/logs/mail.log`.

## Testes

```bash
composer install
vendor/bin/phpunit
```

- **Unidade** (sem banco): validação (inclui CNPJ alfanumérico), criptografia, matriz de permissões, cadeia de hashes da auditoria, rotas, CSRF e redirecionamentos.
- **Integração** (banco próprio, **apagado e recriado** a cada execução): isolamento entre empresas, cadastro, confirmação de e-mail, bloqueio de login, expiração e revogação de sessão, recuperação de senha, convites e detecção de adulteração na auditoria.
  Por padrão usa o banco `isis_test` com as credenciais do `.env`. Para outro servidor, use `ISIS_TEST_DB_HOST`, `ISIS_TEST_DB_PORT`, `ISIS_TEST_DB_DATABASE`, `ISIS_TEST_DB_USERNAME` e `ISIS_TEST_DB_PASSWORD`. O nome precisa conter `test` (proteção contra apagar o banco de produção). Sem banco disponível, esses testes são ignorados.

## Arquitetura

```
public/            index.php (único ponto de entrada), assets, .htaccess
app/Core/          App, Router, Request, Response, View, Session, Csrf, Validator, Crypto, RateLimiter, Mailer, Logger, Database
app/Auth/          Auth (login e sessão), Role (perfis), Permission, TenantContext (empresa da sessão)
app/Audit/         AuditLogger (registro) e AuditHasher (cadeia de hashes)
app/Repositories/  TenantScopedRepository (filtro obrigatório por tenant_id) e repositórios
app/Services/      AccountService (cadastro, convites, recuperação), Installer
app/Controllers/   telas
app/Views/         templates PHP (toda saída escapada com e())
config/            config.php e routes.php
database/          migrations SQL e seeds (segmentos e demonstração)
bin/isis           comandos de linha de comando
storage/           logs, sessões, uploads e backups (fora da raiz pública)
tests/             PHPUnit
```

### Multiempresa e isolamento

- Banco compartilhado com `tenant_id` em toda tabela institucional.
- A empresa ativa vem **só da sessão autenticada**, nunca da URL ou de formulários.
- `TenantScopedRepository` aplica `WHERE tenant_id = ?` em toda leitura, alteração e exclusão e descarta qualquer `tenant_id` vindo de fora.
- Chaves estrangeiras compostas `(tenant_id, id)`: o próprio banco recusa vincular dados de empresas diferentes.
- Registro de outra empresa responde 404 (não confirma a existência) e a tentativa fica na auditoria (`access.not_found`).

### Segurança

- **Senhas** com Argon2id e política mínima (10+ caracteres, maiúsculas, minúsculas e números).
- **Login**: bloqueio após 5 erros por conta ou 20 por IP em 15 minutos, mensagens genéricas e tempo de resposta igual para e-mails inexistentes.
- **Sessão**: cookie `HttpOnly`, `Secure` e `SameSite=Lax`; ID novo no login; expira após 30 min sem uso e 8 h no total. A "época" da sessão derruba outros dispositivos na troca de senha, na desativação ou em "sair de todos".
- **CSRF**: token em todo POST.
- **XSS**: saída escapada, CSP sem scripts ou estilos inline, `X-Frame-Options`, HSTS, `nosniff` e `Referrer-Policy`.
- **SQL injection**: prepared statements reais em tudo e identificadores validados.
- **Criptografia de campos sensíveis**: libsodium XChaCha20-Poly1305, com a empresa como dado associado, e índice cego HMAC para buscas. Os módulos das próximas fases (titulares, incidentes) usam esse recurso.
- **Tokens** de confirmação, recuperação e convite: aleatórios, de uso único, com validade e guardados só como hash SHA-256.
- **Auditoria** somente de inclusão, com usuário, data e hora (microssegundos), IP, navegador e alterações (antes/depois, com segredos mascarados). A cadeia de hashes por empresa detecta alteração ou exclusão.
- **CSV exportado** protegido contra injeção de fórmulas.
- **Cadastro** com campo-isca contra robôs e limite por IP.

### Perfis

| Perfil | Acesso |
|---|---|
| Administrador | Tudo, inclusive usuários e configurações |
| Encarregado (DPO) | Todos os módulos de conformidade e a auditoria; não administra usuários nem configurações |
| Gestor | Inventário, riscos e planos de ação; leitura dos demais |
| Colaborador | Tarefas atribuídas, treinamentos, políticas e comunicação de incidentes |
| Auditor | Somente leitura de tudo, inclusive da trilha de auditoria |
