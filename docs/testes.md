# Testes

Três camadas, todas em container:

```bash
docker compose exec app ./vendor/bin/pest                       # Pest em SQLite em memória (padrão local)
docker compose exec app ./vendor/bin/pest -c phpunit.pgsql.xml  # Pest contra PostgreSQL 18 (o que o CI exige)
npx playwright test                                             # E2E (ver abaixo como rodar em container)
```

Os testes validam **conteúdo** das respostas, não só o status HTTP. Cada
módulo descreve o que a sua suíte cobre na seção *Testes* do próprio
documento: [autenticação](autenticacao.md#testes), [API e chaves](api.md#testes),
[uploads](uploads.md#testes), [painéis e /admin](admin-e-dashboards.md#testes),
[backup](backup.md#testes) e [filas](filas.md#testes).

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

Com a stack de dev no ar, crie o usuário E2E (uma única vez por banco):

```bash
docker compose exec app php artisan tinker --execute='
  \App\Core\Auth\Models\User::factory()->create([
    "email" => "e2e@example.com",
    "password" => "E2eSenhaForte123",
  ]);'
# (credenciais sobreponíveis via E2E_USER_EMAIL / E2E_USER_PASSWORD)
```

Depois rode a suíte:

```bash
# em container (não exige Node local) — o --user evita artefatos
# root-owned (test-results/, tests/e2e/.auth/) no repositório:
docker run --rm --network host --user $(id -u):$(id -g) -e HOME=/tmp \
  -v $(pwd):/work -w /work \
  mcr.microsoft.com/playwright:v1.62.1-noble sh -c "npm install --ignore-scripts && npx playwright test"

# ou localmente, se tiver Node:  npx playwright test
```

Toda a suíte sai do mesmo IP e passa pelo limite de borda (300 requisições
por minuto por IP — ver
[Limite de requisições](seguranca.md#limite-de-requisições-rate-limit-e-contenção-da-trilha)).
Uma rodada cabe folgada nele; duas rodadas seguidas, não. Espere 60 s entre
uma rodada e a próxima, ou os últimos testes recebem 429.
