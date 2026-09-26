# Starter React

O TWS Laravel Starter Kit com o painel do usuário em **React 19 + Inertia 3 +
TypeScript + Tailwind 4 + shadcn/ui**, a partir do
[kit oficial React do Laravel](https://github.com/laravel/react-starter-kit)
— com a autenticação, a segurança e a auditoria dos pacotes `twstec/kit-*`
no lugar das do kit oficial. Composer: `twstec/starter-react` (projeto).

O backend é o mesmo do [starter Livewire](../livewire): os mesmos pacotes, as
mesmas regras, as mesmas mensagens e o mesmo `/admin` (plugin Filament do
`twstec/kit-admin`). Muda a interface do painel e das telas de autenticação.

> **Fase F11a** (esta): base, autenticação e painel mínimo (painel inicial,
> perfil, senha de transação, verificação em duas etapas, notificações).
> **F11b:** seletor de conta, conta e membros, convites, chaves de API,
> projetos e foto de perfil. **F11c:** E2E, imagem de produção e as
> combinações de módulos no CI.

## Rodar (Docker de desenvolvimento, porta 8181)

O `docker-compose.yml` da **raiz** sobe o React ao lado do Livewire (8180),
com o mesmo PostgreSQL, Redis e Mailpit, mas **banco próprio**
(`tws_starter_react`), bancos próprios no Redis e cookie de sessão com nome
próprio. Da raiz do repositório:

```bash
cd starters/react
cp .env.example .env

# Dependências PHP (monta a pasta dos pacotes: path repository ../../packages)
docker run --rm --user $(id -u):$(id -g) -e HOME=/tmp -v $(pwd):/var/www/html \
  -v $(pwd)/../../packages:/var/packages -w /var/www/html composer:2 composer install \
  --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-intl \
  --ignore-platform-req=ext-bcmath --ignore-platform-req=ext-gd

# Front (Node 24 glibc; a pasta dos pacotes entra por causa do tema do /admin)
docker run --rm --user $(id -u):$(id -g) -e HOME=/tmp -v $(pwd):/app \
  -v $(pwd)/../../packages:/packages -w /app node:24-slim npm install --ignore-scripts
docker run --rm --user $(id -u):$(id -g) -e HOME=/tmp -v $(pwd):/app \
  -v $(pwd)/../../packages:/packages -w /app node:24-slim npm run build

# Sobe só os serviços do React (não mexe nos do Livewire)
cd ../..
export UID GID=$(id -g)
docker compose up -d react-nginx react-queue react-scheduler
docker compose exec react-app php artisan key:generate --force
docker compose up -d --force-recreate --no-deps react-app react-queue react-scheduler
docker compose exec react-app php artisan migrate
```

Aplicação: http://localhost:8181 · Mailpit: http://localhost:18025 ·
super admin: http://localhost:8181/admin (promova com
`docker compose exec react-app php artisan user:make-admin email@exemplo.com`).

O serviço `react-db-init` cria `tws_starter_react` e `tws_starter_react_test`
no PostgreSQL compartilhado, se faltarem, e sai — sem tocar no serviço
`postgres` nem no banco do Livewire.

**Servidor de desenvolvimento do Vite** (`npm run dev`, com HMR): em
`APP_ENV=local`, enquanto o `public/hot` existe, a CSP inclui a origem do
servidor do Vite (e o WebSocket do HMR) — e nada além disso, nunca
`'unsafe-eval'`. Ver `App\Support\ViteDevServerCsp`.

## Comandos do dia a dia

```bash
# Testes (Pest) — SQLite em memória / PostgreSQL (banco tws_starter_react_test)
docker compose exec react-app ./vendor/bin/pest
docker compose exec react-app ./vendor/bin/pest -c phpunit.pgsql.xml
docker compose exec react-app ./vendor/bin/pint

# Front: tipos, lint/formatação (Vite+ do kit oficial: oxlint + oxfmt) e build
docker run --rm --user $(id -u):$(id -g) -e HOME=/tmp -v $(pwd):/app -w /app node:24-slim npm run types:check
docker run --rm --user $(id -u):$(id -g) -e HOME=/tmp -v $(pwd):/app -w /app node:24-slim npm run check
docker run --rm --user $(id -u):$(id -g) -e HOME=/tmp -v $(pwd):/app -w /app node:24-slim npx vp fmt
```

