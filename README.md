# TWS Laravel Starter Kit

Base estrutural reutilizável para projetos Laravel — segurança primeiro, Docker autocontido, convenções rígidas de configuração e testes.

**Stack:** PHP 8.4 · Laravel 13 · PostgreSQL 18 · Redis 8 · Pest 4 · Playwright · Livewire 4 (painel do usuário) · Filament 5 (super admin) · Horizon (filas) · spatie/laravel-backup (backup → R2) · nginx+php-fpm.

## O que é e por que existe

Todo projeto novo começa refazendo as mesmas peças — autenticação, 2FA,
chaves de API, trilha de auditoria com LGPD, uploads, painel do cliente,
super admin, e-mails, backup, filas — e é justamente nelas que mora a maior
parte dos problemas de segurança. Este kit entrega essas peças prontas,
testadas e documentadas, para o tempo (e o token, quando se constrói com IA)
ir só no que é do produto.

O que já vem pronto:

- **Autenticação própria** com duas senhas (login e transação), código por
  e-mail para ação sensível, política de senha configurável e conta
  bloqueada perdendo acesso na próxima requisição.
- **API v1 por par de chaves** (pública + secreta, só hash no banco), scopes
  `recurso:acao`, rotação, expiração por inatividade e limite por chave.
- **Trilha de requisições append-only**, gravada antes de qualquer validação,
  com ID de correlação e redaction de dados sensíveis.
- **Filtro de ataques** (observar ou bloquear), rate limit na borda, proxies e
  hosts confiáveis, cabeçalhos de segurança.
- **Uploads validados pelo conteúdo** (magic bytes, polyglot, re-encode).
- **Painel do usuário** (Livewire) e **super admin** (Filament) com
  dashboards, allowlist de IP e i18n em pt-BR, English e Español.
- **Produção em Docker** que recusa subir sem chave, sem mailer real ou com
  backup sem criptografia, e **demonstração fail-closed** (em produção ela
  não existe, a menos que seja declarada).

As regras que o código segue estão em [Convenções](docs/convencoes.md).

## Requisitos

Apenas **Docker** (com Compose v2+). Nada de PHP, Composer ou Node na máquina.

## Instalação (desenvolvimento)

```bash
git clone <repo> meu-projeto && cd meu-projeto
cp .env.example .env

# 1) Dependências PHP (roda em container, nada local)
docker run --rm -v $(pwd):/app -w /app composer:latest composer install --no-interaction

# 2) Subir a stack
#    Os containers de dev rodam com o uid/gid do SEU usuário (padrão 1000),
#    então tudo que o app grava no volume (logs, cache, uploads, `make:*`)
#    fica editável no host e nada vira root-owned. Se `id -u` não for 1000:
#    export UID GID=$(id -g)   # antes do build
docker compose up -d --build

# 3) Gerar a chave da aplicação no .env e RECRIAR os containers
#    (o compose injeta o .env como variáveis de ambiente no start —
#     editar o .env sem recriar não surte efeito)
docker compose exec app php artisan key:generate --force
docker compose up -d --force-recreate app queue scheduler

# 4) Banco (com os dados de demonstração) e testes
#    (SQLite em memória; contra o PostgreSQL, ver docs/testes.md)
docker compose exec app php artisan migrate --seed
docker compose exec app ./vendor/bin/pest

# 5) (Opcional) Promover um usuário a super admin do /admin:
docker compose exec app php artisan user:make-admin email@exemplo.com
```

Aplicação: http://localhost:8180 · Mailpit: http://localhost:18025

Portas conflitando? Ajuste no `.env` (`DEV_WEB_PORT`, `DEV_POSTGRES_PORT`, `DEV_REDIS_PORT`, `DEV_MAILPIT_*`) e recrie os containers.

## Credenciais demo

Com `DEMO_LOGIN_ENABLED=true` (padrão só em `APP_ENV=local`), o
`migrate --seed` cria duas contas e as telas de login já vêm preenchidas:

