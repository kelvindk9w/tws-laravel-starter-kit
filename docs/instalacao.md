# Instalação: escolher o que instalar

O kit é um conjunto de pacotes e um starter (o aplicativo pronto). Você
escolhe o que entra:

- **Frontend (escolha única, ao criar o projeto):** o starter **Livewire**
  (`starters/livewire`, pacote `twstec/starter-livewire`) ou o starter
  **React** (`starters/react`, pacote `twstec/starter-react` — React +
  Inertia + TypeScript + shadcn/ui, a partir do kit oficial do Laravel — ver
  [Starter React](#starter-react)). Os dois
  usam os mesmos pacotes, com as mesmas regras e mensagens, e o mesmo `/admin`.
- **Backend:** `foundation` e `auth` vêm **sempre**. `accounts`, `uploads` e
  `admin` são **opcionais**, marcados um a um.
- **Demonstração:** o pacote `twstec/kit-demo` (landings, vitrine `/ui`,
  contas demo, massa fictícia) **vive só no monorepo** — não é publicado no
  Packagist. Quem clona o monorepo a tem (e a tira com um comando); quem cria
  um projeto (`create-project` / `laravel new --using=`) recebe o starter
  **limpo**, sem ela.

| Módulo | Pacote | O que traz | Precisa de |
| --- | --- | --- | --- |
| Base | `twstec/kit-foundation` | Segurança (filtro de ataques, limites, cabeçalhos, hosts e proxies), trilhas de requisição e de auditoria com LGPD, idioma, e-mail, configurações editáveis, guardas de produção | — (sempre) |
| Autenticação | `twstec/kit-auth` | Login, cadastro, verificação de e-mail, segundo fator, senha de transação, ação sensível | foundation (sempre) |
| Contas e API | `twstec/kit-accounts` | Contas com membros e convites, projetos, chaves de API e a API v1 | foundation, auth |
| Uploads | `twstec/kit-uploads` | Upload validado pelo conteúdo, entrega por URL assinada, foto de perfil, `POST /api/v1/uploads` | foundation, auth, **accounts** (o upload pertence a uma conta) |
| Painel `/admin` | `twstec/kit-admin` | O super admin (plugin do Filament): usuários, logs, trilha de auditoria, configurações, dashboards — e as telas de contas, chaves, projetos e uploads **quando esses módulos estão instalados** | foundation, auth (accounts e uploads são sugeridos, não exigidos) |
| Demonstração | `twstec/kit-demo` | Landings, vitrine, contas demo, seeders de dado fictício — **só no monorepo, não publicado** | **todos** os módulos acima (require-dev do monorepo) |

## Criar um projeto só com o Docker (sem PHP na máquina)

Quem tem só o **Docker Desktop** baixa o espelho
[`kelvindk9w/twstec-kit`](https://github.com/kelvindk9w/twstec-kit) (GitHub →
**Code → Download ZIP**, ou `git clone`), entra na pasta e roda:

```bash
docker compose run --rm instalar   # o menu (o mesmo do comando único, abaixo)
docker compose up -d               # sobe o projeto
```

O `compose.yaml` do `twstec/kit` tem um só serviço, `instalar`: uma imagem
(`twstec-kit-instalar`, a mesma para todos os projetos da máquina) com PHP
8.4 e as extensões dos starters, o Composer, o Node 24 (Debian: os binários
nativos do front do React são de glibc) e a linha de comando do Docker. Ele
monta a pasta em `/app` e roda o **mesmo** `post-create-project-cmd` do
comando único — o menu, o starter, o `composer update`, o instalador do
starter — e depois `npm ci` e `npm run build`. O projeto é montado **na
própria pasta**, que deixa de ser o `twstec/kit`.

- **Dono dos arquivos:** tudo roda com o uid/gid de quem é dono da pasta na
  máquina (`TWS_KIT_UID`/`TWS_KIT_GID` vencem) — nada fica com dono root no
  Linux e no WSL. Numa pasta do Windows montada no Docker (que aparece como
  root no container), vale 1000; o Windows não usa esse dono.
- **Windows:** o roteiro do container é `sh`; o build da imagem tira os finais
  de linha CRLF dele, e o `.gitattributes` do `twstec/kit` força LF num clone
  com `autocrlf`. O resto é PHP e YAML. Numa pasta do Windows, o instalador
  liga `DEV_VITE_POLLING=true` (o Vite passa a conferir os arquivos de tempos
  em tempos, porque a mudança não chega sozinha ao container).
- **Socket do Docker:** montado **só** no container do instalador e só
  durante a instalação. É por ele que o menu vê os projetos e as portas em uso
  (abaixo). A rede do container é a padrão do Docker e não há volume: nada
  fica para trás além da imagem `twstec-kit-instalar` (um volume de cache
  compartilhado entre as pastas faria o Compose avisar, a cada projeto novo,
  que o volume "é de outro projeto").
- **Sem terminal:** `docker compose run --rm -T instalar`, com as variáveis
  `TWS_KIT_*` (tabela abaixo) no ambiente — o `compose.yaml` as repassa.

O CI prova esse caminho (abaixo, "Como o CI garante").

## Criar um projeto: o comando único

```bash
composer create-project "twstec/kit:^2.0@beta" meu-projeto   # durante o beta; na 2.0.0 estável: twstec/kit
```

O pacote `twstec/kit` ([starters/kit](../starters/kit/README.md)) é o ponto
de entrada. Num terminal, um menu (Laravel Prompts) pergunta — toda pergunta
já vem com a resposta sugerida, e Enter aceita:

1. **o nome do projeto** — o da pasta (a do ZIP do `twstec/kit` vira
   `meu-projeto`), com `-2`, `-3`… se já existir projeto Docker com ele; o que
   a pessoa digita vira nome válido ("Loja da Maria" → `loja-da-maria`), e o
   nome de um projeto Docker que já existe é recusado com outra sugestão (ver
   [o Docker de desenvolvimento](#o-docker-de-desenvolvimento-do-projeto-criado));
2. **o número do projeto** — as portas; o sugerido é o primeiro com as quatro
   livres, e o menu mostra antes quem usa os outros ("0 já é usado por
   loja-da-maria"); um número com porta ocupada é recusado dizendo quem ocupa
   e o próximo livre;
3. **a interface** — Livewire ou React;
4. **os módulos opcionais** — contas com membros e API, uploads e foto, painel
   `/admin` (todos marcados de início). `foundation` e `auth` vêm sempre.
   Uploads exige contas: com Uploads marcado e Contas não, o Enter mostra o
   motivo e a pergunta continua aberta;
5. **a confirmação** do plano.

Depois, sem perguntar mais nada:

1. o starter escolhido é baixado pelo mesmo Composer, com a mesma restrição
   de versão do `twstec/kit` e os mesmos repositórios, e conferido;
2. os arquivos do `twstec/kit` dão lugar aos do starter — ele não fica no
   projeto;
3. o `composer.json` do starter perde os módulos **não marcados** — eles nem
   chegam a ser instalados;
4. rodam os passos do `create-project` do próprio starter:
   `post-root-package-install` (o `.env`), `composer update` e
   `post-create-project-cmd`, que chama o instalador do starter
   (`php artisan tws:install --graceful`) com a escolha no ambiente — ele não
   pergunta de novo, gera a `APP_KEY` e, com contas, o pepper dedicado das
   chaves de API, e grava no `.env` o Docker de desenvolvimento do projeto (o
   nome e o número vão em `TWS_KIT_NAME`/`TWS_KIT_SLOT`);
5. o resumo: o endereço do site e dos e-mails e o `docker compose up -d`, que
   cria o banco e roda as migrations. (Num starter sem o `compose.yaml` de
   desenvolvimento, o banco do `.env` é conferido como antes: se não
   respondeu, a mensagem diz o que ajustar — `DB_*`, ou SQLite — e o comando,
   `php artisan migrate`.)

**Sem terminal** (CI, scripts, `composer create-project -n`), a escolha vai
por variáveis de ambiente — o Composer não repassa opções próprias ao script
do `create-project`, e variável de ambiente funciona igual no Linux, no macOS
e no Windows:

| Variável | Valores | Padrão |
| --- | --- | --- |
| `TWS_KIT_NAME` | nome do projeto: letras minúsculas, números e hífen, começando por letra (2 a 40) | o da pasta, sem colidir com projeto Docker que já existe |
| `TWS_KIT_SLOT` | número do projeto, `0` a `99` | o primeiro com as quatro portas livres |
| `TWS_KIT_EXPOSE_DB` | `1` publica o banco em `127.0.0.1:804N` | `0` |
| `TWS_KIT_STACK` | `livewire` ou `react` | `livewire` |
| `TWS_KIT_WITHOUT` | opcionais que ficam de fora, separados por vírgula | nenhum |
| `TWS_KIT_WITH` | opcionais que entram (o padrão já é todos) | todos |
| `TWS_KIT_LOCALE` | idioma do menu: `pt_BR`, `en` ou `es` | o do sistema, se for um dos três; senão `pt_BR` |

```bash
# Linux / macOS: React sem uploads
TWS_KIT_STACK=react TWS_KIT_WITHOUT=uploads composer create-project "twstec/kit:^2.0@beta" meu-projeto

# Só a base (foundation + auth), Livewire
TWS_KIT_WITHOUT=accounts,uploads,admin composer create-project "twstec/kit:^2.0@beta" meu-projeto
```

```powershell
# Windows (PowerShell)
$env:TWS_KIT_STACK = "react"; $env:TWS_KIT_WITHOUT = "uploads"
composer create-project "twstec/kit:^2.0@beta" meu-projeto
```

Qualquer uma dessas variáveis (mesmo vazia) desliga o menu; sem nenhuma e sem
terminal, vale o padrão seguro: o nome da pasta, o primeiro número livre,
Livewire com todos os módulos. Escolha inválida (interface ou módulo
desconhecido, `foundation`/`auth` de fora, uploads sem contas, o mesmo módulo
nas duas listas, nome fora do padrão ou de um projeto Docker que já existe,
número fora de 0–99 ou com alguma porta ocupada) é recusada **antes** de
baixar qualquer coisa, com o motivo e a sugestão.

**Windows:** nada depende de bash (é PHP puro). Sem WSL, o menu sai em listas
numeradas (o Question Helper do Symfony Console), com as mesmas perguntas e a
mesma validação. As extensões: ver [Windows e extensões do PHP](#windows-e-extensões-do-php).

### Windows e extensões do PHP

- **O que a pessoa pede vale por dentro.** `--ignore-platform-req=…` e
  `--ignore-platform-reqs` do `composer create-project` não chegam sozinhos
  aos comandos do Composer que o instalador roda (o `composer update` do
  starter, e depois os do `tws:install`/`tws:add`). O instalador os lê no
  comando que o chamou (pelo `/proc`: Linux e WSL) e os repassa como
  `COMPOSER_IGNORE_PLATFORM_REQ(S)`; essas variáveis, quando a pessoa as
  define, valem em qualquer sistema.
- **Windows: só `ext-pcntl` e `ext-posix` são ignoradas, sozinhas.** Só o
  Horizon as exige, e o PHP do Windows não as tem. Sem elas, o resto roda — foi
  provado num PHP 8.4 sem as duas: a suíte inteira passa, o site sobe com
  login, `queue:work` processa a fila (banco e Redis) e o `schedule:run`
  roda; o que não roda é `php artisan horizon` (`pcntl_async_signals`). O
  código dos pacotes só chama `posix_*` depois de conferir que a função existe.
  O resumo do comando único (e o do `tws:install`, quando chamado sozinho) diz
  isso em linguagem simples, com o `queue:work`, o
  `$env:COMPOSER_IGNORE_PLATFORM_REQ = "ext-pcntl,ext-posix"` para os
  próximos comandos do Composer, e o caminho só com o Docker, que roda tudo.
  (`TWS_KIT_OS_FAMILY=Windows` simula o Windows em outro sistema — é o que a
  prova usa.)
- **Qualquer outra extensão que falte** (bcmath, gd, intl, zip…) **não é
  ignorada**: o `composer update` para, e a mensagem lista as extensões que
  faltam (lidas da saída do Composer) com as duas saídas — instalar (no
  Windows, `extension=…` no `php.ini`; no Ubuntu/Debian, `sudo apt install
  php8.4-…`; no macOS, o PHP do Homebrew) e terminar com os comandos exatos
  (no Windows, começando pelo `$env:COMPOSER_IGNORE_PLATFORM_REQ`), ou usar o
  caminho só com o Docker. O `tws:install` e o `tws:add` dão a mesma
  mensagem quando o Composer deles recusa.
- **A suíte fora do Docker:** o `phpunit.xml` dos starters sobe o
  `memory_limit` para 512M (o padrão do PHP, 128M, não bastava).

**Falha limpa.** O que falha antes de o starter ser montado (escolha
inválida, starter que não baixa, pacote baixado que não é o esperado) não
deixa nada instalado: a pasta temporária sai, e a mensagem diz para apagar a
pasta do projeto e rodar de novo. O que falha depois (o `composer update`, o
instalador) para ali e diz o passo e os **comandos exatos** para terminar sem
recomeçar (`composer update`, `composer run-script
post-create-project-cmd`…) — ou para apagar a pasta e rodar de novo. O código
de saída do `create-project` é diferente de 0.

## O Docker de desenvolvimento do projeto criado

Todo projeto criado — pelo caminho só com o Docker, pelo comando único ou
pelos comandos por starter — nasce com:

- **`compose.yaml`** (na raiz): `app` (PHP-FPM 8.4, a imagem `workspace` do
  starter: o `dev` com o Composer e o git), `nginx` (o site), `init` (de uma
  vez: o `vendor/` se faltar e as migrations — o app sobe depois dele),
  `queue`, `scheduler`, `vite` (Node 24, a recarga ao vivo), `postgres`,
  `redis`, `mailpit` e `db-init` (o banco `<DB_DATABASE>_test` da suíte contra
  o PostgreSQL — o mesmo nome que o `phpunit.pgsql.xml` do projeto força).
  `docker compose up -d` sobe tudo.
- **`.devcontainer/devcontainer.json`**: o VS Code ("Reopen in Container")
  entra no container `app` do mesmo `compose.yaml`, com o projeto em
  `/var/www/html`.
- **`.github/workflows/ci.yml`** e **`scripts/verificar`**: o CI base e a
  mesma verificação na máquina (ver [O CI e o `scripts/verificar` do projeto
  criado](#o-ci-e-o-scriptsverificar-do-projeto-criado)).

**O Vite de desenvolvimento e o CORS.** A página vem do site (nginx, porta
`808N`) e os scripts do front, do Vite (porta `803N`) — outra origem. O
navegador só executa os scripts se o Vite liberar a origem do site no CORS;
sem isso, a tela fica em branco (o React não monta) ou o front do Livewire não
roda. O serviço `vite` recebe a URL do site (`VITE_DEV_APP_URL`) e o
`vite.config` libera no CORS **só** ela: outro projeto `*.localhost`, a
própria origem do Vite ou um site qualquer continuam sem o cabeçalho de
liberação. A CSP do projeto, em `APP_ENV=local` e com o `public/hot` presente,
aceita a origem do Vite (e o WebSocket do HMR) — só em desenvolvimento, nunca
`unsafe-eval` (`App\Support\ViteDevServerCsp`, nos dois starters).

**IPv6 e `*.localhost`.** As portas saem só em `127.0.0.1`. Em alguns sistemas
— e dentro de containers, como o do Playwright — `<nome>.localhost` resolve
primeiro para `::1`: o navegador tenta o `127.0.0.1` sozinho, mas um script
(Node, `curl`) que chame pelo nome recebe "connection refused". Nele, use
`http://127.0.0.1:<porta>` (o E2E do projeto já faz isso com o Mailpit e com o
Vite). O compose não publica em `[::1]` porque isso quebraria o `up` onde o
IPv6 está desligado.

No monorepo, os dois ficam em `starters/<starter>/docker/dev/` (um
`compose.yaml` na raiz do starter mudaria o `docker compose` de quem roda o
monorepo dali); a publicação (`prepare-composer.php`) os põe no lugar, e o
instalador põe o CI base (`docker/dev/ci.yml`) em `.github/workflows/`. O
ambiente de desenvolvimento do monorepo (o `docker-compose.yml` da raiz,
Livewire na 8180 e React em 127.0.0.1:8181, com as configurações do nginx de
dev em `docker/nginx/` da raiz) não muda. **Nada disso do monorepo chega ao
projeto:** o nginx de dev do monorepo e o alvo `dev` do Dockerfile do nginx
não existem mais nos starters; o teste que compara os textos do React com os do
Livewire e o E2E da demonstração ficam fora do pacote publicado
(`export-ignore`), e os `phpunit*.xml` publicados excluem o grupo `demo` (a
demonstração não é publicada — os testes dela não aparecem como pulados); e os comentários dos arquivos de desenvolvimento publicados
(compose, Dockerfiles, `.env.example`, `phpunit.pgsql.xml`, Playwright e
`tests/e2e`) descrevem o projeto — a simulação da instalação publicada
reprova qualquer citação do monorepo neles.

**O nome** do projeto vira o `COMPOSE_PROJECT_NAME` (containers, volumes e
rede com o prefixo do projeto), o endereço `http://<nome>.localhost:<porta>`,
o banco (`DB_DATABASE`, com `_` no lugar do `-`) e o cookie de sessão
(`SESSION_COOKIE=<nome>_session`). Cada projeto no **seu** host: cookie é por
host, não por porta — dois projetos em `localhost` dividiriam a sessão e o
`XSRF-TOKEN`. O nginx de desenvolvimento leva quem abrir `localhost:<porta>`
(ou `127.0.0.1`) ao endereço do projeto (308). Um nome que já existe como
projeto Docker (containers, volumes ou redes com o rótulo do Compose, mesmo
parado) é recusado: reusá-lo trocaria os containers de um pelos do outro, e o
banco novo cairia no volume velho, com outra senha.

**O número** define as quatro portas, com o mesmo final:

| Serviço | Porta (número N, 0–9) | Centena seguinte (10–19) | Publicada |
| --- | --- | --- | --- |
| Site (nginx) | 808N | 818N | sempre, em 127.0.0.1 |
| E-mails (Mailpit, web) | 802N | 812N | sempre, em 127.0.0.1 |
| Vite | 803N | 813N | sempre, em 127.0.0.1 |
| Banco (PostgreSQL) | 804N | 814N | só com `COMPOSE_PROFILES=db-port` |
| Redis | — | — | nunca |

De 0 a 9 cabem dez projetos; ocupados os dez, segue a centena seguinte com o
mesmo padrão (10 = `8180/8120/8130/8140`), até 99 (`8989/8929/8939/8949`).
O número sugerido é o primeiro em que **as quatro** estão livres ao mesmo
tempo (a do banco também, mesmo sem publicá-la: publicar depois não pode
colidir).

**Como as portas são conferidas:**

- pelo **Docker** (`docker inspect` de todos os containers, inclusive os
  parados — um projeto parado volta a usar as portas quando sobe): as portas
  publicadas e **quem** as usa (o projeto do Compose), para o menu dizer "0
  já é usado por loja-da-maria"; e os nomes dos projetos (containers, volumes
  e redes com o rótulo do Compose);
- o que mais ocupa a porta: **fora de container** (o comando único na
  máquina), tentando abri-la (127.0.0.1 e, fora do Windows, 0.0.0.0);
  **dentro do container do instalador**, abrir a porta ali não diz nada
  sobre a máquina — quem tenta é o próprio Docker, com um container
  descartável (a imagem do instalador, `sleep`) com as portas publicadas em
  127.0.0.1, apagado logo depois. **Limitação:** no Docker Desktop com WSL,
  um programa que escuta só dentro da distribuição WSL (fora do Docker) não
  impede o Docker de publicar a porta e não é visto; programas do Windows, do
  macOS e do Linux nativo são. Sem o Docker (não instalado, ou sem acesso ao
  socket), o menu avisa: os nomes dos outros projetos não são conferidos, e as
  portas só pelo que dá para abrir.

**No `.env`** — o instalador (`tws:install`) grava, **uma vez** (enquanto não
houver `COMPOSE_PROJECT_NAME`): `COMPOSE_PROJECT_NAME`, `COMPOSE_PROFILES`
(`db-port` ou vazio), `DEV_SLOT`, `DEV_SITE_PORT`, `DEV_MAIL_PORT`,
`DEV_VITE_PORT`, `DEV_DB_PORT`, `DEV_UID`/`DEV_GID` (o dono dos arquivos: os
containers gravam como ele), `DEV_VITE_POLLING`, `APP_URL` e
`PLATFORM_OFFICIAL_URL`, `SESSION_COOKIE`, `DB_*` (host `postgres`, banco do
projeto, **senha gerada**), `REDIS_PASSWORD` (**gerada**) e o Mailpit
(`MAIL_HOST=mailpit`) e `RATE_LIMIT_SENSITIVE=30` (as rotas sensíveis no
desenvolvimento: a suíte E2E entra e cadastra em paralelo, do mesmo IP; a
produção continua com 5, o padrão da `config/security.php` e do
`.env.prod.example`). Nada de senha fixa do kit: o `.env.example` traz a do
desenvolvimento do monorepo, e o projeto criado recebe outra. Com o Docker de
desenvolvimento configurado, as migrations ficam para o primeiro
`docker compose up -d` (o banco do projeto ainda não existe durante a
criação). **Trocar depois:** edite o `.env` e rode `docker compose up -d`
(trocando a porta do site, troque também a do `APP_URL`); trocar o nome cria
containers e volumes novos, com o banco vazio. Tudo é publicado só em
`127.0.0.1` (`DEV_BIND`); o Mailpit é só local.

**Comandos por starter e `tws:install`:** sem `TWS_KIT_NAME`/`TWS_KIT_SLOT`, o
`tws:install` do `create-project` do starter usa o nome da pasta (sem colidir)
e o primeiro número livre — não pergunta. Com elas, confere como o menu: nome
em uso ou número com porta ocupada param a instalação antes de qualquer
mudança. No monorepo (sem `compose.yaml` na raiz do starter), nada disso
acontece.

**O projeto criado é do projeto** — na mesma hora, o instalador troca o que
o starter trazia:

| Arquivo | O que fica |
| --- | --- |
| `.env.example` | `APP_URL`, `PLATFORM_OFFICIAL_URL`, `DB_DATABASE` e `SESSION_COOKIE` do projeto (as senhas geradas ficam só no `.env`) |
| `phpunit.pgsql.xml` | o banco da suíte `<banco>_test` — o mesmo que o `db-init` do `compose.yaml` cria |
| `docker-compose.prod.yml` | o nome padrão do banco de produção (`PROD_POSTGRES_DB`) |
| `composer.json` | `name` = `<vendor>/<nome>` (`TWS_KIT_VENDOR`, padrão `app`), `license` = `proprietary` (`TWS_KIT_LICENSE`: um identificador SPDX como `MIT` ou `Apache-2.0`), sem a descrição, a página, o suporte, as palavras-chave e a versão do starter; o `content-hash` do `composer.lock` acompanha |
| `LICENSE` → `NOTICE-KIT-MIT.txt` | a licença MIT do kit vira o aviso que a MIT exige manter, com um cabeçalho dizendo que ela não é a licença do projeto — o projeto escolhe a dele (o `license` do `composer.json` e, se quiser, um `LICENSE` próprio) |
| `.github/workflows/ci.yml` | o CI base (sem sobrescrever um que exista) |

Vendor ou licença inválidos param a instalação antes de qualquer mudança,
como o nome e o número. `TWS_KIT_VENDOR` e `TWS_KIT_LICENSE` valem nos dois
caminhos (o `compose.yaml` do instalador em container as repassa); o menu não
pergunta — as duas se trocam depois no `composer.json`, sem efeito colateral.

### O CI e o `scripts/verificar` do projeto criado

O projeto nasce com o mesmo conjunto de verificação em dois lugares:

- **`scripts/verificar`**, na máquina, pelo Docker de desenvolvimento (sem
  PHP nem Node instalados): Pint, `composer audit`, `npm audit` (dependências
  de produção, alta ou crítica), o build do front e o Pest contra o
  PostgreSQL (`<banco>_test`). Para no primeiro passo que falhar. No Windows,
  no terminal do WSL ou no Git Bash.
- **`.github/workflows/ci.yml`**, no GitHub Actions: os mesmos passos, com o
  PostgreSQL 18 como serviço do job. Roda **à mão** (Actions → CI → Run
  workflow) e **uma vez por semana** (pega dependência com falha de segurança
  nova mesmo sem commit) — não a cada push, porque repositório privado tem
  cota de minutos. Para rodar a cada push e pull request, acrescente em `on:`
  `push: { branches: [main] }` e `pull_request:`. Permissão só de leitura
  (`contents: read`), ações de terceiros **fixadas pelo commit** (com a versão
  no comentário) e o checkout sem guardar a credencial.

**A imagem de produção não muda:** o estágio `workspace` é à parte (o `prod`
parte do `base`), e o `.dockerignore` deixa o `compose.yaml`, o
`.devcontainer/`, o `docker/dev/` e o `scripts/` fora do contexto da imagem.

## Criar um projeto a partir de um starter

Os comandos por starter continuam valendo (e são o que o comando único faz
por dentro):

```bash
composer create-project "twstec/starter-livewire:^2.0@beta" meu-app
# ou
laravel new meu-app --using=twstec/starter-livewire
```

(`twstec/starter-react` para o React — ver [Starter React](#starter-react).)

O que acontece: o Composer baixa o starter e os pacotes, copia o
`.env.example` para `.env` e roda o `post-create-project-cmd` do starter, que
chama o **instalador** (`php artisan tws:install --graceful`). Num terminal, o
instalador pergunta os módulos; sem terminal (CI), mantém tudo como veio — ou
aplica `TWS_KIT_WITH`/`TWS_KIT_WITHOUT`, se estiverem no ambiente
(`TWS_KIT_WITHOUT=admin composer create-project …`). O
projeto nasce **sem a demonstração** (ela não é publicada: o `composer.json`
publicado do starter não a cita): `/` é a página inicial do produto e os
testes da demo pulam sozinhos. Depois dele, o projeto tem a `APP_KEY` e, com o módulo de
contas, um pepper dedicado para as chaves de API. As migrations rodam se o
banco do `.env` estiver acessível; senão, ficam para depois
(`php artisan migrate`) — o `.env.example` aponta para o PostgreSQL do
`docker-compose.yml`.

## Starter React

`starters/react` (`twstec/starter-react`) é o mesmo kit com o painel do
usuário em React 19 + Inertia 3 + TypeScript + Tailwind 4 + shadcn/ui, a
partir do [kit oficial React do Laravel](https://github.com/laravel/react-starter-kit).
A autenticação é a do `twstec/kit-auth` (sem o Fortify do kit oficial): as
telas são páginas React, os envios são os controllers do pacote e as
respostas são as implementações Inertia dos contratos de resposta dele. As
contas do `twstec/kit-accounts` são a fonte única (o kit oficial não traz
times, e nada dele é usado para isso). Detalhes em
[starters/react/README.md](../starters/react/README.md).

**Estado:** completo, com as mesmas telas do Livewire — autenticação (login
com limite de tentativas, cadastro, verificação de e-mail obrigatória,
esqueci/redefinir senha, segundo fator por código de e-mail, logout), painel
inicial, perfil (idioma, tema, foto), senha de transação, notificações,
seletor de conta, conta e membros, convites, chaves de API e projetos. Tem E2E
próprio (Playwright), imagem de produção própria (`starters/react/docker`,
conferida no CI) e passa pelas mesmas combinações de módulos no CI.

**Criar um projeto React:** o comando único com a interface React
(`TWS_KIT_STACK=react`, ou escolhida no menu), ou direto pelo starter:

```bash
composer create-project "twstec/starter-react:^2.0@beta" meu-app
# ou
laravel new meu-app --using=twstec/starter-react
cd meu-app && npm install && npm run build
```

Como no Livewire, o `post-create-project-cmd` cria o SQLite local e chama o
instalador (`php artisan tws:install --graceful`): num terminal ele pergunta
os módulos opcionais (contas, uploads, `/admin`); sem terminal, mantém todos.
Gera a `APP_KEY` e, com contas, o pepper dedicado das chaves de API. O React
não tem a demonstração, então ela nunca é perguntada. Enquanto a publicação
não é ligada, o CI simula exatamente isso a partir dos pacotes empacotados
(`STARTER=react sh .github/release/simulate-install.sh`).

**No monorepo (desenvolvimento):** o `docker-compose.yml` da raiz sobe o React
na porta **8181**, ao lado do Livewire (8180), com a imagem PHP do próprio
starter (`starters/react/docker/php/Dockerfile`, target `dev`), o mesmo PostgreSQL, Redis e
Mailpit — mas banco próprio (`tws_starter_react`, criado pelo serviço
`react-db-init`), bancos próprios no Redis (`REDIS_DB=2`, `REDIS_CACHE_DB=3`)
e cookie de sessão com nome próprio, para um não derrubar a sessão, a fila ou
o cache do outro:

```bash
cd starters/react && cp .env.example .env
# composer install e npm install/build: ver starters/react/README.md
cd ../.. && export UID GID=$(id -g)
docker compose up -d react-nginx react-queue react-scheduler
docker compose exec react-app php artisan key:generate --force
docker compose up -d --force-recreate --no-deps react-app react-queue react-scheduler
docker compose exec react-app php artisan migrate
```

Aplicação: **http://127.0.0.1:8181** (não `localhost`). Cookie é por host, não
por porta: com os dois starters em `localhost`, o cookie `XSRF-TOKEN` (nome
fixo do Laravel, lido pelo front) de um sobrescrevia o do outro, e o primeiro
envio depois de trocar de aba caía no "sessão expirou". Com o React em
`127.0.0.1`, cada starter tem os próprios cookies. O `APP_URL` do
`.env.example` do React já é esse, e o nginx de desenvolvimento leva quem abrir
`localhost:8181` ao mesmo caminho em `127.0.0.1:8181` (redirecionamento 308,
que mantém o método). Nada disso existe na produção, que atende pelo domínio
da `APP_URL`. Quem já tinha o `.env` do React: troque o `APP_URL` para
`http://127.0.0.1:8181` e recrie os serviços `react-*`
(`docker compose up -d --force-recreate --no-deps react-app react-queue react-scheduler`).

O instalador `php artisan tws:install` é o
mesmo (`docker compose exec react-app php artisan tws:install`); o React não
usa a demonstração, então ela nunca é perguntada. Os módulos instalados chegam
ao front na prop `kit.modules`, e o menu só mostra tela que existe.

## O instalador: `php artisan tws:install`

Mora no pacote `twstec/kit-installer` (require-dev dos dois starters — o
mesmo pacote no Livewire e no React). Pode rodar a qualquer momento, quantas
vezes quiser.

### Interativo

```bash
php artisan tws:install
```

1. Pergunta quais módulos opcionais você quer — os já instalados vêm
   marcados. Uploads sem Contas é recusado na hora (com a explicação).
2. Se a demonstração estiver instalada (só no clone do monorepo): pergunta se ela fica. Se você tirou
   algum módulo, avisa que a demo exige todos e que ela vai sair junto (e pede
   confirmação).
3. Mostra o plano (módulo por módulo: agora → depois) e pede confirmação.
4. Aplica e imprime o resumo e os próximos passos.

### Não interativo (CI, scripts)

```bash
# Só a base (foundation + auth), tirando também a demo
php artisan tws:install --no-interaction --without=accounts,uploads,admin --no-demo

# Tirar só o /admin
php artisan tws:install --no-interaction --without=admin --no-demo

# Pôr de volta contas e uploads, sem o /admin
php artisan tws:install --no-interaction --with=accounts,uploads --without=admin
```

| Opção | O que faz |
| --- | --- |
| `--with=a,b` | Instala (ou mantém) esses módulos opcionais |
| `--without=a,b` | Remove esses módulos opcionais |
| `--no-demo` | Remove a demonstração (`twstec/kit-demo`) |
| `--force` | Permite rodar com `APP_ENV=production` (sem ela, recusa) |
| `--graceful` | Banco inacessível não reprova a rodada: as migrations ficam para depois |

Sem a opção, `TWS_KIT_WITH` e `TWS_KIT_WITHOUT` no ambiente valem como
`--with` e `--without` (é por onde a escolha chega ao `post-create-project-cmd`
do starter — o Composer não repassa opções ao script). A opção vence a
variável.

Qualquer opção de escolha (`--with`, `--without`, `--no-demo`), uma dessas
variáveis ou `--no-interaction` desliga as perguntas. O que não foi citado
fica como está.
Módulo desconhecido, módulo obrigatório em `--without`, o mesmo módulo nas
duas listas, uploads sem contas e tirar um módulo com a demo ficando são
recusados **antes** de mexer em qualquer coisa.

### O que ele aplica, nesta ordem

1. Com a demo saindo: `php artisan demo:uninstall --drop-tables` (tira do banco
   os gatilhos das contas demo e as tabelas dela — ver [demo](demo.md)) e
   `composer remove --dev twstec/kit-demo`. Se o `demo:uninstall` falhar, para
   ali, sem remover nada (com `--graceful`, avisa e segue).
2. `composer remove` dos módulos não escolhidos (o Filament sai junto com o
   `/admin`: ele vem pelo `twstec/kit-admin`).
3. `composer require` dos escolhidos que faltam, com a mesma restrição de
   versão do `twstec/kit-foundation` no seu `composer.json`.
4. `php artisan optimize:clear` (config, rotas e views em cache mudam com os
   módulos).
5. `.env`: criado do `.env.example` se faltar; `APP_KEY` gerada se vazia;
   com contas, `API_KEYS_HASH_PEPPER` gerado se vazio. Se a `APP_KEY` já
   existia, ela vai para `API_KEYS_PREVIOUS_HASH_PEPPERS` — as chaves de API
   emitidas com o fallback continuam autenticando e migram sozinhas no
   primeiro uso ([API](api.md)).
6. `php artisan migrate --force`.
7. Resumo: módulos (instalado, instalado agora, removido, não instalado),
   `APP_KEY`, pepper, migrations e os próximos passos — recompilar o front
   (`npm install && npm run build`), `migrate:fresh` num banco de
   desenvolvimento que tinha a massa fictícia da demo, e o aviso do `/horizon`
   sem o `/admin`.

Os comandos do artisan rodam num **processo novo**: depois do Composer, o
processo do instalador ainda tem na memória os providers de antes.

**Não apaga arquivo do aplicativo.** As telas, rotas e menus de um módulo
ausente somem sozinhos (próxima seção); o código delas continua no seu
projeto, inerte, e volta a valer se você reinstalar o módulo.

**Idempotente:** a segunda rodada com a mesma escolha não chama o Composer e
não regera chave nenhuma. **Produção:** recusa sem `--force` — tirar pacote e
mexer no `.env` não é coisa de servidor; lá o que vale é o `composer.json`
versionado.

## O que some em cada combinação

A detecção é um ponto só: `Twstec\Kit\Foundation\Kit::has('accounts')` (e a
diretiva `@kit('uploads') … @else … @endkit` nas views). Módulo instalado =
registrado pelo Composer **e** com o provider carregável.

| Sem… | No painel do usuário (Livewire) | No `/admin` | Outros |
| --- | --- | --- | --- |
| `accounts` (e, por consequência, `uploads`) | Não existem as rotas `/api-keys`, `/projects`, `/account`, `/accounts/*` e `/invitations/*` (404); o menu perde o grupo "Desenvolvimento" e "Conta e membros"; o seletor de conta some; o painel inicial mostra os atalhos da conta (perfil, senha de transação, notificações) em vez dos números da conta | Sem as telas de contas, chaves e projetos; sem o filtro por conta na trilha; os dashboards sem os cards de chaves/projetos e sem a tabela de projetos recentes; a guarda de exclusão de usuário não consulta contas | Sem a API v1 e sem o agendamento `api-keys:process-inactivity`; o `/api/health` continua com o limite `throttle:api` e o envelope de erro (postos pelo próprio aplicativo); as chaves de API somem das configurações editáveis |
| `uploads` | Sem a rota `settings/avatar` e sem o cartão da foto no perfil (a ação responde 404); o avatar são as iniciais | Sem a tela de uploads, sem o widget dos últimos uploads e sem o campo de foto (usuários e perfil) | Os limites de upload somem das configurações editáveis; num banco criado sem o módulo, a coluna `users.avatar_upload_id` nasce sem chave estrangeira (não há tabela `uploads`). Tirar um módulo **não apaga as tabelas dele** — elas ficam, sem uso |
| `admin` | — | Não existe: o `AdminPanelProvider` do aplicativo não é registrado e o Filament nem está instalado (`/admin` → 404) | O `/horizon` fecha fora do ambiente local (o critério de acesso é o do painel; declare o seu em `app/Providers/HorizonServiceProvider.php` se quiser); o tema `filament.css` sai do build do front; os scripts do Composer pulam os assets do Filament |
| demo | As landings, a vitrine e o contato ([demo](demo.md)) | As telas de produtos e submissões | Nenhuma conta protegida; `db:seed` não semeia nada |

O model `App\Models\User` compõe as peças dos módulos opcionais por nomes do
próprio aplicativo (`OptionalAdminPanelUser`, `OptionalAdminPanelAccess`,
`OptionalProfilePhoto` — ver `app/Support/optional-modules.php`): com o módulo,
viram a peça do pacote; sem ele, uma peça neutra (sem painel; sem foto).

Escrevendo telas próprias que usam um módulo opcional, faça a mesma pergunta:
rota dentro de `if (Kit::has('accounts')) { … }`, item de menu com
`'module' => 'accounts'` em `App\Livewire\Support\Navigation`, bloco de view em
`@kit('accounts') … @endkit`.

## Só os pacotes, num aplicativo Laravel que já existe

### Com o `tws:add`

```bash
# Durante o beta, o foundation também leva o @beta (a estabilidade só vale no
# projeto raiz); na 2.0.0 estável, basta a segunda linha, sem o @beta.
composer require "twstec/kit-foundation:^2.0@beta"
composer require --dev "twstec/kit-installer:^2.0@beta"
php artisan tws:add                                        # pergunta (Laravel Prompts)
php artisan tws:add accounts admin                         # sem perguntas
```

O `tws:add` (do mesmo pacote do `tws:install`) mostra os módulos do kit que o
aplicativo tem e os que faltam, e instala os escolhidos:

- **a autenticação vem junto** de qualquer módulo, se faltar (ela é
  obrigatória no kit);
- **recusa o que exige dependência ausente**, com a explicação e o comando
  certo — `tws:add uploads` sem contas: "Uploads … precisa de Contas …
  Adicione os dois juntos: `php artisan tws:add accounts uploads`". No menu,
  a mesma regra não deixa a escolha passar;
- **recusa contas (e uploads) enquanto o model de usuário do aplicativo não
  estiver pronto para a autenticação do kit** (`auth.providers.users.model`
  implementando `Twstec\Kit\Auth\Contracts\AuthUser`): o pacote de contas
  liga a conta pessoal aos eventos desse model já no boot, e o `twstec/kit-auth`
  falha alto com um model que não serve — num aplicativo recém-criado (o
  `User` do esqueleto), o aplicativo deixaria de subir. A mensagem dá o
  caminho: `php artisan tws:add auth`, ajustar o model como no
  [README do twstec/kit-auth](../packages/auth/README.md#o-model-de-usuário-é-do-aplicativo)
  e `php artisan tws:add accounts`. A autenticação e o `/admin` não dependem
  disso (o `kit-auth` confere o model só no primeiro uso);
- `composer require` dos escolhidos — e do foundation e da autenticação como
  requisito **direto** do projeto, quando hoje só vêm por dependência de outro
  pacote (senão um `composer install --no-dev` os levaria embora) —, com a
  restrição de versão do kit que está no `composer.json` (a do instalador,
  num aplicativo que só tem ele);
- `optimize:clear`, a configuração publicada de cada módulo
  (`vendor:publish --tag=<módulo>-config`, sem sobrescrever o que existe), a
  `APP_KEY` se faltar, o pepper dedicado das chaves de API com contas, e
  `migrate` (`--graceful` deixa as migrations para depois se o banco não
  responder);
- as **proteções** vêm ligadas pelos próprios pacotes (descoberta automática
  do Laravel);
- o resumo diz, módulo a módulo, o que só o aplicativo pode fazer: o model de
  usuário com o contrato e a trait do `twstec/kit-auth`, a foto de perfil, o
  PanelProvider do Filament com o `AdminPlugin`, a coluna `is_admin` e o
  primeiro admin (`user:make-admin`).

Recusa `APP_ENV=production` sem `--force`, como o `tws:install`.

### Com o Composer

Sem o instalador, instale só o que quiser:

```bash
composer require twstec/kit-foundation twstec/kit-auth
composer require twstec/kit-accounts          # opcional
composer require twstec/kit-uploads           # opcional (exige accounts)
composer require twstec/kit-admin             # opcional (traz o Filament)
```

Durante o beta, com a estabilidade mínima `stable` do seu projeto, acrescente
`:^2.0@beta` a **cada** pacote do kit que você requerer (inclusive
`twstec/kit-foundation`, mesmo que ele venha como dependência) — ou use
`"minimum-stability": "beta"` com `"prefer-stable": true`.

Cada pacote sobe sozinho pela descoberta do Laravel e liga as próprias
proteções. O que o aplicativo fornece está no README de cada pacote
([foundation](../packages/foundation/README.md),
[auth](../packages/auth/README.md), [accounts](../packages/accounts/README.md),
[uploads](../packages/uploads/README.md), [admin](../packages/admin/README.md)):
em resumo, o model de usuário com as traits do auth (e a foto do uploads, se
quiser), as colunas `is_admin` e `avatar_upload_id` se usar o `/admin` e a
foto, e um PanelProvider do Filament que registra o `AdminPlugin`.

## Como o CI garante

- **Cada pacote isolado** (suíte própria, aplicação limpa) — passos do job
  obrigatório.
- **As combinações principais do starter**, no job do SQLite, pelo próprio
  instalador, uma depois da outra: completo (com a demo), sem a demo, sem
  uploads, só o `/admin` (sem contas nem uploads), só a base
  (foundation + auth) e sem o `/admin` (contas e uploads reinstalados pelo
  `--with`). Em cada uma: o build do front e a suíte com o `pest` de sempre —
  os testes de um módulo ausente **pulam sozinhos** (grupos `accounts`,
  `uploads`, `admin`; ver [testes](testes.md)).
- **As mesmas combinações no starter React** (sem uploads, só o `/admin`, só
  a base, sem o `/admin`), no mesmo job, no checkout do React: instalador,
  build (sem o tema do `/admin` quando ele sai) e a suíte.
- **A instalação publicada, simulada:** cada pacote vira um ZIP
  (`composer archive`, com o `composer.json` da versão publicada), o projeto é
  criado **só** a partir deles (`composer create-project` com repositório
  `artifact` — pacotes copiados, sem link para o monorepo) e roda o build e a
  suíte. Por fim constrói as **imagens de produção (app e nginx) do projeto
  criado** — sem a pasta `packages/`, com o Dockerfile e o
  `docker-compose.prod.yml` publicados — e passa as mesmas conferências de
  imagem limpa do job de imagens (sem `.env`, testes, storage, SQLite, demo
  nem instalador; pacotes copiados). O repositório `artifact` é relativo
  (`../packages`): no build da imagem ele chega pelo contexto opcional
  `packages` do Dockerfile, que no uso real fica vazio (os pacotes vêm do
  Packagist). Pega starter que só funciona dentro do monorepo. Script:
  `.github/release/simulate-install.sh` — uma vez com o Livewire e uma com o
  React (`STARTER=react`).
- **O comando único, simulado:** o mesmo, com `composer create-project
  twstec/kit` a partir dos ZIPs (os dois starters empacotados), sem terminal
  e com a escolha pelo ambiente — React sem uploads no job do PostgreSQL,
  Livewire só com a base no do SQLite (`STARTER=kit KIT_STACK=…
  KIT_WITHOUT=…`). Além das conferências de sempre: o projeto é o do starter
  escolhido, nada do `twstec/kit` sobrou, e os módulos **desmarcados** não
  estão no `composer.json`, no vendor, no registro do Composer nem na imagem
  de produção. A suíte do próprio `twstec/kit` roda nos dois jobs.
- **O caminho só com o Docker, simulado** (`simulate-install.sh docker`, no
  job do SQLite, depois da simulação do comando único com o Livewire só com a
  base): o ZIP do `twstec/kit` como o GitHub serve, `docker compose run --rm
  -T instalar` com a escolha pelo ambiente e os ZIPs no lugar do Packagist,
  as conferências (o projeto do starter com o `compose.yaml` e o
  `.devcontainer/`, o `.env` com o nome, as portas e as senhas geradas, os
  arquivos com o dono da máquina), `docker compose up -d`, o site respondendo
  em `http://<nome>.localhost:<porta>/up` (e `localhost` levando para lá) e
  `docker compose down -v --rmi local`.
- **As imagens de produção** dos dois starters são construídas e conferidas
  (o React num job próprio, `Imagens de produção do starter React`).

## Publicação

O workflow `.github/workflows/split.yml` copia, a cada tag `v2.*`, cada
pacote, cada starter e o comando único para o repositório só-leitura dele (9
destinos: `kelvindk9w/twstec-kit-foundation`, `…-auth`, `…-accounts`,
`…-uploads`, `…-admin`, `…-installer`, `kelvindk9w/twstec-starter-livewire`,
`kelvindk9w/twstec-starter-react` e, a partir da 2.0.0-beta.2,
`kelvindk9w/twstec-kit` — a demonstração **não** é publicada), com o
`composer.json` preparado por `.github/release/prepare-composer.php` (sem path
repositories, pacotes do kit em `^2.0`; nos starters, `^2.0@beta` durante o
beta, sem `composer.lock` e sem nenhuma referência à demo; o
`docker-compose.prod.yml` sem o contexto `packages` do monorepo; no
`twstec/kit`, a restrição do starter que ele baixa em
`extra.twstec-kit.constraint` e nada da suíte dele). Cada pasta publicada leva
`README.md` (com o link do monorepo e das docs), `LICENSE` e `SECURITY.md`
(que aponta para a política do monorepo). O job roda com a variável do
repositório `KIT_SPLIT_ENABLED=true` e usa o segredo `SPLIT_TOKEN`, que precisa
dar escrita a **todos** os espelhos da lista — inclusive o
`kelvindk9w/twstec-kit`, novo na 2.0.0-beta.2 —, e cada espelho novo é
registrado no Packagist depois do primeiro split.

### Packagist: atualização automática depois do split

Os espelhos não têm webhook (o split empurra com o `SPLIT_TOKEN`), e o
Packagist mostraria "not auto-updated". Por isso o próprio `split.yml`, depois
que **todos** os splits passam, avisa o Packagist de cada espelho da lista
(inclusive o `twstec/kit`) pela API documentada: `POST
https://packagist.org/api/update-package` com o cabeçalho `Authorization:
Bearer <usuário>:<token>` e o corpo `{"repository":
"https://github.com/kelvindk9w/<espelho>"}`. A lista dos espelhos fica num
lugar só (o job "Espelhos"), usada pelo split e pelo aviso.

**O que o dono configura** (Settings → Secrets and variables → Actions):

1. o **segredo** `PACKAGIST_API_TOKEN`: o token **SAFE** do Packagist (a
   página do perfil mostra os dois, o MAIN e o SAFE) — o endpoint aceita o
   SAFE, que é o recomendado para CI (feito para operações que podem vazar
   sem grande estrago);
2. a **variável** `PACKAGIST_USERNAME`: o nome de usuário no Packagist dono
   dos pacotes `twstec/*`.

Sem os dois, o job "Avisar o Packagist" termina com um aviso no log e **não
falha** (o split já foi feito; dá para clicar em *Update* em cada pacote no
Packagist). Com eles, cada chamada tenta de novo em erro de rede ou 5xx, e uma
resposta de erro (token errado, espelho não registrado) deixa o job vermelho,
com o espelho e o status — o token nunca aparece no log (vai no cabeçalho, por
arquivo, sem `set -x`). **Espelho novo** (como o `kelvindk9w/twstec-kit` na
2.0.0-beta.2): o Packagist só registra um repositório que já tem
`composer.json`, então o primeiro split dele vem antes do registro — nessa
tag, o aviso desse espelho responde erro e o job fica vermelho (os splits já
passaram). Registre o repositório no Packagist e rode de novo só o job
"Avisar o Packagist" (*Re-run failed jobs*).

