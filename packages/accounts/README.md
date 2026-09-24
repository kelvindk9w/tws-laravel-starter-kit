# twstec/kit-accounts

Contas e API do **TWS Laravel Starter Kit**, como pacote Laravel **sem telas**:
projetos, chaves de API e a API v1. É a terceira camada do kit: depende só do
[`twstec/kit-auth`](../auth), do [`twstec/kit-foundation`](../foundation) e do
Laravel — não conhece uploads, o painel de administração nem a interface, e um
teste de arquitetura na suíte do pacote garante isso.

Nesta versão o **dono é a pessoa**: cada projeto e cada chave pertencem a um
usuário. Contas com membros e papéis chegam numa versão futura.

- **Requisitos:** PHP 8.4+, Laravel 13, `twstec/kit-auth` e
  `twstec/kit-foundation` 2.x.
- **Licença:** MIT.

## O que o pacote traz

| Peça | O que faz |
| --- | --- |
| `ApiKeys\Support` | Par de chaves `pk_`/`sk_` (`ApiKeyGenerator`) e o hash da secreta com HMAC-SHA256 + pepper, verificado com `hash_equals` (`ApiKeyHasher`): pepper vazio nunca é usado, peppers anteriores e o legado do pepper vazio (só com flag) migram no primeiro uso, e os avisos de produção sobre o pepper (`PepperWarnings`) |
| `ApiKeys\Models\ApiKey` | Escopos `recurso:acao` com curinga, vínculo com projetos e a marca de restrição (`restricted_to_projects`), validade, rotação com período de graça, inatividade, `last_used_at` com escrita limitada |
| `ApiKeys\Services\ApiKeyService` | Criar, rotacionar (herda nome, escopos, projetos e a restrição), revogar e vincular projetos |
| `ApiKeys\Console\ProcessApiKeyInactivity` | `api-keys:process-inactivity`: aviso prévio por e-mail e desativação por inatividade |
| `ApiKeys\Http`, `Tenancy\Http` | Controllers, Form Requests e Resources da API v1; middlewares `EnsureApiKeyScope` (`scope`) e `EnsureAccountWideApiKey` (`account.key`) |
| `Tenancy\Middleware\ResolveTenant` | `resolve.tenant`: autentica o par de chaves, recusa chave inutilizável e dono inativo ou com e-mail não confirmado, limita as falhas por chave e por IP, vincula o request log ao dono |
| `Tenancy` | `TenantContext` e os helpers `tenant()`/`tenantKey()`, `Project` com o recorte `visibleToApiKey`, `ProjectService` (o CRUD único do painel e da API), `AccountOverviewQuery` (os números do painel do cliente) |
| `Http\ApiRoutes` | As rotas `/api/v1/api-keys…` e `/api/v1/projects…` |

## Instalação

**Hoje (monorepo):** o starter instala o pacote por *path repository*, como os
outros:

```json
"repositories": [
    {
        "type": "path",
        "url": "../../packages/accounts",
        "options": {
            "versions": { "twstec/kit-accounts": "2.x-dev" },
            "reference": "config"
        }
    }
],
"require": {
    "twstec/kit-accounts": "2.x-dev"
}
```

**Depois da publicação no Packagist:** `composer require twstec/kit-accounts:^2.0`.

O `AccountsServiceProvider` é descoberto automaticamente. Depois,
`php artisan migrate`. O model de usuário é o do aplicativo, lido de
`auth.providers.users.model` (ver o README do `twstec/kit-auth`); o pacote o
trata pelo contrato `AuthUser`.

## O que ele instala sozinho

Nenhuma proteção depende de o aplicativo lembrar de chamar algo:

- **Autenticação por chave:** o alias `resolve.tenant`, que entra SEMPRE no
  grupo das rotas v1 (inclusive quando o aplicativo as registra ele mesmo).
- **Escopo e operação de conta:** os aliases `scope` e `account.key`,
  declarados em cada rota.
- **Limite por chave:** o `throttle:api` na frente do grupo `api` e o
  `ResolveTenant` logo antes do `ThrottleRequests` na lista de prioridade — o
  limite roda depois da autenticação e conta a chave (ou o dono, com
  `RATE_LIMIT_API_BY=tenant`), não o IP. O limitador `api` é do foundation.
- **Limite de falhas de autenticação** por chave pública + IP e um teto por
  IP, dentro do próprio `ResolveTenant` (`ApiRateLimit`, do foundation), antes
  de qualquer consulta.
