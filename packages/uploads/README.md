# twstec/kit-uploads

Uploads seguros do **TWS Laravel Starter Kit**, como pacote Laravel **sem
telas**: validação pelo conteúdo real, reprocessamento de imagem, nome seguro,
entrega por URL assinada, foto de perfil e o `POST /api/v1/uploads`. É a
quarta camada do kit: depende só do [`twstec/kit-accounts`](../accounts), do
[`twstec/kit-auth`](../auth), do [`twstec/kit-foundation`](../foundation) e do
Laravel — não conhece o painel de administração nem a interface, e um teste de
arquitetura na suíte do pacote garante isso.

Nesta versão o **dono do upload é a pessoa** (ver
[Quem é o dono](#quem-é-o-dono-do-upload)). Contas com membros chegam numa
versão futura.

- **Requisitos:** PHP 8.4+ com as extensões `fileinfo` e `gd`, Laravel 13,
  `twstec/kit-accounts`, `twstec/kit-auth` e `twstec/kit-foundation` 2.x.
- **Licença:** MIT.

## O que o pacote traz

| Peça | O que faz |
| --- | --- |
| `Services\SecureUploadService` | A função global única de upload: validação de segurança, limite por tipo sobre o conteúdo final, nome seguro (uuid + extensão do MIME real), gravação no disco, registro e log estruturado (`upload.stored` / `upload.rejected`) |
| `Services\FileSecurityValidator` | O núcleo da validação pelo conteúdo: executável disfarçado, MIME real (`finfo`) contra a allowlist por tipo, extensão divergente, script embutido (polyglot), PDF com JavaScript ou ação automática, teto de pixels e re-encode da imagem pela GD (falha fechada) |
| `Rules\SafeFile` | A mesma validação como regra, para formulários que não são Form Request (Filament, Livewire) |
| `Models\Upload` | Registro do arquivo aceito (uuid, `UPL-xxxxxx`, MIME real, tamanho, sha256) e `url()`, a URL temporária assinada |
| `Concerns\HasAvatar` | Foto de perfil do model de usuário: `avatar()` e `avatarUrl()` (coluna `avatar_upload_id`, do aplicativo) |
| `Http\Controllers` | `UploadController` (API v1) e `AvatarController` (avatar pela web), com os Form Requests e o `UploadResource` |
| `Http\UploadRoutes` | A rota `POST /api/v1/uploads` |
| `Support\SignedDelivery` | Liga a entrega assinada do Laravel no disco local de uploads |

## Instalação

**Hoje (monorepo):** o starter instala o pacote por *path repository*, como os
outros:

```json
"repositories": [
    {
        "type": "path",
        "url": "../../packages/uploads",
        "options": {
            "versions": { "twstec/kit-uploads": "2.x-dev" },
            "reference": "config"
        }
    }
],
"require": {
    "twstec/kit-uploads": "2.x-dev"
}
```

**Depois da publicação no Packagist:** `composer require twstec/kit-uploads:^2.0`.

O `UploadsServiceProvider` é descoberto automaticamente. Depois,
`php artisan migrate`. Para a foto de perfil, o model de usuário do aplicativo
usa a trait `HasAvatar` e ganha a coluna `avatar_upload_id` (chave estrangeira
para `uploads`, `nullOnDelete`) numa migration do próprio aplicativo.

## O que ele instala sozinho

Nenhuma proteção depende de o aplicativo lembrar de chamar algo:

- **Validação pelo conteúdo, limite por tipo, reprocessamento de imagem e
  nome seguro:** moram no serviço e no validador, e valem em qualquer
  chamada — não há opção para desligar.
- **Entrega por URL assinada:** `Upload::url()` devolve URL temporária
  assinada (`uploads.temporary_url_minutes`, 15 minutos). No disco `local`
  quem confere a assinatura e a validade é a entrega do Laravel (`serve`), e
  o **pacote a liga** no disco padrão de uploads quando ele é local — mesmo
  que o disco da aplicação não a declare ou a declare desligada. A assinatura
  cobre o caminho inteiro: a URL de um arquivo não abre o de outro.
- **A rota da API:** `POST /api/v1/uploads` com escopo `uploads:create`, no
  mesmo grupo das rotas v1 do `twstec/kit-accounts` — autenticação por chave
  (`resolve.tenant`, sempre), limite por chave (`throttle:api`) e envelope de
  erro vêm de lá.
- **Configuração padrão** em `config('uploads')`; as chaves de primeiro nível
  do `config/uploads.php` do aplicativo prevalecem
  (`vendor:publish --tag=uploads-config`).
- **Migration** com o **mesmo nome de arquivo** que tinha no aplicativo na 1.x
  (`2026_08_20_300000_create_uploads_table.php`): um banco que já a rodou não
  vê nada pendente.
- **Traduções** (pt-BR, en, es) das mensagens do upload e de cada motivo de
  recusa (`uploads.*`), sem namespace. **O aplicativo vence** na mesma chave
  (a regra do foundation, `Localization\PackageTranslations`).

Os eventos de log vão para o canal padrão da aplicação; o disco é o que a
aplicação escolher em `UPLOADS_DISK` (padrão: o `local` que toda aplicação
Laravel tem); o limitador é o `api` do foundation, que a aplicação pode
substituir.

**Opt-out** (só explícito): `UPLOADS_PROTECTIONS=false`
(`uploads.protections`) faz o pacote não mexer no disco — e ele grava um aviso
no log a cada boot, em qualquer ambiente. Também avisam a cada boot: disco de
uploads público (`visibility => public`, a entrega responde sem assinatura) e
outro disco já entregue em `/storage` (o pacote não liga a entrega para não
derrubar o boot). Um driver que não sabe assinar cai na URL comum do disco,
como na 1.x.

## Rota da API v1

Prefixo, grupo e nomes seguem os de `api_keys.api.routes` (do
`twstec/kit-accounts`) enquanto `uploads.api.routes` os deixar nulos. Para
registrar você mesmo, desligue o registro automático com
`UPLOADS_API_ROUTES=false` e chame o registro onde quiser:

```php
use Twstec\Kit\Uploads\Http\UploadRoutes;

// routes/api.php (o Laravel já põe o prefixo `api` e o grupo `api`)
UploadRoutes::register(prefix: 'v1', middleware: []);
```

A autenticação por chave entra no grupo em qualquer caso.

## Quem é o dono do upload

Hoje o dono é a pessoa, gravado de dois jeitos: pela API, em `tenant_uuid`
(o uuid do dono da chave, sem chave estrangeira) com `user_id` nulo; pela web
autenticada, em `user_id` (chave estrangeira, `nullOnDelete`) com
`tenant_uuid` nulo. `Upload::owner()` só enxerga o dono web. Nenhuma rota
entrega upload por dono — o acesso é sempre pela URL assinada do registro —,
então a diferença não abre o arquivo de uma pessoa para outra; é uma
incoerência de modelo que a fase de contas com membros unifica.

## O que o aplicativo liga

| O quê | Como | Por que não no pacote |
| --- | --- | --- |
| Formulário do perfil e campo de foto do painel `/admin` | Componentes do front sobre `SecureUploadService` e `SafeFile` | São a interface |
| Rota web do avatar | `Route::post('settings/avatar', [AvatarController::class, 'update'])` no grupo autenticado do aplicativo | Rotas web (sessão, verificação de e-mail, limites) são do front, como as do `twstec/kit-auth` |
| Coluna `users.avatar_upload_id` e a trait `HasAvatar` no model de usuário | Migration e model do aplicativo | O model de usuário é do aplicativo |
| Escopo `uploads:create` no catálogo da tela de chaves | `api_keys.scopes_catalog` na cópia do aplicativo | O front decide o que oferece; a API aceita o escopo de qualquer jeito |
| Resources e widgets de uploads no `/admin` | No starter | São do painel de administração |

## Nomes antigos → nomes novos

| Na 1.x | Na 2.0 |
| --- | --- |
| `App\Core\Uploads\…` | `Twstec\Kit\Uploads\…` (o resto do nome não muda) |
| `config/uploads.php`, migration `create_uploads_table` e `lang/*/uploads.php` no aplicativo | Vêm do pacote (a cópia do aplicativo, se existir, continua valendo) |
| `POST /api/v1/uploads` em `routes/api.php` | Registrada pelo pacote — tire-a de `routes/api.php` |

**Compatibilidade por uma versão.** Os nomes antigos continuam resolvendo, como
apelidos das classes novas (`src/Compat/legacy-aliases.php`): é a mesma classe,
então `instanceof` e type hints aceitam os dois nomes. Isso protege o que está
gravado fora do código — uma rota em cache da 1.x, o snapshot de um componente
Livewire ou um job com um `Upload`, o `use` da trait no model do usuário. Nada
de uploads grava o nome da classe no banco (a trilha de auditoria usa o nome
curto `upload`) nem no disco (o arquivo é achado pelas colunas `disk` e
`path`). **Os apelidos saem na 3.0**: troque os `use` do seu código.

## Testes

A suíte do pacote é isolada do aplicativo (Pest + Orchestra Testbench) e sobe
uma aplicação Laravel **limpa** — o esqueleto do Testbench, o foundation, o
auth, o accounts e este pacote, com um model de usuário mínimo, a rota que o
próprio pacote registra, o disco `local` do Laravel e o **ambiente de uma
aplicação nova** (o `tests/bootstrap.php` apaga do processo toda variável que
não está no `phpunit.xml`).

```bash
composer update
vendor/bin/pest
vendor/bin/pint --test
```

Ela prova que as proteções vêm do pacote (conteúdo falso com extensão de
imagem, polyglot, extensão divergente, executável e PDF com JavaScript
recusados; limite por tipo e teto de pixels; imagem reprocessada; URL assinada
que entrega o arquivo e recusa pedido sem assinatura, adulterado, vencido ou
com o caminho de outro dono; entrega ligada num disco que não a tinha;
opt-out e disco público com aviso; credencial e escopo na rota), a fiação do
provider (configuração, migration, rota no grupo do accounts e registro
manual), o canal de log, o disco e o limitador com os do aplicativo vencendo,
as traduções (com o aplicativo vencendo), os apelidos e a arquitetura. Os
testes com as telas, o painel e o banco do aplicativo ficam na suíte do
starter.
