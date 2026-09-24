# twstec/kit-auth

A autenticação do **TWS Laravel Starter Kit**, como pacote Laravel **sem telas**.
É a segunda camada do kit: depende só do [`twstec/kit-foundation`](../foundation)
e do Laravel — não conhece contas, chaves de API, uploads, o painel de
administração nem a interface, e um teste de arquitetura na suíte do pacote
garante isso.

- **Requisitos:** PHP 8.4+, Laravel 13, `twstec/kit-foundation` 2.x.
- **Licença:** MIT.

## O que o pacote traz

| Peça | O que faz |
| --- | --- |
| `Actions` | A regra de cada fluxo, sem resposta HTTP: `AttemptLogin` (bloqueio por tentativas por e-mail+IP, conta ativa, anti-enumeração, sessão regenerada, início do segundo fator), `CompleteTwoFactorLogin`, `Logout`, `RegisterUser`, `SendPasswordResetLink`, `ResetPassword`, `VerifyEmail`, `ResendEmailVerification` |
| `Services` | `TwoFactorLogin` (segundo fator por e-mail, limites por código, conta e IP), `VerificationCodes` (motor dos códigos), `SensitiveActionService` (senha de transação + código → token de uso único), `TransactionPasswordService` |
| `Contracts` | `AuthUser` (o que o pacote espera do model de usuário), os contratos de resposta (`Contracts\Responses`), `AccountProtection` (contas protegidas), `LoginPrefillProvider` (credenciais sugeridas no login), `VerificationChannelDriver` |
| `Http` | Controllers de ENVIO (sem telas) com o próprio `throttle:sensitive`, Form Requests, respostas padrão por redirecionamento e os middlewares `EnsureAccountIsActive`, `EnsureEmailIsVerified` e `RequiresSensitiveActionToken` |
| `Models` | `VerificationCode`, `SensitiveActionToken` e as traits do model de usuário (`Models\Concerns`) |
| `Mail`, `Notifications` | E-mail do código (login e ação sensível), recuperação de senha e verificação de e-mail, no layout do foundation e no idioma do destinatário |
| `PasswordPolicy`, `Support`, `Enums`, `Exceptions` | Política de senha configurável, verificação de e-mail, estado intermediário do segundo fator, pós-login seguro, resultados e recusas |

## Instalação

**Hoje (monorepo):** o starter instala o pacote por *path repository*, como o
foundation:

```json
"repositories": [
    {
        "type": "path",
        "url": "../../packages/auth",
        "options": {
            "versions": { "twstec/kit-auth": "2.x-dev" },
            "reference": "config"
        }
    }
],
"require": {
    "twstec/kit-auth": "2.x-dev"
}
```

**Depois da publicação no Packagist:** `composer require twstec/kit-auth:^2.0`.

O `AuthServiceProvider` é descoberto automaticamente. Depois, `php artisan migrate`.

## O model de usuário é do aplicativo

O pacote nunca nomeia a classe do usuário: lê a que está em
`auth.providers.users.model` e confere que ela implementa `AuthUser`
(configuração errada falha alto, com a explicação). O model do aplicativo:

```php
namespace App\Models;

use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Auth\Models\Concerns\KitAuthenticatable;
use Twstec\Kit\Foundation\Identifiers\HasPublicCode;
use Twstec\Kit\Foundation\Identifiers\RoutesByUuid;

class User extends Authenticatable implements AuthUser, HasLocalePreference
{
    use HasPublicCode, HasUuids, KitAuthenticatable, Notifiable, RoutesByUuid;

    protected const PUBLIC_CODE_PREFIX = 'USR';

    public function uniqueIds(): array
    {
        return ['uuid'];
    }
}
```

`KitAuthenticatable` junta as partes, que também existem sozinhas:
`HasAccountStatus`, `HasTransactionPassword`, `HasTwoFactor`,
`ProtectsAccounts`, `VerifiesEmail`, `SendsPasswordResetNotification` e
`HasPreferredLocale`. Os casts das colunas de autenticação vêm delas.

A tabela `users` é a do esqueleto Laravel; as migrations do pacote acrescentam
`uuid`, `codigo_publico`, `status`, `transaction_password`,
`transaction_password_set_at`, `two_factor_enabled_at` e `locale`, e criam
`verification_codes` e `sensitive_action_tokens`.

## O que ele instala sozinho

Nenhuma proteção depende de o aplicativo lembrar de chamar algo:

- **Status da conta a cada requisição web:** `EnsureAccountIsActive` é anexado
  ao FIM do grupo `web` (depois do que o aplicativo pôs nele — no starter,
  depois do `SetLocale`, para a recusa sair no idioma da conta). Conta
  bloqueada ou pendente com sessão aberta perde a sessão na requisição
  seguinte.
- **Aliases** `verified` (a regra do kit: exigência desligável por
  `AUTH_EMAIL_VERIFICATION_REQUIRED`, conta protegida conta como verificada —
  substitui o do framework) e `sensitive.token` (token de ação sensível de uso
  único). Um alias de mesmo nome declarado pelo aplicativo prevalece.
- **`throttle:sensitive` nos envios:** cada controller do pacote declara o
  próprio limite (`HasMiddleware`). Uma rota que aponte para ele já nasce
  limitada; o limitador `sensitive` é do foundation.
- **Limites dentro das regras** (valem com qualquer controller ou front):
  bloqueio de login por e-mail+IP (`auth.login`), segundo fator por código,
  conta e IP (`auth.two_factor`, `auth.verification`), intervalos de reenvio.