- **Envelope de erro da API** (`{"error": {"code", "message",
  "correlation_id"}}`) para tudo em `api/*`, sem stack trace, classe ou
  caminho de servidor — nem com `APP_DEBUG=true` (o `ApiErrorRenderer` do
  foundation, registrado no tratador de exceções).
- **Regras que moram no domínio** e valem com qualquer rota: pepper
  obrigatório no hash (`API_KEYS_HASH_PEPPER`, e a `APP_KEY` quando ele não
  existe **ou está vazio** — vazio nunca é pepper), peppers anteriores com
  migração no primeiro uso (ver abaixo), recusa de chave revogada, expirada, rotacionada fora da graça ou
  inativa há mais que o limite (na autenticação — não depende do agendamento),
  recusa de dono inativo ou com e-mail não confirmado, recorte de projetos pelo
  vínculo da chave (404 uniforme fora dele).
- **Configuração padrão** em `config('api_keys')`; as chaves de primeiro nível
  do `config/api_keys.php` do aplicativo prevalecem
  (`vendor:publish --tag=accounts-config`).
- **Migrations** com os **mesmos nomes de arquivo** que tinham no aplicativo na
  1.x (`projects`, `api_keys`, `api_key_project` e a coluna
  `restricted_to_projects`): um banco que já as rodou não vê nada pendente.
- **Traduções** (pt-BR, en, es) das mensagens da API (`api_keys.*`) e do
  assunto do aviso de inatividade (`mail.api_key_inactivity.subject`), sem
  namespace. **O aplicativo vence** na mesma chave (a regra do foundation,
  `Localization\PackageTranslations`).
- **Comando** `api-keys:process-inactivity` e o aviso de inatividade na
  galeria `/mail-preview` do foundation.

Os aliases só entram se o aplicativo não declarou um de mesmo nome, e um
render de exceção declarado no `bootstrap/app.php` do aplicativo roda antes do
envelope do pacote.

**Opt-out** (só explícito): `API_KEYS_API_PROTECTIONS=false`
(`api_keys.api.protections`) não instala os aliases, o `throttle:api`, a
prioridade nem o envelope — e o pacote grava um aviso no log a cada boot, em
qualquer ambiente. Só é seguro se a aplicação instalar as mesmas proteções por
conta própria.

## Pepper do hash das chaves

| Variável | Config | O que faz |
| --- | --- | --- |
| `API_KEYS_HASH_PEPPER` | `api_keys.hash_pepper` | Pepper atual. Ausente, vazio ou só espaços → `APP_KEY` (vazio conta como ausente, também no `ApiKeyHasher`, que cobre uma cópia antiga do config no aplicativo). Sem pepper e sem `APP_KEY`, o hash é recusado (`MissingApiKeyPepperException`) |
| `API_KEYS_PREVIOUS_HASH_PEPPERS` | `api_keys.previous_peppers` | Peppers anteriores, separados por vírgula (itens vazios descartados). A secreta que confere com um deles autentica e tem o hash regravado com o atual |
| `API_KEYS_ACCEPT_EMPTY_PEPPER_LEGACY` | `api_keys.accept_empty_pepper_legacy` | Desligada por padrão. Ligada, aceita e migra chaves gravadas com pepper vazio (antes desta correção) |

Na autenticação, o `ApiKeyHasher::check()` calcula e compara com `hash_equals`
**todos** os peppers aceitos, sem saída antecipada: o custo depende só da
configuração, e a chave pública inexistente (conferida contra um hash
fictício com o mesmo pepper) leva o mesmo tempo. A migração acontece depois
de a autenticação passar inteira, com escrita condicional ao hash antigo, e
grava `api_keys.secret_hash.migrated` no `request_log` (chave, dono e origem —
sem segredo). A recusa continua 401 no envelope, contando para o limite de
falhas.

