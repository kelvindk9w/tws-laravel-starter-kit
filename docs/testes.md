# Testes

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

## Testes contra o PostgreSQL

O `pest` puro roda em **SQLite em memória** (`phpunit.xml`): rápido e sem
banco nenhum. Produção é **PostgreSQL**, e o SQLite é tolerante onde o
PostgreSQL não é (coluna `uuid` nativa, transação abortada após erro, LIKE
que diferencia maiúsculas). Por isso o **CI roda a suíte contra PostgreSQL 18**
— é o check que vale — e em paralelo repete no SQLite, para o comando local
padrão continuar verde. Alguns testes (o gatilho das contas demo) só existem
no PostgreSQL e ficam como *skip* no SQLite.

Para rodar localmente contra o Postgres do docker de dev, use o
`phpunit.pgsql.xml`. Ele aponta para um banco **separado**,
`tws_starter_test`, nunca para o banco do `.env` — a suíte apaga o schema a
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

**Consultas com valor vindo de fora em coluna `uuid`** (URL, ação do Livewire,
filtro do /admin): use `Model::query()->byUuid($valor)` (escopo da
`RoutesByUuid`) ou `UuidColumn::where()`. No PostgreSQL, texto que não é uuid
comparado com a coluna derruba a consulta com 500; com o helper ele só não
encontra nada (404 uniforme).

## Testes E2E (Playwright)

Com a stack de dev no ar, crie o usuário E2E (uma única vez por banco). A
factory cria a conta **com o e-mail já confirmado** — a verificação de e-mail
é exigida para entrar no painel; sem isso o login do `global-setup` cairia na
tela de aviso. Se o usuário já existia com o e-mail pendente, a migration
`mark_existing_users_email_as_verified` o confirma no `migrate`.

```bash
docker compose exec app php artisan tinker --execute='
  \App\Models\User::factory()->create([
    "email" => "e2e@example.com",
    "password" => "E2eSenhaForte123",
  ]);'
# (credenciais sobreponíveis via E2E_USER_EMAIL / E2E_USER_PASSWORD)
```

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
precisam estar no ar; `E2E_MAILPIT_URL`, padrão `http://localhost:18025`),
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
cadastra uma conta nova (`e2e-2fa-<carimbo>@example.com`), confirma o
e-mail, define a senha de transação, **liga** o segundo fator no perfil
(código de ação sensível lido no Mailpit), sai, entra com a senha, confere
que o painel continua fechado no estado intermediário e conclui com o código
de acesso lido no Mailpit. No fim ele **apaga o que criou**: a conta, pelo
`/admin` com o super admin demo (por isso precisa do modo demo ligado), e as
mensagens dela no Mailpit. Ele usa conta própria de propósito — ligar o
segundo fator no `e2e@example.com` quebraria o login dos outros specs.

Toda a suíte sai do mesmo IP e passa pelo limite de borda (300 requisições
por minuto por IP — ver
[Limite de requisições](seguranca.md#limite-de-requisições-rate-limit-e-contenção-da-trilha)).
Uma rodada cabe folgada nele; duas rodadas seguidas, não. Espere 60 s entre
uma rodada e a próxima, ou os últimos testes recebem 429.