| Onde | E-mail | Senha |
| --- | --- | --- |
| Painel do usuário (`/login`) | `demo@tws.dev` | `Demo-password1` |
| Super admin (`/admin`) | `admin@tws.dev` | `Demo-admin-password1` |

As contas são protegidas contra alteração (UI, model e gatilho no
PostgreSQL) e **não existem em produção** — ver [Modo demo](docs/demo.md).

## Comandos do dia a dia (sempre em container)

```bash
docker compose exec app php artisan <comando>        # artisan
docker compose exec app php artisan test             # testes (Pest 4)
docker compose exec app ./vendor/bin/pest -c phpunit.pgsql.xml   # testes contra PostgreSQL
docker compose exec app ./vendor/bin/pint            # estilo de código
docker run --rm -v $(pwd):/app -w /app composer:latest composer <cmd>

# Build do frontend (Node 24 em container; --user evita node_modules e
# public/build com dono root, que o php-fpm não conseguiria sobrescrever):
docker run --rm --user $(id -u):$(id -g) -e HOME=/tmp -v $(pwd):/app -w /app node:24-alpine npm install
docker run --rm --user $(id -u):$(id -g) -e HOME=/tmp -v $(pwd):/app -w /app node:24-alpine npm run build
```

E2E (Playwright) e o banco de teste do PostgreSQL: [Testes](docs/testes.md).

## Documentação

| Assunto | Documento |
| --- | --- |
| Convenções do código (nada hardcoded, dinheiro inteiro, identificadores, i18n) | [docs/convencoes.md](docs/convencoes.md) |
| Segurança: cadeia de middlewares, filtro de ataques, rate limit, nginx, proxies, CSP | [docs/seguranca.md](docs/seguranca.md) |
| Logs e LGPD: redaction, onde ver os logs, append-only, health check | [docs/logs-lgpd.md](docs/logs-lgpd.md) |
| Autenticação, ação sensível e política de senha | [docs/autenticacao.md](docs/autenticacao.md) |
| API v1 e chaves de API | [docs/api.md](docs/api.md) |
| Tenancy e projetos | [docs/tenancy.md](docs/tenancy.md) |
| Uploads seguros | [docs/uploads.md](docs/uploads.md) |
| Painel do usuário, super admin e dashboards | [docs/admin-e-dashboards.md](docs/admin-e-dashboards.md) |
| E-mails transacionais | [docs/emails.md](docs/emails.md) |
| Modo demo e contas demo | [docs/demo.md](docs/demo.md) |
| Interface: landing, showcase `/ui`, i18n, tema, formulários, identidade visual | [docs/interface.md](docs/interface.md) |
| Produção e deploy | [docs/producao.md](docs/producao.md) |
| Testes (Pest em SQLite e PostgreSQL, E2E) | [docs/testes.md](docs/testes.md) |
| Backup | [docs/backup.md](docs/backup.md) |
| Filas (Horizon) | [docs/filas.md](docs/filas.md) |

Vulnerabilidades: [SECURITY.md](SECURITY.md) · Contribuir: [CONTRIBUTING.md](CONTRIBUTING.md).

## Pendências conhecidas / o que este kit não cobre

Decisões de escopo conscientes — não são bugs. Cada uma é para o projeto que
herdar o kit ou para a infraestrutura em volta dele:

