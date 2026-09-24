# Painéis: painel do usuário, super admin e dashboards

Dois frontends na mesma codebase: **painel do usuário em Livewire 4** e
**super admin em Filament 5**. Branding 100% via `platform()` (nome, logo e
cor primária — `PLATFORM_NAME`/`PLATFORM_LOGO_URL`/`PLATFORM_PRIMARY_COLOR`
no .env — nada hardcoded). Toda string via `__()` (pt-BR/en/es). Tema
claro/escuro/sistema no painel do usuário (toggle de 3 estados no topo,
default = preferência do SO).

## Painel do usuário (Livewire 4)

| Rota | Tela |
|---|---|
| `/dashboard` | Boas-vindas, código público (com copiar), 4 métricas (chaves ativas, projetos, requisições em 7 dias, último uso de chave), gráfico de requisições por dia (30 dias) e as 5 últimas chamadas da API com status |
| `/profile` | Dados, idioma, aparência (tema), senha de login, senha de transação e avatar (mesma tela) |
| `/api-keys` | Chaves de API: criar (scopes + vínculo N:N com projetos), visualização única da secreta, rotacionar (grace period), revogar |
| `/projects` | Projetos: CRUD só com nome, tudo inline |
| `/notifications` | Preferências de e-mail (esqueleto p/ notificações de pagamento) |

**O dashboard mostra tráfego REAL**: as métricas, o gráfico e a lista saem de
`request_logs` filtrados por `tenant_uuid` (o uuid do dono da chave, vinculado
pelo middleware `ResolveTenant` — ver [Tenancy](tenancy.md)). Sem dados, cada bloco tem estado
vazio desenhado — um gráfico de eixos zerados não informa nada e parece
defeito.

**O painel consome o próprio design system.** Nenhuma view de
`resources/views/livewire/**` escreve Tailwind cru de botão, modal, alerta,
estado vazio ou superfície: se falta variante, o componente é estendido.
Isso é lei verificada por teste, não convenção — `tests/Feature/Architecture/
DesignSystemTest.php` reprova o build se aparecer `bg-brand px-4`,
`fixed inset-0 z-40`, `border-dashed`, `dark:bg-gray-900` e companhia (a lista
de padrões proibidos, com o componente que resolve cada um, está no topo do
arquivo). O mesmo teste reprova **ação Livewire com nome de palavra reservada
do JavaScript**: no build CSP-safe do Livewire 4 a expressão de `wire:click` é
compilada por um parser de JS, e uma ação chamada `delete` estoura
`Expected IDENTIFIER but got KEYWORD` — a ação nunca roda, sem erro visível
(foi o bug de "excluir projeto"; hoje o método se chama `removeProject`).

**O painel é o site, logado.** Mesmo cabeçalho, mesma marca, mesmo rodapé
(ver [Interface](interface.md)). O que muda é um **menu lateral "Minha conta"**
(`<x-side-nav mobile="none">`) à esquerda do conteúdo, agrupado por assunto —
Visão geral (Painel), Desenvolvimento (Chaves de API, Projetos) e Conta
(Notificações, Perfil, **Senha de transação**, que só existia como rota solta
sem entrada em menu nenhum). Item atual com `aria-current`, coluna sticky com
rolagem própria. A barra horizontal anterior não escalava: cada tela nova
empurrava a próxima para fora.

O mapa de navegação (site, conta e índice do `/ui`) é declarado **uma vez** em
`App\Livewire\Support\Navigation`; cabeçalho, gaveta e coluna leem a MESMA
lista. Adicionar uma tela é acrescentar uma linha.

**Mobile**: abaixo de `sm:` a navegação inteira vira a gaveta do cabeçalho
(hambúrguer → Esc, backdrop, foco preso), com os links do site e a seção
"Minha conta" dentro; as tabelas viram cartões e as ações destrutivas moram
num menu de overflow (⋯) com modal de confirmação — mirar "Rotacionar" e
acertar "Revogar" destruía uma credencial de produção.

