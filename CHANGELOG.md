# Changelog

Todas as mudanças relevantes deste kit. O formato segue
[Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e a numeração
segue [Semantic Versioning](https://semver.org/lang/pt-BR/).

## [2.0.0-beta.7] — 2026-10-01

Rastreio de ponta a ponta: identificador de correlação nas filas e nas chamadas HTTP de saída.

Para a **2.0.0-beta.7**: o `correlation_id` passa a acompanhar a operação
inteira — requisição, jobs da fila e chamadas HTTP de saída — com trilha
redigida das chamadas de saída (#25).

### Adicionado

- **Correlation id nos jobs da fila** (#25), no `twstec/kit-foundation`,
  ligado pelo pacote: todo job despachado leva no payload a chave
  `twsCorrelation` (só id e origem) e o worker o restaura antes do job —
  contexto do log (`correlation_id`, `correlation_origin`), trilhas e chamadas
  de saída — e o desfaz depois. Drivers `database`, `redis` e `sync`;
  `Bus::chain`, `Bus::batch` e job despachado de dentro de outro job herdam o
  mesmo id. Job sem id (ou com id que não é UUID) ganha um novo, de origem
  `queue`; cada tarefa do agendador ganha o seu, de origem `scheduler`, que
  chega também ao processo filho de um `command()`; comando avulso ganha um
  de origem `console`. No código: `CorrelationId::current()` e
  `CorrelationId::origin()`.
- **Chamadas HTTP de saída rastreadas** (#25): middleware global do cliente
  `Http` do Laravel que envia o id no cabeçalho `X-Correlation-Id` (nome
  configurável; desligável por destino em `TRACING_HTTP_HEADER_EXCEPT_HOSTS` e
  por chamada com `Http::withoutCorrelationHeader()`) e grava cada tentativa
  na tabela só-acréscimo `outbound_http_logs`: método, host, rota
  normalizada (identificadores, documentos, e-mails e tokens do caminho viram
  marcadores), só os nomes dos parâmetros da query, status, duração,
  tentativa, tamanhos, correlation_id e conta. Cabeçalhos nunca; corpo só com
  `Http::withBodyInTrail()`, redigido pelo `Redactor`. Falha ao gravar a
  trilha não afeta a chamada (vira `http.outbound.persist_failed` no canal
  `request_log`). UPDATE/DELETE recusados pelo model, pelo builder e, no
  PostgreSQL, por gatilho; retenção em `TRACING_HTTP_RETENTION_DAYS` (90 dias)
  com a poda `outbound-http:prune` agendada pelo próprio pacote.
- `config/tracing.php` no foundation (e as variáveis `TRACING_*` no
  `.env.example` dos starters); opt-out explícito de cada parte, com aviso no
  log em produção.
- Com o `twstec/kit-accounts` instalado, a linha da trilha de saída leva a
  conta (`tenant_uuid`) em nome da qual a chamada foi feita.

### Mudado

- Rotas leves fora da trilha em banco (health check, assets) também ganham
  correlation_id, para o job e a chamada de saída que elas fizerem.
- A trilha de auditoria de ações gravada de dentro de um job ou de uma tarefa
  agendada (contexto `console`) passa a levar o `correlation_id` da operação
  que a originou (antes, sempre nulo no console).

## [2.0.0-beta.6] — 2026-09-30

Money que calcula com segurança e correção das frequências do backup.

Para a **2.0.0-beta.6**: aritmética monetária segura no `Money` (#26) e as
frequências do backup com a config em cache, mais as travas de arquitetura
que passam a valer no projeto criado (#32).

### Corrigido

- **Frequências do backup ignoradas em produção** (#32). O
  `routes/console.php` dos dois starters lia `BACKUP_RUN_CRON`,
  `BACKUP_CLEAN_CRON` e `BACKUP_MONITOR_CRON` com `env()`; com
  `config:cache` o `.env` não é lido, e o backup rodava sempre no padrão, sem
  aviso. As frequências foram para `config/backup.php` (`schedule.run`,
  `schedule.clean`, `schedule.monitor`) e a rota as lê com `config()`. Um
  teste roda o `artisan` de verdade: gera o cache com frequências fora do
  padrão e confere o `schedule:list` sem as variáveis no ambiente.
  **Projeto já criado:** copie a chave `schedule` do `config/backup.php` e
  troque os três `env('BACKUP_*_CRON', …)` do `routes/console.php` por
  `config('backup.schedule.run|clean|monitor', …)`.

### Adicionado

- **`Money` que calcula** (#26), no `twstec/kit-foundation`, ao lado das
  funções estáticas de sempre: objeto de valor imutável `Money::of($centavos,
  $moeda)` com soma, subtração, negação, valor absoluto, `sum`/`min`/`max`,
  comparação e sinal (moedas diferentes → `CurrencyMismatchException`);
  multiplicação por inteiro; percentual em pontos-base (`basisPoints(399,
  …)`) ou decimal exato em string (`percentage('3.99', …)`), fator decimal,
  fração e divisão com a **regra de arredondamento obrigatória no
  parâmetro** (o enum nativo `RoundingMode`: metade para longe do zero,
  metade para o par, truncar, piso, teto…), e `Money::defaultRounding()`
  para o projeto que quer uma regra só (`PLATFORM_MONEY_ROUNDING`); rateio
  por pesos (`allocate`) e em partes iguais (`split`) com a soma das partes
  sempre igual ao total e o resto distribuído de forma determinística (maior
  resto primeiro; empate, a parte que vem antes); `Money::ofDecimal()` para
  entrada decimal exata; estouro de 64 bits recusado
  (`MoneyOverflowException`) em vez de virar float; casas decimais por moeda
  configuráveis (`platform.money.fraction_digits`). Cast `AsMoney`, que grava
  valor e moeda. Intermediários em bcmath (a extensão já era exigida).
  Documentação com exemplos e a tabela das regras em `docs/convencoes.md`.
- **Travas de arquitetura no projeto criado** (#32), nos dois starters:
  todo PHP de `app/`, `database/` e `routes/` com `declare(strict_types=1)`;
  nenhum `env()` fora de `config/`; models e domínio sem classe de interface
  (Filament, Livewire, Inertia), com os prefixos lidos do autoload do
  Composer, pacote a pacote; e a trava "todo model com `account_id` usa
  `BelongsToAccount`" também no React, com a lista de exceções explícita e
  o motivo de cada uma. Cada trava tem o teste que prova que ela não é cega.
- `docs/testes.md`: as travas do projeto e a pegadinha do `arch()` do Pest com
  pacotes divididos (`not->toUse('Filament')` não pega
  `Filament\Notifications`).

### Mudado

- `Money::format()` e `Money::parse()` sem float por dentro, com a mesma
  assinatura e o mesmo resultado (a formatação é idêntica à anterior em 13
  combinações de locale e moeda testadas, e exata além de 2^53); o `parse`
  também lê dígitos de outros sistemas (árabe-índicos) e os parênteses de
  contabilidade como negativo. Uma trava do pacote reprova float, `/`,
  `round()` e afins no módulo `Money`.
- O construtor do `Money` passou a ser privado (a classe só tinha métodos
  estáticos; `new Money()` não fazia sentido e agora lança erro — use
  `Money::of()`).
- `strict_types` nos arquivos do esqueleto que ainda não tinham: o
  controller base, as três migrations iniciais, `UserFactory`,
  `DatabaseSeeder` e `routes/console.php`.

## [2.0.0-beta.5] — 2026-09-30

Correções do projeto criado, encontradas no primeiro uso real do kit.

Para a **2.0.0-beta.5**: as correções do primeiro uso real do kit (um projeto
criado pelo caminho só com o Docker, starter React com contas, uploads e
`/admin`) e da mesma prova com o starter Livewire. Tudo no projeto criado;
o monorepo e a produção seguem como estavam.

### Corrigido

- **Tela em branco no desenvolvimento de todo projeto criado pelo Docker.** A
  página vem do site (nginx) e os scripts do Vite, de outra porta; com o
  `server.origin` do Vite, o `laravel-vite-plugin` liberava no CORS só a
  origem do próprio Vite, e o navegador bloqueava os scripts. O serviço `vite`
  recebe a URL do site (`VITE_DEV_APP_URL`) e o `vite.config` libera no CORS
  **só** ela (outro projeto `*.localhost`, a origem do Vite ou um site
  qualquer continuam sem o cabeçalho). Nos dois starters. Provado no
  navegador pelo E2E novo `tests/e2e/front-assets.spec`.
- **Starter Livewire: a CSP recusava o Vite de desenvolvimento** (os scripts e
  o CSS dele não carregavam no projeto criado). Em `APP_ENV=local`, com o
  `public/hot` presente, a origem do Vite entra na CSP — como no React
  (`App\Support\ViteDevServerCsp`); nunca `unsafe-eval`, nada muda fora do
  desenvolvimento.
- **Starter Livewire: modais e ações do `/admin` não abriam no projeto
  criado.** Os assets do Livewire só eram publicados no `composer install`;
  o projeto nasce de um `composer update`, e o `/admin` recebia o bundle
  CSP-safe. O `post-update-cmd` também os publica.
- **`RATE_LIMIT_SENSITIVE=5` no `.env` de desenvolvimento** fazia o E2E do
  projeto criado levar 429. O desenvolvimento usa 30 (`.env.example` e o
  instalador); a produção continua com 5 (`config/security.php` e
  `.env.prod.example`).
- **O E2E do projeto criado apontava para o monorepo** (`127.0.0.1:8181` e o
  Mailpit `18025`): sem variáveis, criaria e apagaria pessoas e mensagens no
  ambiente do kit. O E2E lê `E2E_BASE_URL`/`E2E_MAILPIT_URL` e, na falta, a
  `APP_URL` e a `DEV_MAIL_PORT` do `.env` do projeto; sem nenhum dos dois, para
  com a instrução. Antes de qualquer teste, confere que o site que responde é
  o projeto (o cookie de sessão tem o nome dele) e que o Mailpit é o dele; o
  teardown do React não varre nada fora do projeto. No monorepo, nada muda.
- **Banco de teste com dois nomes:** o `db-init` criava `tws_starter_test` e o
  `phpunit.pgsql.xml` do React usava `tws_starter_react_test`. Agora os dois
  são `<banco do projeto>_test` (o compose deriva do `DB_DATABASE`; o
  instalador grava o `phpunit.pgsql.xml`).
- **Testes "pulados" para sempre no projeto criado:** no React, os 24 da
  comparação dos textos com o starter Livewire (só existe no monorepo), que
  viraram arquivo próprio, fora do pacote publicado (`export-ignore`) — a
  conferência das chaves dos três idiomas continua no projeto; no Livewire, os
  349 da demonstração (grupo `demo`), que não é publicada: os `phpunit*.xml`
  publicados excluem o grupo. A suíte do projeto criado roda sem nenhum
  "pulado" no PostgreSQL (no SQLite, só os que exigem o PostgreSQL).
- **Arquivos e comentários do monorepo no projeto:** o nginx de dev do
  monorepo saiu dos starters (para `docker/nginx/` da raiz), e o alvo `dev`
  do Dockerfile do nginx deixou de existir; o E2E e as capturas da
  demonstração do Livewire ficam fora do pacote publicado; os comentários do
  compose, dos Dockerfiles, do `.env.example`, do `phpunit.pgsql.xml`, do
  Playwright e de `tests/e2e` descrevem o projeto (a simulação da instalação
  publicada reprova citação do monorepo neles), e a mensagem da guarda de path
  repository do Dockerfile do PHP não fala mais em monorepo.
- **Valores do starter no projeto:** o `.env.example` recebe o `APP_URL`, o
  `PLATFORM_OFFICIAL_URL`, o `DB_DATABASE` e o `SESSION_COOKIE` do projeto
  (as senhas ficam só no `.env`), e o `docker-compose.prod.yml`, o nome do
  banco dele.
- E2E do Livewire: o teste da foto de perfil esperava por um texto que já
  estava na página e recarregava antes de o envio terminar (falhava por
  pressa); agora espera a foto aparecer.

### Adicionado

- **O projeto criado com a identidade dele.** O `composer.json` ganha o nome
  `<vendor>/<nome>` (`TWS_KIT_VENDOR`, padrão `app`) e a licença
  `proprietary` (`TWS_KIT_LICENSE`), sem a descrição, a página, o suporte, as
  palavras-chave e a versão do starter (o `content-hash` do lock acompanha); a
  licença MIT do kit vira `NOTICE-KIT-MIT.txt`, com o aviso de que não é a
  licença do projeto. Vendor ou licença inválidos param antes de qualquer
  mudança.
- **CI base e `scripts/verificar` no projeto criado.**
  `.github/workflows/ci.yml` (Pint, auditorias, build e Pest contra o
  PostgreSQL 18), à mão e semanal por padrão — ligar em push está documentado —,
  só leitura, ações fixadas por commit; e `scripts/verificar`, o mesmo conjunto
  na máquina, pelo Docker.
- **E2E do Livewire sem a demonstração:** `tests/e2e/fixtures.php` (as pessoas
  fixas, como no React), login do `/admin` com o admin do E2E quando não há o
  super admin demo, e os specs da demonstração ou de um módulo ausente fora da
  rodada (sem aparecer como pulados) — os testes do `/admin` sobre os dados
  demo só são registrados com a demonstração instalada.
- Documentação: `docs/testes.md` e `docs/instalacao.md` com a versão "projeto
  criado" (testes, E2E, CI, CORS do Vite, IPv6 e `*.localhost`).

## [2.0.0-beta.4] — 2026-09-30

Correção do caminho do iniciante publicado na beta.3.

### Corrigido

- **O nome sugerido (o da pasta) era recusado no caminho só com o Docker.**
  O `docker compose run --rm instalar` roda num container do projeto com o
  nome da pasta — e a conferência de "projeto Docker que já existe" contava o
  próprio instalador. Seguindo o guia (pasta com o nome do projeto, Enter na
  sugestão), todo iniciante caía em "já existe um projeto Docker chamado…". O
  container do instalador (serviço `instalar`, de uma vez) não conta mais, nem
  nos nomes nem nas portas; um projeto de verdade com o mesmo nome (containers
  ou só o volume do banco) continua recusado.
- **`docker compose run --rm instalar` numa pasta que já é o projeto**: em vez
  de "no such service", o recado — a pasta já é o projeto, como subir, e como
  criar outro.

## [2.0.0-beta.3] — 2026-09-30

Terceira versão de testes da 2.0: começar só com o Docker Desktop, nome e
portas por projeto para rodar vários ao mesmo tempo, e instalação com PHP
nativo no Windows.

### Adicionado

- **Criar um projeto só com o Docker Desktop, sem PHP na máquina:** baixe o
  espelho `twstec-kit` (GitHub → Code → Download ZIP, ou `git clone`), entre na
  pasta e rode `docker compose run --rm instalar`. O mesmo menu do comando
  único roda num container (imagem `twstec-kit-instalar`: PHP 8.4 com as
  extensões dos starters, Composer, Node 24 e a linha de comando do Docker) e
  monta o projeto **na própria pasta**; depois, `docker compose up -d`. Os
  arquivos saem com o dono da pasta na máquina (nunca root); o roteiro não
  quebra com CRLF; a rede é a padrão do Docker e não fica volume para trás.
  Sem terminal: `docker compose run --rm -T instalar` com as variáveis
  `TWS_KIT_*`.
- **Duas perguntas novas no começo do menu, com a resposta sugerida (Enter
  aceita):** o **nome do projeto** (vira `COMPOSE_PROJECT_NAME`, o endereço
  `http://<nome>.localhost:<porta>`, o banco e o cookie de sessão; o nome de
  um projeto Docker que já existe é recusado com outra sugestão) e o **número
  do projeto**, que define todas as portas com o mesmo final — site `808N`,
  e-mails `802N`, Vite `803N`, banco `804N`; ocupados de 0 a 9, a centena
  seguinte (`818N`…). O número sugerido é o primeiro com as quatro portas
  livres, e o menu mostra quem usa os outros ("0 já é usado por
  loja-da-maria"). Pelo ambiente: `TWS_KIT_NAME`, `TWS_KIT_SLOT` e
  `TWS_KIT_EXPOSE_DB`.
- **Todo projeto criado já vem com o Docker de desenvolvimento** (pelos dois
  caminhos e pelos comandos por starter): `compose.yaml` com app, nginx,
  PostgreSQL, Redis, Mailpit, fila, agendador, Vite (recarga ao vivo) e as
  migrations na primeira subida; `.devcontainer/devcontainer.json` para o VS
  Code. O `tws:install` grava no `.env` o nome, as portas, o dono dos arquivos
  e senhas geradas para o banco e o Redis (nada da senha fixa do
  `.env.example`); tudo publicado só em `127.0.0.1`, o Redis nunca, o banco só
  com `COMPOSE_PROFILES=db-port`. O nome e o número de um projeto criado ficam
  reservados até o primeiro `up`.
- **Guia "Primeiros passos para iniciantes"** no README do `twstec-kit` (e o
  link no README do monorepo).
- Starters: estágio `workspace` no `docker/php/Dockerfile` (o `dev` com o
  Composer e o git), para o desenvolvimento do projeto criado. O `dev` do
  monorepo e a imagem de produção não mudam.
- CI: o caminho só com o Docker, simulado no job do SQLite (instalar, subir e
  o site respondendo), depois da simulação do comando único.

- **Windows e extensões do PHP.** As opções de plataforma do `composer
  create-project` (`--ignore-platform-req…`, lidas no Linux e no WSL) e as
  variáveis `COMPOSER_IGNORE_PLATFORM_REQ(S)` chegam ao Composer que o
  instalador roda por dentro. No Windows, só `ext-pcntl` e `ext-posix` (o
  Horizon) são ignoradas, sozinhas, com o aviso no resumo (a fila roda com
  `queue:work`). Qualquer outra extensão que falte para a instalação com a
  lista e as duas saídas: instalar, ou o caminho só com o Docker. O mesmo no
  `tws:install` e no `tws:add`.

### Alterado

- O `phpunit.xml` dos starters sobe o `memory_limit` para 512M: a suíte
  passava dos 128M do padrão do PHP fora do Docker.
- O menu do comando único tem cinco perguntas (nome, número, interface,
  módulos, confirmação). Com o Docker de desenvolvimento configurado, o
  `tws:install` deixa as migrations para o `docker compose up -d`.
- O teste do `X-Forwarded-Host` do starter Livewire compara com o host da
  `APP_URL` (e não com `localhost` fixo): passa num projeto com o endereço
  `<nome>.localhost`.
- O teto do job `Pest · SQLite (comando local padrão)` subiu de 75 para 90
  minutos.

## [2.0.0-beta.2] — 2026-09-29

Segunda versão de testes da 2.0: comando único com menu para criar o
projeto, `tws:add` para apps existentes e aviso automático ao Packagist.

### Adicionado

- **Comando único: `composer create-project "twstec/kit:^2.0@beta"
  meu-projeto`.** Pacote novo `twstec/kit` (`starters/kit`, tipo projeto). Um
  menu (Laravel Prompts) pergunta a interface (Livewire ou React) e os módulos
  opcionais (contas com membros e API, uploads e foto, painel `/admin`;
  `foundation` e `auth` vêm sempre). O menu não aceita uploads sem contas: o
  Enter mostra o motivo e a pergunta continua aberta. Depois, o starter
  escolhido é baixado pelo mesmo Composer e toma o lugar do `twstec/kit`, o
  `composer.json` dele perde os módulos não marcados (eles nem chegam a ser
  instalados) e rodam os passos do `create-project` do starter, com o
  `tws:install` recebendo a escolha sem perguntar de novo: `APP_KEY`, pepper
  das chaves de API e migrations. Se o banco do `.env` não responder, a
  mensagem final diz o que ajustar e o comando a rodar.
  - **Sem terminal:** `TWS_KIT_STACK` (`livewire`/`react`),
    `TWS_KIT_WITHOUT` e `TWS_KIT_WITH` no ambiente (padrão: Livewire com todos
    os módulos); `TWS_KIT_LOCALE` escolhe o idioma do menu (pt_BR, en, es).
    Escolha inválida é recusada antes de baixar qualquer coisa.
  - **Windows:** PHP puro, sem bash; sem WSL, o menu sai em listas numeradas
    (Question Helper do Symfony Console), com a mesma validação.
  - **Falha limpa:** antes de o starter ser montado, nada fica instalado (a
    mensagem manda apagar a pasta e rodar de novo); depois, a mensagem diz o
    passo que falhou e os comandos exatos para terminar. O `create-project`
    sai com código diferente de 0.
- **`php artisan tws:add`** (`twstec/kit-installer`): acrescenta pacotes do
  kit a um aplicativo Laravel que já existe. Mostra o que está instalado e o
  que falta; a autenticação vem junto de qualquer módulo; recusa o módulo cujo
  pré-requisito falta, com a explicação e o comando certo (`tws:add uploads`
  sem contas → "adicione os dois juntos: `php artisan tws:add accounts
  uploads`"); recusa contas enquanto o model de usuário do aplicativo não
  implementar o contrato da autenticação do kit (o pacote de contas o usa já
  no boot: num aplicativo recém-criado, ele deixaria de subir), com o caminho —
  `tws:add auth`, ajustar o model, `tws:add accounts`; instala com
  `composer require` (o foundation e a autenticação
  viram requisito direto do projeto), publica a configuração de cada módulo
  sem sobrescrever, gera a `APP_KEY` se faltar e o pepper com contas, roda as
  migrations e resume o que só o aplicativo pode fazer (model de usuário,
  foto, painel do Filament, primeiro admin). Recusa produção sem `--force`.
- **`tws:install` lê `TWS_KIT_WITH`/`TWS_KIT_WITHOUT`** quando `--with`/
  `--without` faltam (a opção vence), e com elas não pergunta. Serve ao
  comando único e a quem cria o projeto direto pelo starter sem terminal
  (`TWS_KIT_WITHOUT=admin composer create-project twstec/starter-livewire …`).
- **CI:** a suíte do `twstec/kit` (menu, escolha pelo ambiente, troca dos
  arquivos, falhas limpas, catálogo igual ao do foundation) roda nos dois jobs
  de teste. A simulação da instalação publicada ganhou o comando único
  (`STARTER=kit`), com os dois starters empacotados: React sem uploads no job
  do PostgreSQL e Livewire só com a base no do SQLite, cada uma com build,
  suíte e imagens de produção do projeto criado. Ela confere também que o
  projeto é o do starter escolhido, que nada do `twstec/kit` sobrou e que os
  módulos desmarcados não estão no `composer.json`, no vendor, no registro do
  Composer nem na imagem.
- **Publicação:** 9º espelho, `starters/kit → kelvindk9w/twstec-kit`. O
  `prepare-composer.php` grava no `twstec/kit` publicado a restrição do
  starter que ele baixa (`^2.0@beta` durante o beta) e tira a suíte dele
  (`require-dev`, `autoload-dev`, scripts de teste).
- **Packagist atualizado pelo split:** depois que todos os splits passam, o
  `split.yml` chama a API `update-package` do Packagist para cada espelho
  (os espelhos não têm webhook e apareciam como "not auto-updated"). Token no
  segredo `PACKAGIST_API_TOKEN` (o SAFE basta) e usuário na variável
  `PACKAGIST_USERNAME`; sem eles, o passo avisa e não falha o split. A lista
  dos espelhos passou a ficar num job só ("Espelhos"), usada pelo split e pelo
  aviso.

### Alterado

- As conferências da imagem de produção (`.github/images/check-*-app.sh`)
  recebem os módulos do projeto em `KIT_MODULES` (padrão: todos, o starter do
  monorepo, sem mudança para o job de imagens) e reprovam módulo não escolhido
  no vendor ou no registro do Composer; as fontes e o tema do `/admin` só são
  exigidos com o `/admin`.
- O instalador acha a restrição de versão do kit também num aplicativo que só
  tem o instalador em `require-dev` (antes, só pela do foundation em
  `require`).
- Tetos dos jobs obrigatórios: PostgreSQL 65 → 85 min, SQLite 55 → 75 min
  (uma simulação a mais em cada, com imagens).

### Para publicar a 2.0.0-beta.2

- O mantenedor cria o segredo `PACKAGIST_API_TOKEN` (token SAFE do
  Packagist) e a variável `PACKAGIST_USERNAME`, para o split avisar o
  Packagist.
- O mantenedor inclui o repositório `kelvindk9w/twstec-kit` (já criado) no
  token do segredo `SPLIT_TOKEN`, antes da tag; depois do primeiro split,
  registra `https://github.com/kelvindk9w/twstec-kit` no Packagist (vendor
  `twstec`) e roda de novo o job "Avisar o Packagist" (nessa primeira vez ele
  fica vermelho só para esse espelho, ainda não registrado).

## [2.0.0-beta.1] — 2026-09-29

Primeira versão de testes da 2.0: o kit vira monorepo com pacotes
instaláveis pelo Composer e dois starters (Livewire e React).

### A 2.0 em resumo

- **Monorepo com pacotes.** O kit virou 7 pacotes Composer
  (`twstec/kit-foundation`, `-auth`, `-accounts`, `-uploads`, `-admin`,
  `-installer` e, só no monorepo, `-demo`) e dois starters
  (`twstec/starter-livewire` e `twstec/starter-react`), publicados por split
  em repositórios só-leitura e no Packagist. `composer create-project
  "twstec/starter-livewire:^2.0@beta"` (ou `starter-react`) cria um projeto
  limpo, sem a demonstração; `php artisan tws:install` escolhe os módulos
  opcionais (contas e API, uploads, `/admin`).
- **Contas com membros** (dono, admin, member), convites, transferência de
  propriedade e exclusão de conta, com toda recusa na trilha de auditoria.
- **Uploads da conta** e foto de perfil pessoal.
- **Starter React** (Inertia + TypeScript) com o mesmo backend do Livewire.

### Atualizando da 1.x para a 2.0 (resumo)

O detalhe está nas seções "Quebra de compatibilidade" e "Atualizando um clone
existente", abaixo. Em produção:

1. **Backup do banco** e uma **janela de manutenção** (ver os riscos abaixo).
2. **Esvazie a fila antes do deploy**: pare de aceitar trabalho novo, deixe o
   Horizon terminar o que está na fila (`php artisan horizon:pause`, espere a
   fila zerar, `php artisan horizon:terminate`). Um job da 1.x que rode depois
   da migração não conhece a conta (projetos, chaves e uploads passam a ser
   da conta) e falha.
3. **Pepper das chaves de API**: defina `API_KEYS_HASH_PEPPER` (dedicado). Se
   a 1.x usava o fallback da `APP_KEY`, declare a `APP_KEY` atual em
   `API_KEYS_PREVIOUS_HASH_PEPPERS` — as chaves emitidas continuam
   autenticando e migram de hash no primeiro uso. Se o `.env` tinha
   `API_KEYS_HASH_PEPPER=` vazio, ver "Pepper vazio" em `docs/api.md`.
4. **Demonstração**: se a instalação rodava a demo, **antes** de subir a 2.0
   rode `php artisan demo:uninstall --drop-tables` (tira os gatilhos das
   contas demo, que ficariam intocáveis no banco depois que o pacote sai) —
   a imagem de produção da 2.0 não tem a demo.
5. Troque o código próprio conforme "Quebra de compatibilidade" (a trava de
   arquitetura do starter aponta consulta que pula o escopo da conta).
6. Suba a imagem nova; o container `migrate` roda `php artisan migrate --force`
   (cria as contas, passa projetos, chaves e uploads para elas, instala os
   gatilhos); depois reinicie Horizon e agendador e retome a fila
   (`php artisan horizon:continue`, se pausou sem terminar).

### Riscos de deploy conhecidos

- **A migração das contas roda numa transação** (PostgreSQL): cria a conta
  pessoal de cada pessoa e passa projetos e chaves, por faixas
  (`ACCOUNTS_MIGRATION_CHUNK`), e só confirma no fim. As tabelas de projetos e
  chaves ficam travadas até lá; a autenticação da API por chave espera. Numa
  base grande, isso é uma **janela de manutenção**, não um deploy sem parada.
  Se falhar, nada muda (e `migrate:rollback` desfaz depois de um sucesso).
- **Fila com jobs da 1.x**: ver o passo 2 acima — sem esvaziar, os jobs
  antigos falham depois da migração (ficam em `failed_jobs`).
- **`demo:uninstall` antes de tirar a demo**: sem ele, os gatilhos das contas
  demo continuam no banco e `demo@…`/`admin@…` seguem intocáveis.
- **Pepper**: trocar a `APP_KEY` sem declarar a antiga como pepper anterior
  (quando ela era o fallback) invalida todas as chaves de API emitidas.
- **Projeto criado pelo `create-project`**: o `.env.example` aponta para o
  PostgreSQL do Docker de desenvolvimento do monorepo (`DB_HOST=postgres`),
  que o projeto não tem — as migrations ficam para depois (`--graceful`), até
  o `.env` apontar para um banco. A imagem de produção do projeto instala os
  pacotes do kit pelo Packagist (o mesmo Dockerfile do monorepo).

### Adicionado
- **Pré-lançamento: imagem de produção de um projeto criado pelo
  `create-project`.** O Dockerfile de produção dos dois starters tem um
  estágio `packages` vazio, que o contexto de build nomeado do monorepo
  substitui: no monorepo os pacotes vêm da pasta `packages/`; num projeto
  criado a partir dos pacotes publicados, do Composer — o mesmo arquivo.
  O `docker-compose.prod.yml` publicado sai sem o `additional_contexts` do
  monorepo (`prepare-composer.php`). A simulação da instalação publicada, no
  CI, constrói as imagens (app e nginx) do projeto criado, dos dois starters,
  e passa as conferências de imagem limpa (`.github/images/check-livewire-app.sh`,
  novo, e `check-react-app.sh`).
- **Pré-lançamento: espelhos prontos para publicar.** `LICENSE` nos dois
  starters; `SECURITY.md` em cada pasta publicada, apontando para o
  monorepo; READMEs dos espelhos com o link do monorepo, das docs e a
  instalação pelo Packagist (links relativos trocados por absolutos, que
  valem no espelho); `.gitattributes` no starter Livewire.
- **Starter React — fase F11c: E2E, imagem de produção, combinações e
  publicação preparada.** E2E em Playwright (`starters/react/tests/e2e`):
  cadastro com verificação de e-mail e login com segundo fator pelo Mailpit,
  perfil (idioma, tema, foto), senha de transação, contas (convidar → aceitar
  criando o acesso → trocar de conta → transferir com senha de transação e
  código → remover), chave de API com a secreta uma vez, projetos e o `/admin`
  (login e ação auditada); pessoas fixas por `tests/e2e/fixtures.php`,
  limpeza pelo `/admin` independente do idioma e varredura final. Imagem de
  produção própria (`starters/react/docker`, app e nginx, no desenho da do
  Livewire), conferida por `.github/images/check-react-app.sh` num job novo
  do CI (**"Imagens de produção do starter React"**, não obrigatório; os 4
  checks obrigatórios não mudaram). As combinações de módulos do Livewire
  (sem uploads, só o `/admin`, só a base, sem o `/admin`) agora também no
  React, e a instalação publicada simulada também com
  `create-project twstec/starter-react` (`STARTER=react`). Publicação
  preparada (continua desligada): `starters/react →
  kelvindk9w/twstec-starter-react` no split.
- **Starter React — fase F11b: contas, membros, chaves de API, projetos e
  foto de perfil.** Seletor de conta em todo o painel; página da conta
  (renomear, membros com papéis pela regra do pacote, convites, transferir e
  excluir com senha de transação + código); criar conta de empresa; tela
  pública do convite (aceitar logado, entrar e voltar, criar o acesso já
  verificado, estados de erro sem dado da conta); chaves de API (criar com
  escopos e projetos, a secreta **uma vez** — só na resposta imediata, como
  `flash` do Inertia —, rotacionar com transição, revogar, vínculo com
  projetos); projetos (criar, renomear, arquivar/reativar, excluir); foto de
  perfil (enviar, validada pelo conteúdo, e tirar). Tudo sobre as Actions e os
  serviços dos pacotes, com a trilha gravada por eles; respostas Inertia dos
  contratos de convite e troca de conta; rotas com parâmetro enviadas ao front
  como modelo. `twstec/kit-uploads`: `AvatarService::remove()`.
- **Starter React (`starters/react`, `twstec/starter-react`) — fase F11a:
  base e autenticação.** React 19 + Inertia 3 + TypeScript + Tailwind 4 +
  shadcn/ui a partir do kit oficial React do Laravel, com a autenticação do
  `twstec/kit-auth` no lugar do Fortify: telas Inertia (login, cadastro,
  verificação de e-mail, esqueci/redefinir senha, segundo fator por código de
  e-mail), envios pelos controllers do pacote (com o `throttle:sensitive`
  deles) e as **implementações Inertia dos 11 contratos de resposta** — quem
  entra ou sai recebe carga completa (409 + `X-Inertia-Location`), o destino
  guardado passa pelo `SafeRedirect`. Painel mínimo com a arquitetura de
  informação do Livewire (menu lateral e avatar): painel inicial com os
  números da conta (`AccountOverviewQuery`), perfil (nome, idioma, tema,
  senha de login, senha de transação e o segundo fator como ação sensível,
  com o token emitido e consumido no servidor) e notificações. i18n pt-BR/en/es
  pelos arquivos de tradução do Laravel, enviados uma vez por idioma; tema
  claro/escuro/sistema com o padrão da conta; props compartilhadas em lista
  fechada, sem nenhuma credencial (teste que varre todas as telas); CSP estrita
  sem `unsafe-eval` (o servidor do Vite só entra em `APP_ENV=local`, com o
  `public/hot`); módulos opcionais detectados por `Kit::has()` e enviados ao
  front; o mesmo `/admin`. Docker de dev: serviços `react-*` na porta 8181, com
  banco (`tws_starter_react`), bancos do Redis e cookie de sessão próprios. CI:
  passos do React dentro dos dois jobs de teste (Pint, auditorias, tipos,
  lint/formatação, build, Pest no SQLite e no PostgreSQL), sem check novo. Ver
  [starters/react/README.md](starters/react/README.md) e
  [docs/instalacao.md](docs/instalacao.md#starter-react).
- **Módulos opcionais e o instalador `php artisan tws:install`.**
  `twstec/kit-foundation` e `twstec/kit-auth` vêm sempre; contas e API
  (`twstec/kit-accounts`), uploads (`twstec/kit-uploads`, que exige contas) e
  o painel `/admin` (`twstec/kit-admin`) passam a ser **opcionais**. O
  instalador (pacote novo `twstec/kit-installer`, em `require-dev` do
  starter) pergunta os módulos e a demonstração (Laravel Prompts) ou recebe
  `--with`/`--without`/`--no-demo`, e aplica: `demo:uninstall` antes de a
  demo sair, `composer remove`/`require`, `optimize:clear`, o `.env` (do
  `.env.example` se faltar), a `APP_KEY` e o pepper dedicado das chaves de
  API quando faltam (a `APP_KEY` que já existia vai para os peppers
  anteriores — nenhuma chave emitida deixa de autenticar) e `migrate`.
  Idempotente; recusa `APP_ENV=production` sem `--force`. Ver
  [docs/instalacao.md](docs/instalacao.md).
- **Detecção de módulos num ponto só:** `Twstec\Kit\Foundation\Kit::has()`
  e a diretiva `@kit('uploads') … @else … @endkit`. O starter pergunta antes de
  registrar rota, item de menu ou bloco de tela de um módulo opcional; sem o
  módulo, a rota não existe (404), o menu não a mostra e o painel inicial abre
  com os atalhos da conta. O model de usuário compõe o acesso ao `/admin` e a
  foto de perfil por nomes do próprio aplicativo
  (`app/Support/optional-modules.php`), que viram a peça do pacote ou uma
  peça neutra.
- **O `/admin` se adapta aos pacotes instalados:** sem contas, sem as telas de
  contas, chaves e projetos (e o que delas dependia nos dashboards, na trilha
  e na guarda de exclusão); sem uploads, sem a tela de uploads, o widget e o
  campo de foto. `twstec/kit-accounts` e `twstec/kit-uploads` saem do
  `require` do `twstec/kit-admin` (ficam em `suggest`).
- **CI das combinações:** o job do SQLite passa, com o instalador, pelas
  combinações sem uploads, só o `/admin`, só a base e sem o `/admin` (build do
  front e `pest` em cada uma), depois do "sem a demo" que já existia.
- **Preparação da publicação (desligada):** `composer.json` de cada pacote
  pronto para o Packagist (homepage, suporte, autores, `branch-alias`); o
  starter vira `twstec/starter-livewire` (projeto; `post-create-project-cmd`
  chama o instalador) para `composer create-project` e
  `laravel new --using=`; o workflow `split.yml` (tag `v2.*` → 7 repositórios
  só-leitura `kelvindk9w/twstec-*`, desligado até a variável
  `KIT_SPLIT_ENABLED`); e a **simulação da instalação publicada** no CI — os
  pacotes empacotados com `composer archive` e o projeto criado só a partir
  deles, com build e suíte.
- **A demonstração vive só no monorepo:** `twstec/kit-demo` não é publicada
  (sem repositório só-leitura, fora do split). O `composer.json` publicado do
  starter sai sem ela — quem cria um projeto recebe o starter limpo, com a
  página inicial do produto (os testes da demo pulam). No monorepo nada muda:
  ela segue em `require-dev` e o ambiente de desenvolvimento tem as landings.
- **Uploads da conta** (`twstec/kit-uploads`). Todo upload passa a pertencer
  a uma **conta** (`account_id`) e guarda quem enviou (`created_by`); a web e
  a API gravam do mesmo jeito (a conta atual e quem agiu). O isolamento é o
  mesmo dos projetos e das chaves: toda consulta sai filtrada pela conta atual
  e, sem conta, dá erro; a URL assinada só sai para upload da conta atual (ou
  em modo sistema declarado, como no `/admin`). A **foto de perfil é da
  pessoa**: vira um upload pessoal, sem conta, que aparece em todas as contas
  dela e é lido só pela foto de perfil — restrito ao upload que a própria
  pessoa aponta. Migração dos uploads antigos na `php artisan migrate` (ver
  "Quebra de compatibilidade — uploads da conta").
- **LGPD — excluir a pessoa apaga os arquivos dela:** a foto de perfil, as
  fotos pessoais que ela enviou e os uploads das contas que somem junto (a
  pessoal e as de que era a única dona) saem do banco e do **disco**; os
  uploads que ela criou em contas de outras pessoas ficam (são daquela conta).
  Excluir uma **conta** apaga os uploads dela. Os registros saem na transação
  da exclusão; os arquivos, por job na fila **depois do commit**, com nova
  tentativa; exclusão recusada ou desfeita não apaga nada. Trilha de auditoria
  (`upload.erased`, `upload.files_deleted`) só com contagens e motivo.
- **`uploads:prune-orphans`** (com `--dry-run`): apaga os órfãos antigos da
  migração, as fotos pessoais que não são a foto de ninguém e os arquivos nas
  pastas de upload sem registro no banco; agendado pelo próprio pacote
  (`UPLOADS_PRUNE_SCHEDULE`, vazio desliga com aviso no log). Novas variáveis
  `UPLOADS_PRUNE_*` e `UPLOADS_MIGRATION_CHUNK` (`.env.example`).
- **`/admin`:** a tela de Uploads mostra a conta de cada linha, quem enviou e
  o tipo (da conta, foto pessoal, órfão), com filtro por conta e por tipo; a
  Auditoria filtra pela conta em que a ação aconteceu.
- **Cache do papel por requisição** (`twstec/kit-accounts`): as perguntas de
  papel de uma tela consultam o banco uma vez por conta e pessoa; o cache some
  quando um vínculo muda, no fim de cada requisição e a cada job da fila.
- **Eventos de exclusão** no `twstec/kit-accounts` para quem guarda dado das
  contas fora dele: `PersonDeleting` (só leitura), `PersonDeleted` e
  `AccountDeleting` (dentro da transação da exclusão da conta).
- **Membros, convites e transferência de propriedade** (`twstec/kit-accounts`
  + telas no starter Livewire + "Contas" no `/admin`). Seletor de conta em todo
  o painel (conta atual, papel, troca só para conta de que a pessoa é membro);
  página da conta (`/account`) com membros em tabela ou cartões, convidar,
  reenviar e revogar convite, mudar papel e remover pela regra de quem mexe em
  quem (o dono em todos; o admin só em members; ninguém no dono), sair da
  conta, **transferir a propriedade** e **excluir a conta** (os dois com senha
  de transação + código por e-mail; o antigo dono vira admin, sempre um dono);
  criar conta de empresa. **Convite** por e-mail com token só em hash, uso
  único, validade, limites e intervalo configuráveis, sem enumeração; o aceite
  pede o mesmo e-mail e, para quem não tem conta, **cria a conta já
  verificada**. **Aviso de chave órfã** por e-mail ao dono e aos admins quando
  alguém sai, é removido ou tem o acesso excluído por qualquer caminho (as
  chaves continuam valendo; uma vez por conta, pela fila, depois do commit;
  sem segredo no e-mail). **Trilha no banco** de todo evento de conta (e `denied` nas
  recusas), na transação da mudança. A regra mora em Actions do pacote, com
  respostas HTTP em contratos (para o front React reaproveitar). No `/admin`,
  "Contas" só leitura: membros e papéis, projetos e chaves da conta. Novas
  variáveis `ACCOUNTS_INVITATION_*` e `ACCOUNTS_MAX_OWNED` (`.env.example`),
  componentes `<x-icon-button>`, `<x-view-toggle>` e `<x-account-switcher>`,
  e-mails "Convite para uma conta" e "Chave de API órfã" na galeria. Guia em
  `docs/tenancy.md`.
- **Trilha de auditoria por conta:** `audit_events.tenant_uuid` (migration do
  `twstec/kit-foundation`; o mesmo valor de `request_logs.tenant_uuid`), com
  índice por período; `AuditTrail::record()`/`denied()` aceitam a conta.
- **Contas com membros e isolamento automático** (`twstec/kit-accounts`, sem
  telas novas). Projetos e chaves de API passam a pertencer a uma **conta**
  (e guardam quem os criou); uma pessoa pode estar em várias contas, com um
  papel fixo em cada (`owner`, `admin`, `member` — exatamente um dono por
  conta, garantido no código e no banco), e toda pessoa tem a sua conta
  pessoal, com o mesmo uuid dela. Toda consulta de dado de conta sai filtrada
  sozinha pela **conta atual** (no painel, a selecionada na sessão — padrão a
  pessoal; na API, a da chave) e, **sem conta, dá erro em vez de devolver
  tudo**. O `/admin`, os comandos, os seeders e os jobs que varrem contas
  operam num **modo sistema** explícito, com motivo, e cada uso é conferido
  por uma trava de arquitetura; jobs enfileirados levam a conta de quem os
  enfileirou e a restauram no worker. Papéis com a matriz em `docs/tenancy.md`
  (também no Gate como `accounts.*`); o painel Livewire esconde e recusa (403)
  o que o papel não permite — o dono da conta pessoal, único caso da 1.x, faz
  tudo como antes. No `/admin`, projetos e chaves mostram a conta de cada
  linha (com filtro), o dono e quem criou. Guia em `docs/tenancy.md`.
- **Exclusão de pessoa com contas:** recusada enquanto ela for dona de conta
  com outros membros (no `/admin`, com o motivo na trilha; por qualquer outro
  caminho, exceção; por SQL, o gatilho do PostgreSQL); senão a conta pessoal
  sai com os dados, como na 1.x. Chaves de API de quem sai da conta continuam
  valendo (são da conta).

### Alterado
- **Starter React no dev em `http://127.0.0.1:8181`** (antes `localhost:8181`):
  cookie é por host, e em `localhost` o `XSRF-TOKEN` do React e o do Livewire
  (`localhost:8180`) eram o mesmo cookie. O nginx de dev leva `localhost:8181`
  para lá (308). Os serviços `react-*` passam a usar a imagem PHP do próprio
  starter (`starters/react/docker/php/Dockerfile`). Nada muda na produção.
- **Sem o pacote de contas, o aplicativo mantém as proteções da API** do
  `/api/health`: o `throttle:api` e o envelope de erro de `api/*`, que antes
  vinham só do `twstec/kit-accounts`, são ligados pelo `bootstrap/app.php`
  quando ele não está instalado. Com ele, nada muda.
- **Sem o `/admin`, o `/horizon` fecha** fora do ambiente local (o critério de
  acesso é o do painel). Com ele, nada muda.
- **Scripts do Composer:** `filament:upgrade`/`filament:assets` passam por
  `php artisan tws:filament-assets`, que só chama o Filament quando ele está
  instalado; o `filament/filament` sai do `require` do starter (vem pelo
  `twstec/kit-admin`). O `filament.css` só entra no build com o `/admin`.
- A migration de usuários do aplicativo só cria a chave estrangeira de
  `avatar_upload_id` quando a tabela `uploads` existe (instalação sem uploads).
- **A demonstração virou o pacote `twstec/kit-demo`** (`packages/demo`,
  namespace `Twstec\Kit\Demo`), instalado **só no desenvolvimento**: o
  starter o declara em `require-dev`. As landings (`/`, `/v2`), a vitrine
  `/ui`, o contato, o catálogo e as submissões do `/admin`, as contas demo, os
  seeders de dado fictício, as migrations da demo (mesmos nomes de arquivo:
  nenhuma migration pendente num banco existente), as views, as traduções, o
  JS, o CSS e as imagens das landings saíram do aplicativo — inclusive o que
  a separação anterior tinha deixado nele. O pacote se liga pela descoberta
  automática e pelos pontos de extensão; nenhum arquivo do produto o nomeia.
  A **imagem de produção não leva a demo** (`composer install --no-dev`; o
  código dela nem entra no contexto do build), e o CI confere isso na imagem.
  Sem a demo, `/` mostra a página inicial do produto e a suíte do starter
  passa com o `pest` de sempre (os testes do grupo `demo` pulam sozinhos).
  As landings continuam idênticas. Ver [docs/demo.md](docs/demo.md).
- **`php artisan demo:uninstall [--drop-tables]`** (no pacote da demo): tira
  do banco os gatilhos das contas demo — e, com `--drop-tables`, as tabelas e
  o registro das migrations da demo — antes do `composer remove --dev
  twstec/kit-demo`.
- **Mensagens de conta protegida neutras.** `admin.users.demo_protected`,
  `admin.command.demo_protected` e `auth.two_factor.demo_blocked` viraram
  `admin.users.account_protected`, `admin.command.account_protected` e
  `auth.two_factor.account_protected`, com texto que vale para qualquer conta
  protegida (e as notas do perfil do `/admin` deixaram de falar em "demo"). Os
  nomes antigos continuam existindo, com o mesmo texto, até a 3.0. Com a
  demonstração instalada, ela devolve às chaves o texto "de demo" de antes.
- A variante de dashboard padrão do produto é `overview,growth`. O `content`
  é da demonstração: com ela instalada e sem `DASHBOARD_ENABLED` declarado,
  ela o acrescenta ao fim da lista; declarado, vale a lista do operador (o
  `.env.example` continua listando os três).
- **Primeiro pacote: `twstec/kit-foundation`** (`packages/foundation`). A base
  de segurança e infraestrutura — filtro de ataques, limites, cabeçalhos,
  hosts e proxies, trilhas de requisição e de auditoria, e-mail, idioma,
  dinheiro, identificadores, configurações editáveis, guarda de segredos e de
  backup — saiu de `app/Core` para o pacote, que o starter instala por path
  repository. O comportamento é o mesmo: a pilha de segurança continua na
  frente de tudo, na mesma ordem (agora instalada pelo provider do pacote), e
  as migrations mantêm os nomes, então nenhum banco vê migration pendente.
  As classes passam a `Twstec\Kit\Foundation\<Módulo>\…`; os nomes antigos
  `App\Core\<Módulo>\…` continuam resolvendo até a 3.0 (tabela em
  `packages/foundation/README.md`). Mensagens de log e de erro que citam uma
  classe da base passam a citar o nome novo.
- **Segundo pacote: `twstec/kit-auth`** (`packages/auth`), sem telas: a regra
  de login, cadastro, verificação de e-mail, segundo fator, senha de
  transação, ação sensível e status da conta. Mesmo comportamento, sem
  migration pendente. O pacote liga sozinho o status da conta no grupo `web`,
  os aliases `verified` e `sensitive.token` e o `throttle:sensitive` dos
  envios (opt-out: `AUTH_WEB_PROTECTIONS=false`, com aviso no log). Telas,
  rotas e `user:make-admin` ficam no starter.
- **Terceiro pacote: `twstec/kit-accounts`** (`packages/accounts`), sem
  telas: projetos, chaves de API e a API v1 (o dono continua sendo a pessoa).
  Mesmo comportamento, mesmas rotas e sem migration pendente. O pacote liga
  sozinho a autenticação por chave, os escopos, o limite por chave e o
  envelope de erro de `api/*` (opt-out: `API_KEYS_API_PROTECTIONS=false`, com
  aviso no log); as rotas `/api/v1` podem ser registradas pelo aplicativo
  (`API_KEYS_API_ROUTES=false`). Classes em `Twstec\Kit\Accounts\…`; os nomes
  `App\Core\Tenancy\…` e `App\Core\ApiKeys\…` resolvem até a 3.0. Telas,
  resources do painel e o agendamento da inatividade ficam no starter.
- **Quarto pacote: `twstec/kit-uploads`** (`packages/uploads`), sem telas:
  upload validado pelo conteúdo, re-encode de imagem, URL assinada, foto de
  perfil (`HasAvatar`) e o `POST /api/v1/uploads`, agora registrado pelo
  pacote no mesmo grupo da API v1 (`UPLOADS_API_ROUTES=false` deixa o
  aplicativo registrá-lo). Mesmo comportamento e sem migration pendente. O
  pacote liga sozinho a entrega assinada no disco local de uploads, mesmo que
  o disco não a declare (opt-out: `UPLOADS_PROTECTIONS=false`, com aviso no
  log). Classes em `Twstec\Kit\Uploads\…`; os nomes `App\Core\Uploads\…`
  resolvem até a 3.0. Telas, rota web do avatar e o painel ficam no starter,
  e `app/Core` deixou de existir.
- **Quinto pacote: `twstec/kit-admin`** (`packages/admin`), o super admin
  `/admin` como **plugin do Filament** (`AdminPlugin`), que o aplicativo
  registra no próprio painel: resources, páginas, login com segundo fator por
  e-mail, dashboards, a trilha de auditoria das ações, as guardas e o comando
  `user:make-admin`. Mesmo comportamento, mesmas rotas e o mesmo visual, sem
  migration. Marca, cores, tema e o seletor de idioma ficam no
  `AdminPanelProvider` do starter. As proteções do painel passam a ser ligadas
  pelo pacote, em qualquer ordem do `PanelProvider`: a allowlist de IP em
  primeiro lugar e persistente nas ações Livewire (e no download de exports),
  o acesso só de `is_admin` com conta ativa conferido também pelo pacote, e as
  ações em transação (opt-out: `ADMIN_PROTECTIONS=false`, com aviso no log).
  O tema compilado pelo aplicativo importa as fontes do pacote
  (`vendor/twstec/kit-admin/resources/css/sources.css`). Classes em
  `Twstec\Kit\Admin\…`; os nomes `App\Filament\…` e
  `App\Console\Commands\MakeAdminUser` resolvem até a 3.0, e o estado de
  tabela e de visualização guardado na sessão migra sozinho. As traduções
  `admin.*` do produto vêm do pacote; as da demonstração continuam no
  `lang/admin.php` do aplicativo.
- O canal de log `request_log` (a segunda camada das trilhas de requisição,
  segurança e auditoria) passa a vir do `twstec/kit-foundation`, com a mesma
  definição; uma aplicação sem o canal não perde mais essas linhas para o log
  de emergência, e um `request_log` próprio no `config/logging.php` vence.
- O model de usuário é do aplicativo: **`App\Models\User`** (era
  `App\Core\Auth\Models\User`). Os nomes antigos `App\Core\Auth\…`
  resolvem até a 3.0, inclusive em job na fila durante o deploy. Quem
  implementa `AccountProtection`, `VerificationChannelDriver` ou
  `RegisterResponse` passa a tipar o usuário como `AuthUser`.
- As guardas de produção (recusa sem `APP_KEY`, HTTPS, `APP_DEBUG` desligado,
  avisos dos opt-outs) e os limitadores `api` e `sensitive` passam a ser
  aplicados pelo pacote sozinho, em qualquer aplicação que o instale; saíram
  do `AppServiceProvider`. Desligar é opt-out explícito
  (`SECURITY_PRODUCTION_GUARDS=false`, `RATE_LIMIT_DEFINE_LIMITERS=false`), com
  aviso no log em produção. Nas traduções, o `lang/` do aplicativo vence o do
  pacote na mesma chave.
- **O repositório virou monorepo.** O aplicativo foi para `starters/livewire/`
  (histórico preservado); a raiz guarda o CI, a documentação (`docs/`), o
  `docker-compose.yml` de desenvolvimento e `packages/`, que recebe os pacotes
  de backend nas próximas versões. Comandos de `composer`, `npm`, `artisan`,
  Pest e Playwright rodam dentro de `starters/livewire`; `docker compose`
  funciona da raiz ou de dentro do starter.
- O código de demonstração (landings, vitrine `/ui`, contato, catálogo,
  proteção das contas demo, seeders de dado fictício) fica isolado em
  `app/Demo` e `demo/`, ligado por um único provider; o produto funciona sem
  ele.
- A regra de negócio de projetos, do dashboard do cliente e da autenticação
  saiu das telas e dos controllers para serviços e Actions; as respostas de
  autenticação passam por contratos substituíveis.

### Corrigido
- **Instabilidade do teste de idempotência do seeder de usuários da demo**
  (rodada paralela): o Faker sorteia pelo `mt_rand` global do processo, e todo
  gerador do Faker, ao ser destruído, chama `mt_srand()` sem semente; um
  gerador descartado antes (preso num ciclo de referências) podia ser coletado
  no meio da montagem da lista e o resto dela saía aleatório. A lista agora é
  montada com a coleta de ciclos desligada (`UserSeeder::linhas()`), com teste
  de regressão que provoca a coleta em cada ponto da montagem.
- **Suíte local vazando para o ambiente de dev:** o `phpunit.xml` (e o
  `phpunit.pgsql.xml`) declarava fila `sync`, e-mail `array`, sessão `array` e
  o custo mínimo do Argon2id sem `force`, e o container de dev injeta os
  valores de produção — os testes mandavam jobs para a fila do Redis de dev
  (processados pelo worker de dev contra o banco de dev), e-mails para o
  Mailpit e rodavam o hash com 64 MB. Agora forçados, como o CI já rodava.

### Segurança
- **Recusas das telas de chaves de API e de projetos na trilha.** Nos dois
  starters, toda recusa grava `denied` em `audit_events` com a ação tentada
  (`api_key.created`, `api_key.rotated`, `api_key.revoked`,
  `api_key.projects_synced`, `project.created`, `project.updated`,
  `project.deleted`), quem, a conta e o alvo: o papel que não permite (o
  mesmo 403 e a mesma mensagem), a chave ou o projeto que não está na conta
  atual (o mesmo 404 — idêntico para "de outra conta" e "não existe", sem
  revelar existência) e o projeto de fora da conta no vínculo da chave (o
  mesmo erro de validação). Novo `Account\Support\AccountResourceGuard` no
  `twstec/kit-accounts`. Na API v1, a chave autenticada que tenta além do que
  pode — escopo que não tem, ou operação de conta com chave vinculada a
  projetos — também grava `denied` (contexto `api`, `api_key.scope_denied` e
  `api_key.account_key_required`); os 401 e 404 da API continuam só no
  `request_logs`. Corrigido junto: criar chave pelo Livewire com projeto de
  outra conta forjado respondia 500 no último passo; agora é o erro de
  validação no primeiro.
- **A pré-checagem de papel das ações de conta também fica na trilha.**
  Quem não é dono e forjava transferir a propriedade ou excluir a conta
  recebia 403 da pré-checagem da tela **sem** linha em `audit_events`; o
  mesmo valia para abrir renomear, remover membro e revogar convite no
  Livewire. Agora a pré-checagem é o `authorize()` da própria Action
  (`TransferOwnership`, `DeleteAccount`, `RenameAccount`, `RemoveMember`,
  `RevokeInvitation`, no `twstec/kit-accounts`): o mesmo 403, a mesma
  mensagem, e a recusa gravada como `denied`, nos dois starters.
- **Foto de perfil no `/admin` só de upload da própria conta.** O campo de
  foto do cadastro de usuário e do perfil do admin vinculava como avatar
  qualquer upload cujo uuid chegasse no formulário, sem conferir o dono — o
  valor vem do navegador. Agora só vira foto o upload da conta editada
  (enviado por ela na web, pela chave de API dela, ou a foto atual) ou o que
  acabou de ser enviado naquele formulário; qualquer outro é recusado antes de
  gravar (nada muda, nem os outros campos) e a recusa fica na trilha de
  auditoria como `denied` (`user.updated` / `user.created`). Só
  administradores chegavam a esse campo.
- **Pepper vazio nunca é pepper.** O `.env.example` trazia
  `API_KEYS_HASH_PEPPER=` sem valor, e variável vazia não aciona o fallback do
  `env()`: o hash das chaves de API rodava com pepper de 0 caracteres, em
  silêncio — verificável por quem tivesse só uma cópia do banco. Agora vazio
  ou só espaços conta como ausente e o pepper passa a ser a `APP_KEY` (no
  config do pacote, na cópia do starter e no `ApiKeyHasher`, que cobre uma
  cópia antiga publicada no aplicativo). A linha do `.env.example` virou
  comentário, com a instrução de gerar um valor dedicado.
- **Peppers anteriores** (`API_KEYS_PREVIOUS_HASH_PEPPERS`, no estilo do
  `APP_PREVIOUS_KEYS`): trocar o pepper — ou sair do fallback da `APP_KEY` —
  não invalida mais as chaves emitidas. A chave que confere com um anterior
  autentica e tem o hash regravado com o atual no primeiro uso (evento
  `api_keys.secret_hash.migrated` no `request_log`, sem segredo). Todos os
  peppers aceitos são comparados em tempo constante; a recusa continua 401 no
  envelope, contando para o limite de falhas.
- **Legado do pepper vazio** (`API_KEYS_ACCEPT_EMPTY_PEPPER_LEGACY`, desligada
  por padrão): aceita e migra as chaves emitidas com o pepper vazio.
- Em `APP_ENV=production` o boot avisa no log, a cada boot, quando não há
  pepper dedicado (ausente ou vazio) e quando a flag do legado está ligada.
  Aviso, não recusa.

**Upgrade — quem tem `API_KEYS_HASH_PEPPER=` vazio e já emitiu chaves** (todo
servidor montado a partir do `.env.example` antigo): sem ação, essas chaves
passam a receber 401 depois do deploy. Antes do deploy:
1. defina um `API_KEYS_HASH_PEPPER` dedicado (`php artisan tinker` →
   `Str::random(64)`) e ligue `API_KEYS_ACCEPT_EMPTY_PEPPER_LEGACY=true`;
2. cada chave migra para o pepper novo no primeiro uso (acompanhe
   `api_keys.secret_hash.migrated` no `request_log`);
3. desligue a flag quando todas as chaves em uso tiverem migrado ou sido
   rotacionadas — com a inatividade ligada (padrão), bastam
   `API_KEYS_INACTIVITY_MONTHS` meses. Chave que não migrou na janela precisa
   ser rotacionada.

Quem não tinha pepper dedicado (variável ausente) não é afetado; para sair do
fallback da `APP_KEY`, defina o pepper e declare a `APP_KEY` atual em
`API_KEYS_PREVIOUS_HASH_PEPPERS`. Detalhes em `docs/api.md`.

### Quebra de compatibilidade — contas com membros

O que muda para quem tem código próprio sobre o `twstec/kit-accounts` (a API
HTTP v1 não muda: mesmas rotas, respostas, códigos e envelopes):

- **Dado de conta sem conta atual é exceção.** Consulta a `Project`/`ApiKey`
  num comando, job, seeder, tinker ou teste sem conta atual lança
  `MissingAccountContextException`. Declare: `Accounts::asSystem('motivo', fn
  () => …)` (todas as contas) ou `Accounts::actingAs($conta, fn () => …)`.
  Jobs enfileirados de uma requisição levam a conta sozinhos.
- **`projects.user_id` e `api_keys.user_id` saíram**: `account_id` +
  `created_by`. `Project::owner()` e `ApiKey::owner()` saíram: use
  `account()`, `creator()` e `account->owner`.
- **`tenant()` devolve a conta** (`Account`), não mais a pessoa; a pessoa por
  trás da chave é `app(TenantContext::class)->user()` (e o `$request->user()`
  da rota). `TenantContext::resolve()` recebe conta, chave e pessoa.
- Serviços: `ProjectService::list()`/`find($uuid)` (eram
  `listForUser`/`findForUser`), `ApiKeyService::resolveProjectIds($uuids)`
  (sem a pessoa), `AccountOverviewQuery::forCurrentAccount()` (era
  `for($pessoa)`) — todos na conta atual. `ProjectService::create()` e
  `ApiKeyService::create()` mantêm a assinatura; o primeiro parâmetro é quem
  cria, e a conta é a atual.
- Excluir pessoa **dona de conta com outros membros** passa a ser recusado.
- Testes com `Livewire::test` de telas do `/admin` precisam declarar o modo
  sistema (o painel o declara pelo middleware; o `Livewire::test` não passa
  por ele) — o starter faz isso em `tests/Pest.php`.

**Atualizando uma base da 1.x** (a migração dos dados roda no
`php artisan migrate`):
1. faça backup do banco;
2. `php artisan migrate` — cria a conta pessoal de cada pessoa com o mesmo id
   e uuid, passa projetos e chaves para ela (`created_by` = o dono), confere
   que nada ficou sem conta e instala os gatilhos. Em lotes
   (`ACCOUNTS_MIGRATION_CHUNK`, padrão 1000); no PostgreSQL, numa transação.
   `migrate:rollback` desfaz (devolve `user_id`). A trilha de auditoria e a de
   requisições não são reescritas;
3. reinicie filas e agendador (`docker compose restart queue scheduler`);
4. troque o código próprio conforme a lista acima (a trava de arquitetura do
   starter aponta consulta que pula o escopo).

### Quebra de compatibilidade — uploads da conta

- **`uploads.user_id` e `uploads.tenant_uuid` saíram**: `account_id` (a
  conta; nulo só na foto pessoal e no órfão da migração), `created_by` (quem
  enviou), `personal` e `orphaned_at`. `Upload::owner()` saiu: use
  `account()`, `creator()`.
- **Upload é dado de conta**: `Upload::query()` sem conta atual lança
  `MissingAccountContextException`; `SecureUploadService::handle()` exige conta
  atual (grava nela). A foto de perfil vai por
  `SecureUploadService::handlePersonal()` / `Avatar\AvatarService`.
- **`Upload::url()` só assina upload da conta atual** (ou em modo sistema):
  fora disso, `UploadOutsideAccountException`. A foto de perfil se lê por
  `avatarUpload()` / `avatarUrl()` (a relação `avatar()` passa pelo escopo da
  conta e não enxerga a foto pessoal).
- A migration `2026_09_28_000001_move_uploads_to_accounts` (do pacote) passa os
  uploads antigos: foto de perfil em uso → pessoal; `user_id` → conta pessoal
  de quem enviou; `tenant_uuid` → a conta com aquele uuid; sem destino →
  órfão (`orphaned_at`), nunca uma conta qualquer. Em lotes
  (`UPLOADS_MIGRATION_CHUNK`), idempotente e reversível (o órfão volta sem
  dono). Os arquivos não mudam de lugar: as URLs assinadas antigas continuam
  abrindo o mesmo conteúdo.

### Atualizando um clone existente
1. `docker compose down` **antes** do `git pull`.
2. Depois do pull, mover para `starters/livewire/` o que não é versionado:
   `.env`, `vendor/`, `node_modules/`, `public/build/` e o conteúdo de
   `storage/`.
3. `docker compose up -d`. O nome do projeto Compose é fixo
   (`tws-laravel-starter-kit`), então o banco de desenvolvimento é o mesmo; em
   clone com outro nome de pasta, defina `COMPOSE_PROJECT_NAME` com o nome
   antigo para reaproveitar os volumes.
4. `php artisan migrate` (há uma migration nova da demo) e `npm run build`.
5. Instalar as dependências PHP de novo, montando a **raiz** do repositório no
   container do Composer (o starter instala `packages/foundation` por path
   repository — ver o README) e subir com `docker compose up -d --build` (os
   containers PHP passam a montar `packages/` em `/var/packages`). Quem tinha
   código próprio usando `App\Core\<Módulo da base>\…` continua funcionando
   pelos apelidos; troque os `use` antes da 3.0. O mesmo vale para
   `App\Core\Auth\…` (agora `Twstec\Kit\Auth\…`, e `App\Models\User` para o
   model); um `AUTH_MODEL` antigo no `.env` também continua valendo.
   O mesmo para `App\Filament\…` (agora `Twstec\Kit\Admin\…`): um resource
   próprio que estende `BaseResource` ou uma config de dashboards publicada
   com os nomes antigos continuam funcionando.
6. Se o `.env` de desenvolvimento tem `API_KEYS_HASH_PEPPER=` vazio (vindo do
   `.env.example` antigo), as chaves de API já criadas no banco de dev foram
   gravadas com pepper vazio: acrescente `API_KEYS_ACCEPT_EMPTY_PEPPER_LEGACY=true`
   para que continuem autenticando (e migrem no primeiro uso), ou recrie-as.
7. O build do front em container precisa enxergar `packages/` (o tema do
   `/admin` importa as fontes do pacote): acrescente
   `-v $(pwd)/../../packages:/packages` ao `docker run … npm run build`.
8. Contas com membros: `php artisan migrate` migra os dados (ver "Quebra de
   compatibilidade — contas com membros" acima) e `docker compose restart
   queue scheduler`.
9. Uploads da conta: `php artisan migrate` passa os uploads antigos para as
   contas (ver "Quebra de compatibilidade — uploads da conta" acima) e
   `docker compose restart queue scheduler` (o worker precisa do job novo de
   remoção de arquivos; o agendador, da limpeza `uploads:prune-orphans`).
10. Demonstração como pacote: instalar as dependências PHP de novo (o
   `composer.json` passa a exigir `twstec/kit-demo` em `require-dev`, por path
   repository), `npm run build` (as entradas e as imagens das landings vêm do
   pacote) e `php artisan config:clear`. Nenhuma migration nova: as da demo
   mantêm os nomes. Código próprio que usava `App\Demo\…` passa a
   `Twstec\Kit\Demo\…`; quem tinha tirado o `DemoServiceProvider` de
   `bootstrap/providers.php` para desligar a demo agora tira o pacote
   (`php artisan demo:uninstall --drop-tables` e
   `composer remove --dev twstec/kit-demo`).
11. Módulos opcionais: instalar as dependências PHP de novo (o
   `composer.json` do starter passa a exigir `twstec/kit-installer` em
   `require-dev` e deixa de declarar o `filament/filament`, que vem pelo
   admin) e `php artisan config:clear`. Nada muda num clone completo; para
   tirar módulos, `php artisan tws:install` (ver
   [docs/instalacao.md](docs/instalacao.md)). Código próprio que usa telas ou
   classes de `accounts`, `uploads` ou `admin` deve perguntar
   `Kit::has('<módulo>')` antes, se você pretende tirar o módulo.
12. Starter React no dev: no `starters/react/.env`, troque `APP_URL` (e
   `PLATFORM_OFFICIAL_URL`) de `http://localhost:8181` para
   `http://127.0.0.1:8181` e recrie os serviços `react-*`
   (`docker compose up -d --build --force-recreate --no-deps react-app
   react-queue react-scheduler react-nginx`). Para o E2E do React, crie as
   pessoas fixas: `docker compose exec -T react-app php artisan tinker
   --execute="require 'tests/e2e/fixtures.php';"`.

## [1.1.1] — 2026-09-24

Correção de segurança da linha 1.x (a mesma correção está na 2.0.0-beta.1
acima, para a 2.0).

### Segurança
- Pepper vazio das chaves de API deixa de valer como pepper: vazio conta como
  ausente e cai na `APP_KEY`; peppers anteriores e o legado do pepper vazio
  (`API_KEYS_ACCEPT_EMPTY_PEPPER_LEGACY`, desligado por padrão) continuam
  autenticando e migram no primeiro uso. Passo a passo de upgrade no
  CHANGELOG da tag `v1.1.1` e em `docs/api.md`.

## [1.1.0] — 2026-09-24

### Adicionado
- `/admin` → Usuários: ação de suporte **Marcar e-mail como verificado**
  (listagem, cards e detalhe; só para conta não verificada, nunca para conta
  demo, com confirmação e registrada na trilha de auditoria) e filtro
  **E-mail verificado: sim/não**.
- **Trilha de auditoria de ações no banco** (`audit_events`, append-only):
  toda escrita do `/admin` — usuários (criar, editar, excluir, bloquear,
  desbloquear, marcar e-mail verificado), 2FA do próprio admin, chaves de
  API, produtos, configurações (chave e de/para) e perfil — e o
  `user:make-admin` (contexto `console`) gravam quem agiu, o registro
  afetado, o antes/depois redigido (nunca senha, hash, token ou código;
  e-mail e nome mascarados), IP, User-Agent e o `correlation_id` da linha
  de `request_logs`. Tentativas recusadas pelas guardas ficam como
  `denied`. A captura é central (`AdminAudit` + `AuditTrail`): resource
  novo já nasce auditado, e um teste de arquitetura reprova escrita que
  escapa da trilha. Falha fechada: o painel roda Actions e Criar/Salvar em
  transação, e sem a linha da trilha a mudança é desfeita.
- Tela **Auditoria** no `/admin` (somente leitura, pt-BR/en/es): filtros por
  ação, resultado, origem, quem agiu, registro afetado e período; detalhe
  com o resumo mascarado e link para a requisição.
- Retenção da trilha por `AUDIT_RETENTION_DAYS` (padrão 365; 0 = não poda),
  com poda diária `audit:prune` — a única remoção aceita; no PostgreSQL um
  gatilho recusa UPDATE/TRUNCATE e DELETE fora da poda.
- Segunda camada no arquivo: linha `audit.event` (depois do commit) e
  `audit.persist_failed`, no lugar da antiga `admin.action`.

### Alterado
- Dependências: Filament 5.8.4, Laravel 13.33.0, Horizon 5.50.0,
  Livewire 4.4.6 (e brick/math 1.0.0, transitiva).
- O E2E da verificação de e-mail apaga a conta e as mensagens que cria, como
  o de duas etapas (limpeza comum em `tests/e2e/support/cleanup.js`).

## [1.0.0] — 2026-09-24

Primeira versão estável. Base Laravel 13 com Livewire 4 (painel do cliente)
e Filament 5 (super admin), testada contra PostgreSQL 18.

### Autenticação e contas
- Cadastro, login, recuperação de senha e verificação de e-mail obrigatória
  (desligável por `.env`).
- Verificação em duas etapas opcional no login, por código enviado por
  e-mail, no painel do cliente e no `/admin`.
- Senha de transação e confirmação por código para ações sensíveis.
- Política de senha configurável por `.env` (padrão: 6 caracteres).
- Conta bloqueada, pendente ou não verificada perde o acesso na próxima
  requisição.

### API
- API v1 com par de chaves (pública + secreta com hash e pepper), escopos,
  vínculo opcional a projetos, rotação com período de graça e expiração por
  inatividade.
- Limite de requisições por chave, limite de falha de autenticação por
  chave e por IP, e envelope de erro padronizado sem vazamento de detalhes.

### Segurança
- Hosts e proxies confiáveis explícitos; `Host` forjado recebe 400.
- Limite de requisições na borda para todo o tráfego.
- Filtro de ataques em modo observar (padrão) ou bloquear, com inspeção de
  query, corpo, cabeçalhos relevantes e caminho.
- Uploads validados pelo conteúdo, reprocessados e servidos por URL
  assinada.
- Cabeçalhos de segurança e CSP por rota; versões de PHP e nginx ocultas.
- Contas demo protegidas no painel, no model e no banco.

### Trilha de auditoria e LGPD
- Registro de cada requisição com identificador de correlação gerado pelo
  servidor, sem gravar o caminho real (tokens na URL não vazam).
- Redação de CPF, CNPJ, e-mail e número de cartão (validado por Luhn) em
  logs e submissões.
- Contenção da gravação em varreduras anônimas.

### Operação
- Imagem de produção enxuta e sem arquivos sensíveis, construída no CI.
- Produção recusa subir sem `APP_KEY`, recusa backup sem criptografia e
  recusa mailer que não entrega (`log`/`array`).
- Payloads de e-mail criptografados na fila; retenção de jobs falhos.
- Backup criptografado do banco para armazenamento compatível com S3.

### Interface
- Painel do cliente com o layout do site, menu lateral e avatar.
- Três dashboards de admin nomeados, alternador tabela/cartões e base de
  componentização para telas novas.
- Template único de e-mail, pré-visualização em desenvolvimento, i18n
  pt-BR/en/es e tema claro/escuro.

### Qualidade
- Suíte Pest rodando em PostgreSQL e em SQLite no CI, testes de ponta a
  ponta com Playwright, build das imagens de produção obrigatório para
  promover código.

[Não publicado]: https://github.com/kelvindk9w/tws-laravel-starter-kit/compare/v2.0.0-beta.6...desenvolvimento
[2.0.0-beta.7]: https://github.com/kelvindk9w/tws-laravel-starter-kit/releases/tag/v2.0.0-beta.7
[2.0.0-beta.6]: https://github.com/kelvindk9w/tws-laravel-starter-kit/releases/tag/v2.0.0-beta.6
[2.0.0-beta.5]: https://github.com/kelvindk9w/tws-laravel-starter-kit/releases/tag/v2.0.0-beta.5
[2.0.0-beta.4]: https://github.com/kelvindk9w/tws-laravel-starter-kit/releases/tag/v2.0.0-beta.4
[2.0.0-beta.3]: https://github.com/kelvindk9w/tws-laravel-starter-kit/releases/tag/v2.0.0-beta.3
[2.0.0-beta.2]: https://github.com/kelvindk9w/tws-laravel-starter-kit/releases/tag/v2.0.0-beta.2
[2.0.0-beta.1]: https://github.com/kelvindk9w/tws-laravel-starter-kit/releases/tag/v2.0.0-beta.1
[1.1.1]: https://github.com/kelvindk9w/tws-laravel-starter-kit/releases/tag/v1.1.1
[1.1.0]: https://github.com/kelvindk9w/tws-laravel-starter-kit/releases/tag/v1.1.0
[1.0.0]: https://github.com/kelvindk9w/tws-laravel-starter-kit/releases/tag/v1.0.0
