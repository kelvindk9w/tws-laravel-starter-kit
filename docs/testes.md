# Testes

> **Num projeto criado** (pelo `twstec/kit` ou por um starter), veja
> [Testes no projeto criado](#testes-no-projeto-criado) logo abaixo. O resto
> deste documento descreve a suíte **dentro do monorepo** do kit.

## Testes no projeto criado

Na raiz do projeto, com ele no ar (`docker compose up -d`). Nada disso exige
PHP ou Node na máquina.

```bash
# Tudo o que o CI confere, de uma vez (Pint, auditorias, build, Pest PostgreSQL):
scripts/verificar

# Ou cada camada:
docker compose exec app ./vendor/bin/pest                       # Pest em SQLite em memória
docker compose exec app ./vendor/bin/pest -c phpunit.pgsql.xml  # Pest contra o PostgreSQL (o banco <banco>_test)
```

O banco da suíte PostgreSQL é `<banco do projeto>_test`: o instalador grava o
nome no `phpunit.pgsql.xml`, e o serviço `db-init` do `compose.yaml` cria o
mesmo (`${DB_DATABASE}_test`) na subida. A `tests/TestCase.php` recusa
qualquer banco que não termine em `_test`.

**E2E (Playwright), sem Node na máquina:**

```bash
# As pessoas fixas do E2E (e2e@, login-e2e@, admin-e2e@ e — no React —
# admin-login-e2e@example.com), idempotente; rode antes de cada rodada:
docker compose exec -T app php artisan tinker --execute="require 'tests/e2e/fixtures.php';"

# A suíte, num container (o --user evita arquivos com dono root):
docker run --rm --network host --user $(id -u):$(id -g) -e HOME=/tmp \
  -v $(pwd):/work -w /work mcr.microsoft.com/playwright:v1.63.0-noble npx playwright test
```

- **Onde o E2E roda:** sempre no **próprio projeto**. O site é o de
  `E2E_BASE_URL` ou, sem ela, a `APP_URL` do `.env`; o Mailpit, o de
  `E2E_MAILPIT_URL` ou `http://127.0.0.1:<DEV_MAIL_PORT do .env>`
  (`tests/e2e/support/project-env`). Sem a variável e sem o valor no `.env`, o
  E2E para com a instrução. Antes de qualquer teste, o global-setup confere que
  o site que responde é **este** projeto (o cookie de sessão tem o nome do
  `SESSION_COOKIE` do `.env`) e que o Mailpit é o dele — senão a suíte para sem
  criar nem apagar nada (o teardown do React também não varre). O E2E cria e
  apaga pessoas e mensagens: apontado para outro ambiente da máquina, ele
  mexeria no banco e no Mailpit de outro projeto.
- **IPv6:** dentro do container do Playwright, `<nome>.localhost` pode
  resolver para `::1`, e as portas só saem em IPv4; o navegador tenta o IPv4
  sozinho, e as chamadas do Node (Mailpit, Vite) usam `127.0.0.1`.
- **O front no navegador:** `tests/e2e/front-assets.spec` abre o login e
  prova que os scripts do front carregam sem bloqueio — com o Vite de
  desenvolvimento no ar, que eles vêm dele e que ele libera no CORS **só** a
  origem do site.
- **Limite das rotas sensíveis:** o `.env` de desenvolvimento usa
  `RATE_LIMIT_SENSITIVE=30` (o instalador grava); com o 5 da produção, o login
  e o cadastro paralelos do E2E recebem 429.
- **Entre duas rodadas, espere 60 s** (o limite de borda por IP). Cada teste
  apaga o que criou; para conferir sobras:
  `docker compose exec app php artisan tinker --execute='echo \App\Models\User::where("email","like","e2e-%")->count();'`.
- **Starter Livewire:** os specs da demonstração do kit (landings, vitrine
  `/ui`, dados demo do `/admin`) não vão para o projeto criado; os de um
  módulo opcional ausente ficam de fora sozinhos (`playwright.config.js`).
- **Segundo fator obrigatório e cadastro fechado:** a suíte roda com
  `AUTH_TWO_FACTOR_REQUIRED` = `none`, `admins` ou `all` e com
  `AUTH_REGISTRATION_ENABLED` = `true` ou `false`. Ver
  [E2E com segundo fator obrigatório e cadastro fechado](#e2e-com-segundo-fator-obrigatório-e-cadastro-fechado).

### Travas de arquitetura do projeto

Vão junto para o projeto criado e reprovam a suíte quando:

- um PHP de `app/`, `database/` ou `routes/` não começa com
  `declare(strict_types=1);` (os `make:*` do Laravel geram sem — acrescente
  a linha);
- aparece `env()` fora de `config/` (em `app/`, `bootstrap/`, `database/`,
  `routes/` ou nas views): com `config:cache` o `.env` não é lido e o valor
  cai no padrão em silêncio — leve para um arquivo de `config/` e leia com
  `config()`;
- um model (`app/Models`) ou o domínio (`app/Domain`) usa classe de
  interface (Filament, Livewire, Inertia);
- um model cuja tabela tem `account_id` não usa `BelongsToAccount` (com o
  módulo de contas; as colunas vêm do banco de teste migrado, e as exceções
  ficam numa lista explícita, com o motivo, em
  `tests/Feature/Architecture/AccountModelsTest.php`).

Arquivos: `tests/Unit/Architecture/ProjectGuardsTest.php` e
`tests/Feature/Architecture/AccountModelsTest.php`. Cada trava tem um teste
"não é cega", que prova que ela pega o que deve.

### Análise estática (Larastan)

O projeto nasce com o [Larastan](https://github.com/larastan/larastan)
(PHPStan com as regras do Laravel) em `require-dev` e o `phpstan.neon.dist` na
raiz: **nível 8** no código do aplicativo (`app/`), **sem baseline**.

```bash
docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=2G
```

- O código dos starters passa limpo, e o CI do kit roda essa análise nos dois
  starters a cada push: erro novo reprova.
- Erro é para corrigir, não para guardar numa lista de ignorados. A única
  entrada de `ignoreErrors` é o aviso `trait.unused` das peças neutras dos
  módulos opcionais (`app/Models/Concerns/Fallbacks`), que só entram em uso sem
  o módulo — com `reportUnmatched: false`, porque sem o módulo o aviso some.
- Os models dos pacotes (`Account`, `AccountMembership`, `ApiKey`, `Project`,
  `RequestLog`) declaram as colunas em `@property`: as migrations deles ficam
  no `vendor/`, fora do alcance da leitura de migrations do Larastan.
- Do contrato de usuário (`Twstec\Kit\Auth\Contracts\AuthUser`), use os
  métodos, não as colunas: o e-mail é `getEmailForVerification()`. Coluna
  lida de um objeto tipado pelo contrato (`$user->email`) reprova no nível 8.
- **Projeto criado sem algum módulo opcional:** as telas do módulo ausente
  continuam em `app/` (as rotas e o menu não as registram) e citam classes
  que não estão instaladas; a análise acusa essas citações. Ela não entra no
  `scripts/verificar` por isso. Com todos os módulos, passa limpa.

### Pacotes divididos: a pegadinha do `arch()` do Pest

`arch()->expect('App\Models')->not->toUse('Filament')` **não** pega
`Filament\Notifications\Notification`: o Pest resolve `Filament` pelas
pastas do autoload desse prefixo (o pacote `filament/filament`), e
`filament/notifications`, `filament/forms`, `filament/tables`… declaram cada
um o seu prefixo PSR-4. O mesmo com `Psr\Http` × `Psr\Http\Message` ou
`GuzzleHttp` × `GuzzleHttp\Psr7`. A trava parece existir e deixa passar
metade.

Duas saídas, e as travas do kit usam as duas: comparar o **nome completo**
de cada classe citada (lido dos tokens do PHP) com `str_starts_with` do
prefixo terminado em `\` — assim `Filament\` pega qualquer
`Filament\…` —, e, quando a proibição é de um **pacote** (não de um
namespace), gerar a lista de prefixos do autoload do Composer
(`vendor/composer/installed.json`), pacote a pacote, como faz a trava de
interface do `ProjectGuardsTest`. Se usar o `arch()` do Pest, passe a lista
completa de prefixos, nunca só a raiz do vendor.

## Testes no monorepo

Três camadas, todas em container:

```bash
docker compose exec app ./vendor/bin/pest                       # Pest em SQLite em memória (padrão local)
docker compose exec app ./vendor/bin/pest -c phpunit.pgsql.xml  # Pest contra PostgreSQL 18 (o que o CI exige)
npx playwright test                                             # E2E (ver abaixo como rodar em container)
```

**O ambiente da suíte é o mesmo no container e no CI.** O container `app`
injeta o `.env` de desenvolvimento (fila no Redis, e-mail no Mailpit, sessão no
Redis, o Argon2id de produção); o `phpunit.xml` e o `phpunit.pgsql.xml` forçam
(`force="true"`, em `<env>` e `<server>`) fila `sync`, e-mail e sessão
`array`, cache `array` e o custo mínimo do hash. Sem o `force`, o valor do
container vencia: os testes mandavam jobs para a fila do Redis de dev (e o
worker de dev os processava contra o banco de dev) e e-mails para o Mailpit.

E a suíte de cada **pacote** (`packages/<pacote>`), isolada do aplicativo, com
Orchestra Testbench:

```bash
docker compose exec -w /var/packages/foundation app ./vendor/bin/pest   # pacote twstec/kit-foundation
docker compose exec -w /var/packages/auth app ./vendor/bin/pest         # pacote twstec/kit-auth
docker compose exec -w /var/packages/accounts app ./vendor/bin/pest     # pacote twstec/kit-accounts
docker compose exec -w /var/packages/uploads app ./vendor/bin/pest      # pacote twstec/kit-uploads
docker compose exec -w /var/packages/admin app ./vendor/bin/pest        # pacote twstec/kit-admin
```

Ela cobre as peças da base que não dependem de rotas nem telas (filtro de
ataques, redação LGPD, balde de cliente, dinheiro, sanitização), a ordem da
pilha global de segurança, os apelidos de compatibilidade, a fiação do provider
e a arquitetura do pacote; a do auth prova, numa aplicação Laravel limpa, que
as proteções de login, conta bloqueada e segundo fator vêm do pacote; a do
accounts prova o mesmo para a API (401 no envelope, limites por chave e de
falha, escopo, vínculo com projetos), com o ambiente de uma aplicação nova
fixado; e a do uploads prova o mesmo para o upload (conteúdo falso, executável,
script embutido e PDF com ações recusados, limite por tipo, imagem
reprocessada, URL assinada com validade e sem acesso ao arquivo de outro
dono); e a do admin sobe o Filament com um painel que só registra o plugin e
prova que as proteções do painel vêm do pacote (sem acesso para não-admin e
admin inativo, allowlist de IP nas páginas, nas ações Livewire e no download
de exports, trilha de auditoria de criar/editar/excluir/bloquear com recusa
`denied` e falha fechada, foto de perfil só de upload da própria conta). Os testes de ponta a ponta dessas peças (rotas, painel, banco)
continuam na suíte do starter. Como instalar as dependências do
pacote está em [`packages/README.md`](../packages/README.md).

**Contas com membros nos testes.** Dado de conta (projetos, chaves) sem conta
atual é exceção — também num teste. Os helpers de
`tests/Feature/ApiKeys/Helpers.php` montam o arranjo como o produto faria:
`criarChave($pessoa)` e `projetoDe($pessoa, 'Nome')` criam na conta pessoal da
pessoa (e ela é quem criou), `naConta($pessoa, fn () => …)` roda algo na conta
dela, `contaPessoal($pessoa)` devolve a conta, e `comoSistema(fn () => …)` faz
a leitura direta de conferência sem filtro de conta — o que toda consulta de
teste fazia antes das contas. Os testes de telas do `/admin` com
`Livewire::test` (`tests/Feature/Admin`) rodam em modo sistema, declarado no
`tests/Pest.php` (o painel o declara pelo middleware; o `Livewire::test` não
passa por ele). As provas do isolamento ficam em `tests/Feature/Accounts`, nos
gatilhos em `tests/Feature/Database/AccountDatabaseGuardsTest.php` e nas
travas de arquitetura (`tests/Unit/Architecture/AccountIsolationTest.php` e
`tests/Feature/Architecture/AccountModelsTest.php`).

Os testes validam **conteúdo** das respostas, não só o status HTTP. Cada
módulo descreve o que a sua suíte cobre na seção *Testes* do próprio
documento: [autenticação](autenticacao.md#testes), [API e chaves](api.md#testes),
[uploads](uploads.md#testes), [painéis e /admin](admin-e-dashboards.md#testes),
[backup](backup.md#testes) e [filas](filas.md#testes).

Os testes da **demonstração** do kit (o pacote `twstec/kit-demo`, instalado
só no desenvolvimento — ver [demo.md](demo.md)) estão em dois lugares:

- **`packages/demo/tests`** (Orchestra Testbench, aplicação limpa): o que a
  demo liga sozinha — pontos de extensão, configuração, rotas, migrations,
  traduções, proteção das contas demo, fail-closed de produção, o comando
  `demo:uninstall` e as regras que só leem os arquivos do pacote;
- **`starters/livewire/tests/Demo`** (grupo `demo`) e os casos do produto
  marcados com `->group('demo')`: o que exercita a demo **dentro do
  aplicativo** (layout, componentes Blade, painel, `/admin`, gatilho no
  PostgreSQL).

Sem a demo instalada (`composer remove --dev twstec/kit-demo`), os testes do
grupo `demo` **pulam sozinhos**, com o motivo (`tests/TestCase.php`): a suíte
do produto é o `pest` de sempre, sem filtro de grupo. O CI roda os dois jeitos.

```bash
docker compose exec -T -w /var/packages/demo app ./vendor/bin/pest   # suíte do pacote
```

## Módulos opcionais: um teste por módulo, pulando sozinho

`accounts`, `uploads` e `admin` são opcionais ([instalação](instalacao.md)).
Todo teste do starter que exercita um deles fica no **grupo com o nome do
módulo** — as pastas e os arquivos inteiros em `tests/Pest.php`
(`pest()->group('accounts')->in('Feature/Tenancy', …)`), os casos soltos com
`->group('accounts')` no próprio teste; um teste que usa dois módulos fica nos
dois grupos. Sem o módulo instalado (`Kit::has`), o teste **pula**, com o
motivo (`tests/TestCase.php`) — a suíte de qualquer combinação é o `pest` de
sempre, sem `--exclude-group`.

Regras para escrever teste novo:

- Precisa de um módulo opcional? Ponha no grupo dele. Uma proteção que vale em
  qualquer combinação (sessão encerrada, verificação de e-mail, allowlist)
  merece também uma prova numa tela da base (perfil, notificações) — os
  testes de `AccountStatusEnforcementTest`, `EmailVerificationTest` e
  `AdminLivewireEndpointBarrierTest` têm os dois.
- Dataset com telas de módulo opcional: acrescente-as só com o módulo
  (`...(Kit::has('accounts') ? ['chaves de API' => '/api-keys'] : [])`).
- **Classe de apoio que depende de um módulo opcional nasce DENTRO do teste**
  (classe anônima: `new class implements DeletionCheck { … }`), nunca no topo
  do arquivo. O Pest carrega todos os arquivos antes de decidir o que pula:
  uma classe de topo que estende ou implementa um tipo de `accounts`,
  `uploads`, `admin` (ou do Filament) derruba a suíte inteira numa instalação
  sem o módulo ("Interface … not found") — foi o que reprovou a 2.0.0-beta.10
  na simulação "Livewire só a base". `tests/Feature/Architecture/OptionalModuleTestFilesTest.php`
  (nos dois starters) reprova isso. `use` no topo pode: ele não carrega nada.
  A trava lê os **tokens** do PHP e só acusa declaração de verdade
  (`class`/`interface`/`trait`/`enum` com nome, no topo do arquivo ou de um
  bloco `namespace`, que estende, implementa ou usa como trait um tipo do
  módulo — por `use`, apelido, `use` em grupo ou nome completo). Não acusa
  `X::class`, `new X` ou `instanceof X` dentro de função ou closure, classe
  anônima, comentário nem texto. Os casos que ela **não pode** acusar e os que
  ela **tem de** acusar (inclusive a classe da 2.0.0-beta.10) estão no próprio
  arquivo, como datasets.
- Teste que varre `app/` com `class_exists`: pule as classes que só carregam
  com um módulo (`TestCase::appClassLoadable()` — hoje, o PanelProvider do
  `/admin`, que estende o Filament).
- `tests/Feature/Modules/OptionalModulesTest.php` prova, em toda combinação,
  que existe exatamente o que está instalado (rotas, menu, `/admin`, model de
  usuário, configurações editáveis, agendamento) e, com `Kit::pretendAbsent`,
  que é a detecção quem decide (uma tela registrada sem perguntar reprova ali).

O CI roda a suíte nas combinações principais (completo, sem a demo, sem
uploads, só o `/admin`, só a base, sem o `/admin`). Para rodar uma combinação
localmente, numa CÓPIA do starter (nunca no do repositório — o instalador
mexe no `composer.json`):

```bash
php artisan tws:install --no-interaction --without=accounts,uploads,admin --no-demo
npm run build && ./vendor/bin/pest
```

## Testes contra o PostgreSQL

O `pest` puro roda em **SQLite em memória** (`phpunit.xml`): rápido e sem
banco nenhum. Produção é **PostgreSQL**, e o SQLite é tolerante onde o
PostgreSQL não é (coluna `uuid` nativa, transação abortada após erro, LIKE
que diferencia maiúsculas). Por isso o **CI roda a suíte contra PostgreSQL 18**
— é o check que vale — e em paralelo repete no SQLite, para o comando local
padrão continuar verde. Alguns testes (o gatilho das contas demo) só existem
no PostgreSQL e ficam como *skip* no SQLite.

Para rodar localmente contra o Postgres do docker de dev, use o
`phpunit.pgsql.xml`. No monorepo, ele aponta para um banco **separado**,
`tws_starter_test` (o do React, `tws_starter_react_test`; num projeto criado,
`<banco>_test`), nunca para o banco do `.env` — a suíte apaga o schema a
cada execução, e a `tests/TestCase.php` recusa qualquer banco cujo nome não
termine em `_test`.

```bash
# Uma vez só, se o volume do Postgres já existia antes deste arquivo
# (volumes novos já nascem com o banco — docker/postgres/initdb/):
docker compose exec postgres createdb -U tws tws_starter_test

# Suíte contra o PostgreSQL:
docker compose exec app ./vendor/bin/pest -c phpunit.pgsql.xml
```

Host, porta, usuário e senha vêm do `.env` (os mesmos do banco de dev); só o
nome do banco é forçado pelo `phpunit.pgsql.xml`.

**Uma suíte por vez no mesmo banco de teste.** Duas execuções simultâneas
contra o mesmo `<banco>_test` se atropelam (o `migrate:fresh` de uma apaga as
tabelas da outra; o que um teste grava com commit aparece na contagem de outro)
e dão falhas ao acaso. A `tests/TestCase.php` pega uma trava consultiva do
PostgreSQL (`pg_try_advisory_lock`) no começo da suíte e a segura até o fim: a
segunda execução para logo, com "Suíte recusada: outra execução está usando o
banco de teste", em vez de falhar ao acaso.

**Consultas com valor vindo de fora em coluna `uuid`** (URL, ação do Livewire,
filtro do /admin): use `Model::query()->byUuid($valor)` (escopo da
`RoutesByUuid`) ou `UuidColumn::where()`. No PostgreSQL, texto que não é uuid
comparado com a coluna derruba a consulta com 500; com o helper ele só não
encontra nada (404 uniforme).

## Testes E2E (Playwright)

Com a stack de dev no ar, prepare as pessoas fixas do E2E com o script
idempotente (rode antes de cada rodada e sempre que a regra do segundo fator
mudar). Ele cria ou devolve ao estado inicial `e2e@example.com` (a pessoa do
painel, sessão do `global-setup`), `login-e2e@example.com` (só do teste de
login pela tela) e `admin-e2e@example.com` (o admin do E2E), todas com o
e-mail já confirmado — a verificação de e-mail é exigida para entrar no
painel. Credenciais sobreponíveis via `E2E_USER_EMAIL`, `E2E_USER_PASSWORD`,
`E2E_LOGIN_USER_EMAIL`, `E2E_ADMIN_EMAIL` e `E2E_ADMIN_PASSWORD`.

```bash
docker compose exec -T app php artisan tinker --execute="require 'tests/e2e/fixtures.php';"
```

No monorepo, o E2E do Livewire usa a demonstração (o super admin demo faz
login no `/admin` e a limpeza); sem ela, o admin do E2E de
`tests/e2e/fixtures.php`. Os endereços vêm do `.env` do starter (a `APP_URL`);
sem `DEV_MAIL_PORT` no `.env`, o Mailpit é o do `docker-compose.yml` da raiz
(`http://localhost:18025`) — só quando o `composer.json` instala os pacotes
por path repository (o monorepo).

Depois rode a suíte, **de dentro de `starters/livewire`** (é onde estão o
`playwright.config.js`, o `package.json` e `tests/e2e`; o comando monta a pasta
atual no container):

```bash
cd starters/livewire
# em container (não exige Node local) — o --user evita artefatos
# root-owned (test-results/, tests/e2e/.auth/) no repositório:
docker run --rm --network host --user $(id -u):$(id -g) -e HOME=/tmp \
  -v $(pwd):/work -w /work \
  mcr.microsoft.com/playwright:v1.63.0-noble sh -c "npm install --ignore-scripts && npx playwright test"

# ou localmente, se tiver Node:  npx playwright test
```

O `email-verification.spec.js` faz o fluxo real do cadastro: registra uma
conta nova (`e2e-verificacao-<carimbo>@example.com`), lê o e-mail de
verificação na API do **Mailpit** (entregue pelo worker `queue`, então os dois
precisam estar no ar; `E2E_MAILPIT_URL`, no monorepo `http://localhost:18025`),
clica no link e confere o painel liberado. No fim — passando ou falhando — ele
**apaga o que criou**, como o `two-factor.spec.js` abaixo: a conta, pelo
`/admin` com o super admin demo, e as mensagens dela no Mailpit.

A limpeza dos dois specs mora em `tests/e2e/support/cleanup.js`
(`deleteAccountViaAdmin` e `deleteMailpitMessagesTo`). Spec novo que cadastrar
conta deve chamá-la num `finally`. Para conferir que nada sobrou depois de uma
rodada:

```bash
docker compose exec app php artisan tinker --execute='
  echo \App\Models\User::where("email", "like", "e2e-%@example.com")
    ->where("email", "!=", "e2e@example.com")->count();'
```

O `two-factor.spec.js` faz a verificação em duas etapas de ponta a ponta:
cria uma conta nova (`e2e-2fa-<carimbo>@example.com` — pelo cadastro, ou
pelo `/admin` com ele fechado) e liga o segundo fator pelo caminho que a
instalação oferece: opcional, define a senha de transação e **liga** no
perfil (código de ação sensível lido no Mailpit); obrigatório, passa pela
configuração ao entrar e confere no perfil o selo "obrigatória" e o desligar
travado. Depois sai, entra com a senha, confere que o painel continua fechado
no estado intermediário e conclui com o código de acesso lido no Mailpit. No
fim ele **apaga o que criou**: a conta, pelo `/admin` com a sessão gravada
pelo `global-setup`, e as mensagens dela no Mailpit. Ele usa conta própria de
propósito — ligar o segundo fator no `e2e@example.com` mudaria o login dos
outros specs. As mensagens das pessoas fixas (códigos de login e de
confirmação) saem do Mailpit no fim da rodada (`tests/e2e/global-teardown.js`).

Toda a suíte sai do mesmo IP e passa pelo limite de borda (300 requisições
por minuto por IP — ver
[Limite de requisições](seguranca.md#limite-de-requisições-rate-limit-e-contenção-da-trilha)).
Uma rodada cabe folgada nele; duas rodadas seguidas, não. Espere 60 s entre
uma rodada e a próxima, ou os últimos testes recebem 429.

### E2E do starter React

Em `starters/react/tests/e2e` (TypeScript, o mesmo Playwright 1.63), contra
a `APP_URL` do `.env` do React — no monorepo, `http://127.0.0.1:8181`; o host
é outro que o do Livewire para os cookies não colidirem (ver
[instalação](instalacao.md#starter-react)). O React não tem a demonstração,
então as pessoas fixas do E2E vêm de um script idempotente (`e2e@example.com`,
a pessoa do painel; `admin-e2e@example.com`, o admin que faz a limpeza; e
`login-e2e@` e `admin-login-e2e@example.com`, só dos testes de login pela
tela):

```bash
docker compose exec -T react-app php artisan tinker --execute="require 'tests/e2e/fixtures.php';"
cd starters/react
docker run --rm --network host --user $(id -u):$(id -g) -e HOME=/tmp \
  -v $(pwd):/work -w /work mcr.microsoft.com/playwright:v1.63.0-noble npx playwright test
```

Cobre: cadastro com verificação de e-mail e login com segundo fator (códigos e
links lidos no Mailpit), perfil (idioma, tema gravado na conta, foto),
senha de transação, contas (convidar → aceitar criando o acesso → trocar de
conta → transferir com senha de transação e código → remover), chave de API
com a secreta uma vez, projetos e o `/admin` (login e uma ação auditada). As
pessoas criadas pelos testes (`e2e-…@example.com`) saem no `finally` pelo
`/admin` (sem depender do idioma, `tests/e2e/support/cleanup.ts`), e uma
varredura no fim da suíte (`tests/e2e/global-teardown.ts`) apaga qualquer
resto — a rodada termina sem pessoa, conta, projeto, chave, convite, foto ou
mensagem de teste no banco e no Mailpit (as mensagens das pessoas fixas
também saem). O mesmo limite de borda vale: 60 s entre duas rodadas.

### E2E com segundo fator obrigatório e cadastro fechado

As duas suítes (Livewire e React) rodam verdes nas seis combinações de
`AUTH_TWO_FACTOR_REQUIRED` (`none`, `admins`, `all`) com
`AUTH_REGISTRATION_ENABLED` (`true`, `false`). O Playwright não lê essas
variáveis: quem decide é o servidor, e os passos se adaptam ao que ele
responde.

- **Pessoas fixas** (`tests/e2e/fixtures.php`): quem a regra alcança
  (`TwoFactorRequirement::appliesTo`; no modo `admins`, só os admins do E2E)
  nasce com o segundo fator **ligado** e com a senha de transação que ligar
  exige. O script apaga os códigos de verificação que elas tinham, para a
  rodada não começar dentro de um intervalo de reenvio. Rode-o de novo a cada
  troca de combinação.
- **Login com código:** o `global-setup` (painel e `/admin`) e os testes de
  login pela tela leem o código **real** no Mailpit, ignorando as mensagens
  que a pessoa já tinha. No `/admin`, o código entra no formulário que o
  Filament troca no lugar do login.
- **Uma pessoa fixa por login:** com o segundo fator, cada login manda um
  código e o código novo só sai depois do intervalo de reenvio (60 s). Por
  isso os testes de login pela tela usam pessoas próprias (`login-e2e@` e,
  no React, `admin-login-e2e@example.com`), e a limpeza do Livewire reusa a
  sessão do `/admin` gravada pelo `global-setup`, sem logar de novo.
- **Pessoa nova:** com o cadastro aberto, cadastro + link do Mailpit; fechado,
  criada pelo `/admin` e login pela tela. Se o servidor a leva à configuração
  do segundo fator, ela passa por ela (senha de transação → código do
  Mailpit) e segue para onde ia. Com o cadastro fechado, só o teste do
  próprio cadastro (verificação de e-mail) pula, com o motivo; o estado
  fechado é coberto pelo `registration.spec`.
- **Sem espera depois da configuração:** a ação sensível logo depois de
  configurar o segundo fator (chave de API, transferência de conta) manda o
  código na hora. O código da configuração é de outra família e não conta
  para o intervalo da confirmação de segurança (ver
  [autenticação](autenticacao.md#segundo-fator-obrigatório)).

**Como trocar a combinação.** As variáveis ficam no `.env` de
desenvolvimento do projeto:

```dotenv
AUTH_TWO_FACTOR_REQUIRED=all        # none | admins | all
AUTH_REGISTRATION_ENABLED=false     # true | false
```

Num **projeto criado**, os serviços PHP leem o `.env` montado, sem
`env_file`: basta salvar o arquivo, rodar as pessoas fixas de novo e a suíte.

```bash
docker compose exec -T app php artisan tinker --execute="require 'tests/e2e/fixtures.php';"
docker run --rm --network host --user $(id -u):$(id -g) -e HOME=/tmp \
  -v $(pwd):/work -w /work mcr.microsoft.com/playwright:v1.63.0-noble npx playwright test
```

No **monorepo**, o `docker-compose.yml` da raiz passa o `.env` de cada
starter aos containers (`env_file`), e a variável do container vence a do
arquivo. Depois de editar `starters/livewire/.env` ou `starters/react/.env`,
recrie os serviços PHP (o Compose recria só o que mudou) e reinicie o nginx,
que guarda o endereço do container antigo (sem isso, 502):

```bash
docker compose up -d app queue scheduler react-app react-queue react-scheduler
docker compose restart nginx react-nginx
docker compose exec -T app php artisan tinker --execute="require 'tests/e2e/fixtures.php';"
docker compose exec -T react-app php artisan tinker --execute="require 'tests/e2e/fixtures.php';"
```

Para voltar ao padrão, tire as duas linhas do `.env` e repita os comandos.
