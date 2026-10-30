# ÍSIS · Gestão da Privacidade e Proteção de Dados

**LGPD • Governança • Segurança da Informação • Conformidade**

SaaS multiempresa para implantar e gerir a LGPD em organizações de qualquer segmento.
Endereço de produção: **https://isis.aicode.dev.br**. Todas as empresas usam o mesmo domínio, sem subdomínios.

> O ÍSIS apoia a organização da conformidade com a LGPD e **não substitui análise jurídica especializada nem garante conformidade legal**.

## Situação: MVP completo (versão 1.0.0)

O manual do usuário fica dentro do sistema, em `/manual`, com imagens das telas. Ele é atualizado a cada mudança.

| Fase | Conteúdo | Situação |
|---|---|---|
| 1 | Cadastro da empresa, confirmação de e-mail, login, recuperação de acesso, convites, perfis e permissões, configuração inicial por segmento, usuários, setores, painel, perfil, trilha de auditoria com verificação de integridade | **pronta** |
| 2 | Inventário de operações de tratamento (registro do art. 37 com exportação CSV), catálogos, riscos (matriz 5×5), planos de ação com histórico, evidências com hash SHA-256 e painel de conformidade completo | **pronta** |
| 3 | Solicitações de titulares (protocolo, prazo de 15 dias, resposta por e-mail, dados criptografados), canal público `/titular/<empresa>` com acompanhamento e incidentes de segurança (classificação, investigação criptografada, prazo de 3 dias úteis para a ANPD) | **pronta** |
| 4 | Fornecedores (avaliação com nota e risco, contratos, revisão), políticas e documentos (versões, aprovação, validade, ciência, modelos) e treinamentos (avaliação, designação, certificado com verificação pública em `/certificado/<código>`) | **pronta** |
| 5 | Backup criptografado com rotação e restauração, relatório de conformidade com exportações CSV, lembretes diários por e-mail, manutenção e verificação do ambiente | **pronta** |

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

Envie os arquivos novos sem sobrescrever o `.env` e a pasta `storage/` do servidor. Depois rode:

```bash
php bin/isis migrate        # aplica migrations novas e atualiza os catálogos (segmentos e hipóteses legais)
php bin/isis seed:demo      # opcional: completa as empresas fictícias com os dados dos módulos novos
php bin/isis audit:verify   # confere a integridade da trilha de auditoria de todas as empresas
```

**A `APP_KEY` do servidor precisa ser a mesma usada para gravar os dados criptografados.** Isso vale para os dados do titular, os detalhes de incidentes e os dados demonstrativos gravados a partir de outra máquina. Se a chave for diferente, esses campos aparecem como "conteúdo protegido indisponível".

## Operação (rotina diária)

Agende **uma vez por dia**, de madrugada, o comando que faz backup, rotação, lembretes e limpeza:

```bash
cd /home/USUARIO/isis && php bin/isis diario >> storage/logs/rotina.log 2>&1
```

- **cPanel:** em *Cron Jobs*, escolha "Uma vez por dia" (ex.: 02:30). Use o caminho completo do PHP 8.3 se o `php` padrão do servidor for outro (ex.: `/usr/local/bin/ea-php83` ou `/opt/cpanel/ea-php83/root/usr/bin/php`).
- **Linux (crontab -e):**
  ```
  30 2 * * * cd /home/USUARIO/isis && php bin/isis diario >> storage/logs/rotina.log 2>&1
  ```
- **Windows (Agendador de Tarefas):** programa `php.exe`, argumentos `bin\isis diario`, iniciar em: pasta do ÍSIS.

### Backups

| Comando | Para quê |
|---|---|
| `php bin/isis backup` | Cria o backup agora (`--sem-arquivos` deixa as evidências de fora) |
| `php bin/isis backup:listar` | Lista os backups em `storage/backups` |
| `php bin/isis backup:restaurar <arquivo> --banco=novo_banco` | Restaura num banco **vazio** (`--force` substitui um banco com dados; `--sem-arquivos` não restaura as evidências) |
| `php bin/isis backup:decifrar <arquivo> <saida.zip>` | Gera um ZIP comum (`database.sql`, evidências e `manifest.json`) para conferência. Apague o ZIP depois, porque ele contém dados em claro. |

- **O que vai no backup:** banco completo (exportação SQL feita em PHP, sem depender do `mysqldump`) e evidências.
- **Formato:** compactado e criptografado com libsodium (secretstream XChaCha20-Poly1305), com chave derivada da `APP_KEY`.
- **Retenção:** os últimos `BACKUP_KEEP_DAILY` backups (padrão 7) e, além deles, um por semana das `BACKUP_KEEP_WEEKLY` semanas anteriores (padrão 4).
- **Integridade:** o hash do SQL é conferido na restauração. Um arquivo alterado ou decifrado com outra chave é recusado.
- **Leve cópias para fora do servidor regularmente**, por exemplo baixando `storage/backups` ou com a sincronização da hospedagem. Sem a `APP_KEY` os backups não podem ser abertos: guarde a chave num cofre separado.

### Outros comandos

