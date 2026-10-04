# Changelog

Todas as mudanças relevantes deste kit. O formato segue
[Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e a numeração
segue [Semantic Versioning](https://semver.org/lang/pt-BR/).

## [1.1.2] — 2026-10-04

Correção de segurança da linha 1.x (achada na 2.0.0-beta.15; o defeito existe
desde a 1.0.0).

### Segurança
- **Chave de API criada pela tela com a restrição de projetos perdida.** As
  caixas de seleção da tela de chaves de API (`<x-checkbox>` com `wire:model`
  de lista) não sincronizavam no navegador: o componente punha o `wire:model`
  e o `value` na `<label>`, e o `<input>` saía sempre com `value="1"`. Os
  projetos marcados na criação não chegavam ao servidor, e a chave nascia
  **valendo para a conta toda**, mais ampla do que a pessoa pediu. A edição
  dos projetos de uma chave (o modal "Projetos") também não gravava o que
  fosse marcado ou desmarcado: salvava de novo o vínculo que já existia. Os escopos
  granulares marcados também não chegavam, mas aí a criação era recusada
  ("formato recurso:acao"), sem gerar chave. A API v1 não tinha o defeito.
- **Sem escalada de privilégio pela API v1.** Uma chave de conta com o escopo
  `api-keys:create` conseguia criar chave mais ampla que ela mesma — com
  `scopes` omitido, a chave nova saía `*:*` —, uma com `api-keys:rotate`
  rotacionava uma chave mais ampla (e recebia a secreta nova dela), e uma com
  `api-keys:assign` tirava a restrição de projetos de uma chave mais ampla. A
  criação e a rotação já exigiam a ação sensível (senha de transação e código
  por e-mail), então não havia escalada sem a pessoa; ainda assim, uma
  credencial restrita gerava uma mais ampla. Agora a regra mora no
  `ApiKeyService`: pela API, a chave criada, rotacionada ou editada tem de
  caber na chave autenticada — escopos (com curinga: `orders:*` cobre
  `orders:create`; `*:*` só quem tem `*:*`) e, se ela for restrita, projetos.
  **`scopes` omitido herda exatamente os escopos da chave autenticada**,
  nunca `*:*`. A recusa é `403` com código estável no envelope
  (`api_key_scope_exceeded` ou `api_key_projects_exceeded`, os mesmos da
  2.x). Pelo painel, nada muda. Ver `docs/api.md`, "Sem escalada de
  privilégio pela API".

### Corrigido
- **`<x-checkbox>`:** os atributos que carregam o estado (`wire:model`,
  `value`, `data-*`, `aria-*`) vão para o `input`, não mais para a `label`
  (que fica só com a classe); o valor padrão continua `"1"` (caixa única de
  formulário, como o "Lembrar de mim" do login). Uma lista de caixas com
  `wire:model` passa a sincronizar no navegador, em qualquer tela.
- Envelope de erro da API: um 4xx pode trazer um `code` mais específico que o
  do status, quando a exceção o declara (`ProvidesApiErrorCode`).
- `SECURITY.md`: a tabela de versões suportadas inclui a linha 1.x.

### Testes
- `tests/Feature/UiCheckboxTest.php` (o componente e a tela de chaves
  renderizada: cada projeto e cada escopo com o próprio valor e o
  `wire:model` no input), `tests/Feature/ApiKeys/ApiKeyPrivilegeTest.php`
  (pela requisição) e o E2E `tests/e2e/api-key-scopes.spec.js`, que cria
  chaves marcando escopos e projetos no navegador e confere, pela API v1 com a
  própria chave, que ela pode exatamente o que foi marcado.

### Atualizando da 1.1.1
- **Troque o `resources/views/components/checkbox.blade.php`** do seu projeto
  pelo da versão nova (ou aplique a mesma regra: os atributos, menos a classe,
  no `input`). Se o seu projeto usa o `<x-checkbox>` em outras telas com
  `wire:model` de lista, elas também passam a funcionar.
- **Confira as chaves criadas pela tela que deviam estar restritas a
  projetos:** as que estão "para a conta toda" sem a pessoa ter escolhido isso
  nasceram sem a restrição. Restrinja-as pela tela (o modal "Projetos" já
  funciona) ou rotacione/revogue. As candidatas são as chaves ativas, sem
  restrição, de quem já tinha projeto quando a chave foi criada (quem não
  tinha projeto não podia marcar nenhum). Para listá-las:

  ```bash
  php artisan tinker --execute='
  App\Core\ApiKeys\Models\ApiKey::query()
      ->where("restricted_to_projects", false)
      ->where("status", "active")
      ->whereExists(fn ($q) => $q->selectRaw("1")->from("projects")
          ->whereColumn("projects.user_id", "api_keys.user_id")
          ->whereColumn("projects.created_at", "<=", "api_keys.created_at"))
      ->with("owner:id,email")
      ->get()
      ->each(fn ($k) => print($k->uuid." | ".$k->name." | ".$k->owner?->email." | ".$k->created_at.PHP_EOL));'
  ```

  A lista inclui as chaves que são da conta toda de propósito e as criadas
  pela API (que não tinham o defeito): confira com o dono de cada uma. Chave
  rotacionada herda a falta de restrição da antiga.
- **Clientes da API v1 que criam, rotacionam ou vinculam chaves** (mudança de
  contrato):
  - `POST /api/v1/api-keys` **sem `scopes`** passa a herdar os escopos da
    chave que fez a chamada (antes: `*:*`). Quem chama com uma chave `*:*`
    não vê diferença; com uma chave restrita, a chave nova sai com os mesmos
    escopos dela. Mande `scopes` para escolher menos.
  - Pedir escopo que a chave autenticada não tem, rotacionar ou editar os
    projetos de chave mais ampla que ela responde `403` com
    `api_key_scope_exceeded` (ou `api_key_projects_exceeded`). Trate o
    `code`, não a mensagem.
  - Para gerar uma chave mais ampla, use o painel ou uma chave `*:*`.
- Se você customizou os arquivos de idioma `lang/*/api_keys.php`, traga as
  chaves novas `scopes.exceeded` e `projects.exceeded`.

## [1.1.1] — 2026-09-24

Correção de segurança da linha 1.x.

### Segurança
- **Pepper vazio nunca é pepper.** O `.env.example` trazia
  `API_KEYS_HASH_PEPPER=` sem valor, e variável vazia não aciona o fallback do
  `env()`: o hash das chaves de API rodava com pepper de 0 caracteres, em
  silêncio — verificável por quem tivesse só uma cópia do banco. Agora vazio
  ou só espaços conta como ausente e o pepper passa a ser a `APP_KEY` (no
  `config/api_keys.php` e no `ApiKeyHasher`, que cobre um `config/api_keys.php`
  antigo que tenha ficado na instalação). Sem pepper dedicado e sem `APP_KEY`,
  o hash é recusado em vez de ser calculado sem segredo. A linha do
  `.env.example` virou comentário, com a instrução de gerar um valor dedicado.
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
`API_KEYS_PREVIOUS_HASH_PEPPERS`. Se você customizou o `config/api_keys.php`,
traga para ele as chaves novas `previous_peppers` e
`accept_empty_pepper_legacy` (sem elas, as duas variáveis não têm efeito; o
pepper vazio continua recusado mesmo assim). Detalhes em `docs/api.md`.

**Desenvolvimento:** se o `.env` local tem `API_KEYS_HASH_PEPPER=` vazio
(vindo do `.env.example` antigo), as chaves de API já criadas no banco de dev
foram gravadas com pepper vazio: acrescente
`API_KEYS_ACCEPT_EMPTY_PEPPER_LEGACY=true` para que continuem autenticando (e
migrem no primeiro uso), ou recrie-as.

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

[1.1.2]: https://github.com/kelvindk9w/tws-laravel-starter-kit/releases/tag/v1.1.2
[1.1.1]: https://github.com/kelvindk9w/tws-laravel-starter-kit/releases/tag/v1.1.1
[1.1.0]: https://github.com/kelvindk9w/tws-laravel-starter-kit/releases/tag/v1.1.0
[1.0.0]: https://github.com/kelvindk9w/tws-laravel-starter-kit/releases/tag/v1.0.0