Princípio de UI: tudo se resolve na MESMA tela — formulários
inline e modais em vez de navegação. Ações sensíveis (criar/rotacionar
chave) abrem o modal de confirmação: senha de transação → código por e-mail
→ executa. Nada de lógica duplicada: as telas consomem `ApiKeyService`
e `SensitiveActionService`; a senha de transação usa o
`TransactionPasswordService` compartilhado com o controller web de
[autenticação](autenticacao.md).

## Super admin (Filament 5) — `/admin`

- **Acesso**: somente `is_admin` + conta ativa (`User::canAccessPanel`) —
  qualquer outro usuário recebe **403**; guest vai ao login do painel.
- **Criar o primeiro admin** (bootstrap e resgate de acesso — a flag NUNCA é
  mass-assignable; pela UI ela só muda no formulário de usuário do painel,
  que aplica as guardas descritas abaixo):

```bash
docker compose exec app php artisan user:make-admin email@exemplo.com
# revogar:  ... user:make-admin email@exemplo.com --remove
```

- **i18n + seletor compacto na topbar** (bandeira + sigla): o painel segue a
  mesma resolução de locale do app (preferência da conta → cookie → padrão).
- **Três dashboards nomeados para escolher** (`/admin`): "Visão geral",
  "Crescimento & API" e "Conteúdo & Operação" — a seção
  [Dashboards: escolhendo e adaptando a sua variante](#dashboards-escolhendo-e-adaptando-a-sua-variante)
  explica como ligar/desligar cada uma, como criar a quarta e como um widget
  novo nasce em poucas linhas. Todo número vem de tabela real; os seeders do
  kit garantem que as três nasçam CHEIAS em qualquer instalação.
- **Identidade unificada com o resto do produto**: `->font('Space Grotesk
  Variable')` (self-hosted, sem CDN de fonte — a CSP não permite),
  `->viteTheme('resources/css/filament.css')` (que importa o preset do
  Filament 5 + os tokens de `resources/css/theme.css`), primária
  `Color::Neutral` casando com `--color-brand`, marca monocromática própria
  quando `PLATFORM_LOGO_URL` está vazio e ícone Heroicon em **todos** os
  resources e páginas.
- **Resources**: **Usuários** (CRUD completo — listar, ver, criar, editar,
  bloquear/desbloquear e excluir; senha com confirmação sob a MESMA política
  do registro público; guardas de servidor no `UserAdminGuard`: contas demo
  intocáveis, o admin não se exclui nem se bloqueia e o último admin ativo
  não perde a flag/acesso; `UserSeeder` idempotente com 40 usuários
  realistas para paginação e filtros nascerem com conteúdo),
  Chaves de API (visão global de todos os tenants + revogar), Projetos,
  **Produtos** (vitrine de CRUD: foto por upload validado ou URL, valor
  monetário em centavos — nunca float —, paginação de 10 e paginação/filtros
  refletidos na query string; `ProductSeeder` com 36 itens),
  **Submissões de formulário** (read-only; ataques bloqueados no topo, com
  selo do tipo e **trecho neutralizado** — ver abaixo; filtro por origem na
  URL — os dois forms demo do `/ui` **e o formulário de contato real da
  landing**, origem `contact`, a única com remetente identificado),
  Request Logs (auditoria de API
  + web + admin, com filtros de status/tenant/endpoint/período — logs órfãos,
  sem tenant, destacados em vermelho) e Uploads.
- **Menu do usuário**: avatar (foto de perfil de quem subiu uma — o mesmo
  Upload validado pelo [módulo de uploads](uploads.md) —, senão as **iniciais** desenhadas localmente em
  SVG `data:`, sem CDN de avatar), Perfil, **alternador de tema**
  claro/escuro/sistema, Voltar ao site e Sair. O seletor de idioma continua
  na topbar, fora do menu (decisão do dono): trocar de idioma reescreve a
  tela inteira e não é um item de conta. A foto é resolvida por
  `App\Filament\Support\InitialsAvatarProvider`
  (`->defaultAvatarProvider()`), não por um `getFilamentAvatarUrl()` no
  model — o domínio não precisa conhecer o Filament para isso.
- **Perfil demo-safe** (`/admin/profile`, link no menu do usuário): **foto**
  e nome editáveis; e-mail read-only com nota explicativa; seção de senha
  montada só como prévia (campo desabilitado, sem endpoint) — nada derruba o
  acesso demo. A foto sobe pela **mesma função global de upload** do resto
  do kit e vira o avatar do cabeçalho na hora.
- **Foto de perfil no cadastro de usuário** (`App\Filament\Support\AvatarUpload`):
  o `FileUpload` do Filament grava, de fábrica, direto no disco — o que
  pularia o `SecureUploadService` e, com ele, a validação por magic bytes, o
  re-encode GD, o nome derivado do MIME real e o registro em `uploads`. Aqui
  o campo delega a gravação ao service (`saveUploadedFileUsing`) e valida
  antes, na própria validação do formulário (regra `SafeFile`), para que um
  `.txt` renomeado para `.png` apareça como erro embaixo do campo e não como
  erro de servidor depois do "Salvar". A imagem é servida por **URL assinada
  de curta duração** (`visibility('private')` → `temporaryUrl()`); quem não
  tem foto aparece com as iniciais em SVG `data:`, nunca com um quadrado
  quebrado.
- **Configurações** (`/admin/settings`): parâmetros operacionais editáveis
  pela UI (meses de inatividade p/ expirar chaves, dias de aviso prévio,
  limites de upload, rate limits) gravados na tabela `settings` — sem
  editar .env. Somente a whitelist de `config/settings.php` é gravável;
  campo vazio = volta ao valor do .env. Os overrides são aplicados no boot
  (`SettingsServiceProvider`, cacheados) e lidos pelo helper `setting()`.
  Os campos são **agrupados por assunto** (chaves de API / uploads / rate
  limits) com largura proporcional ao número esperado — `group` e `span`
  vêm do próprio `config/settings.php`, nada hardcoded na tela.
- **Barreira de origem** (o /admin não fica exposto à internet inteira — obrigatória em produção,
  e agora VERIFICADA em runtime): `ADMIN_ALLOWED_IPS` no .env, aceitando IP
  exato, faixa CIDR IPv4 e IPv6 com ou sem prefixo, separados por vírgula
  (espaços são aparados). É o PRIMEIRO middleware da pilha do painel —
  origem não permitida é recusada antes de a sessão ser aberta. Vale também
  para o `/horizon`, para os downloads de export/import do Filament e para
  as **ações dos componentes do painel**: essas não chegam pelas rotas
  `/admin/...`, e sim pelo endpoint de atualização do Livewire (uma rota
  única, compartilhada com o painel do usuário). Por isso a barreira é
  registrada como **middleware persistente do Livewire**
  (`->middleware([...], isPersistent: true)` no `AdminPanelProvider`): o
  Livewire a reaplica só para componentes cuja rota de origem, gravada no
  snapshot assinado, é do `/admin` — o painel do usuário não é afetado.
  - **Fora de produção, vazio libera** (o IP de quem desenvolve é o que o
    Docker der, e não há segredo atrás do `/admin` de dev).
  - **Em produção, vazio RECUSA** (403). Antes, vazio liberava qualquer
    origem — e vazio era o padrão do `docker-compose.prod.yml`, então a
    barreira que esta documentação prometia não existia em nenhuma
    instalação que não a tivesse preenchido à mão. Silêncio não pode
    significar permitido.
  - **Escape hatch**: `ADMIN_ALLOW_ANY_IP=true`, para quem administra de IP
    dinâmico ou já tem a segunda barreira fora da aplicação (VPN,
    Cloudflare Access, WAF). Grava aviso no log a cada boot.
  - **Trancado fora?** Só a superfície administrativa para; site, API,
    painel do usuário, filas e `/up` seguem atendendo. Corrija a variável no
    ambiente dos serviços PHP e reinicie-os — o log diz o que falta. A
    recuperação nunca depende de entrar no painel.
  - **Atrás de CDN/load balancer**: o endereço comparado depende de
    `TRUSTED_PROXIES` (ver [Proxies confiáveis e host confiável](seguranca.md#proxies-confiáveis-e-host-confiável-atrás-de-cdnlb)). Com o proxy
    declarado, a lista compara **quem administra** — é isso que você escreve
    aqui. **Nunca** ponha o IP do load balancer na lista: todas as requisições
    chegam com ele e a barreira fica aberta para a internet inteira, parecendo
    configurada. A aplicação reconhece esse erro e avisa no log a cada boot.
  - Regra, decisões e justificativas: `App\Core\Security\AdminIpAllowlist`.
    Middleware: `EnsureAdminIpAllowed`.

## Dashboards: escolhendo e adaptando a sua variante

Como nos temas de admin clássicos ("Analytics / SaaS / E-commerce"), o
`/admin` entrega **três dashboards nomeados**. A ideia não é que os três
fiquem ligados para sempre: é que você abra os três, escolha o que mais se
parece com o seu produto, **apague os outros dois** e adapte o que sobrou.

| Variante | Slug | Para quem | O que mostra |
|---|---|---|---|
| **Visão geral** | `overview` | quem abre o painel para saber se está tudo bem | usuários, requisições, chaves ativas e projetos (total + Δ do período); requisições por dia com a linha de erro; respostas por faixa 2xx/3xx/4xx/5xx; últimas submissões; últimos uploads |
| **Crescimento & API** | `growth` | o dono técnico acompanhando adoção | novos usuários, taxa de erro, latência média e chaves emitidas; curva **acumulada** da base; ranking de endpoints; **meta do mês** com marca de ritmo; projetos recentes com dono e nº de chaves |
| **Conteúdo & Operação** | `content` | quem cuida do catálogo e da fila do dia | produtos, uploads, volume armazenado e tentativas bloqueadas; composição por tipo de arquivo; entrada por dia (uploads + submissões); últimos produtos; fila de entrada com as bloqueadas no topo |

Todas têm, no topo, **um** seletor de período (7/30/90 dias) que vale para a
página inteira, e **todo** card compara com o período anterior de mesmo
tamanho — com seta, cor e sparkline. Quando não há período anterior, o card
diz "sem base de comparação" em vez de inventar um "+100%".

### Ligar, desligar e escolher a home

Tudo em `config/dashboards.php`, alimentado pelo `.env` (nada hardcoded):

```dotenv
DASHBOARD_ENABLED=overview,growth,content   # quais existem, nesta ordem no menu
DASHBOARD_DEFAULT=overview                  # qual responde em /admin
DASHBOARD_PERIOD=30                         # janela padrão do seletor
DASHBOARD_GOAL_MONTHLY_REQUESTS=1500        # meta do widget de progresso
DASHBOARD_LATEST_RECORDS=6                  # linhas das tabelas "últimos N"
```

- a variante **padrão** responde em `/admin`; as demais em
  `/admin/dashboards/{slug}`;
- tirar um slug de `DASHBOARD_ENABLED` remove a variante do **menu E da
  rota** — a página deixa de ser registrada no painel e a URL responde 404.
  Não é um item escondido com a rota viva;
- `DASHBOARD_DEFAULT` apontando para variante desligada não deixa o painel
  sem home: a primeira habilitada (na ordem do menu) assume;
- sem nenhuma variante habilitada, o painel volta ao Dashboard de fábrica do
  Filament — `/admin` nunca dá 404.

Quem lê essa config é o `App\Filament\Dashboards\DashboardRegistry`, e é
ele que o `AdminPanelProvider` consulta (`->pages(DashboardRegistry::pages())`).
Nenhuma lista de dashboards existe hardcodada em código.

### Criar uma quarta variante (4 passos)

1. **Traduções** — um bloco `admin.dashboards.finance` em
   `lang/{pt_BR,en,es}/admin.php` com `nav`, `title` e `subheading` (o teste
   de paridade de chaves reprova se faltar em algum idioma).
2. **A página** — `app/Filament/Dashboards/FinanceDashboard.php`, estendendo
   `BaseDashboard`. Ela declara só duas coisas:

```php
final class FinanceDashboard extends BaseDashboard
{
    public static function variant(): string
    {
        return 'finance';
    }

    public function getWidgets(): array
    {
        return [FinanceStats::class, RevenueChart::class];
    }
}
```

   Rota, ícone, grupo de navegação, ordem no menu, título, subtítulo, grade de
   12 colunas e o seletor de período vêm da base.

3. **A config** — a entrada nova em `config/dashboards.php`:

```php
'finance' => [
    'page' => App\Filament\Dashboards\FinanceDashboard::class,
    'icon' => 'heroicon-o-banknotes',
    'sort' => 4,
],
```

4. **O `.env`** — acrescentar `finance` a `DASHBOARD_ENABLED` (e
   `php artisan optimize:clear`). A variante aparece no menu e responde em
   `/admin/dashboards/finance`.

### Criar um widget com a base

A base vive em `app/Filament/Widgets/Support/` e existe para que um widget
novo não repita decisão de design nem conta de porcentagem:

| Peça | O que entrega |
|---|---|
| `Period` | a janela do filtro da página **e** a janela anterior de mesmo tamanho |
| `Metric` | contagem/soma/média/razão por dia, com `current()`, `previous()`, `delta()`, `series()` e `cumulativeSeries()` |
| `MetricStat` | o card: valor formatado, seta, Δ%, cor de status, sparkline |
| `MetricFormat` | inteiro, porcentagem, milissegundos e bytes — formatação só na borda |
| `StatusPalette` | a **única** paleta de status (nome da cor do Filament + hex para o Chart.js) |
| `BaseStatsWidget` | faixa de 4 KPIs, largura total, período da página |
| `BaseTimeSeriesWidget` | gráfico de linha/área/barras por dia, opções do Chart.js e estado vazio |
| `BaseCompositionWidget` | doughnut de participação ou barra horizontal de ranking |
| `BaseLatestRecordsWidget` | tabela "últimos N" sem paginação, com "ver tudo" e estado vazio |
| `InteractsWithDashboardPeriod` | o período **da página** chegando no widget (`$this->pageFilters`) |

Um KPI novo:

```php
final class FaturasStats extends BaseStatsWidget
{
    protected function metrics(Period $period): array
    {
        return [
            MetricStat::make(__('admin.dashboards.finance.invoices'), Metric::count(
                fn () => Fatura::query(),
                $period,
            ))
                ->icon(Heroicon::OutlinedDocumentText)
                ->hint(__('admin.dashboards.finance.invoices_hint')),
        ];
    }
}
```

Um gráfico novo:

```php
final class FaturasChart extends BaseTimeSeriesWidget
{
    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 8];

    public function getHeading(): string
    {
        return __('admin.dashboards.finance.chart');
    }

    protected function chartSeries(Period $period): array
    {
        return [ChartSeries::make(
            __('admin.dashboards.finance.chart_series'),
            Metric::count(fn () => Fatura::query(), $period)->series(),
            StatusPalette::Neutral,
        )];
    }
}
```

Ajustes finos por propriedade: `$columnSpan` (span na grade de 12,
responsivo), `$chartType` (`line`/`bar`/`doughnut`), `$maxHeight`,
`->inverted()` no card quando **subir é ruim** e `->deltaInPoints()` quando a
métrica já é uma porcentagem. Para o que não é card, gráfico nem tabela, o
exemplo de widget **custom** é o `RequestsGoalProgress` (Blade livre dentro da
mesma `<x-filament::section>` dos demais).

### Por que os dashboards nascem cheios

`users` (90 dias) e `request_logs` (30 dias) já vinham semeados; `projects`,
`api_keys` e `uploads` nasciam **vazios**, e as submissões cabiam todas nas
últimas 40 horas — metade dos cards abriria em zero. O
`DashboardHistorySeeder` (chamado pelo `DatabaseSeeder`, só com o login demo
habilitado) resolve isso com seis seeders **idempotentes**, todos por
identificador determinístico (UUID v5 com semente fixa):

| Seeder | O que semeia |
|---|---|
| `ProjectSeeder` | 22 projetos espalhados em até 200 dias, com status variado |
| `ApiKeySeeder` | 18 chaves **inertes** (nenhuma secreta existe — só um hash sem par), com os quatro status e vínculo com projetos |
| `UploadSeeder` | ~700 registros de upload em 200 dias, com tipo real, tamanho plausível e volume crescendo rumo ao presente |
| `SubmissionHistorySeeder` | submissões ANTIGAS (conteúdo banal — payload de ataque continua sendo assunto do `FormSubmissionSeeder`) |
| `ProductHistorySeeder` | data de cadastro dos produtos derivada do uuid: não cria nem apaga nada, só espalha o catálogo no tempo |
| `RequestLogHistorySeeder` | o passado dos logs (dia 31 ao 200), respeitando append-only e a MESMA redaction do middleware |

A profundidade de 200 dias é proposital: a janela de 90 dias precisa de
outros 90 atrás dela, senão todo card diria "sem base de comparação".

> `php artisan db:seed` roda tudo de novo sem duplicar nada (é o comando de
> reseed do kit — **nunca** `migrate:fresh`). Em `APP_ENV=production` ele não
> semeia nada: todo seeder do kit cria dado fictício e é recusado (o agregador
> avisa e segue; um seeder chamado direto lança). Ver
> [Superfície de demonstração: fail-closed em produção](demo.md#superfície-de-demonstração-fail-closed-em-produção).


## Submissões bloqueadas: listagem neutralizada, detalhe forense

O payload de uma tentativa de ataque **não aparece na listagem**. Ele
aparecia — escapado, portanto inerte —, e inerte não é o mesmo que
inofensivo: uma fila de tentativas exibidas por extenso é um catálogo de
ataques pronto para copiar, na tela de quem tem acesso ao painel.

Hoje:

- **na listagem**: o selo do tipo de ataque (XSS, SQL injection, null byte,
  path traversal, honeypot) e um **trecho neutralizado** do conteúdo — sem
  tags, sem entidades que possam virar tags, espaços colapsados, ~60
  caracteres —, com a legenda "conteúdo neutralizado". Vale para a mensagem
  **e para o apelido**: o payload entra por qualquer campo. Quem neutraliza é
  `App\Core\Showcase\Support\SubmissionExcerpt`, e o teste prova que o
  trecho não tem como voltar a ser marcação;
- **no detalhe** (`/admin/form-submissions/{uuid}`): o payload **íntegro**,
  escapado, dentro de um bloco monoespaçado rotulado "evidência forense",
  com callout de aviso — e sem botão de copiar. Ao lado, os metadados da
  tentativa: origem, IP, remetente (quando houver), recebida em e bloqueada
  em;
- **no banco**: nada muda. A gravação continua **crua** (auditoria:
  a trilha guarda o que chegou). Quem neutraliza é a exibição, nunca o registro. A única
  exceção é **número de cartão**, gravado só com os 4 últimos dígitos (PCI
  DSS — ver [Redaction](logs-lgpd.md#redaction-lgpd)): não é evidência de ataque, e a detecção roda antes,
  sobre o texto original.

O IP passou a ser gravado junto da submissão (migration
`add_ip_to_form_submissions_table`): sem ele a evidência responde "o quê" e
não responde "de onde", e não dá para correlacionar a tentativa com os
`request_logs`.

## Alternador tabela/cards nas listagens

Toda listagem do painel tem, **na barra da tabela — colado no ícone de
filtros e na busca** —, um botão **só de ícone** (grade ↔ lista) que troca
entre a tabela clássica e a grade de cards. Sem texto: o nome aparece no
hover, exatamente como o botão de filtros do Filament. O ícone mostra o
**destino** do clique, não o estado atual.

Ele já morou no cabeçalho, ao lado de "Novo usuário". Saiu de lá por dois
motivos: dividia espaço com a ação primária da tela (uma cria registro, a
outra só muda como você olha) e ficava longe dos seus irmãos — filtro,
busca e alternador respondem à mesma pergunta, "como esta lista aparece", e
por isso agora moram juntos. Efeito colateral bem-vindo: nenhuma página
consegue mais derrubar o alternador ao sobrescrever `getHeaderActions()`,
porque ele não passa mais por lá (`BaseResource::table()` +
`App\Filament\Support\ViewModeToggle`).

Os cards mostram de três a cinco campos com hierarquia (o que identifica o
registro em destaque, o resto em cinza). A tabela segue sendo o padrão: é o
modo denso, o certo para quem abre o painel procurando alguma coisa.

**Ações no modo cards.** No cartão, as ações ocupam uma **linha própria no
rodapé, dividida em partes iguais** — N ações, N colunas de mesma largura,
cada ícone centralizado na sua fatia (a área de clique é a coluna inteira).
São **só ícone**, com o nome no hover e a **cor dizendo o que a ação faz**:

| Cor | Significado | Ações |
| --- | --- | --- |
| `info` (azul) | consulta, não altera nada | Visualizar |
| `success` (verde) | constrói ou devolve acesso | Editar, Desbloquear, Restaurar |
| `danger` (vermelho) | tira acesso ou destrói | Bloquear, Excluir, Revogar |
| `warning` (âmbar) | substitui um segredo em uso | Rotacionar |
| `gray` | neutra | Abrir arquivo, Baixar |

Na **tabela** nada muda: continua o link com rótulo do Filament. Ali o
texto ajuda (a linha é densa e se varre coluna a coluna); no cartão, uma
fileira de links come metade do rodapé e faz todo registro parecer um
formulário.

O resource **não precisa saber disso**: quem converte é a base
(`BaseResource::table()` → `App\Filament\Support\CardActions`), via o hook
`modifyUngroupedRecordActionsUsing` do Filament, e só quando o modo vigente
é cards. A grade de colunas iguais é CSS do tema
(`resources/css/filament.css`). Ação com nome fora do mapa de semântica
**não é adivinhada**: mantém a cor que o resource declarou — uma ação nova
nasce neutra, não vermelha por acidente.

**Onde a escolha mora (decisão documentada):** na **sessão**, com uma chave
por recurso (`App\Filament\Support\ViewMode`). A sessão já é por usuário,
então não é preciso coluna nova nem escrita no banco a cada clique; e a
chave por recurso deixa cada tela com o seu modo — quem tria submissões em
cards continua querendo request logs em tabela. Trocar de máquina reinicia
no padrão. Se um dia a preferência tiver de atravessar sessões, o ponto de
troca é só o `ViewMode`: nada mais no painel muda.

## Como criar uma tela nova no /admin (5 passos)

Todo resource herda de `App\Filament\Support\BaseResource` e toda listagem
de `App\Filament\Support\BaseListRecords` — isso é **verificado por teste**
(`tests/Feature/Admin/TableViewModeTest.php`). De graça vêm: rota por uuid,
rótulos e grupo de navegação traduzidos, ordenação padrão, paginação, estado
vazio traduzido, o alternador tabela/cards e as colunas repetidas de sempre
(`AdminColumns::publicCode()`, `AdminColumns::dateTime()` — esta última já no
fuso de exibição da plataforma).

1. **Traduções** — um bloco novo em `lang/{pt_BR,en,es}/admin.php` com, no
   mínimo, `label` e `plural` (o teste de paridade de chaves reprova se
   faltar em algum idioma).
2. **O resource** — em `app/Filament/Resources/Faturas/FaturaResource.php`:

```php
final class FaturaResource extends BaseResource
{
    protected static ?string $model = Fatura::class;

    protected static string $translationKey = 'admin.faturas';

    protected static ?string $navigationGroupKey = 'admin.nav.group_management';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    /** Colunas da tabela clássica. */
    public static function tableColumns(): array
    {
        return [
            AdminColumns::publicCode(),
            TextColumn::make('valor')->label(__('admin.faturas.valor'))->sortable(),
            AdminColumns::dateTime('created_at'),
        ];
    }

    /** Opcional: declarar isto liga o alternador tabela/cards. */
    public static function cardComponents(): array
    {
        return [
            Stack::make([
                TextColumn::make('codigo_publico')->weight(FontWeight::SemiBold),
                TextColumn::make('valor')->color('gray'),
            ])->space(2),
        ];
    }

    /** Opcional: filtros, ações e o que mais for específico da tela. */
    protected static function tableExtras(Table $table): Table
    {
        return $table->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListFaturas::route('/')];
    }
}
```

3. **A página de listagem** — `Pages/ListFaturas.php` estendendo
   `BaseListRecords`; ações próprias da tela (um `CreateAction`, por
   exemplo) vão em `getResourceHeaderActions()`, **nunca** sobrescrevendo
   `getHeaderActions()` — é de lá que sai o alternador.
4. **`php artisan optimize:clear`** e a tela já aparece na navegação (o
   painel descobre os resources sozinho).
5. **O teste** — em `tests/Feature/Admin/`, com `Livewire::test(ListFaturas::class)`
   validando **conteúdo** (registros visíveis, filtro que filtra, ação que
   age), não só status HTTP.

Ajustes finos disponíveis por propriedade estática: `$defaultSortColumn` /
`$defaultSortDirection` (`null` na coluna = o resource ordena sozinho, como
Submissões, que sobem as bloqueadas), `$paginationOptions` e `cardGrid()`.

## Testes

`./vendor/bin/pest` (Pest): telas Livewire (renderização + ações com
conteúdo — criar projeto, **excluir projeto de verdade**, criar/rotacionar/
revogar chave com o fluxo 2FA real e código capturado do mailable, avatar,
preferências, isolamento anti-IDOR entre tenants), dashboard com
`request_logs` inseridos no próprio teste (janela de 7 dias, série de 30 dias
sem buracos, 5 últimas chamadas, estados vazios), **arquitetura do design
system** (`tests/Feature/Architecture/DesignSystemTest.php` — que também varre
`resources/views/layouts/**`, o esqueleto agora compartilhado por todo o
produto), **layout unificado** (`tests/Feature/Panel/LayoutTest.php`: mesmo
cabeçalho e rodapé em landing/`/ui`/auth/painel, menu lateral com item atual,
gaveta com "Minha conta"), **avatar e menu da conta**
(`tests/Feature/UiAvatarTest.php`: iniciais com acento, foto, 3 tamanhos),
**cor de estado do toggle** (`tests/Feature/ThemeTest.php`), layout mobile
(drawer, alvos de 44px), acesso ao /admin (403 a não-admin, comando de
promoção, IP allowlist), resources Filament (listagens, bloquear usuário,
revogar chave, filtros de request logs), **alternador tabela/cards**
(persistência por usuário e por recurso, as duas listagens renderizando com
conteúdo, e a arquitetura: todo resource estende a base),
**evidência das submissões** (a listagem não mostra o payload em nenhuma
forma, o detalhe mostra íntegro e escapado, o banco continua cru),
**menu do usuário** (itens, alternador de tema, avatar por foto ou iniciais)
e Settings (override, fallback ao .env, whitelist). E2E Playwright: login → dashboard → chaves de API,
gaveta mobile com "Minha conta", menu do avatar (abre, troca o tema, Esc
fecha), menu lateral no desktop e gating do /admin
(`tests/e2e/panel.spec.js`); índice do `/ui` no mobile e no desktop
(`tests/e2e/smoke.spec.js`).