| Comando | Para quê |
|---|---|
| `php bin/isis lembretes` | Envia o resumo diário de pendências por e-mail: um por pessoa por dia, só para quem tem pendências |
| `php bin/isis manutencao` | Remove tentativas de login antigas, tokens vencidos, sessões expiradas e temporários |
| `php bin/isis verificar` | Confere requisitos, banco, migrations, integridade da auditoria, backup recente, SMTP e modo de depuração |

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

São 95 testes. A fase 5 testa backup e restauração com contagem de registros, a recusa de backup adulterado, os lembretes (sem duplicar no mesmo dia) e o relatório. A fase 4 inclui regras de avaliação de fornecedores, fluxo de versões e ciência de políticas, correção da avaliação de treinamento, certificado e isolamento entre empresas.

- **Unidade** (sem banco): validação (inclui CNPJ alfanumérico), criptografia, matriz de permissões, cadeia de hashes da auditoria, rotas, CSRF, redirecionamentos, matriz de risco, pendências do inventário e prazos (15 dias corridos e 3 dias úteis).
- **Integração** (banco próprio, **apagado e recriado** a cada execução):
  - isolamento entre empresas em todos os módulos;
  - cadastro, confirmação de e-mail, bloqueio de login, sessão e recuperação de senha;
  - convites e adulteração da auditoria;
  - escopo do gestor e do colaborador;
  - evidências (tipos recusados e hash);
  - criptografia dos dados do titular e dos incidentes;
  - canal público e acompanhamento por protocolo;
  - prazos da ANPD;
  - exclusão de setores e operações com vínculos.
  Por padrão usa o banco `isis_test` com as credenciais do `.env`. Para outro servidor, use `ISIS_TEST_DB_HOST`, `ISIS_TEST_DB_PORT`, `ISIS_TEST_DB_DATABASE`, `ISIS_TEST_DB_USERNAME` e `ISIS_TEST_DB_PASSWORD`. O nome precisa conter `test` (proteção contra apagar o banco de produção). Sem banco disponível, esses testes são ignorados.

## Arquitetura

```
public/            index.php (único ponto de entrada), assets, .htaccess
app/Core/          App, Router, Request, Response, View, Session, Csrf, Validator, Crypto, RateLimiter, Mailer, Logger, Database
app/Auth/          Auth (login e sessão), Role (perfis), Permission, TenantContext (empresa da sessão)
app/Audit/         AuditLogger (registro) e AuditHasher (cadeia de hashes)
app/Repositories/  TenantScopedRepository (filtro obrigatório por tenant_id) e repositórios
app/Services/      AccountService, RequestService, EvidenceService, RiskMatrix, AccessScope, CatalogService, Installer
app/Support/       Labels (rótulos), Deadline (prazos), Text
app/Controllers/   telas
app/Views/         templates PHP (toda saída escapada com e())
config/            config.php e routes.php
database/          migrations SQL e seeds (segmentos e demonstração)
bin/isis           comandos de linha de comando
bin/manual-screenshots.mjs  gera as imagens do manual (Chrome headless, só contra o ÍSIS local)
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
- **Criptografia de campos sensíveis**: libsodium XChaCha20-Poly1305, com a empresa como dado associado, e índice cego HMAC para buscas. São criptografados os dados do titular (nome, contatos, documento, pedido e resposta) e os detalhes de incidentes (descrição, contenção, causa e lições).
- **Tokens** de confirmação, recuperação e convite: aleatórios, de uso único, com validade e guardados só como hash SHA-256.
- **Auditoria** somente de inclusão, com usuário, data e hora (microssegundos), IP, navegador e alterações (antes/depois, com segredos mascarados). A cadeia de hashes por empresa detecta alteração ou exclusão.
- **CSV exportado** protegido contra injeção de fórmulas.
- **Cadastro e canal do titular** com campo-isca contra robôs e limite por IP. O acompanhamento exige protocolo e e-mail.

### Perfis

| Perfil | Acesso |
|---|---|
| Administrador | Tudo, inclusive usuários e configurações |
| Encarregado (DPO) | Todos os módulos de conformidade e a auditoria; não administra usuários nem configurações |
| Gestor | Inventário, riscos e planos de ação: vê tudo, mas só altera o que é do seu setor, o que criou ou o que está sob sua responsabilidade. Vê solicitações e incidentes. |
| Colaborador | Vê e atualiza só as próprias tarefas. Comunica incidentes e acompanha os que comunicou. Lê as políticas vigentes, registra ciência e faz os treinamentos designados. |
| Auditor | Somente leitura de tudo, inclusive da trilha de auditoria |

### Evidências

- **Formatos:** PDF, PNG/JPG, TXT/CSV, Office e LibreOffice, até 10 MB. O tipo é conferido pelo conteúdo real do arquivo.
- **Armazenamento:** `storage/uploads/<empresa>/`, com nome aleatório e fora da raiz pública. O hash SHA-256 de cada arquivo é registrado.
- **Download:** só depois de conferir a empresa e a permissão.
- **Servidor:** se o PHP limitar uploads a 2 MB, ajuste `upload_max_filesize` e `post_max_size` (ex.: 12M).

### Prazos de referência

- **Solicitações de titulares:** 15 dias corridos (art. 19, II).
- **Comunicação de incidente à ANPD:** 3 dias úteis (Res. CD/ANPD nº 15/2024). Feriados não são considerados.

São referências de apoio, não parecer jurídico.