(Os `docker run` montam a pasta atual: rode-os dentro de `starters/react`.)

## O que veio do kit oficial e o que foi trocado

| Veio do kit oficial | Trocado |
| --- | --- |
| React 19, Inertia 3 (`@inertiajs/vite`, `Form`, layouts por página), TypeScript estrito, Tailwind 4, componentes shadcn/ui (`resources/js/components/ui`), o layout do painel com menu lateral recolhível e o avatar no pé, as telas de autenticação em cartão, o React Compiler, o Vite+ (`vp`: build, lint com oxlint e formatação com oxfmt) | **Autenticação:** sai o Fortify (e passkeys, 2FA por app, confirmação de senha); entram os controllers do `twstec/kit-auth` com respostas Inertia (abaixo) |
| | **Rotas no front:** sai o Wayfinder (roda `php artisan` no build; aqui o build é só Node). O servidor manda o mapa nome → caminho das rotas usadas (`App\Support\FrontRoutes`) e o front pergunta `route('login')` (`resources/js/lib/routes.ts`) |
| | **Textos:** nada em inglês fixo. O front lê as traduções do Laravel (`lang/{pt_BR,en,es}`), enviadas uma vez por idioma (`App\Support\FrontTranslations`, `resources/js/lib/i18n.ts`) |
| | **Fonte:** a Instrument Sans vem do `@fontsource` (a CSP do kit não permite fonte externa; o oficial usa o Bunny Fonts) |
| | **Tema:** a regra do Livewire — `localStorage` do dispositivo → o tema da CONTA (`users.theme`) → sistema, aplicada antes da primeira pintura |
| | **Usuário nas props:** lista fechada de campos (`App\Support\SharedUser`), nunca o model serializado |
| | **Marca:** a do kit (monocromática), ou o `PLATFORM_LOGO_URL` |

## Autenticação: a do pacote, com respostas Inertia

As **telas** (GET) são do starter (`App\Http\Controllers\Auth\AuthPageController`
→ páginas em `resources/js/pages/auth`); os **envios** (POST) são os
controllers do `twstec/kit-auth`, que trazem o próprio `throttle:sensitive`.
Os endereços e os nomes das rotas são os mesmos do Livewire. O que volta ao
navegador sai das **implementações Inertia dos 11 contratos de resposta** do
pacote (`App\Http\Responses\Inertia`, registradas no `AppServiceProvider`):

- **Quem atravessa a porta** (login, login pelo código do segundo fator,
  cadastro, e-mail confirmado, logout) recebe carga **completa** do destino:
  numa visita do Inertia, 409 com `X-Inertia-Location`; fora dele, o redirect
  de sempre. O estado do front da sessão anterior não sobrevive, e o destino
  pode nem ser uma página Inertia (`/admin`, o link tentado antes do login).
  O destino guardado passa pelo `SafeRedirect` — só volta para dentro da
  aplicação.
- **Quem fica do mesmo lado** (código errado, reenvio, link reenviado,
  recusa de redefinição) recebe um redirect comum, com o erro no campo
  (prop `errors`) ou o aviso na sessão (prop `flash.status`, mostrado como
  toast; `flash.verification_error` fica fixo na tela).

A regra (bloqueio por tentativas, conta ativa, anti-enumeração, sessão
regenerada, segundo fator, e-mails) roda no pacote antes da resposta; as
mensagens são as mesmas do Livewire.

**Ligar/desligar o segundo fator** (perfil) é ação sensível: senha de
transação → código por e-mail → a operação. O token de ação sensível nasce e
morre **no servidor** (`TwoFactorPreferenceController`), como no Livewire; as
rotas JSON `sensitive-actions.*` do pacote seguem disponíveis para clientes
próprios.

## Props compartilhadas