Em `APP_ENV=production`, o provider grava aviso no log a cada boot quando não
há pepper dedicado (ausente ou vazio) e quando a flag do legado está ligada —
aviso, não recusa. Roteiro de transição em [`docs/api.md`](../../docs/api.md#roteiro-de-transição).

## Rotas da API v1

O pacote registra as rotas com o prefixo `api/v1`, o grupo `api` e os nomes
`api.v1.*` (`api_keys.api.routes`). Para registrar você mesmo — outro
prefixo, outro grupo —, desligue o registro automático com
`API_KEYS_API_ROUTES=false` e chame o registro onde quiser:

```php
use Twstec\Kit\Accounts\Http\ApiRoutes;

// routes/api.php (o Laravel já põe o prefixo `api` e o grupo `api`)
ApiRoutes::register(prefix: 'v1', middleware: []);
```

A autenticação por chave (`resolve.tenant`) entra no grupo em qualquer caso.
O envelope de erro vale para `api/*`: um prefixo fora de `api/` fica sem ele.

## O que o aplicativo liga

| O quê | Como | Por que não no pacote |
| --- | --- | --- |
| Telas de chaves, projetos e painel do cliente | Componentes do front sobre `ApiKeyService`, `ProjectService` e `AccountOverviewQuery` | São a interface |
| Agendamento do `api-keys:process-inactivity` | `Schedule::command('api-keys:process-inactivity')->daily()` em `routes/console.php` | A ordem do agendamento é do aplicativo; a recusa da chave inativa já vale na autenticação sem ele |
| Corpo do aviso de inatividade | View `mail.messages.api-key-inactivity-warning` | É interface (o layout `<x-email::…>` é do foundation) |
| Catálogo de escopos oferecido na tela | `api_keys.scopes_catalog` na cópia do aplicativo | O front decide o que oferece; a API aceita qualquer `recurso:acao` bem formado |
| Resources do painel `/admin` | No starter | São do painel de administração |

## Nomes antigos → nomes novos

| Na 1.x | Na 2.0 |
| --- | --- |
| `App\Core\Tenancy\…` | `Twstec\Kit\Accounts\Tenancy\…` (o resto do nome não muda) |
| `App\Core\ApiKeys\…` | `Twstec\Kit\Accounts\ApiKeys\…` (o resto do nome não muda) |
| `App\Core\Tenancy\Providers\TenancyServiceProvider` em `bootstrap/providers.php` | `Twstec\Kit\Accounts\AccountsServiceProvider`, descoberto automaticamente — tire-o de `bootstrap/providers.php` |
| Aliases `resolve.tenant`, `scope`, `account.key`, o `throttle:api` no grupo `api`, a prioridade do `ResolveTenant` e o render do `ApiErrorRenderer` em `bootstrap/app.php` | Instalados pelo pacote — tire-os de `bootstrap/app.php` |
| Rotas de chaves e projetos em `routes/api.php` | Registradas pelo pacote — tire-as de `routes/api.php` |
| `ProcessApiKeyInactivity` em `$this->commands()` do `AppServiceProvider` | Registrado pelo pacote |
| `app/Core/Tenancy/helpers.php` e `app/Core/ApiKeys/Mail/previews.php` no `autoload.files` | Carregados pelo pacote — tire-os do `composer.json` |
| `?User` em `tenant()`, `TenantContext::user()` e nos serviços | `?AuthUser` (o objeto continua sendo o model do aplicativo) |

**Compatibilidade por uma versão.** Os nomes antigos continuam resolvendo, como
apelidos das classes novas (`src/Compat/legacy-aliases.php`): é a mesma classe,
então `instanceof` e type hints aceitam os dois nomes. Isso protege o que está
gravado fora do código — o aviso de inatividade que estava na fila no deploy
(com o nome da classe e o do model da chave), o snapshot de um componente
Livewire aberto no navegador, uma rota em cache. **Os apelidos saem na 3.0**:
troque os `use` do seu código.

## Testes

A suíte do pacote é isolada do aplicativo (Pest + Orchestra Testbench) e sobe
uma aplicação Laravel **limpa** — o esqueleto do Testbench, o foundation, o
auth e este pacote, com um model de usuário mínimo, as rotas que o próprio
pacote registra e o **ambiente de uma aplicação nova**: o `tests/bootstrap.php`
apaga do processo toda variável que não está no `phpunit.xml` (o container de
desenvolvimento do kit injeta o `.env` do starter; o CI não injeta nada), então
a suíte dá o mesmo resultado nos dois lugares.

```bash
composer update
vendor/bin/pest
vendor/bin/pint --test
```

Ela prova que as proteções vêm do pacote (401 no envelope sem detalhe, limite
de falhas por chave e por IP, 403 de escopo e de operação de conta, 404 fora do
vínculo, 429 do limite por chave com outra chave do mesmo IP passando, chave
revogada/expirada/rotacionada/inativa e dono não verificado recusados, pepper
(vazio vale como ausente, peppers anteriores e legado migrados no primeiro uso,
avisos de produção),
opt-out com aviso), a fiação do provider (rotas, aliases, prioridade, envelope
sem duplicar, registro manual das rotas), o canal de log e o limitador vindos
do foundation com os do aplicativo vencendo, as traduções (com o aplicativo
vencendo), os apelidos e a arquitetura. Os testes com as telas, o painel e o
banco do aplicativo ficam na suíte do starter.
