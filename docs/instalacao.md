# Instalação: escolher o que instalar

O kit é um conjunto de pacotes e um starter (o aplicativo pronto). Você
escolhe o que entra:

- **Frontend (escolha única, ao criar o projeto):** o starter **Livewire**
  (`starters/livewire`, pacote `twstec/starter-livewire`) ou o starter
  **React** (`starters/react`, pacote `twstec/starter-react` — React +
  Inertia + TypeScript + shadcn/ui, a partir do kit oficial do Laravel — ver
  [Starter React](#starter-react)). Os dois
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

## Criar um projeto: o comando único

```bash
composer create-project "twstec/kit:^2.0@beta" meu-projeto   # durante o beta; na 2.0.0 estável: twstec/kit
```

O pacote `twstec/kit` ([starters/kit](../starters/kit/README.md)) é o ponto
de entrada. Num terminal, um menu (Laravel Prompts) pergunta:

1. **a interface** — Livewire ou React;
2. **os módulos opcionais** — contas com membros e API, uploads e foto, painel
   `/admin` (todos marcados de início). `foundation` e `auth` vêm sempre.
   Uploads exige contas: com Uploads marcado e Contas não, o Enter mostra o
   motivo e a pergunta continua aberta;
3. **a confirmação** do plano.

Depois, sem perguntar mais nada:

1. o starter escolhido é baixado pelo mesmo Composer, com a mesma restrição
   de versão do `twstec/kit` e os mesmos repositórios, e conferido;
2. os arquivos do `twstec/kit` dão lugar aos do starter — ele não fica no
   projeto;
3. o `composer.json` do starter perde os módulos **não marcados** — eles nem
   chegam a ser instalados;
4. rodam os passos do `create-project` do próprio starter:
   `post-root-package-install` (o `.env`), `composer update` e
   `post-create-project-cmd`, que chama o instalador do starter
   (`php artisan tws:install --graceful`) com a escolha no ambiente — ele não
   pergunta de novo, gera a `APP_KEY` e, com contas, o pepper dedicado das
   chaves de API, e roda as migrations;
5. o banco é conferido: se o do `.env` não respondeu (o `.env.example` aponta
   para o PostgreSQL do `docker-compose.yml` de desenvolvimento do kit), a
   mensagem final diz o que ajustar (`DB_*`, ou SQLite: `DB_CONNECTION=sqlite`
   sem `DB_DATABASE` — o `database/database.sqlite` já existe) e o comando
   (`php artisan migrate`).

**Sem terminal** (CI, scripts, `composer create-project -n`), a escolha vai
por variáveis de ambiente — o Composer não repassa opções próprias ao script
do `create-project`, e variável de ambiente funciona igual no Linux, no macOS
e no Windows:

| Variável | Valores | Padrão |
| --- | --- | --- |
| `TWS_KIT_STACK` | `livewire` ou `react` | `livewire` |
| `TWS_KIT_WITHOUT` | opcionais que ficam de fora, separados por vírgula | nenhum |
| `TWS_KIT_WITH` | opcionais que entram (o padrão já é todos) | todos |
| `TWS_KIT_LOCALE` | idioma do menu: `pt_BR`, `en` ou `es` | o do sistema, se for um dos três; senão `pt_BR` |

```bash
# Linux / macOS: React sem uploads
TWS_KIT_STACK=react TWS_KIT_WITHOUT=uploads composer create-project "twstec/kit:^2.0@beta" meu-projeto

# Só a base (foundation + auth), Livewire
TWS_KIT_WITHOUT=accounts,uploads,admin composer create-project "twstec/kit:^2.0@beta" meu-projeto
```

```powershell
# Windows (PowerShell)
$env:TWS_KIT_STACK = "react"; $env:TWS_KIT_WITHOUT = "uploads"
composer create-project "twstec/kit:^2.0@beta" meu-projeto
```

Qualquer uma dessas variáveis (mesmo vazia) desliga o menu; sem nenhuma e sem
terminal, vale o padrão seguro: Livewire com todos os módulos. Escolha
inválida (interface ou módulo desconhecido, `foundation`/`auth` de fora,
uploads sem contas, o mesmo módulo nas duas listas) é recusada **antes** de
baixar qualquer coisa.

**Windows:** nada depende de bash (é PHP puro). Sem WSL, o menu sai em listas
numeradas (o Question Helper do Symfony Console), com as mesmas perguntas e a
mesma validação. O Horizon do starter exige `ext-pcntl`, que o PHP do Windows
não tem: fora do Docker ou do WSL, use
`$env:COMPOSER_IGNORE_PLATFORM_REQ = "ext-pcntl,ext-posix"` antes do comando
(a produção roda na imagem Docker do starter, que tem a extensão).

**Falha limpa.** O que falha antes de o starter ser montado (escolha
inválida, starter que não baixa, pacote baixado que não é o esperado) não
deixa nada instalado: a pasta temporária sai, e a mensagem diz para apagar a
pasta do projeto e rodar de novo. O que falha depois (o `composer update`, o
instalador) para ali e diz o passo e os **comandos exatos** para terminar sem
recomeçar (`composer update`, `composer run-script
post-create-project-cmd`…) — ou para apagar a pasta e rodar de novo. O código
de saída do `create-project` é diferente de 0.

## Criar um projeto a partir de um starter

Os comandos por starter continuam valendo (e são o que o comando único faz
por dentro):

```bash
composer create-project "twstec/starter-livewire:^2.0@beta" meu-app
# ou
laravel new meu-app --using=twstec/starter-livewire
```

(`twstec/starter-react` para o React — ver [Starter React](#starter-react).)

O que acontece: o Composer baixa o starter e os pacotes, copia o
`.env.example` para `.env` e roda o `post-create-project-cmd` do starter, que
chama o **instalador** (`php artisan tws:install --graceful`). Num terminal, o
instalador pergunta os módulos; sem terminal (CI), mantém tudo como veio — ou
aplica `TWS_KIT_WITH`/`TWS_KIT_WITHOUT`, se estiverem no ambiente
(`TWS_KIT_WITHOUT=admin composer create-project …`). O
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

**Estado:** completo, com as mesmas telas do Livewire — autenticação (login
com limite de tentativas, cadastro, verificação de e-mail obrigatória,
esqueci/redefinir senha, segundo fator por código de e-mail, logout), painel
inicial, perfil (idioma, tema, foto), senha de transação, notificações,
seletor de conta, conta e membros, convites, chaves de API e projetos. Tem E2E
próprio (Playwright), imagem de produção própria (`starters/react/docker`,
conferida no CI) e passa pelas mesmas combinações de módulos no CI.

**Criar um projeto React:** o comando único com a interface React
(`TWS_KIT_STACK=react`, ou escolhida no menu), ou direto pelo starter:

```bash
composer create-project "twstec/starter-react:^2.0@beta" meu-app
# ou
laravel new meu-app --using=twstec/starter-react
cd meu-app && npm install && npm run build
```

Como no Livewire, o `post-create-project-cmd` cria o SQLite local e chama o
instalador (`php artisan tws:install --graceful`): num terminal ele pergunta
os módulos opcionais (contas, uploads, `/admin`); sem terminal, mantém todos.
Gera a `APP_KEY` e, com contas, o pepper dedicado das chaves de API. O React
não tem a demonstração, então ela nunca é perguntada. Enquanto a publicação
não é ligada, o CI simula exatamente isso a partir dos pacotes empacotados
(`STARTER=react sh .github/release/simulate-install.sh`).

**No monorepo (desenvolvimento):** o `docker-compose.yml` da raiz sobe o React
na porta **8181**, ao lado do Livewire (8180), com a imagem PHP do próprio
starter (`starters/react/docker/php/Dockerfile`, target `dev`), o mesmo PostgreSQL, Redis e
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

Aplicação: **http://127.0.0.1:8181** (não `localhost`). Cookie é por host, não
por porta: com os dois starters em `localhost`, o cookie `XSRF-TOKEN` (nome
fixo do Laravel, lido pelo front) de um sobrescrevia o do outro, e o primeiro
envio depois de trocar de aba caía no "sessão expirou". Com o React em
`127.0.0.1`, cada starter tem os próprios cookies. O `APP_URL` do
`.env.example` do React já é esse, e o nginx de desenvolvimento leva quem abrir
`localhost:8181` ao mesmo caminho em `127.0.0.1:8181` (redirecionamento 308,
que mantém o método). Nada disso existe na produção, que atende pelo domínio
da `APP_URL`. Quem já tinha o `.env` do React: troque o `APP_URL` para
`http://127.0.0.1:8181` e recrie os serviços `react-*`
(`docker compose up -d --force-recreate --no-deps react-app react-queue react-scheduler`).

O instalador `php artisan tws:install` é o
mesmo (`docker compose exec react-app php artisan tws:install`); o React não
usa a demonstração, então ela nunca é perguntada. Os módulos instalados chegam
ao front na prop `kit.modules`, e o menu só mostra tela que existe.

## O instalador: `php artisan tws:install`

Mora no pacote `twstec/kit-installer` (require-dev dos dois starters — o
mesmo pacote no Livewire e no React). Pode rodar a qualquer momento, quantas
vezes quiser.

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

Sem a opção, `TWS_KIT_WITH` e `TWS_KIT_WITHOUT` no ambiente valem como
`--with` e `--without` (é por onde a escolha chega ao `post-create-project-cmd`
do starter — o Composer não repassa opções ao script). A opção vence a
variável.

Qualquer opção de escolha (`--with`, `--without`, `--no-demo`), uma dessas
variáveis ou `--no-interaction` desliga as perguntas. O que não foi citado
fica como está.
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

### Com o `tws:add`

```bash
# Durante o beta, o foundation também leva o @beta (a estabilidade só vale no
# projeto raiz); na 2.0.0 estável, basta a segunda linha, sem o @beta.
composer require "twstec/kit-foundation:^2.0@beta"
composer require --dev "twstec/kit-installer:^2.0@beta"
php artisan tws:add                                        # pergunta (Laravel Prompts)
php artisan tws:add accounts admin                         # sem perguntas
```

O `tws:add` (do mesmo pacote do `tws:install`) mostra os módulos do kit que o
aplicativo tem e os que faltam, e instala os escolhidos:

- **a autenticação vem junto** de qualquer módulo, se faltar (ela é
  obrigatória no kit);
- **recusa o que exige dependência ausente**, com a explicação e o comando
  certo — `tws:add uploads` sem contas: "Uploads … precisa de Contas …
  Adicione os dois juntos: `php artisan tws:add accounts uploads`". No menu,
  a mesma regra não deixa a escolha passar;
- **recusa contas (e uploads) enquanto o model de usuário do aplicativo não
  estiver pronto para a autenticação do kit** (`auth.providers.users.model`
  implementando `Twstec\Kit\Auth\Contracts\AuthUser`): o pacote de contas
  liga a conta pessoal aos eventos desse model já no boot, e o `twstec/kit-auth`
  falha alto com um model que não serve — num aplicativo recém-criado (o
  `User` do esqueleto), o aplicativo deixaria de subir. A mensagem dá o
  caminho: `php artisan tws:add auth`, ajustar o model como no
  [README do twstec/kit-auth](../packages/auth/README.md#o-model-de-usuário-é-do-aplicativo)
  e `php artisan tws:add accounts`. A autenticação e o `/admin` não dependem
  disso (o `kit-auth` confere o model só no primeiro uso);
- `composer require` dos escolhidos — e do foundation e da autenticação como
  requisito **direto** do projeto, quando hoje só vêm por dependência de outro
  pacote (senão um `composer install --no-dev` os levaria embora) —, com a
  restrição de versão do kit que está no `composer.json` (a do instalador,
  num aplicativo que só tem ele);
- `optimize:clear`, a configuração publicada de cada módulo
  (`vendor:publish --tag=<módulo>-config`, sem sobrescrever o que existe), a
  `APP_KEY` se faltar, o pepper dedicado das chaves de API com contas, e
  `migrate` (`--graceful` deixa as migrations para depois se o banco não
  responder);
- as **proteções** vêm ligadas pelos próprios pacotes (descoberta automática
  do Laravel);
- o resumo diz, módulo a módulo, o que só o aplicativo pode fazer: o model de
  usuário com o contrato e a trait do `twstec/kit-auth`, a foto de perfil, o
  PanelProvider do Filament com o `AdminPlugin`, a coluna `is_admin` e o
  primeiro admin (`user:make-admin`).

Recusa `APP_ENV=production` sem `--force`, como o `tws:install`.

### Com o Composer

Sem o instalador, instale só o que quiser:

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
- **As mesmas combinações no starter React** (sem uploads, só o `/admin`, só
  a base, sem o `/admin`), no mesmo job, no checkout do React: instalador,
  build (sem o tema do `/admin` quando ele sai) e a suíte.
- **A instalação publicada, simulada:** cada pacote vira um ZIP
  (`composer archive`, com o `composer.json` da versão publicada), o projeto é
  criado **só** a partir deles (`composer create-project` com repositório
  `artifact` — pacotes copiados, sem link para o monorepo) e roda o build e a
  suíte. Por fim constrói as **imagens de produção (app e nginx) do projeto
  criado** — sem a pasta `packages/`, com o Dockerfile e o
  `docker-compose.prod.yml` publicados — e passa as mesmas conferências de
  imagem limpa do job de imagens (sem `.env`, testes, storage, SQLite, demo
  nem instalador; pacotes copiados). O repositório `artifact` é relativo
  (`../packages`): no build da imagem ele chega pelo contexto opcional
  `packages` do Dockerfile, que no uso real fica vazio (os pacotes vêm do
  Packagist). Pega starter que só funciona dentro do monorepo. Script:
  `.github/release/simulate-install.sh` — uma vez com o Livewire e uma com o
  React (`STARTER=react`).
- **O comando único, simulado:** o mesmo, com `composer create-project
  twstec/kit` a partir dos ZIPs (os dois starters empacotados), sem terminal
  e com a escolha pelo ambiente — React sem uploads no job do PostgreSQL,
  Livewire só com a base no do SQLite (`STARTER=kit KIT_STACK=…
  KIT_WITHOUT=…`). Além das conferências de sempre: o projeto é o do starter
  escolhido, nada do `twstec/kit` sobrou, e os módulos **desmarcados** não
  estão no `composer.json`, no vendor, no registro do Composer nem na imagem
  de produção. A suíte do próprio `twstec/kit` roda nos dois jobs.
- **As imagens de produção** dos dois starters são construídas e conferidas
  (o React num job próprio, `Imagens de produção do starter React`).

## Publicação

O workflow `.github/workflows/split.yml` copia, a cada tag `v2.*`, cada
pacote, cada starter e o comando único para o repositório só-leitura dele (9
destinos: `kelvindk9w/twstec-kit-foundation`, `…-auth`, `…-accounts`,
`…-uploads`, `…-admin`, `…-installer`, `kelvindk9w/twstec-starter-livewire`,
`kelvindk9w/twstec-starter-react` e, a partir da 2.0.0-beta.2,
`kelvindk9w/twstec-kit` — a demonstração **não** é publicada), com o
`composer.json` preparado por `.github/release/prepare-composer.php` (sem path
repositories, pacotes do kit em `^2.0`; nos starters, `^2.0@beta` durante o
beta, sem `composer.lock` e sem nenhuma referência à demo; o
`docker-compose.prod.yml` sem o contexto `packages` do monorepo; no
`twstec/kit`, a restrição do starter que ele baixa em
`extra.twstec-kit.constraint` e nada da suíte dele). Cada pasta publicada leva
`README.md` (com o link do monorepo e das docs), `LICENSE` e `SECURITY.md`
(que aponta para a política do monorepo). O job roda com a variável do
repositório `KIT_SPLIT_ENABLED=true` e usa o segredo `SPLIT_TOKEN`, que precisa
dar escrita a **todos** os espelhos da lista — inclusive o
`kelvindk9w/twstec-kit`, novo na 2.0.0-beta.2 —, e cada espelho novo é
registrado no Packagist depois do primeiro split.

### Packagist: atualização automática depois do split

Os espelhos não têm webhook (o split empurra com o `SPLIT_TOKEN`), e o
Packagist mostraria "not auto-updated". Por isso o próprio `split.yml`, depois
que **todos** os splits passam, avisa o Packagist de cada espelho da lista
(inclusive o `twstec/kit`) pela API documentada: `POST
https://packagist.org/api/update-package` com o cabeçalho `Authorization:
Bearer <usuário>:<token>` e o corpo `{"repository":
"https://github.com/kelvindk9w/<espelho>"}`. A lista dos espelhos fica num
lugar só (o job "Espelhos"), usada pelo split e pelo aviso.

**O que o dono configura** (Settings → Secrets and variables → Actions):

1. o **segredo** `PACKAGIST_API_TOKEN`: o token **SAFE** do Packagist (a
   página do perfil mostra os dois, o MAIN e o SAFE) — o endpoint aceita o
   SAFE, que é o recomendado para CI (feito para operações que podem vazar
   sem grande estrago);
2. a **variável** `PACKAGIST_USERNAME`: o nome de usuário no Packagist dono
   dos pacotes `twstec/*`.

Sem os dois, o job "Avisar o Packagist" termina com um aviso no log e **não
falha** (o split já foi feito; dá para clicar em *Update* em cada pacote no
Packagist). Com eles, cada chamada tenta de novo em erro de rede ou 5xx, e uma
resposta de erro (token errado, espelho não registrado) deixa o job vermelho,
com o espelho e o status — o token nunca aparece no log (vai no cabeçalho, por
arquivo, sem `set -x`). **Espelho novo** (como o `kelvindk9w/twstec-kit` na
2.0.0-beta.2): o Packagist só registra um repositório que já tem
`composer.json`, então o primeiro split dele vem antes do registro — nessa
tag, o aviso desse espelho responde erro e o job fica vermelho (os splits já
passaram). Registre o repositório no Packagist e rode de novo só o job
"Avisar o Packagist" (*Re-run failed jobs*).