1. **Ataque distribuído por muitos IPs**: todos os limites de requisição do
   kit são por IP (ou por chave). Um ataque espalhado por muitos IPs fica
   abaixo de cada teto individual; contra isso a defesa é um **WAF/CDN** na
   borda (ex.: Cloudflare), não a aplicação. Ver
   [Limite de requisições](docs/seguranca.md#limite-de-requisições-rate-limit-e-contenção-da-trilha).
2. **CSP ainda com `'unsafe-inline'`** no `script-src`: remover exige nonces
   (a CSP atual já não usa `unsafe-eval` fora de `/admin` e `/horizon`). Ver
   [CSP e JavaScript](docs/seguranca.md#csp-e-javascript-decisão-documentada).
3. **Demo pública hospedada precisa de banco efêmero**: a senha do admin demo
   é pública; com um banco compartilhado, qualquer visitante altera os dados
   que o próximo vai ver. Uma demo hospedada (`DEMO_ALLOW_IN_PRODUCTION`)
   precisa de banco efêmero ou reset periódico — ou admin somente-leitura.
   Ver [Modo demo](docs/demo.md).
4. **PITR/WAL archiving → R2** (RPO de segundos): camada de
   **infraestrutura** (pgBackRest/WAL-G no PostgreSQL de produção) —
   documentada em [Backup](docs/backup.md), intencionalmente fora da aplicação.
5. **Receptor do webhook de validação cruzada no sandbox**: o contrato do
   payload está em [Backup](docs/backup.md); o endpoint que
   baixa/restaura/valida o dump é responsabilidade do projeto filho.
6. **Canais de verificação TOTP/WhatsApp**: o contrato
   `VerificationChannelDriver` está pronto; hoje só e-mail.
7. **IP allowlist por chave de API** (restringe de onde uma chave vazada pode
   ser usada): essencial quando existirem chaves com permissão de mover
   dinheiro.
8. **Append-only em nível de banco**: `REVOKE UPDATE, DELETE` da role da
   aplicação no PostgreSQL de produção (a imutabilidade hoje é garantida
   pela aplicação — ver [Logs e LGPD](docs/logs-lgpd.md#append-only)).
9. **E-mail autenticado (operação, não código)**: SPF/DKIM/DMARC no domínio
   de envio — sem isso, e-mail com código de verificação é fácil de forjar.
10. **Octane/FrankenPHP**: reavaliar só com volume relevante e auditoria de
    estado entre requisições (singletons e estáticos que sobrevivem à
    requisição podem vazar dado de um tenant para outro) — php-fpm foi
    escolha deliberada.
11. **Pooler em modo transação** (PgBouncer `pool_mode=transaction`): a flag
    de sessão que alinha o gatilho das contas demo com o modo demo não
    sobrevive entre transações — ver [Modo demo](docs/demo.md#contas-demo-são-intocáveis-como-e-por-quê).
12. **Docs públicas da API**: site estático separado — fora do escopo do kit.

## Estrutura

```
docker/
  php/Dockerfile       # PHP-FPM 8.4 multi-stage (dev/prod): pgsql, redis,
                       # intl (icu-data-full p/ pt_BR), bcmath, gd, zip,
                       # opcache, pcntl, sqlite (testes), pg_dump 18 (backups)
  php/*.ini            # configs PHP dev/prod + opcache
  nginx/Dockerfile     # nginx dev (HTTP) e prod (HTTPS + headers OWASP)
docker-compose.yml       # DESENVOLVIMENTO
docker-compose.prod.yml  # PRODUÇÃO autocontida
config/platform.php      # config centralizada da plataforma (nada hardcoded)
lang/{pt_BR,en,es}/      # traduções (pt-BR é o idioma padrão)
app/
  Core/    # tudo que é genérico e reutilizável: Auth, ApiKeys, Tenancy,
           # Security, Logging, Uploads, Money, Identifiers, Http/Resources,
           # Settings (configs editáveis pelo admin), Support (Platform +
           # helpers globais)
  Livewire/   # painel do usuário
  Filament/   # super admin /admin
  Domain/  # regras de negócio do projeto filho
docs/      # documentação por assunto (ver a tabela acima)
tests/     # Pest (Unit/Feature) + e2e/ (Playwright)
```

## Branches

`desenvolvimento` → `sandbox` → `producao`. Nunca commit direto nas protegidas.

## Licença

MIT (ver LICENSE).
