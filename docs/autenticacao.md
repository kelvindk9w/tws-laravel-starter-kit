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
| Registro | `GET/POST /register` | senha forte via config (`AUTH_PASSWORD_MIN`); sessão regenerada; com a verificação ligada, envia o e-mail e vai à tela de aviso |
| Verificação de e-mail | `GET /email/verify`, `POST /email/verification-notification`, `GET /email/verify/{uuid}/{hash}` | ver [Verificação de e-mail](#verificação-de-e-mail-no-cadastro) |
| Login | `GET/POST /login` | **bloqueio por tentativas** (RateLimiter, e-mail+IP — `AUTH_LOGIN_MAX_ATTEMPTS`/`AUTH_LOGIN_LOCKOUT_MINUTES`); mensagem única anti-enumeração; `session()->regenerate()` (fixation) |
| Logout | `POST /logout` | invalida sessão + renova token CSRF |
| Recuperação | `GET/POST /forgot-password`, `GET/POST /reset-password` | broker nativo do Laravel (token com hash + expiração); resposta uniforme anti-enumeração; `remember_token` renovado no reset |
| Senha de transação | `GET/PUT /settings/transaction-password` | deve ser **diferente** da senha de login; alteração exige a atual |
| Ação sensível | `POST /sensitive-actions/code` + `POST /sensitive-actions/confirm` | ver abaixo |

Todas as rotas sensíveis passam por `throttle:sensitive` (5/min padrão,
`config/security.php`) além dos limites de negócio próprios.

## Verificação de e-mail no cadastro

**Ligada por padrão.** Quem se cadastra recebe um e-mail com um link e só
opera o painel depois de clicar nele. Até lá, a conta vê apenas a tela de
aviso (`/email/verify`, no layout do site), que diz para onde o e-mail foi e
oferece **reenviar** e **sair**.

O que fica fechado para conta sem e-mail confirmado:

- **Painel** (middleware `verified` → `App\Core\Auth\Http\Middleware\EnsureEmailIsVerified`):
  dashboard, chaves de API, projetos, perfil, notificações, senha de
  transação, ação sensível e avatar. Página vai ao aviso; chamada que espera
  JSON recebe 403 com a mensagem traduzida.
- **Ações Livewire** dessas páginas: o middleware é registrado como
  *persistente* do Livewire (`AppServiceProvider`), então uma ação disparada
  de uma aba que ficou aberta passa pela mesma barreira que a página.
- **API v1**: chave de conta sem e-mail confirmado recebe o mesmo 401 mudo de
  conta inativa (`ResolveTenant`). Chave só nasce pelo painel, que já exige a
  confirmação — então uma conta nova **não consegue criar chave** antes de
  confirmar. A checagem na API cobre a conta que já tinha chave quando a
  exigência foi ligada.

O que continua livre: login, logout, recuperação de senha, troca de idioma e
de tema, e o `/admin` (o painel do Filament não usa verificação de e-mail:
admin é criado por outro admin ou pelo `user:make-admin`, e nos dois casos a
conta já nasce/fica confirmada).

**O link** (`App\Core\Auth\Support\EmailVerification`, que concentra a regra):

- assinado e com expiração (`AUTH_EMAIL_VERIFICATION_LINK_TTL_MINUTES`,
  padrão 60), identifica a conta pelo `uuid` e carrega o hash do e-mail —
  trocar o e-mail da conta invalida o link antigo;
- **ancorado em `APP_URL`, nunca no `Host` da requisição**: a assinatura é
  calculada sobre o caminho (relativa) e a origem vem da configuração. Mesmo
  que o e-mail seja montado dentro de uma requisição (fila síncrona) ou que o
  TrustHosts um dia seja afrouxado, o link aponta para a aplicação;
- precisa ser aberto com a própria conta logada. Sem sessão, o login devolve
  ao link; link de outra conta, adulterado ou vencido volta ao aviso com a
  explicação e o botão de reenviar;
- depois de confirmar, a pessoa volta para a página que tentou abrir —
  **pelo `SafeRedirect`** (destino fora da aplicação cai no painel).

**O e-mail** é a notificação `VerifyEmailNotification`: layout único do kit,
strings em `mail.email_verification.*` nos três idiomas, no idioma do
**destinatário** (o cadastro grava o idioma em que a pessoa estava navegando),
enfileirado com payload criptografado como todos os e-mails do kit. Aparece em
`/mail-preview`.

**Reenvio**: `throttle:sensitive` na rota e intervalo mínimo por conta
(`AUTH_EMAIL_VERIFICATION_RESEND_COOLDOWN_SECONDS`, padrão 60 — o envio do
cadastro também conta).

**Desligar** (projeto que não quer a etapa):

```dotenv
AUTH_EMAIL_VERIFICATION_REQUIRED=false
```

Com a flag desligada o cadastro vai direto ao painel, nenhum e-mail de
verificação é enviado e a API não olha a verificação. As contas criadas nesse
período ficam **sem** e-mail confirmado: se a exigência for ligada depois,
elas passam pela tela de aviso no próximo acesso (e as chaves delas param até
confirmar) — marque-as como confirmadas antes, se não for isso que você quer.

**Contas que já existiam** quando esta regra entrou: a migration
`2026_09_24_000001_mark_existing_users_email_as_verified` marca todas como
confirmadas, para o deploy da atualização não trancar ninguém. Numa
instalação nova ela não faz nada.

**Contas demo e semeadas**: os seeders criam tudo já confirmado (contas demo e
a massa do `UserSeeder`). Além disso, enquanto o modo demo está ligado, a
conta demo protegida **conta como confirmada** mesmo com a coluna zerada
(`User::hasVerifiedEmail()`): o e-mail dela é fictício e `email_verified_at`
é um campo que a blindagem deixa livre — sem isso, alguém zeraria a coluna e o
próximo visitante ficaria preso no aviso. Confirmar o e-mail da demo continua
permitido. Com o modo demo desligado, ela é uma conta comum.

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

`tests/Feature/Auth/` (Pest): registro, verificação de e-mail
(`EmailVerificationTest`: painel, formulários, ação Livewire e API fechados;
link assinado/vencido/adulterado/de outra conta; origem do link em APP_URL;
reenvio com intervalo; flag desligada; demo, admin e contas existentes),
login ok/errado, bloqueio após N
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
