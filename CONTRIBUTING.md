# Contribuindo

Obrigado pelo interesse. O kit está na linha **2.x estável**: issues
com defeitos, dúvidas de uso e sugestões são bem-vindas, e pull requests
também — para mudança grande, abra uma issue antes para combinar o caminho.
Ao participar, você concorda com o [código de conduta](CODE_OF_CONDUCT.md).
Ao enviar um pull request, você concorda em licenciar a contribuição sob a
[licença MIT](LICENSE) do projeto.

Regras curtas:

1. **Branches**: abra o pull request contra `desenvolvimento`; `sandbox` e
   `producao` são protegidas e só recebem fast-forward com o CI verde, na
   publicação de cada versão.
2. **Ambiente**: uma vez por clone, `scripts/preparar-dev` na raiz (cria o
   `.env` dos starters e o `.env` da raiz com as senhas de desenvolvimento do
   PostgreSQL e do Redis); depois, `docker compose up -d` (ver [Instalação](#instalação-desenvolvimento)). As
   portas ficam só em 127.0.0.1.
3. **Antes de abrir o PR**: `./vendor/bin/pint`,
   `./vendor/bin/phpstan analyse` (Larastan nível 8, sem baseline),
   `./vendor/bin/pest` e `npm run build` passando (tudo em container, rodando no starter —
   `starters/livewire` —, ver [Comandos do dia a dia](#comandos-do-dia-a-dia-sempre-em-container)).
4. **Convenções**: código em inglês, comentários e docs em português;
   nada hardcoded (config/platform.php e .env); toda string de UI passa por
   `__()` nos três idiomas; todo comportamento novo vem com teste.
5. **Segurança**: vulnerabilidades vão pelo canal privado (SECURITY.md),
   nunca por issue.
6. **Commits**: mensagem no imperativo, descrevendo o comportamento
   (`feat(admin): ...`, `fix(api): ...`).

## Desenvolver o kit (o monorepo)

Tudo o que segue é sobre o **desenvolvimento do próprio kit**. Para criar um
projeto com ele, veja o [README](README.md#comece-aqui).

### Como o repositório está organizado

Este repositório é um **monorepo**: o kit está sendo separado em pacotes
reutilizáveis e em pontos de partida (starters) de interface.

| Pasta | O que tem |
| --- | --- |
| [`starters/livewire/`](starters/livewire) | O aplicativo completo com painel em Livewire e super admin em Filament — é o kit que você roda hoje. |
| [`starters/kit/`](starters/kit) | O comando único, `twstec/kit`: `composer create-project twstec/kit` pergunta a interface e os módulos e entrega o projeto do starter escolhido. Não é um aplicativo — ele dá lugar ao starter. |
| [`starters/react/`](starters/react) | O mesmo kit com o painel em React + Inertia + TypeScript + shadcn/ui, a partir do kit oficial do Laravel, e a autenticação dos pacotes: as mesmas telas do Livewire, E2E próprio e imagem de produção própria (`twstec/starter-react`). |
| [`packages/`](packages) | Os pacotes do kit: [`foundation`](packages/foundation) (`twstec/kit-foundation`) e [`auth`](packages/auth) (`twstec/kit-auth`), que vêm sempre; [`accounts`](packages/accounts) (`twstec/kit-accounts`), [`uploads`](packages/uploads) (`twstec/kit-uploads`), [`webhooks`](packages/webhooks) (`twstec/kit-webhooks`) e o painel de administração, [`admin`](packages/admin) (`twstec/kit-admin`, plugin do Filament), que são **opcionais**; a demonstração, [`demo`](packages/demo) (`twstec/kit-demo`, só no desenvolvimento e só neste monorepo — não é publicada); e o instalador, [`installer`](packages/installer) (`twstec/kit-installer`: o `php artisan tws:install`, que escolhe os módulos de um starter, e o `php artisan tws:add`, que acrescenta pacotes a um aplicativo que já existe). |
| [`docs/`](docs) | A documentação do kit, por assunto. |
| `docker-compose.yml` | O ambiente de desenvolvimento: Postgres, Redis e Mailpit compartilhados + o starter Livewire em `localhost:8180` e o starter React em `127.0.0.1:8181` (serviços `react-*`, banco próprio; outro host para os cookies dos dois não colidirem). |

Todos os comandos `docker compose` funcionam tanto da raiz quanto de dentro de
`starters/livewire` (o Compose procura o `docker-compose.yml` nas pastas
acima). Os comandos que montam a pasta atual num container (`docker run ...
-v $(pwd):/app`) precisam rodar **dentro de `starters/livewire`**.

### Requisitos

Apenas **Docker** (com Compose v2+). Nada de PHP, Composer ou Node na máquina.

### Instalação (desenvolvimento)

```bash
git clone https://github.com/kelvindk9w/tws-laravel-starter-kit.git meu-projeto && cd meu-projeto

# 0) Uma vez por clone: cria o .env de cada starter (do .env.example) e o .env
#    da RAIZ com as senhas aleatórias do PostgreSQL e do Redis de
#    desenvolvimento — o docker-compose.yml não sobe sem elas.
scripts/preparar-dev
cd starters/livewire

# 1) Dependências PHP (roda em container, nada local). Monta a RAIZ do
#    repositório: o starter instala os pacotes de packages/ por path
#    repository (../../packages/…). A imagem do Composer não tem as extensões
#    pcntl, intl e bcmath; quem confere as extensões de verdade é o build da
#    imagem de produção (composer check-platform-reqs contra o PHP dela).
docker run --rm --user $(id -u):$(id -g) -e HOME=/tmp -v $(cd ../.. && pwd):/repo -w /repo/starters/livewire composer:latest composer install --no-interaction --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-intl --ignore-platform-req=ext-bcmath

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

As portas do ambiente de desenvolvimento (site, PostgreSQL, Redis, Mailpit)
são publicadas **só em 127.0.0.1** — porta publicada pelo Docker passa por
cima do firewall da máquina. Para abrir para a rede de propósito (nunca numa
máquina com acesso público): `DEV_BIND=0.0.0.0` no `.env` da raiz. O Redis
exige a senha do `.env` da raiz.

#### Escolher os módulos

`foundation` e `auth` vêm sempre; **contas e API** (`accounts`), **uploads**,
**webhooks** e o **painel `/admin`** são opcionais, e a **demonstração** é só de
desenvolvimento. O clone vem com tudo; para tirar o que não quer (ou pôr de
volta), rode o instalador — interativo, ou com opções:

```bash
docker compose exec app php artisan tws:install
# ou, sem perguntas: só a base, sem a demo
docker compose exec app php artisan tws:install --no-interaction --without=accounts,uploads,admin,webhooks --no-demo
```

Ele faz o `composer remove`/`require`, tira a demo do banco antes de tirar o
pacote, roda as migrations e gera a `APP_KEY` e o pepper das chaves de API
quando faltam. As telas, rotas e menus de um módulo ausente somem sozinhos.
Tudo em [Instalação e módulos](docs/instalacao.md) — inclusive como criar um
projeto novo (o comando único `composer create-project twstec/kit`, ou
`twstec/starter-livewire` / `twstec/starter-react` / `laravel new
--using=`) e como acrescentar os pacotes a um aplicativo Laravel que já
existe (`php artisan tws:add`).

Conta criada pelo `/register` só entra no painel depois de confirmar o
e-mail — em dev o link chega no Mailpit (desligável com
`AUTH_EMAIL_VERIFICATION_REQUIRED=false`; ver
[Autenticação](docs/autenticacao.md#verificação-de-e-mail-no-cadastro)).

Portas conflitando? Exporte `DEV_WEB_PORT`, `DEV_REACT_WEB_PORT`, `DEV_POSTGRES_PORT`, `DEV_REDIS_PORT` ou `DEV_MAILPIT_*` no shell (ou num `.env` na **raiz** do repositório, que é o que o Compose lê para montar o `docker-compose.yml`) e recrie os containers. Com outra porta do site, ajuste também, no `.env` de cada starter, o `APP_URL` e o `PLATFORM_OFFICIAL_URL` (e o `DEV_MAIL_PORT`, com outra porta do Mailpit) — o E2E lê o site e o Mailpit de lá. Para rodar uma segunda cópia do ambiente ao lado da primeira, use outro nome de projeto (`docker compose -p <nome> ...`) em todos os comandos. O `starters/livewire/.env` é o ambiente da aplicação, não do Compose; se trocar `DB_DATABASE` ou `DB_USERNAME` nele, ponha o mesmo valor no `.env` da raiz. As senhas (`DB_PASSWORD`, `DB_APP_PASSWORD`, `REDIS_PASSWORD`) vêm só do `.env` da raiz: o Compose as entrega aos containers dos dois starters, por cima das do `.env` de cada um. O banco tem **dois papéis**, como em produção: o superusuário do container (`tws`, `DB_PASSWORD` da raiz) é o papel de migração, e a aplicação conecta com `tws_app` (`DB_APP_PASSWORD`), sem superusuário e sem posse — o `migrate` de cada starter o cria e acerta os privilégios ([Papéis do PostgreSQL](docs/producao.md#papéis-do-postgresql-migração--aplicação)). Clone de antes dos dois papéis: rode `scripts/preparar-dev` de novo (acrescenta o `DB_APP_PASSWORD`), recrie os containers e rode o `migrate` de cada starter.

### Credenciais demo

Com `DEMO_LOGIN_ENABLED=true` (padrão só em `APP_ENV=local`), o
`migrate --seed` cria duas contas e as telas de login já vêm preenchidas:

| Onde | E-mail | Senha |
| --- | --- | --- |
| Painel do usuário (`/login`) | `demo@tws.dev` | `Demo-password1` |
| Super admin (`/admin`) | `admin@tws.dev` | `Demo-admin-password1` |

As duas nascem com o e-mail confirmado. As contas são protegidas contra alteração (UI, model e gatilho no
PostgreSQL) e **não existem em produção** — ver [Demonstração](docs/demo.md).

A demonstração inteira (landings, vitrine `/ui`, contato, catálogo e submissões
no `/admin`, contas demo, massa fictícia) é o pacote **`twstec/kit-demo`**,
que **vive só neste monorepo** (declarado em `require-dev` do starter): vem no
`composer install` de quem clona o kit, **não** vai para a imagem de produção
(`--no-dev`) e **não é publicado** — quem cria um projeto com
`composer create-project`/`laravel new --using=` recebe o starter limpo. Para começar o seu produto
sem ela: `php artisan demo:uninstall --drop-tables` e
`composer remove --dev twstec/kit-demo` — sem a demo, `/` mostra a página
inicial mínima do produto e a suíte continua passando com o `pest` de sempre
(ver [Demonstração → Como remover](docs/demo.md#como-remover)).

### Comandos do dia a dia (sempre em container)

```bash
# Subir o ambiente (da raiz ou de starters/livewire)
export UID GID=$(id -g)
docker compose up -d --build

docker compose exec app php artisan <comando>        # artisan
docker compose exec app php artisan test             # testes (Pest 4)
docker compose exec app ./vendor/bin/pest -c phpunit.pgsql.xml   # testes contra PostgreSQL
docker compose exec app ./vendor/bin/pint            # estilo de código
docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=2G   # análise estática (Larastan nível 8)

# Os comandos abaixo montam a pasta atual: rode-os DENTRO de starters/livewire.
docker run --rm --user $(id -u):$(id -g) -e HOME=/tmp -v $(pwd):/app -w /app composer:latest composer <cmd>

# Build do frontend (Node 24 em container; --user evita node_modules e
# public/build com dono root, que o php-fpm não conseguiria sobrescrever). O
# tema do /admin importa as fontes do pacote twstec/kit-admin, que no vendor/
# é um link para packages/: por isso a pasta dos pacotes também é montada.
docker run --rm --user $(id -u):$(id -g) -e HOME=/tmp -v $(pwd):/app -w /app node:24-alpine npm install
docker run --rm --user $(id -u):$(id -g) -e HOME=/tmp -v $(pwd):/app -v $(pwd)/../../packages:/packages -w /app node:24-alpine npm run build
```

E2E (Playwright) e o banco de teste do PostgreSQL: [Testes](docs/testes.md).

### Estrutura

```
docker-compose.yml       # DESENVOLVIMENTO (raiz do monorepo)
docs/                    # documentação por assunto (tabela no README)
packages/                # pacotes do kit (ver packages/README.md):
  foundation/            # twstec/kit-foundation — segurança, trilhas de
                         # requisição e auditoria, e-mail, idioma, dinheiro,
                         # identificadores, configs editáveis, plataforma
  auth/                  # twstec/kit-auth — autenticação sem telas: login,
                         # cadastro, verificação de e-mail, segundo fator,
                         # senha de transação, ação sensível
  accounts/              # twstec/kit-accounts — projetos, chaves de API e a
                         # API v1 (autenticação, escopos, limites, envelope)
  uploads/               # twstec/kit-uploads — upload validado pelo conteúdo,
                         # re-encode de imagem, URL assinada, foto de perfil
  webhooks/              # twstec/kit-webhooks — webhooks de saída assinados,
                         # outbox, novas tentativas, SSRF, log e reenvio
  admin/                 # twstec/kit-admin — o super admin /admin como plugin
                         # do Filament: telas, dashboards, trilha de auditoria
                         # das ações e as proteções do painel
  demo/                  # twstec/kit-demo — a demonstração (só no dev)
  installer/             # twstec/kit-installer — php artisan tws:install
                         # (escolhe os módulos opcionais; só no dev)
starters/react/          # o mesmo kit com o painel em React + Inertia +
                         # TypeScript + shadcn/ui (kit oficial do Laravel),
                         # http://127.0.0.1:8181 no dev — ver
                         # starters/react/README.md
starters/livewire/       # o aplicativo:
  docker/
    php/Dockerfile       # PHP-FPM 8.4 multi-stage (dev/prod): pgsql, redis,
                         # intl (icu-data-full p/ pt_BR), bcmath, gd, zip,
                         # opcache, pcntl, sqlite (testes), pg_dump 18 (backups)
    php/*.ini            # configs PHP dev/prod + opcache
    nginx/Dockerfile     # nginx dev (HTTP) e prod (HTTPS + headers OWASP)
  docker-compose.prod.yml  # PRODUÇÃO autocontida (rodar de starters/livewire)
  config/                  # só a config do Laravel e o que o app muda do kit
                           # (o padrão do kit mora nos pacotes — ver
                           # docs/personalizacao.md)
  lang/{pt_BR,en,es}/      # só os textos do app (pt-BR é o idioma padrão); os
                           # do kit vêm dos pacotes
  app/
    Models/User.php  # o model de usuário (do app; compõe as traits dos pacotes)
    Livewire/   # painel do usuário
    Providers/Filament/  # o painel /admin do app (marca, tema) + o plugin
                         # do twstec/kit-admin; telas próprias em Filament/
    Domain/  # regras de negócio do projeto filho
  tests/     # Pest (Unit/Feature) + e2e/ (Playwright)
```

Dentro de `starters/livewire/`, a árvore é a do aplicativo Laravel: os caminhos
acima (`docker/`, `config/`, `app/`, `tests/`...) são relativos a ela.

### Branches

`desenvolvimento` → `sandbox` → `producao`. A linha `1.x` recebe só correções de segurança da versão 1 (por 6 meses depois da 2.0); o desenvolvimento novo acontece em `desenvolvimento`. Nunca commit direto nas protegidas.
