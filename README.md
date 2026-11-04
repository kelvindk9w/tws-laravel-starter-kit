# TWS Laravel Starter Kit

Base estrutural reutilizável para projetos Laravel — segurança primeiro, Docker autocontido, convenções rígidas de configuração e testes.

**Stack:** PHP 8.4 · Laravel 13 · PostgreSQL 18 · Redis 8 · Pest 4 · Playwright · Livewire 4 (painel do usuário) · Filament 5 (super admin) · Horizon (filas) · spatie/laravel-backup (backup → R2) · nginx+php-fpm.

> **Beta público (2.0.0-beta).** O escopo está congelado até a 2.0.0 estável:
> só entram correções achadas no uso e a lista do [roteiro](docs/roadmap.md).

## Comece aqui

**Só com o Docker Desktop** (sem PHP, Composer nem Node na máquina): baixe o
[twstec-kit](https://github.com/kelvindk9w/twstec-kit) (Code → Download ZIP),
entre na pasta e rode:

```bash
docker compose run --rm instalar
docker compose up -d
```

Passo a passo, para quem está começando:
[Primeiros passos para iniciantes](starters/kit/README.md#primeiros-passos-para-iniciantes-só-com-o-docker).

**Com PHP 8.4 e Composer na máquina:**

```bash
composer create-project "twstec/kit:^2.0@beta" meu-projeto   # na 2.0.0 estável: twstec/kit
cd meu-projeto
docker compose up -d
```

Os dois caminhos fazem as mesmas perguntas, todas com a resposta sugerida
(Enter aceita): **o nome e o número do projeto** (o endereço vira
`http://<nome>.localhost:<porta>`, e o número separa as portas de vários
projetos na mesma máquina), **a interface** (Livewire ou React), **os
módulos opcionais** (contas e API, uploads, painel `/admin`, webhooks), **o
banco de dados** (PostgreSQL, o recomendado, ou SQLite, o modo protótipo —
sem as travas por privilégio do banco) e **se usa o Redis** (sem ele,
sessão, cache e fila ficam no banco, sem o Horizon). Sai o projeto pronto,
com o Docker de desenvolvimento (site, banco, Redis se escolhido, Mailpit,
fila, agendador e Vite com recarga ao vivo) — ver
[Banco de dados e Redis](docs/instalacao.md#banco-de-dados-e-redis-só-na-criação).

- **Aplicativo Laravel que já existe:** instale o `twstec/kit-foundation` e o
  `twstec/kit-installer` pelo Composer e rode `php artisan tws:add`, que
  acrescenta os outros pacotes — ver [Instalação e módulos](docs/instalacao.md).
- **Atualizar um projeto criado:** `composer update 'twstec/*'` traz os
  textos, e-mails, configurações e telas do kit; `php artisan tws:upgrade
  --check` lista os arquivos que ficam no projeto (Docker, CI, `.env*`,
  scripts) e que a versão nova mudou; a seção "Atenção ao atualizar" de cada
  versão no [CHANGELOG](CHANGELOG.md) diz o que fazer — ver
  [Personalização](docs/personalizacao.md).
- **Sem terminal (CI, scripts), por starter ou com `laravel new`:**
  [Instalação e módulos](docs/instalacao.md).

## O que é e por que existe

Todo projeto novo começa refazendo as mesmas peças — autenticação, 2FA,
chaves de API, trilha de auditoria com LGPD, uploads, painel do cliente,
super admin, e-mails, backup, filas — e é justamente nelas que mora a maior
parte dos problemas de segurança. Este kit entrega essas peças prontas,
testadas e documentadas, para o tempo (e o token, quando se constrói com IA)
ir só no que é do produto.

O que já vem pronto:

- **Autenticação própria** com verificação de e-mail no cadastro (link
  assinado; desligável), duas senhas (login e transação), código por
  e-mail para ação sensível, **verificação em duas etapas opcional no
  login** (código por e-mail, no painel e no `/admin`), política de senha
  configurável e conta bloqueada perdendo acesso na próxima requisição.
- **API v1 por par de chaves** (pública + secreta, só hash no banco), scopes
  `recurso:acao`, rotação, expiração por inatividade e limite por chave.
- **Trilha de requisições append-only**, gravada antes de qualquer validação,
  com ID de correlação e redaction de dados sensíveis.
- **Filtro de ataques** (observar ou bloquear), rate limit na borda, proxies e
  hosts confiáveis, cabeçalhos de segurança.
- **Uploads validados pelo conteúdo** (magic bytes, polyglot, re-encode).
- **Webhooks de saída assinados** (HMAC com carimbo de tempo), com outbox,
  novas tentativas, log de entregas, reenvio auditado e proteção contra SSRF
  (DNS revalidado no envio, conexão no IP conferido).
- **Painel do usuário** (Livewire) e **super admin** (Filament) com
  dashboards, allowlist de IP e i18n em pt-BR, English e Español.
- **Produção em Docker** que recusa subir sem chave, sem mailer real ou com
  backup sem criptografia, e **demonstração fail-closed** (em produção ela
  não existe, a menos que seja declarada).

As regras que o código segue estão em [Convenções](docs/convencoes.md).

## Documentação

| Assunto | Documento |
| --- | --- |
| Instalação e módulos opcionais: o instalador, as combinações, pacotes avulsos, publicação | [docs/instalacao.md](docs/instalacao.md) |
| Convenções do código (nada hardcoded, dinheiro inteiro, identificadores, i18n) | [docs/convencoes.md](docs/convencoes.md) |
| Identidade do projeto: nome, marca de reserva, ícone da aba, cor, idiomas | [docs/identidade.md](docs/identidade.md) |
| Segurança: cadeia de middlewares, filtro de ataques, rate limit, nginx, proxies, CSP | [docs/seguranca.md](docs/seguranca.md) |
| Logs e LGPD: redaction, onde ver os logs, append-only, health check | [docs/logs-lgpd.md](docs/logs-lgpd.md) |
| Autenticação, ação sensível e política de senha | [docs/autenticacao.md](docs/autenticacao.md) |
| API v1 e chaves de API | [docs/api.md](docs/api.md) |
| Tenancy e projetos | [docs/tenancy.md](docs/tenancy.md) |
| Uploads seguros | [docs/uploads.md](docs/uploads.md) |
| Webhooks de saída: assinatura, SSRF, novas tentativas, log e reenvio | [docs/webhooks.md](docs/webhooks.md) |
| Painel do usuário, super admin e dashboards | [docs/admin-e-dashboards.md](docs/admin-e-dashboards.md) |
| E-mails transacionais | [docs/emails.md](docs/emails.md) |
| Personalização: mudar textos, e-mails, configuração e telas do kit sem copiar arquivos (`kit:overrides`) | [docs/personalizacao.md](docs/personalizacao.md) |
| Demonstração (pacote `twstec/kit-demo`), modo demo e contas demo | [docs/demo.md](docs/demo.md) |
| Interface: landing, showcase `/ui`, i18n, tema, formulários, identidade visual | [docs/interface.md](docs/interface.md) |
| Produção e deploy | [docs/producao.md](docs/producao.md) |
| Testes (Pest em SQLite e PostgreSQL, E2E) | [docs/testes.md](docs/testes.md) |
| Backup | [docs/backup.md](docs/backup.md) |
| Filas (Horizon) | [docs/filas.md](docs/filas.md) |

**Estado do kit e roteiro** (o que falta para a 2.0.0 estável e o que vem
depois): [docs/roadmap.md](docs/roadmap.md).

Vulnerabilidades: [SECURITY.md](SECURITY.md) · Contribuir: [CONTRIBUTING.md](CONTRIBUTING.md) · Conduta: [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md).

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
   Ver [Demonstração](docs/demo.md#demo-pública-hospedada-banco-efêmero-e-reset).
4. **PITR/WAL archiving → R2** (RPO de segundos): camada de
   **infraestrutura** (pgBackRest/WAL-G no PostgreSQL de produção) —
   documentada em [Backup](docs/backup.md), intencionalmente fora da aplicação.
5. **Receptor do webhook de validação cruzada no sandbox**: o contrato do
   payload está em [Backup](docs/backup.md); o endpoint que
   baixa/restaura/valida o dump é responsabilidade do projeto filho.
6. **Segundo fator por app autenticador (TOTP), códigos de recuperação e
   WhatsApp**: a verificação em duas etapas da 1.0 usa só o e-mail, e a
   recuperação é o próprio e-mail (ver
   [Autenticação](docs/autenticacao.md#verificação-em-duas-etapas-no-login-opcional)).
   A evolução natural é o TOTP com códigos de recuperação de uso único — o
   contrato `VerificationChannelDriver` e o motor `VerificationCodes` já
   separam canal de fluxo.
7. **IP allowlist por chave de API** (restringe de onde uma chave vazada pode
   ser usada): essencial quando existirem chaves com permissão de mover
   dinheiro.
8. **Append-only em nível de banco**: `REVOKE UPDATE, DELETE` da role da
   aplicação no PostgreSQL de produção para `request_logs` (a imutabilidade
   dela hoje é garantida pela aplicação; a de `audit_events` já tem gatilho
   no banco — ver [Logs e LGPD](docs/logs-lgpd.md#append-only)).
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

## Desenvolver o kit

Este repositório é o monorepo do kit (pacotes, starters e documentação).
Como montar o ambiente, rodar os testes e enviar mudanças:
[CONTRIBUTING.md](CONTRIBUTING.md).

## Versões

O histórico de mudanças por versão está em [CHANGELOG.md](CHANGELOG.md). Cada versão estável tem uma tag `vX.Y.Z` na branch `producao`.

## Licença

MIT (ver [LICENSE](LICENSE)).