`App\Http\Middleware\HandleInertiaRequests`: `app` (nome, logotipo, idioma,
idiomas), `auth.user` (lista fechada: uuid, código público, nome, e-mail,
idioma, tema, foto, e três estados), `kit.modules` (módulos opcionais
instalados — `Kit::has()`), `navigation` (o menu lateral, montado no servidor
com a arquitetura de informação do Livewire), `routes`, `flash`,
`accountMenu` (o seletor de conta: a conta atual e as contas da pessoa, com
o papel — só com o pacote de contas), `translations` e `sidebarOpen`. Rota com
parâmetro chega em `routes` como modelo (`/api-keys/{key}/rotate`), preenchido
no front (`route('panel.api-keys.rotate', { key })`). **Nenhuma credencial** — um teste varre as
props de todas as telas atrás de senha, hash, token, código e pepper
(`tests/Feature/Inertia/SharedPropsTest.php`).

## Módulos opcionais e demonstração

Como no Livewire: `foundation` e `auth` sempre; `accounts`, `uploads` e
`admin` opcionais (`php artisan tws:install`). O front recebe os módulos
instalados em `kit.modules`, e o menu só mostra tela que existe. O `/admin` é
o mesmo plugin Filament do Livewire (tema em `resources/css/filament.css`,
só no build com o painel instalado). O starter React **não usa a
demonstração do kit** (`twstec/kit-demo`): `/` é a página inicial mínima do
produto.

## Contas, chaves de API, projetos e foto (módulos opcionais)

Com o `twstec/kit-accounts`: o **seletor de conta** em todo o painel, a
**página da conta** (`/account`: dados, membros em tabela ou cartões,
convites, transferir e excluir), **criar conta de empresa**
(`/accounts/create`), **chaves de API** (`/api-keys`), **projetos**
(`/projects`) e a **tela pública do convite** (`/invitations/{token}`). Com o
`twstec/kit-uploads`: a **foto de perfil** no `/profile` (enviar e tirar).

- A regra é do pacote: cada mudança de conta é uma Action (papel, trilha,
  recusas `denied`); chaves pelo `ApiKeyService`, projetos pelo
  `ProjectService`. A tela esconde o que o papel não permite e o servidor
  recusa (403) o que vier por fora.
- Ações sensíveis (criar e rotacionar chave, transferir e excluir a conta):
  `…/code` confere o pedido (`stage=check`) e manda o código (`stage=send`,
  com a senha de transação); o envio da ação traz o código, que vira o token
  **no servidor** (`App\Http\Controllers\Panel\Concerns\ConfirmsSensitiveAction`).
- **A secreta da chave** só existe na resposta imediata da criação/rotação,
  como `flash` do Inertia (fora das props e do histórico do navegador); nunca
  na listagem nem na sessão depois da resposta.
- As respostas do link de convite e da troca de conta são as Inertia do
  aplicativo (`App\Http\Responses\Inertia\Accounts`). O token do convite
  não vai para as props: o front o lê do próprio endereço.
- Componentes reutilizáveis em `resources/js/components` (seletor de conta,
  diálogo de ação sensível, diálogo de confirmação, alternador tabela/cartões,
  ação só com ícone + tooltip, revelação única da secreta, listas de membros e
  convites, foto de perfil).

## Diferenças conhecidas para o Livewire (nesta fase)

- Projetos: além de renomear, o React **arquiva e reativa** (o
  `ProjectService` já aceita o status; o Livewire só renomeia).
- A foto de perfil pode ser **removida** (`AvatarService::remove()`); o
  Livewire só troca.
- A escolha tabela/cartões fica no navegador (no Livewire, na sessão).
- `LoginPrefillProvider` (credenciais sugeridas no login, usado pela demo)
  não é lido: credencial não vai para as props.
- `/mail-preview` é uma página Blade autossuficiente (os e-mails em si são os
  mesmos).
- No Docker de dev, os dois starters em `localhost` dividem o cookie
  `XSRF-TOKEN` (o nome é fixo no Laravel e cookie não separa por porta): com
  as duas abas abertas, o primeiro envio depois de trocar de aba pode
  receber o aviso de sessão vencida (419); o seguinte já passa.
