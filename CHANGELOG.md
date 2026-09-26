# Changelog

Todas as mudanças relevantes deste kit. O formato segue
[Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e a numeração
segue [Semantic Versioning](https://semver.org/lang/pt-BR/).

## [Não publicado]

### Adicionado
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
6. Se o `.env` de desenvolvimento tem `API_KEYS_HASH_PEPPER=` vazio (vindo do
   `.env.example` antigo), as chaves de API já criadas no banco de dev foram
   gravadas com pepper vazio: acrescente `API_KEYS_ACCEPT_EMPTY_PEPPER_LEGACY=true`
   para que continuem autenticando (e migrem no primeiro uso), ou recrie-as.

## [1.1.1] — 2026-09-24

Correção de segurança da linha 1.x (a mesma correção está em "Não publicado"
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

[1.1.1]: https://github.com/kelvindk9w/tws-laravel-starter-kit/releases/tag/v1.1.1
[1.1.0]: https://github.com/kelvindk9w/tws-laravel-starter-kit/releases/tag/v1.1.0
[1.0.0]: https://github.com/kelvindk9w/tws-laravel-starter-kit/releases/tag/v1.0.0
