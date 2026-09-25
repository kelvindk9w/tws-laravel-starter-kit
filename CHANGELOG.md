# Changelog

Todas as mudanças relevantes deste kit. O formato segue
[Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e a numeração
segue [Semantic Versioning](https://semver.org/lang/pt-BR/).

## [Não publicado]

### Alterado
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
