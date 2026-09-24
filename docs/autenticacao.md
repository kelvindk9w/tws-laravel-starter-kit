# Autenticação

Implementação própria e enxuta — **sem** Breeze/Jetstream/Fortify. A regra mora no
pacote **`twstec/kit-auth`** ([`packages/auth`](../packages/auth), sem telas); as
telas, as rotas e o model de usuário são do starter (ver
[O pacote e os pontos de extensão](#o-pacote-twsteckit-auth-e-os-pontos-de-extensão)).
Autenticação web por **sessão** (os painéis usam sessão/cookie; a API pública usa
o par de chaves pk_/sk_ no header — ver [API e chaves de API](api.md) e [Tenancy](tenancy.md)).

## Model User (`app/Models/User.php`)

O model é do **aplicativo**, e compõe o que cada pacote traz: a trait
`KitAuthenticatable` e o contrato `AuthUser` do pacote de autenticação (status
da conta, senha de transação, segundo fator, contas protegidas, verificação de
e-mail e recuperação com os e-mails do kit, idioma preferido), a trait de avatar
de uploads e o acesso ao `/admin` (Filament). O pacote nunca nomeia a classe:
trabalha com o model configurado em `auth.providers.users.model`. Até a 1.x ele
era `App\Core\Auth\Models\User`; o nome antigo continua resolvendo até a 3.0
(payload de fila antigo o carrega — ver `app/Support/legacy-aliases.php`).

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

## Fluxos web (rotas em `routes/web.php`, Form Requests no pacote)

| Fluxo | Rotas | Observações |
|---|---|---|
| Registro | `GET/POST /register` | senha forte via config (`AUTH_PASSWORD_MIN`); sessão regenerada; com a verificação ligada, envia o e-mail e vai à tela de aviso |
| Verificação de e-mail | `GET /email/verify`, `POST /email/verification-notification`, `GET /email/verify/{uuid}/{hash}` | ver [Verificação de e-mail](#verificação-de-e-mail-no-cadastro) |
| Login | `GET/POST /login` | **bloqueio por tentativas** (RateLimiter, e-mail+IP — `AUTH_LOGIN_MAX_ATTEMPTS`/`AUTH_LOGIN_LOCKOUT_MINUTES`); mensagem única anti-enumeração; `session()->regenerate()` (fixation) |
| Segundo fator do login | `GET/POST /two-factor-challenge`, `POST /two-factor-challenge/resend`, `POST /two-factor-challenge/cancel` | só para quem ligou; ver [Verificação em duas etapas](#verificação-em-duas-etapas-no-login-opcional) |
| Logout | `POST /logout` | invalida sessão + renova token CSRF |
| Recuperação | `GET/POST /forgot-password`, `GET/POST /reset-password` | broker nativo do Laravel (token com hash + expiração); resposta uniforme anti-enumeração; `remember_token` renovado no reset |
| Senha de transação | `GET/PUT /settings/transaction-password` | deve ser **diferente** da senha de login; alteração exige a atual |
| Ação sensível | `POST /sensitive-actions/code` + `POST /sensitive-actions/confirm` | ver abaixo |

Todos os envios sensíveis passam por `throttle:sensitive` (5/min padrão,
`config/security.php`) além dos limites de negócio próprios. O limite vem com o
**controller do pacote** (`HasMiddleware`), não com a declaração da rota: uma
rota que aponte para ele já nasce limitada.

## Regra em Actions, resposta em contratos (trocar o front sem tocar na regra)

Os controllers só fazem HTTP. Os que **recebem os formulários** são do pacote
(`Twstec\Kit\Auth\Http\Controllers`); as **telas** (GET) são do starter
(`App\Http\Controllers\Auth\AuthPageController` + views em
`resources/views/auth`). A regra de cada fluxo mora numa **Action**
(`Twstec\Kit\Auth\Actions`), chamável de qualquer front, e o que volta ao
navegador sai de um **contrato de resposta** (`Twstec\Kit\Auth\Contracts\Responses`)
com implementação padrão registrada no container pelo
`Twstec\Kit\Auth\Providers\AuthServiceProvider`.

| Fluxo | Action | Contratos de resposta |
|---|---|---|
| Login por senha | `AttemptLogin` (bloqueio por tentativas, conta ativa, anti-enumeração, sessão regenerada, início do segundo fator) | `LoginResponse`, `TwoFactorRequiredResponse` |
| Segundo fator | `CompleteTwoFactorLogin` (código, reenvio, desistência; conta conferida de novo; sessão nova) | `TwoFactorLoginResponse`, `TwoFactorChallengeResponse` |
| Logout | `Logout` (invalida a sessão, renova o CSRF) | `LogoutResponse` |
| Cadastro | `RegisterUser` (conta, idioma, sessão regenerada, e-mail de verificação) | `RegisterResponse` |
| Link de redefinição | `SendPasswordResetLink` | `PasswordResetLinkSentResponse` |
| Redefinição | `ResetPassword` (senha nova, `remember_token` renovado, evento `PasswordReset`) | `PasswordResetResponse`, `FailedPasswordResetResponse` |
| Verificação de e-mail | `VerifyEmail`, `ResendEmailVerification` | `VerifyEmailResponse`, `EmailVerificationResponse` |

As Actions devolvem um **resultado** (`LoginOutcome`, `TwoFactorChallengeResult`,
`EmailVerificationResult`, o status do broker) e recusam com
`ValidationException` quando a recusa é de formulário (senha errada, conta
inativa, bloqueio) — o Laravel já a devolve como redirect com erro no Blade e
como 422 em JSON. O controller escolhe o contrato pelo resultado; o contrato
decide só a resposta. As respostas padrão (`Twstec\Kit\Auth\Http\Responses`)
reproduzem exatamente o redirect, a mensagem e o destino das telas Blade.

**Para trocar uma resposta** (outro front, uma API JSON, Inertia), registre a
sua implementação do contrato num provider do app:

```php
$this->app->bind(
    \Twstec\Kit\Auth\Contracts\Responses\LoginResponse::class,
    \App\Http\Responses\MyLoginResponse::class,
);
```

O padrão é registrado com `bindIf`, então o registro do app prevalece em
qualquer ordem de providers. O que **não** muda ao trocar a resposta: o
limite de tentativas, a regeneração e a invalidação da sessão, os eventos
(`Login`, `Logout`, `PasswordReset`, `Verified`) e os
logs — eles acontecem na Action, antes da resposta. O `throttle:sensitive`
(no controller do pacote) e o `SafeRedirect` do pós-login (na resposta padrão)
continuam onde estão: uma resposta própria que leve a um destino vindo do
cliente precisa passar por `SafeRedirect::url()` também.

Senha de transação e ação sensível já tinham a regra em serviços
(`TransactionPasswordService`, `SensitiveActionService`, compartilhados com o
painel Livewire) e seguem assim. As telas `GET` (formulários) continuam como
views do starter.

Um teste de arquitetura (`tests/Unit/Architecture/BusinessLogicPlacementTest.php`)
reprova o build se um desses controllers voltar a autenticar, mexer no
broker de senha, no limiter, no banco, na sessão ou no estado intermediário do
segundo fator.

## Verificação de e-mail no cadastro

**Ligada por padrão.** Quem se cadastra recebe um e-mail com um link e só
opera o painel depois de clicar nele. Até lá, a conta vê apenas a tela de
aviso (`/email/verify`, no layout do site), que diz para onde o e-mail foi e
oferece **reenviar** e **sair**.

O que fica fechado para conta sem e-mail confirmado:

- **Painel** (middleware `verified` → `Twstec\Kit\Auth\Http\Middleware\EnsureEmailIsVerified`):
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

**O link** (`Twstec\Kit\Auth\Support\EmailVerification`, que concentra a regra):

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

**Quando o e-mail não chega** (filtro de spam, caixa corporativa que descarta):
o suporte confirma a identidade por outro canal e usa, no `/admin` → Usuários,
a ação **Marcar e-mail como verificado** (na listagem e no detalhe; só aparece
para conta ainda não verificada, nunca para conta demo, pede confirmação e fica
registrada na trilha como ação de admin — ver
[Admin e dashboards](admin-e-dashboards.md)). A listagem tem o filtro
**E-mail verificado: sim/não** para achar essas contas.

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

## Verificação em duas etapas no login (opcional)

**Cada conta decide.** No `/profile` (e no `/admin/profile`, para o admin)
há o cartão **Verificação em duas etapas**, com o estado (ligada/desligada) e
um botão. Com ela ligada, o login passa a ter dois passos: senha certa →
código de 6 dígitos por e-mail → sessão.

**Ligar e desligar são ações sensíveis.** O botão abre a mesma confirmação
das chaves de API: senha de transação → código por e-mail → token de ação
sensível de uso único. O token é conferido pelo próprio
`Twstec\Kit\Auth\Services\TwoFactorLogin` (não só pela tela), então nenhum
caminho troca a preferência sem ele. Conta **sem senha de transação** vê o
motivo no cartão ("defina sua senha de transação antes") e o botão
desabilitado — a senha de transação fica no mesmo perfil, logo acima.

**O login com o segundo fator** (`AuthenticatedSessionController` →
`TwoFactorChallengeController`):

1. Senha certa numa conta ativa com o segundo fator ligado **não autentica**.
   A sessão ganha só o **estado intermediário**
   (`Twstec\Kit\Auth\Support\PendingTwoFactorLogin`: qual conta, se "manter
   conectado" foi marcado, validade e uma impressão digital da senha), com ID
   de sessão novo. O guard continua vazio: painel, formulários e ações
   Livewire tratam a sessão como visitante.
2. O código sai por e-mail — layout do kit, idioma do **destinatário**, fila
   com payload criptografado, texto próprio do login ("Seu código de acesso";
   o aviso final diz que receber o e-mail sem ter tentado entrar significa
   que alguém tem a senha). A pessoa vai à tela do código
   (`/two-factor-challenge`, layout do site, 3 idiomas), que oferece
   **enviar outro código** (intervalo mínimo) e **voltar ao login**
   (cancela: o código enviado deixa de valer).
3. Código certo → a conta é conferida de novo (ativa?), `Auth::login` com o
   "manter conectado" guardado no passo 1 — **o cookie de longa duração só
   nasce depois do segundo fator** —, ID de sessão novo e o destino original
   pelo `SafeRedirect`.

O estado intermediário **deixa de valer** quando passa da validade
(`AUTH_TWO_FACTOR_CHALLENGE_TTL_MINUTES`, padrão 10), quando a senha da conta
muda no meio do caminho (redefinição por e-mail, troca em outra sessão) ou
quando a conta some. A pessoa volta ao login com a explicação.

**O código** é o do motor comum `Twstec\Kit\Auth\Services\VerificationCodes`
(o mesmo da ação sensível), com finalidade própria
(`VerificationPurpose::LoginChallenge` — um código de ação sensível não
conclui login, e vice-versa):

- só o hash no banco (Argon2id), comparação com `Hash::check`;
- validade `AUTH_VERIFICATION_CODE_TTL_MINUTES`; código novo invalida o
  anterior; reenvio com `AUTH_VERIFICATION_CODE_RESEND_COOLDOWN_SECONDS`;
- **uso único** com consumo condicional no banco (`consumed_at IS NULL`):
  duas requisições com o mesmo código certo, só uma vence;
- **tentativas limitadas em três camadas**: por código
  (`AUTH_VERIFICATION_CODE_MAX_ATTEMPTS` — a tentativa é reservada no banco
  antes da conferência, então nem requisições simultâneas passam do limite),
  por **conta** (`AUTH_TWO_FACTOR_MAX_ATTEMPTS_PER_ACCOUNT`, padrão 10 — sem
  ele bastaria pedir código novo e seguir tentando) e por **IP**
  (`AUTH_TWO_FACTOR_MAX_ATTEMPTS_PER_IP`, padrão 30), as duas últimas numa
  janela de `AUTH_TWO_FACTOR_LOCKOUT_MINUTES` (padrão 15). Ao atingir o
  limite, o estado intermediário acaba, o código morre e a senha certa não
  reabre o desafio até a janela passar. As rotas ainda passam pelo
  `throttle:sensitive`.

**Sem enumeração nova.** Senha errada numa conta com o segundo fator recebe
exatamente a resposta de sempre (`auth.failed`, mesmo destino, nenhum e-mail,
nenhum estado na sessão) — igual a e-mail inexistente. A tela do código só
aparece para quem acertou a senha, o mesmo que o login de um passo já
revelava ao autenticar. Conta bloqueada/pendente continua com a recusa
específica do login atual, antes de qualquer código; conta sem e-mail
confirmado conclui o código e cai no aviso de verificação, como antes.

**O que NÃO desliga o segundo fator:** trocar a senha no perfil e redefinir
por e-mail. Ele existe justamente para o dia em que a senha vazou — se
trocar a senha o desligasse, quem descobriu a senha também desligaria.

**Recuperação.** O canal é o e-mail da conta, então a recuperação é o próprio
e-mail: quem perdeu a senha redefine pelo e-mail e, no login, recebe o código
no mesmo e-mail. **Quem perdeu o acesso ao e-mail não conclui o login** — é o
preço de o segundo fator valer alguma coisa; o cartão do perfil avisa. O
suporte de uma instalação pode desligar a preferência de uma conta
verificando a identidade por fora (ex.:
`User::whereKey($id)->update(['two_factor_enabled_at' => null])`). App
autenticador (TOTP) e códigos de recuperação ficam para depois da 1.0 (ver
README, "Pendências conhecidas").

**`/admin` (Filament).** O login do `/admin` usa o mecanismo de MFA do
Filament 5 (`->multiFactorAuthentication()` no `AdminPanelProvider`): a
página troca o formulário da senha pelo do código e, depois do código,
confere as credenciais de novo e cria a sessão com "lembrar de mim" e ID
novo. O **provedor** é do kit (`App\Filament\Auth\EmailCodeAuthentication`),
não o `EmailAuthentication` nativo, porque o nativo guarda o código na sessão
sem limite de tentativas por código, manda uma notificação fora do layout,
do idioma e da fila criptografada do kit e tem preferência e ações de
ligar/desligar próprias, sem senha de transação. O provedor do kit usa o
mesmo `TwoFactorLogin`: mesmo código, mesmo e-mail, mesmos limites. A página
de login do `/admin` acrescenta a **validade** do estado intermediário e o
botão **voltar**; o perfil do `/admin` liga/desliga com a mesma confirmação
sensível (dois modais: senha de transação → código).

**Uma preferência só (`users.two_factor_enabled_at`) para os dois
painéis — de propósito.** O `/login` e o `/admin/login` autenticam o mesmo
guard de sessão (`web`): quem entra por um já está dentro do outro. Com
preferências separadas, ligar o segundo fator só no `/admin` deixaria o
`/login` como porta dos fundos para a mesma sessão.

**Contas demo.** Com o modo demo ligado, a coluna está entre os campos
blindados (`DemoAccountGuard::SENSITIVE_ATTRIBUTES` — tela, model e gatilho
do PostgreSQL): ligar o segundo fator numa conta de senha pública mandaria o
código para uma caixa que ninguém lê e trancaria a demo para todos. O cartão
explica e o botão fica desabilitado. Com o modo demo desligado, é uma conta
comum.

**Configuração** (`config/auth.php` → `two_factor`):

```dotenv
AUTH_TWO_FACTOR_ENABLED=true                 # false = opção escondida e login só com senha para todos
AUTH_TWO_FACTOR_CHALLENGE_TTL_MINUTES=10     # validade do estado intermediário
AUTH_TWO_FACTOR_MAX_ATTEMPTS_PER_ACCOUNT=10  # códigos errados por conta na janela
AUTH_TWO_FACTOR_MAX_ATTEMPTS_PER_IP=30       # códigos errados por IP na janela
AUTH_TWO_FACTOR_LOCKOUT_MINUTES=15           # janela/bloqueio
```

Desligar `AUTH_TWO_FACTOR_ENABLED` esconde a opção dos perfis e faz o login
ignorar a preferência de quem já tinha ligado (ela fica gravada e volta a
valer se a flag for religada). Sem `.env` (instalação nova, CI) os padrões
acima valem — nada disso roda no boot.

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

O código (geração, hash, validade, tentativas reservadas no banco, uso único
com consumo condicional) é do motor comum `VerificationCodes`, o mesmo do
segundo fator do login; o token de ação sensível também é consumido com
`UPDATE` condicional.

**Canais de verificação plugáveis** (TOTP/WhatsApp futuros): contrato
`Twstec\Kit\Auth\Contracts\VerificationChannelDriver` + `VerificationChannelManager`.
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

## O pacote `twstec/kit-auth` e os pontos de extensão

A autenticação é um pacote Laravel **sem telas** ([`packages/auth`](../packages/auth),
namespace `Twstec\Kit\Auth`), que depende só do `twstec/kit-foundation`. O
starter Livewire é um dos fronts que o usam; um starter React usa o mesmo
pacote, com as próprias telas e respostas.

**O que o pacote liga sozinho** (nenhuma proteção depende de o front lembrar):

- o status da conta a cada requisição do grupo `web` (`EnsureAccountIsActive`,
  anexado ao fim do grupo — no starter, logo depois do `SetLocale`);
- os aliases `verified` (a regra do kit, que substitui o do framework) e
  `sensitive.token` (um alias de mesmo nome declarado pelo aplicativo
  prevalece);
- o `throttle:sensitive` de cada envio, declarado nos próprios controllers;
- os limites que moram nas regras: bloqueio de login por e-mail+IP
  (`AttemptLogin`), limites do código do segundo fator por código, conta e IP
  (`TwoFactorLogin`), intervalos de reenvio;
- as migrations (com os nomes de sempre), a configuração padrão das chaves do
  kit em `config('auth')` e as traduções do domínio.

Opt-out só explícito: `AUTH_WEB_PROTECTIONS=false` desliga o middleware de
status e os dois aliases, e o pacote grava um aviso no log a cada boot.

**O que um front novo faz para ligar o pacote:**

1. **Model de usuário:** o seu `App\Models\User` estende o `User` do framework,
   implementa `Twstec\Kit\Auth\Contracts\AuthUser` (e `HasLocalePreference`)
   e usa a trait `Twstec\Kit\Auth\Models\Concerns\KitAuthenticatable` (ou as
   partes dela: `HasAccountStatus`, `HasTransactionPassword`, `HasTwoFactor`,
   `ProtectsAccounts`, `VerifiesEmail`, `SendsPasswordResetNotification`,
   `HasPreferredLocale`). Configure-o em `auth.providers.users.model`. A tabela
   `users` é a do esqueleto Laravel; as colunas do kit vêm das migrations do
   pacote.
2. **Rotas de envio:** aponte os POSTs para os controllers do pacote
   (`AuthenticatedSessionController@store/destroy`, `RegisteredUserController@store`,
   `TwoFactorChallengeController@store/resend/destroy`,
   `PasswordResetLinkController@store`, `NewPasswordController@store`,
   `EmailVerificationController@resend/verify`,
   `TransactionPasswordController@update`, `SensitiveActionController@store/confirm`).
   Os endereços são seus; o limite vem junto.
3. **Telas:** são suas (no starter Livewire, `AuthPageController` + views Blade;
   num starter React, as páginas dele). A tela do código do segundo fator pergunta
   `CompleteTwoFactorLogin::pendingUser()` e, sem estado, responde com
   `TwoFactorChallengeController::respond()`.
4. **Respostas:** as padrão redirecionam para as rotas nomeadas `login`,
   `dashboard`, `two-factor.challenge` e `verification.notice` (e os e-mails
   usam `password.reset` e `verification.verify`). Para JSON ou Inertia,
   registre a sua implementação de cada contrato de
   `Twstec\Kit\Auth\Contracts\Responses` num provider do app — o padrão é
   `bindIf` e cede.
5. **E-mails:** as classes (código, recuperação de senha, verificação) e os
   assuntos são do pacote; os corpos são views do front
   (`mail.messages.verification-code`, `mail.messages.password-reset`,
   `mail.messages.email-verification`), no layout `<x-email::…>` do foundation.
6. **Front com Livewire:** registre o `EnsureEmailIsVerified` como middleware
   persistente do Livewire (`Livewire::addPersistentMiddleware`, no starter em
   `AppServiceProvider`), para que as ações dos componentes também exijam o
   e-mail confirmado. O pacote não conhece o Livewire, então esse passo é do
   front.
7. **Extensões opcionais:** `AccountProtection` (contas protegidas contra
   alteração) e `LoginPrefillProvider` (credenciais sugeridas no login) — sem
   implementação registrada, nada é protegido nem preenchido.

**Textos:** as mensagens do domínio (recusas, avisos, política de senha,
assuntos dos e-mails) vêm do pacote, e o `lang/` do aplicativo vence na mesma
chave — para trocar `auth.failed`, basta pô-la no `lang/pt_BR/auth.php` do
aplicativo. Os textos das telas são do front.

**Nomes antigos:** `App\Core\Auth\…` continua resolvendo para
`Twstec\Kit\Auth\…` até a 3.0 (payload de fila e extensões antigas); o model e o
comando `user:make-admin`, que ficaram no starter, têm o apelido deles no
starter.

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
usuário), verificação em duas etapas (`TwoFactorLoginTest`: estado
intermediário não autenticado, uso único, finalidades separadas, limites por
código/conta/IP, validade, senha trocada e conta bloqueada no meio,
anti-enumeração, "manter conectado" só depois do código, troca/redefinição
de senha não desligam; `ProfileTwoFactorTest`: ligar/desligar com a
confirmação sensível, token exigido pelo service, demo; `AdminTwoFactorTest`:
login do Filament com o provedor do kit, voltar, validade, limites e o perfil
do `/admin`; `VerificationCodesTest`: o motor).

## Política de senha (configurável, sem tocar em código)

Um lugar só decide o que uma senha de login precisa ter:
`Twstec\Kit\Auth\PasswordPolicy`, lido por registro, reset por e-mail, troca
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
