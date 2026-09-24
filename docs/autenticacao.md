# Autenticação

Implementação própria e enxuta em `app/Core/Auth/` — **sem** Breeze/Jetstream/Fortify.
Autenticação web por **sessão** (os painéis usam sessão/cookie; a API pública usa
o par de chaves pk_/sk_ no header — ver [API e chaves de API](api.md) e [Tenancy](tenancy.md)).

## Model User (`app/Core/Auth/Models/User.php`)

- Identificadores em 3 camadas (anti-enumeração): `id` interno nunca exposto, `uuid` (HasUuids)
  e `codigo_publico` `USR-xxxxxx` (HasPublicCode).
- **Duas senhas separadas** (quem rouba a sessão ou a senha de login ainda não executa ação sensível): `password` (login) e `transaction_password`
  (ações sensíveis), ambas com cast `hashed` → **Argon2id** (hash lento e resistente a GPU;
  `config/hashing.php`, `HASH_DRIVER`). Parâmetros Argon por `.env` (`ARGON_*`);
  `rehash_on_login` faz upgrade gradual de hashes antigos.
- **Dados pessoais criptografados em repouso** (um dump vazado não expõe o nome): `name` com cast
  `encrypted` (AES-256-GCM da `APP_KEY`). `email` fica em texto (é a chave de lookup
  do login; UNIQUE no banco). Classificação de dados: o que pode ser texto
  é texto; o que exige criptografia é criptografado; segredos ficam só como hash.
- `status` (`UserStatus`): **deny-by-default** — só conta `active` opera, e isso vale
  **a cada requisição**, não só no login:
  - **Web** (`EnsureAccountIsActive`, no grupo `web`): conta bloqueada ou pendente com
    sessão aberta tem a sessão encerrada na requisição seguinte (página, formulário ou
    ação Livewire — o endpoint de atualização do Livewire também está no grupo `web`) e
    vai ao login com a mensagem traduzida `auth.account_inactive`. Chamada que espera
    JSON recebe 401 com a mesma mensagem.
  - **API**: o `ResolveTenant` confere o dono da chave a cada chamada; conta não ativa
    → 401 no envelope padrão, sem dizer o motivo. A chave não é revogada — volta a
    funcionar se a conta for reativada.
  - **/admin**: `canAccessPanel` exige `is_admin` + conta ativa (também nas ações
    Livewire do painel — o `Authenticate` do Filament é middleware persistente).
  - **`pending` é tratado como `blocked`**: é conta ainda não liberada, e o kit não tem
    fluxo que dependa de ela operar parcialmente. Se o seu projeto precisar de conta
    pendente com acesso restrito (ex.: completar cadastro), crie a exceção explícita
    no `EnsureAccountIsActive` — não afrouxe o `isActive()`.

## Fluxos web (rotas em `routes/web.php`, Form Requests em `Http/Requests`)

| Fluxo | Rotas | Observações |
|---|---|---|
| Registro | `GET/POST /register` | senha forte via config (`AUTH_PASSWORD_MIN`); sessão regenerada |
| Login | `GET/POST /login` | **bloqueio por tentativas** (RateLimiter, e-mail+IP — `AUTH_LOGIN_MAX_ATTEMPTS`/`AUTH_LOGIN_LOCKOUT_MINUTES`); mensagem única anti-enumeração; `session()->regenerate()` (fixation) |
| Logout | `POST /logout` | invalida sessão + renova token CSRF |
| Recuperação | `GET/POST /forgot-password`, `GET/POST /reset-password` | broker nativo do Laravel (token com hash + expiração); resposta uniforme anti-enumeração; `remember_token` renovado no reset |
| Senha de transação | `GET/PUT /settings/transaction-password` | deve ser **diferente** da senha de login; alteração exige a atual |
| Ação sensível | `POST /sensitive-actions/code` + `POST /sensitive-actions/confirm` | ver abaixo |

Todas as rotas sensíveis passam por `throttle:sensitive` (5/min padrão,
`config/security.php`) além dos limites de negócio próprios.

## Ação sensível: senha de transação + código por e-mail (2FA)

Fluxo (para saque, rotação de chave de API e alterações críticas):

1. `POST /sensitive-actions/code` com a senha de transação → gera código de
   **6 dígitos**, persiste **somente o hash** (`verification_codes`) com
   expiração (`AUTH_VERIFICATION_CODE_TTL_MINUTES`, padrão 10 min) e envia por
   **e-mail enfileirado** (Redis; Mailpit em dev). Reenvio com cooldown
   (`AUTH_VERIFICATION_CODE_RESEND_COOLDOWN_SECONDS`, padrão 60s); código novo
   invalida os anteriores.