- **Configuração padrão** das chaves do kit em `config('auth')`
  (`password_rules`, `login`, `transaction_password`, `verification`,
  `email_verification`, `sensitive_action`, `two_factor`, `web_protections`).
  As chaves de primeiro nível do `config/auth.php` do aplicativo prevalecem.
- **Migrations** com os **mesmos nomes de arquivo** que tinham no aplicativo
  na 1.x: um banco que já as rodou não vê nada pendente.
- **Traduções** (pt-BR, en, es) das mensagens do domínio (`auth.*`) e dos
  assuntos dos e-mails (`mail.*.subject`), sem namespace. **O aplicativo
  vence** na mesma chave (a regra do foundation,
  `Localization\PackageTranslations`).
- **Respostas padrão** de cada contrato, por `bindIf` (a do aplicativo
  prevalece).
- **Galeria de e-mails:** os três e-mails de autenticação entram no
  `/mail-preview` do foundation.

**Opt-out** (só explícito): `AUTH_WEB_PROTECTIONS=false`
(`auth.web_protections.enabled`) não instala o middleware de status nem os
dois aliases — e o pacote grava um aviso no log a cada boot. Só é seguro se a
aplicação instalar as mesmas proteções por conta própria.

## O que o front liga

| O quê | Como | Por que não no pacote |
| --- | --- | --- |
| Telas (login, cadastro, código, esqueci/redefinir senha, aviso de e-mail, senha de transação) | Rotas GET e views do front; os POSTs apontam para os controllers do pacote | São a interface; o endereço de cada rota é do front |
| Respostas próprias (JSON, Inertia…) | `bind` do contrato de `Contracts\Responses` num provider do app | As padrão redirecionam para as rotas nomeadas `login`, `dashboard`, `two-factor.challenge` e `verification.notice` |
| Corpo dos e-mails | Views `mail.messages.verification-code`, `mail.messages.password-reset`, `mail.messages.email-verification` | São interface (o layout `<x-email::…>` é do foundation) |
| Ações Livewire exigirem e-mail confirmado | `Livewire::addPersistentMiddleware([EnsureEmailIsVerified::class])` | O pacote não conhece o Livewire |
| `user:make-admin` e o acesso ao `/admin` | No starter (vão para o pacote admin) | São do painel de administração |
| Contas protegidas, credenciais sugeridas | Registrar `AccountProtection` / `LoginPrefillProvider` | Pontos de extensão; sem registro, nada é protegido nem preenchido |

O guia completo, com o passo a passo de um front novo, está em
[`docs/autenticacao.md`](../../docs/autenticacao.md).

## Nomes antigos → nomes novos

| Na 1.x | Na 2.0 |
| --- | --- |
| `App\Core\Auth\…` | `Twstec\Kit\Auth\…` (o resto do nome não muda) |
| `App\Core\Auth\Models\User` | `App\Models\User` — do aplicativo (o apelido do nome antigo é do starter) |
| `App\Core\Auth\Console\MakeAdminUser` | `App\Console\Commands\MakeAdminUser` — do starter |
| `App\Core\Auth\Providers\AuthServiceProvider` em `bootstrap/providers.php` | Descoberto automaticamente — tire-o de `bootstrap/providers.php` |
| `EnsureAccountIsActive` no grupo `web` e os aliases `verified`/`sensitive.token` em `bootstrap/app.php` | Instalados pelo pacote — tire-os de `bootstrap/app.php` |
| `throttle:sensitive` declarado em cada rota de envio | Vem com o controller do pacote — tire-o da rota (senão o limite conta duas vezes) |
| `create()`/`edit()`/`notice()` dos controllers de autenticação | As telas são do front (no starter, `App\Http\Controllers\Auth\AuthPageController`) |
| `User $user` nas implementações de `AccountProtection`, `VerificationChannelDriver` e `RegisterResponse` | `AuthUser $user` |
| `app/Core/Auth/Mail/previews.php` no `autoload.files` | Carregado pelo pacote — tire-o do `composer.json` |

**Compatibilidade por uma versão.** Os nomes antigos `App\Core\Auth\…`
continuam resolvendo, como apelidos das classes novas
(`src/Compat/legacy-aliases.php`): é a mesma classe, então `instanceof` e type
hints aceitam os dois nomes. Isso protege o que está gravado fora do código —
a notificação ou o e-mail que estava na fila no deploy (com o nome da classe e
o do model do destinatário) e config ou extensão que ainda usa o nome antigo.
**Os apelidos saem na 3.0**: troque os `use` do seu código.

## Testes

A suíte do pacote é isolada do aplicativo (Pest + Orchestra Testbench) e sobe
uma aplicação Laravel **limpa** — o esqueleto do Testbench, o foundation e este
pacote, com um model de usuário mínimo e rotas que não declaram nenhum
middleware além do grupo `web`:

```bash
composer update
vendor/bin/pest
vendor/bin/pint --test
```

Ela prova que as proteções vêm do pacote (login com bloqueio por tentativas e
o limite do controller, conta bloqueada recusada no login e com a sessão
encerrada, segundo fator exigido e o limite do código, `verified` e
`sensitive.token`, o opt-out com aviso), a fiação do provider, as traduções
(com o aplicativo vencendo), os apelidos e a arquitetura. Os testes de ponta a
ponta com as telas, o painel e o banco do aplicativo ficam na suíte do starter.
