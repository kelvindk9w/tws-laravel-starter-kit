# Instalação: escolher o que instalar

O kit é um conjunto de pacotes e um starter (o aplicativo pronto). Você
escolhe o que entra:

- **Frontend (escolha única, ao criar o projeto):** o starter **Livewire**
  (`starters/livewire`, pacote `twstec/starter-livewire`) ou o starter
  **React** (`starters/react`, pacote `twstec/starter-react` — React +
  Inertia + TypeScript + shadcn/ui, a partir do kit oficial do Laravel; em
  construção: fases F11a–F11c, ver [Starter React](#starter-react)). Os dois
  usam os mesmos pacotes, com as mesmas regras e mensagens, e o mesmo `/admin`.
- **Backend:** `foundation` e `auth` vêm **sempre**. `accounts`, `uploads` e
  `admin` são **opcionais**, marcados um a um.
- **Demonstração:** o pacote `twstec/kit-demo` (landings, vitrine `/ui`,
  contas demo, massa fictícia) **vive só no monorepo** — não é publicado no
  Packagist. Quem clona o monorepo a tem (e a tira com um comando); quem cria
  um projeto (`create-project` / `laravel new --using=`) recebe o starter
  **limpo**, sem ela.

| Módulo | Pacote | O que traz | Precisa de |
| --- | --- | --- | --- |
| Base | `twstec/kit-foundation` | Segurança (filtro de ataques, limites, cabeçalhos, hosts e proxies), trilhas de requisição e de auditoria com LGPD, idioma, e-mail, configurações editáveis, guardas de produção | — (sempre) |
| Autenticação | `twstec/kit-auth` | Login, cadastro, verificação de e-mail, segundo fator, senha de transação, ação sensível | foundation (sempre) |
| Contas e API | `twstec/kit-accounts` | Contas com membros e convites, projetos, chaves de API e a API v1 | foundation, auth |
| Uploads | `twstec/kit-uploads` | Upload validado pelo conteúdo, entrega por URL assinada, foto de perfil, `POST /api/v1/uploads` | foundation, auth, **accounts** (o upload pertence a uma conta) |
| Painel `/admin` | `twstec/kit-admin` | O super admin (plugin do Filament): usuários, logs, trilha de auditoria, configurações, dashboards — e as telas de contas, chaves, projetos e uploads **quando esses módulos estão instalados** | foundation, auth (accounts e uploads são sugeridos, não exigidos) |
| Demonstração | `twstec/kit-demo` | Landings, vitrine, contas demo, seeders de dado fictício — **só no monorepo, não publicado** | **todos** os módulos acima (require-dev do monorepo) |

## Criar um projeto

> **Estado atual:** a publicação no Packagist (repositórios só-leitura por
> pacote, tag `2.0.0-beta.1`) é a fase seguinte (F10b). Até lá, o caminho é
> clonar o monorepo — ver [README](../README.md#instalação-desenvolvimento) — e
> rodar o instalador dentro de `starters/livewire`. Os comandos abaixo são os
> que valerão depois da publicação; o CI já os simula a cada push (seção
> [Como o CI garante](#como-o-ci-garante)).

Com o Composer:

```bash
composer create-project twstec/starter-livewire meu-app
```

Ou com o instalador do Laravel (ele chama o mesmo `create-project` e depois
faz as perguntas dele — banco, npm):

```bash
laravel new meu-app --using=twstec/starter-livewire
```

Durante o beta, peça a versão explicitamente (`twstec/starter-livewire:^2.0@beta`).

O que acontece: o Composer baixa o starter e os pacotes, copia o
`.env.example` para `.env` e roda o `post-create-project-cmd` do starter, que
chama o **instalador** (`php artisan tws:install --graceful`). Num terminal, o
instalador pergunta os módulos; sem terminal (CI), mantém tudo como veio. O
projeto nasce **sem a demonstração** (ela não é publicada: o `composer.json`
publicado do starter não a cita): `/` é a página inicial do produto e os
testes da demo pulam sozinhos. Depois dele, o projeto tem a `APP_KEY` e, com o módulo de
contas, um pepper dedicado para as chaves de API. As migrations rodam se o
banco do `.env` estiver acessível; senão, ficam para depois
(`php artisan migrate`) — o `.env.example` aponta para o PostgreSQL do
`docker-compose.yml`.

## Starter React

`starters/react` (`twstec/starter-react`) é o mesmo kit com o painel do
usuário em React 19 + Inertia 3 + TypeScript + Tailwind 4 + shadcn/ui, a
partir do [kit oficial React do Laravel](https://github.com/laravel/react-starter-kit).
A autenticação é a do `twstec/kit-auth` (sem o Fortify do kit oficial): as
telas são páginas React, os envios são os controllers do pacote e as
respostas são as implementações Inertia dos contratos de resposta dele. As
contas do `twstec/kit-accounts` são a fonte única (o kit oficial não traz
times, e nada dele é usado para isso). Detalhes em
[starters/react/README.md](../starters/react/README.md).

**Estado (F11a):** base, autenticação completa (login com limite de
tentativas, cadastro, verificação de e-mail obrigatória, esqueci/redefinir
senha, segundo fator por código de e-mail, logout) e o painel mínimo (painel
inicial com os números da conta, perfil, senha de transação, verificação em
duas etapas, notificações). Seletor de conta, conta e membros, chaves de API,
projetos e foto de perfil chegam na F11b; E2E, imagem de produção e as
combinações de módulos no CI, na F11c. A publicação do `twstec/starter-react`
(Packagist) entra depois disso.

**No monorepo (desenvolvimento):** o `docker-compose.yml` da raiz sobe o React
na porta **8181**, ao lado do Livewire (8180), com o mesmo PostgreSQL, Redis e
Mailpit — mas banco próprio (`tws_starter_react`, criado pelo serviço
`react-db-init`), bancos próprios no Redis (`REDIS_DB=2`, `REDIS_CACHE_DB=3`)
e cookie de sessão com nome próprio, para um não derrubar a sessão, a fila ou
o cache do outro:

```bash
cd starters/react && cp .env.example .env
# composer install e npm install/build: ver starters/react/README.md
cd ../.. && export UID GID=$(id -g)
docker compose up -d react-nginx react-queue react-scheduler
docker compose exec react-app php artisan key:generate --force
docker compose up -d --force-recreate --no-deps react-app react-queue react-scheduler
docker compose exec react-app php artisan migrate
```

Aplicação: http://localhost:8181. O instalador `php artisan tws:install` é o
mesmo (`docker compose exec react-app php artisan tws:install`); o React não
usa a demonstração, então ela nunca é perguntada. Os módulos instalados chegam
ao front na prop `kit.modules`, e o menu só mostra tela que existe.

## O instalador: `php artisan tws:install`

Mora no pacote `twstec/kit-installer` (require-dev do starter; o starter React
usará o mesmo). Pode rodar a qualquer momento, quantas vezes quiser.

### Interativo

```bash
php artisan tws:install
```

1. Pergunta quais módulos opcionais você quer — os já instalados vêm
   marcados. Uploads sem Contas é recusado na hora (com a explicação).
2. Se a demonstração estiver instalada (só no clone do monorepo): pergunta se ela fica. Se você tirou
   algum módulo, avisa que a demo exige todos e que ela vai sair junto (e pede
   confirmação).
3. Mostra o plano (módulo por módulo: agora → depois) e pede confirmação.
4. Aplica e imprime o resumo e os próximos passos.

### Não interativo (CI, scripts)

```bash
# Só a base (foundation + auth), tirando também a demo
php artisan tws:install --no-interaction --without=accounts,uploads,admin --no-demo

# Tirar só o /admin
php artisan tws:install --no-interaction --without=admin --no-demo

# Pôr de volta contas e uploads, sem o /admin
php artisan tws:install --no-interaction --with=accounts,uploads --without=admin
```

| Opção | O que faz |
| --- | --- |
| `--with=a,b` | Instala (ou mantém) esses módulos opcionais |
| `--without=a,b` | Remove esses módulos opcionais |
| `--no-demo` | Remove a demonstração (`twstec/kit-demo`) |
| `--force` | Permite rodar com `APP_ENV=production` (sem ela, recusa) |
| `--graceful` | Banco inacessível não reprova a rodada: as migrations ficam para depois |

Qualquer opção de escolha (`--with`, `--without`, `--no-demo`) ou
`--no-interaction` desliga as perguntas. O que não foi citado fica como está.
Módulo desconhecido, módulo obrigatório em `--without`, o mesmo módulo nas
duas listas, uploads sem contas e tirar um módulo com a demo ficando são
recusados **antes** de mexer em qualquer coisa.

### O que ele aplica, nesta ordem

1. Com a demo saindo: `php artisan demo:uninstall --drop-tables` (tira do banco
   os gatilhos das contas demo e as tabelas dela — ver [demo](demo.md)) e
   `composer remove --dev twstec/kit-demo`. Se o `demo:uninstall` falhar, para
   ali, sem remover nada (com `--graceful`, avisa e segue).
2. `composer remove` dos módulos não escolhidos (o Filament sai junto com o
   `/admin`: ele vem pelo `twstec/kit-admin`).
3. `composer require` dos escolhidos que faltam, com a mesma restrição de
   versão do `twstec/kit-foundation` no seu `composer.json`.
4. `php artisan optimize:clear` (config, rotas e views em cache mudam com os
   módulos).
5. `.env`: criado do `.env.example` se faltar; `APP_KEY` gerada se vazia;
   com contas, `API_KEYS_HASH_PEPPER` gerado se vazio. Se a `APP_KEY` já
   existia, ela vai para `API_KEYS_PREVIOUS_HASH_PEPPERS` — as chaves de API
   emitidas com o fallback continuam autenticando e migram sozinhas no
   primeiro uso ([API](api.md)).
6. `php artisan migrate --force`.
7. Resumo: módulos (instalado, instalado agora, removido, não instalado),
   `APP_KEY`, pepper, migrations e os próximos passos — recompilar o front
   (`npm install && npm run build`), `migrate:fresh` num banco de
   desenvolvimento que tinha a massa fictícia da demo, e o aviso do `/horizon`
   sem o `/admin`.

Os comandos do artisan rodam num **processo novo**: depois do Composer, o
processo do instalador ainda tem na memória os providers de antes.

**Não apaga arquivo do aplicativo.** As telas, rotas e menus de um módulo
ausente somem sozinhos (próxima seção); o código delas continua no seu
projeto, inerte, e volta a valer se você reinstalar o módulo.

**Idempotente:** a segunda rodada com a mesma escolha não chama o Composer e
não regera chave nenhuma. **Produção:** recusa sem `--force` — tirar pacote e
mexer no `.env` não é coisa de servidor; lá o que vale é o `composer.json`
versionado.

## O que some em cada combinação

A detecção é um ponto só: `Twstec\Kit\Foundation\Kit::has('accounts')` (e a
diretiva `@kit('uploads') … @else … @endkit` nas views). Módulo instalado =
registrado pelo Composer **e** com o provider carregável.

| Sem… | No painel do usuário (Livewire) | No `/admin` | Outros |
| --- | --- | --- | --- |
| `accounts` (e, por consequência, `uploads`) | Não existem as rotas `/api-keys`, `/projects`, `/account`, `/accounts/*` e `/invitations/*` (404); o menu perde o grupo "Desenvolvimento" e "Conta e membros"; o seletor de conta some; o painel inicial mostra os atalhos da conta (perfil, senha de transação, notificações) em vez dos números da conta | Sem as telas de contas, chaves e projetos; sem o filtro por conta na trilha; os dashboards sem os cards de chaves/projetos e sem a tabela de projetos recentes; a guarda de exclusão de usuário não consulta contas | Sem a API v1 e sem o agendamento `api-keys:process-inactivity`; o `/api/health` continua com o limite `throttle:api` e o envelope de erro (postos pelo próprio aplicativo); as chaves de API somem das configurações editáveis |
| `uploads` | Sem a rota `settings/avatar` e sem o cartão da foto no perfil (a ação responde 404); o avatar são as iniciais | Sem a tela de uploads, sem o widget dos últimos uploads e sem o campo de foto (usuários e perfil) | Os limites de upload somem das configurações editáveis; num banco criado sem o módulo, a coluna `users.avatar_upload_id` nasce sem chave estrangeira (não há tabela `uploads`). Tirar um módulo **não apaga as tabelas dele** — elas ficam, sem uso |
| `admin` | — | Não existe: o `AdminPanelProvider` do aplicativo não é registrado e o Filament nem está instalado (`/admin` → 404) | O `/horizon` fecha fora do ambiente local (o critério de acesso é o do painel; declare o seu em `app/Providers/HorizonServiceProvider.php` se quiser); o tema `filament.css` sai do build do front; os scripts do Composer pulam os assets do Filament |
| demo | As landings, a vitrine e o contato ([demo](demo.md)) | As telas de produtos e submissões | Nenhuma conta protegida; `db:seed` não semeia nada |

O model `App\Models\User` compõe as peças dos módulos opcionais por nomes do
próprio aplicativo (`OptionalAdminPanelUser`, `OptionalAdminPanelAccess`,
`OptionalProfilePhoto` — ver `app/Support/optional-modules.php`): com o módulo,
viram a peça do pacote; sem ele, uma peça neutra (sem painel; sem foto).

Escrevendo telas próprias que usam um módulo opcional, faça a mesma pergunta:
rota dentro de `if (Kit::has('accounts')) { … }`, item de menu com
`'module' => 'accounts'` em `App\Livewire\Support\Navigation`, bloco de view em
`@kit('accounts') … @endkit`.

## Só os pacotes, num aplicativo Laravel que já existe

Sem starter nenhum (inclusive sobre o starter oficial do Laravel), instale só
o que quiser:

```bash
composer require twstec/kit-foundation twstec/kit-auth
composer require twstec/kit-accounts          # opcional
composer require twstec/kit-uploads           # opcional (exige accounts)
composer require twstec/kit-admin             # opcional (traz o Filament)
```

Durante o beta, com a estabilidade mínima `stable` do seu projeto, acrescente
`:^2.0@beta` a **cada** pacote do kit que você requerer (inclusive
`twstec/kit-foundation`, mesmo que ele venha como dependência) — ou use
`"minimum-stability": "beta"` com `"prefer-stable": true`.

Cada pacote sobe sozinho pela descoberta do Laravel e liga as próprias
proteções. O que o aplicativo fornece está no README de cada pacote
([foundation](../packages/foundation/README.md),
[auth](../packages/auth/README.md), [accounts](../packages/accounts/README.md),
[uploads](../packages/uploads/README.md), [admin](../packages/admin/README.md)):
em resumo, o model de usuário com as traits do auth (e a foto do uploads, se
quiser), as colunas `is_admin` e `avatar_upload_id` se usar o `/admin` e a
foto, e um PanelProvider do Filament que registra o `AdminPlugin`.

## Como o CI garante

- **Cada pacote isolado** (suíte própria, aplicação limpa) — passos do job
  obrigatório.
- **As combinações principais do starter**, no job do SQLite, pelo próprio
  instalador, uma depois da outra: completo (com a demo), sem a demo, sem
  uploads, só o `/admin` (sem contas nem uploads), só a base
  (foundation + auth) e sem o `/admin` (contas e uploads reinstalados pelo
  `--with`). Em cada uma: o build do front e a suíte com o `pest` de sempre —
  os testes de um módulo ausente **pulam sozinhos** (grupos `accounts`,
  `uploads`, `admin`; ver [testes](testes.md)).
- **A instalação publicada, simulada:** cada pacote vira um ZIP
  (`composer archive`, com o `composer.json` da versão publicada), o projeto é
  criado **só** a partir deles (`composer create-project` com repositório
  `artifact` — pacotes copiados, sem link para o monorepo) e roda o build e a
  suíte. Pega starter que só funciona dentro do monorepo. Script:
  `.github/release/simulate-install.sh`.

## Publicação (fase F10b)

Pronto e desligado: o workflow `.github/workflows/split.yml` copia, a cada
tag `v2.*`, cada pacote e o starter para o repositório só-leitura dele
(7 destinos: `kelvindk9w/twstec-kit-foundation`, `…-auth`, `…-accounts`,
`…-uploads`, `…-admin`, `…-installer` e `kelvindk9w/twstec-starter-livewire`
— a demonstração **não** é publicada), com o `composer.json` preparado por
`.github/release/prepare-composer.php` (sem path repositories, pacotes do kit
em `^2.0`; no starter, `^2.0@beta` durante o beta, sem `composer.lock` e sem
nenhuma referência à demo). O job só roda com a variável do
repositório `KIT_SPLIT_ENABLED=true` e usa o segredo `SPLIT_TOKEN`; nenhum dos
dois existe ainda.