2. `POST /sensitive-actions/confirm` com o código → valida (expiração +
   máx. `AUTH_VERIFICATION_CODE_MAX_ATTEMPTS` tentativas, padrão 5 — ao esgotar,
   o código morre) e emite o **token de ação sensível**: 64 chars aleatórios,
   só hash SHA-256 no banco, curta duração (`AUTH_SENSITIVE_TOKEN_TTL_MINUTES`,
   padrão 10 min), **uso único** (consumido na validação).
3. Rotas de operação sensível usam o middleware **`sensitive.token`**
   (`RequiresSensitiveActionToken`): exige o token no header
   `X-Sensitive-Action-Token` (ou campo `sensitive_action_token`), sempre
   combinado com `auth`:

```php
Route::post('/saque', ...)->middleware(['auth', 'sensitive.token']);
```

**Canais de verificação plugáveis** (TOTP/WhatsApp futuros): contrato
`App\Core\Auth\Contracts\VerificationChannelDriver` + `VerificationChannelManager`.
Hoje só `EmailVerificationDriver`; novo canal = novo driver no mapa + case no enum
`VerificationChannel`, sem tocar no fluxo.

## Sessão e CSRF

- Cookies de sessão: `HttpOnly` + `SameSite=Lax` sempre; `Secure` por padrão em
  produção (`config/session.php` — `SESSION_SECURE_COOKIE`, default
  `APP_ENV=production`). Sessão regenerada no login, invalidada no logout.
- CSRF nativo do grupo `web` (Laravel 13: `PreventRequestForgery` — token +
  validação de origem `Sec-Fetch-Site`/`Origin`), testado com e sem token.
- Credenciais nunca aparecem em logs: `password`, `transaction_password`, `code`
  e afins são `[REDACTED]` pelo Redactor (testado na pipeline de request log).

## Testes

`tests/Feature/Auth/` (Pest): registro, login ok/errado, bloqueio após N
tentativas + liberação após o decay, conta inativa, sessão regenerada, flags do
cookie, deny-by-default, logout, CSRF (419 sem token), recuperação de senha
(`Notification::fake()`), senha de transação (definir/alterar/erros), fluxo
completo de ação sensível (`Mail::fake()` — código válido/inválido/expirado/
tentativas esgotadas, cooldown de reenvio, token uso único/expirado/de outro
usuário).

## Política de senha (configurável, sem tocar em código)

Um lugar só decide o que uma senha de login precisa ter:
`App\Core\Auth\PasswordPolicy`, lido por registro, reset por e-mail, troca
no perfil e criação/edição de usuário no `/admin`. O kit nasce com o mínimo
(6 caracteres) e as demais exigências prontas para ligar no `.env`:

```dotenv
AUTH_PASSWORD_MIN=6
#AUTH_PASSWORD_LETTERS=true        # pelo menos uma letra
#AUTH_PASSWORD_MIXED_CASE=true     # maiúscula E minúscula
#AUTH_PASSWORD_NUMBERS=true        # pelo menos um número
#AUTH_PASSWORD_SYMBOLS=true        # pelo menos um símbolo
#AUTH_PASSWORD_UNCOMPROMISED=true  # recusa senha vazada (Have I Been Pwned)
```

Descomentou, valeu: a validação passa a cobrar, a dica embaixo do campo
descreve exatamente o que está ativo (`PasswordPolicy::hint()`, nos 3
idiomas) e as mensagens de erro já existem em `lang/*/validation.php`. A
senha de **transação** tem política própria (`AUTH_TRANSACTION_PASSWORD_MIN`).

**NUNCA habilite em produção** — credenciais conhecidas seriam uma backdoor.
Em produção nada disso aparece na tela — e não por causa da flag: a
superfície de demonstração é recusada por ambiente (ver
[Superfície de demonstração: fail-closed em produção](demo.md#superfície-de-demonstração-fail-closed-em-produção)).

**Contas demo são intocáveis** (`User::isDemo()`) — e não só pela UI:
bloquear, editar ou excluir essas contas é recusado no `/admin` com
notification clara, nos eventos do model e, no PostgreSQL, por um trigger
que pega até o `update`/`delete` em massa. Ver
[Contas demo são intocáveis: como e por quê](demo.md#contas-demo-são-intocáveis-como-e-por-quê).
