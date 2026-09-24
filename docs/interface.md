# Interface: landing, showcase, i18n, tema e identidade visual

## Landing pública, showcase de componentes (/ui) e demos

A home `/` é a landing **"Céu"** — a direção escolhida pelo dono entre as três
que existiram. Ela nasceu em `/v3` enquanto era avaliada; hoje é a oficial e
`/v3` responde **301 para `/`** (links compartilhados continuam valendo, sem
duas URLs servindo a mesma página). A landing anterior saiu do kit e vive no
histórico do git; `/v2` ("O Rastro") segue ao lado como **conceito
alternativo**, não como página do produto.

O que a página tem, na ordem: herói com céu gerado (sem imagem de fundo), o
**leque de capturas reais** do produto, o split-screen `código ↔ tela`, as três
etapas do clone, o bento de **segurança de fábrica**, a linha "pronto para
produzir" (Mailpit/e-mails e "feito em componentes"), a conta das **horas já
feitas**, o **formulário de contato funcional** e o rodapé-céu com o arco das
oito tecnologias.

**Promessa (copy):** a página fala com quem constrói com IA — *"A base que a
sua IA não precisa gerar"*. O argumento não é prazo, é **token e retrabalho**:
auth, 2FA, API keys, logs com LGPD, uploads, painel e admin já prontos e
testados, para o tempo de geração ir só no que é do produto.

A landing é da **demonstração** do kit (ver [demo.md](demo.md)). Arquivos:
`demo/resources/views/landing.blade.php` + `demo/resources/views/landing/*`,
`demo/resources/css/landing.css`, `demo/resources/js/landing.js` (+
`demo/resources/js/landing/`), capturas em `public/img/landing/`. Strings em
`lang/*/landing.php` + `demo/lang/*/contact.php`; branding via `platform()`.

**Números vêm do `.env`, nunca da view** (`demo/config/landing.php`):
`LANDING_CLONES` (prova social; 0 troca a frase pela suíte verde),
`LANDING_TESTS`, `LANDING_HOURS_SAVED` (0 esconde a faixa inteira) e
`LANDING_WEBGL` (desliga o 3D e cai no fallback em CSS). Nenhum número
inventado no Blade.

**Documento próprio, não `<x-layouts.site>`**: só esta página carrega o bundle
dela (`landing.css` / `landing.js`, registrados no `vite.config.js`). GSAP,
Lenis e Three.js entram por `import()` dinâmico — nenhuma outra tela do produto
baixa um byte disso. Cabeçalho e rodapé são os componentes do kit em variantes
aditivas: `<x-site-header variant="floating">` (a cápsula que flutua sobre o
céu e **pousa** — vira superfície opaca do kit — quando o céu acaba) e
`<x-site-footer variant="plain">`.

**Objetos 3D com os logotipos oficiais**: os oito cubos esmaltados usam a marca
real de cada tecnologia (Laravel, PHP, PostgreSQL, Redis, Docker, Livewire,
Filament, Tailwind), em SVG **inline no repositório** — nenhum CDN. Uma única
fonte de verdade: o desenho e a cor da marca moram em
`demo/resources/views/landing/tech-mark.blade.php` (que é o fallback em CSS quando
não há WebGL) e o Three.js **levanta esse mesmo SVG do DOM** para virar
decalque (`demo/resources/js/landing/marks.js`). A tinta do decalque é calculada
pela luminância da cor da marca: branco na maioria, quase-preto sobre esmalte
claro (o âmbar do Filament). As marcas são de seus donos e aparecem em uso
nominativo, sem deformação e sem caixa.

**Um esqueleto para o produto inteiro** (`resources/views/components/layouts/
site.blade.php`): showcase, telas de auth e **painel do usuário** passam pelo
mesmo `<head>`, pelo mesmo `<x-site-header>` e pelo mesmo `<x-site-footer>`.
(A landing é o único documento próprio — pelo bundle extra dela —, mas usa o
MESMO cabeçalho e o MESMO rodapé, em variantes.) Eram três esqueletos, com marcas, larguras e rodapés
diferentes — e o cliente logado sentia que tinha saído do site. Muda só o que
precisa mudar: `background` (o painel usa a superfície rebaixada, para os
cartões flutuarem) e `width` (1152px na landing, 1280px no painel, onde a
coluna do menu lateral come 240px). `<x-layouts.landing>` sobrevive como
apelido de `<x-layouts.site>` para as views públicas.

- **Hierarquia de CTA**: UM primário ("Clonar"), UM secundário ("Ver a demo")
  e o resto como link de texto sublinhado (o "Ver admin demo" só existe onde o
  login demo está ligado). Quatro botões lado a lado não são quatro opções,
  são nenhuma.
- **Tipografia**: títulos em Space Grotesk Variable e corpo em Instrument Sans
  Variable — as **duas** self-hosted via `@fontsource-variable` e importadas
  no `app.css` (tokens `--font-display` / `--font-sans`). Nenhuma fonte vem de
  CDN: a CSP do kit não permite `font-src` externo.
- **Mobile**: abaixo de `sm:` a nav vira um **drawer** (`<x-drawer id="site-menu">`)
  aberto pelo hambúrguer, com Esc, backdrop, foco preso e alvos de 44px. É a
  MESMA gaveta em todas as telas; com sessão aberta ela ganha a seção
  **"Minha conta"** (avatar, nome, e-mail e os itens do menu lateral do
  painel) — duas gavetas seriam duas navegações concorrentes no mesmo polegar.
- **Cabeçalho logado**: seletor de idioma visível + **avatar** (`<x-avatar>`:
  foto do perfil ou iniciais sobre fundo neutro) que abre o menu da conta —
  nome e e-mail, Painel, Perfil, os 3 estados do tema com ✓ no atual, "Voltar
  ao site" e Sair. Deslogado, continuam Entrar e Criar conta.
- **Screenshots de validação**: `node tests/e2e/screenshots.js` (landing e
  showcase, 3 idiomas) e `node tests/e2e/shots-panel.js <pasta>` (telas
  autenticadas + login, desktop/mobile × claro/escuro) — saída em
  `test-results/`.
- **Capturas do herói**: `public/img/landing/*.webp` (commitadas) — as quatro
  telas reais do kit (painel, super admin, showcase, login) em claro e escuro,
  720w e 1440w, mais a tabela do split-screen. São capturas do produto rodando
  (viewport 1440×900, login demo, um arquivo por tema), exportadas para WebP
  nas duas larguras e commitadas — a landing não busca imagem de lugar nenhum.
  Para trocar uma delas, recapture a tela e substitua o par claro/escuro com o
  mesmo nome. O antigo `capture-hero.js`, que produzia um único
  `dashboard.png` para a landing anterior, saiu junto com ela.
- **Motion**: tokens de easing/duração em `resources/css/theme.css`
  (`--ease-out`, `--ease-in-out`); scroll-reveal discreto via
  IntersectionObserver em `resources/js/ui.js` (`data-reveal`), desligado
  com `prefers-reduced-motion`. **Rede de segurança de 2s**: o reveal esconde
  conteúdo com `opacity: 0` via JS — se o observer não disparar (aba em
  segundo plano, captura sem scroll), tudo aparece mesmo assim. Animação é
  enfeite; conteúdo não é opcional.

### i18n (pt-BR · English · Español)

Toda string de UI passa por `__()` e o kit já sai com **3 idiomas
completos** (`lang/pt_BR`, `lang/en`, `lang/es` — um teste de paridade garante
que nenhuma chave fica para trás). Resolução do locale (middleware `SetLocale`):

1. **Usuário logado** → preferência salva na conta (`users.locale`, editável no
   Perfil e pelos e-mails transacionais — o destinatário recebe no idioma dele);
2. **Visitante** → cookie `locale` (seletor `<x-locale-switcher>` nas navs da
   landing, do showcase e do painel → rota `GET /locale/{locale}`);
3. **Fallback** → `PLATFORM_LOCALE` (padrão do kit: pt-BR).

Whitelist em `PLATFORM_AVAILABLE_LOCALES` (config/platform.php).

O seletor (`<x-locale-switcher>`) é um **dropdown do kit**: bandeira em **SVG
inline** + sigla no gatilho, nome do idioma por extenso e ✓ no ativo na lista,
navegável por teclado (setas/Home/End/Esc), nos dois temas. Cada item é um
**link real** para `locale.switch`. Era um `<select>` nativo com bandeira em
emoji — a lista era desenhada pelo sistema operacional e o emoji dependia da
fonte instalada (no Windows 🇧🇷 vira "BR"); bandeira também não é idioma, por
isso o nome por extenso é a informação e a bandeira é só apoio.

O **super admin Filament (/admin) segue a MESMA resolução** (o middleware
`SetLocale` está no stack do painel): seletor na topbar
(`resources/views/filament/topbar-locale-switcher.blade.php` — mesma linguagem
visual, mas autocontido em `<details>` + estilo inline, porque o /admin tem
bundle CSS próprio e não carrega o `ui.js`), `lang/*/admin.php` nos 3 idiomas.

### Tema claro/escuro/sistema

Seletor de **3 estados NOMEADOS** (`<x-theme-toggle>` — um `<x-dropdown>` com
Sistema/Claro/Escuro e ✓ no ativo) nas navs, e segmented control no Perfil.
Era um ícone que ciclava os três às cegas: para saber onde se estava era
preciso clicar. Padrão = **Sistema** (`prefers-color-scheme`), sem flash de tema errado
no carregamento (script inline mínimo em `resources/views/partials/theme-script.blade.php`
— coberto pela CSP base, que já permite `script-src 'unsafe-inline'`).
Persistência: `localStorage.theme` (dispositivo) + `users.theme` (conta, via
`POST /settings/theme` quando logado — padrão entre dispositivos).

O escuro é um **tema**, não uma inversão: as superfícies vêm dos tokens
semânticos do `theme.css` (`--color-surface`, `--color-surface-raised`,
`--color-surface-sunken`, `--color-surface-disabled`, `--color-border`,
`--color-text-muted`), definidos uma vez por tema, com a MESMA ordem de
elevação nos dois (sunken < surface < raised). Nunca escreva
`bg-white dark:bg-gray-900` num componente — use `bg-surface`. Um teste de
arquitetura reprova quem escrever (ver [Painel do usuário](admin-e-dashboards.md#painel-do-usuário-livewire-4)).

### Formulário de contato (landing)

`POST /contato` (nome, e-mail, assunto, mensagem): validação server-side
(`ContactRequest`), **honeypot** anti-spam (campo invisível `website` → sucesso
falso para bots), rate limit de rota sensível (5/min) e e-mail **enfileirado**
para `PLATFORM_CONTACT_EMAIL` (em dev, visível no **Mailpit**:
http://localhost:18025). Feedback via toast do kit (flash de sessão).

Além do e-mail, **toda mensagem vira registro auditável**: passa pelo MESMO
`FormSubmissionGuard` dos forms demo do `/ui` e grava em `form_submissions`
com origem `contact` e o e-mail do remetente (`sender_email` — os forms demo
são anônimos). Tentativas bloqueadas (honeypot ou ataque detectado) também
ficam registradas, com sucesso FALSO para quem enviou e **nenhum e-mail**
disparado. A listagem em `/admin/form-submissions` mostra a origem e permite
filtrar por ela. Sem isso, a única trilha de contato seria a caixa de
entrada. Um número de cartão digitado na mensagem é gravado e enviado por
e-mail já mascarado (`**** **** **** 1111` — ver [Redaction](logs-lgpd.md#redaction-lgpd)).

### Showcase de componentes (`/ui`)

Documentação viva dos **componentes Blade do kit** (estilo docs: índice
lateral com scrollspy, texto de orientação por seção — quando usar, variantes e
notas de acessibilidade): `<x-button>` (primary/secondary/outline/ghost/danger),
`<x-alert>`, `<x-badge>`, `<x-input>` (com **olho de senha** embutido em
`type="password"`), `<x-textarea>`, `<x-select>`, `<x-checkbox>`, `<x-toggle>`,
`<x-card>`, `<x-modal>`, `<x-toast>`, `<x-empty-state>`, `<x-spinner>`,
`<x-skeleton>` (shimmer, com exemplo real via `wire:loading` em Projetos),
`<x-loading-overlay>` (uso restrito documentado), `<x-snippet>`,
`<x-table>` + `<x-table-row>` + `<x-table-cell>` (colunas declaradas; **vira
cartões abaixo de `sm:`**, cada célula com o próprio rótulo), `<x-stat>`
(métrica de dashboard), `<x-chart>` (Chart.js com estado vazio desenhado),
`<x-dropdown>` + `<x-dropdown-item>` (menu ancorado, teclado completo),
`<x-drawer>` (gaveta lateral — mesmo motor do modal), `<x-file-input>`
(seletor de arquivo traduzido), `<x-flag>` (bandeiras em SVG),
`<x-locale-switcher>`, `<x-theme-toggle>`, `<x-avatar>` (foto ou iniciais, 3
tamanhos), `<x-side-nav>` + `<x-side-nav-items>` (menu lateral — o mesmo do
`/ui` e do painel), `<x-brand>`, `<x-site-header>`, `<x-site-footer>`,
`<x-user-menu>`, `<x-ui-icon>`, `<x-form-errors>` e
`<x-flash-toast>` (em `resources/views/components/` — copie e use em qualquer
tela). Duas seções novas no `/ui`: **Tabela e dados** e **Navegação**. Abre com a seção **Tema** (design tokens vivos) e fecha com
**Padrões de formulário**: os dois modos canônicos funcionais (Blade clássico
e Livewire/AJAX) e as 4 estratégias de exibição de erros.

- **Índice lateral (`<x-side-nav>`)** — comportamento de docs, o mesmo do
  painel: no desktop, coluna fixa com **rolagem própria** (o índice não
  arrasta a página) e seções agrupadas; no celular, uma **barra compacta**
  grudada abaixo do cabeçalho dizendo em que seção o leitor está e um toque
  abre o índice inteiro como gaveta, com grupos colapsáveis (`<details>`
  nativo — sem JS e sem estado a manter) e o item atual marcado. Navegar
  fecha a gaveta e rola até a âncora com o desconto do cabeçalho
  (`scroll-mt-32 lg:scroll-mt-24` — 64px de cabeçalho + 48px de barra). Antes
  eram 13 pílulas empilhadas ANTES do conteúdo: a página começava com um menu
  do tamanho da tela.

- **Snippets copiáveis**: cada variante exibe o código `<x-…>` **inteiro**
  (quebra em várias linhas — nada de `truncate`: um snippet cortado no meio é
  pior do que nenhum) com botão de copiar (clipboard via `data-copy` em
  `resources/js/ui.js`, feedback no próprio botão + toast do kit).
- **JS de UI centralizado**: modal e drawer (`data-modal-open`/
  `data-modal-close`, com foco preso e devolvido), dropdown (`data-dropdown`),
  toast (`data-toast-show`), copiar, scroll-reveal, scrollspy, olho de senha
  (`data-password-toggle`), seletor de arquivo (`data-file-input`), tema e
  idioma vivem em `resources/js/ui.js`; os gráficos em `resources/js/chart.js`
  (Chart.js — sem `eval`, roda sob a CSP estrita do painel),
  servido pelo Vite — nada de `<script>` inline nas views (CSP-friendly; a
  única exceção deliberada é o anti-flash de tema no `<head>`).

Kill switch: `UI_SHOWCASE_ENABLED` (config/ui.php). **Padrão: ligado só em
`APP_ENV=local`**; desabilitado, a rota responde **404**. Em `APP_ENV=production`
a vitrine responde 404 **mesmo com a flag ligada** — ver
[Superfície de demonstração: fail-closed em produção](demo.md#superfície-de-demonstração-fail-closed-em-produção).

> Nota: o nome `<x-icon>` pertence ao pacote `blade-icons` (dependência do
> Filament) — por isso os ícones inline do kit usam `<x-ui-icon>`.

### Padrões de formulário

Dois padrões canônicos — **não invente um terceiro** (fetch/AJAX manual em
Blade puro é redundante com o Livewire):

1. **Blade clássico** — POST + redirect + `old()` + erros. Para formulários
   públicos e simples (contato, login, cadastro). Referência viva: telas de
   auth, form de contato da landing e o exemplo funcional do `/ui`
   (`POST /ui/form-demo`, mesma flag do showcase).
2. **Livewire (AJAX)** — `wire:submit` + `wire:model`, validação server-side
   sem reload, estado preservado (não existe `old()` no Livewire). Para
   interações ricas no painel. Referência viva: telas do painel e o form demo
   em versão Livewire no `/ui` (`App\Demo\Livewire\ContactForm`).

**Os 2 forms demo do `/ui` gravam de verdade** (campos: apelido, assunto,
mensagem + honeypot invisível): cada submissão vira uma linha em
`form_submissions` (origem `classic` ou `livewire`) e aparece no super admin
(`/admin/form-submissions`, mais recentes primeiro, filtro por origem na URL).
O `FormSubmissionSeeder` cria 40 submissões variadas.

**Vitrine de segurança**: os forms demo são *autodefendidos* — o middleware
global delega a detecção para a camada do formulário
(`security.validation.delegated_paths` / `delegated_components`), que roda o
MESMO `AttackDetector`. Ataques (XSS, SQLi, honeypot disparado) são gravados
com `blocked_at` + `attack_type`, o payload fica **inerte** (texto cru exibido
escapado — nunca `{!! !!}`), a resposta ao atacante é **sucesso falso** e as
tentativas aparecem **no topo da listagem do admin com badge vermelho**
"ataque bloqueado". Testes Pest executam ataques reais contra os dois forms
(`tests/Feature/FormSubmissionsTest.php`).

**Regras do kit:**

- **Repopulação**: `old()` em todos os campos, EXCETO senhas/segredos —
  nunca repopular (segurança, não opção).
- **Erros componentizados**: a estratégia de exibição vive em
  `config/ui.php → error_display` (`UI_ERROR_DISPLAY` no .env):
  - `inline` (padrão) — erro embaixo de cada campo, via
    `:error="field_error('email')"` nos inputs;
  - `summary` — só o resumo `<x-form-errors>` no topo, com **âncoras** que
    rolam até o campo;
  - `toast` — os erros disparam o toast do kit;
  - `both` — inline + resumo (acessibilidade reforçada).
- **Override por formulário**: `<x-form-errors display="summary" />` e
  `field_error('email', 'summary')` vencem o config naquele form. A demo
  clássica do `/ui` tem um seletor que troca a estratégia ao vivo.
- **Flash de sessão → toast**: renderize `<x-flash-toast />` uma vez no
  layout (já está nos 3 layouts do kit). Chaves padronizadas:
  `session('status')`/`session('success')`/`session('contact_status')` →
  toast de sucesso; `session('error')` → toast de erro.

## Identidade visual / design tokens

Rebranding de um projeto novo = **1 arquivo + .env**:

- **`resources/css/theme.css`** — bloco `@theme` do Tailwind 4 com os tokens da
  linguagem: cor de marca (`--color-brand`, `--color-brand-hover`),
  **superfícies semânticas** (`--color-surface`, `--color-surface-raised`,
  `--color-surface-sunken`, `--color-surface-disabled`, `--color-border`,
  `--color-border-strong`, `--color-text-muted`), **cores de estado**
  (`--color-success`, `--color-success-foreground`, `--color-switch-off` —
  ver abaixo), tipografia (`--font-display`,
  `--font-sans`), **escala tipográfica de 5 degraus** (`--text-display`,
  `--text-h1`, `--text-h2`, `--text-body`, `--text-caption` — utilitários
  `text-display`/`text-h1`/…), radii (`--radius-lg/xl`) e motion (`--ease-out`,
  `--ease-in-out`, `--animate-spin/shimmer`). Importado pelo `app.css` e pelo
  `filament.css` (o /admin fala a mesma língua).
- **Escala tipográfica**: cinco degraus e só. `display` (manchete/número de
  campanha), `h1` (título da tela), `h2` (título de seção/cartão), `body`
  (texto e controles), `caption` (metadado/rótulo). Tracking negativo só nos
  dois primeiros. Use os degraus em vez de reinventar `text-2xl font-semibold`
  a cada tela.
- **Estado não se diz com a cor da marca.** A primária do kit é
  monocromática — quase-BRANCA no tema escuro. Enquanto o `<x-toggle>` ligado
  usava `bg-brand`, o estado ligado era um trilho branco com um knob branco em
  cima: indistinguível do desligado justo no tema em que mais gente trabalha.
  Ligado = `--color-success` (green-600 no claro, green-500 no escuro);
  desligado = `--color-switch-off` (gray-500 nos dois temas, ≥3:1 contra a
  superfície — WCAG 1.4.11); knob sempre branco.
- **Identidade monocromática por padrão** (esquema Vercel/Linear):
  `--color-brand` é quase-preto no tema claro e quase-branco no escuro
  (invertido pela classe `.dark`), com `--color-brand-foreground` para o texto
  sobre a primária. Azul sobrevive só como cor de **status** (badge/alert
  `info`). O super admin Filament usa a paleta `Zinc` por padrão.
- **`.env` → `config/platform.php`** — nome (`PLATFORM_NAME`), logo
  (`PLATFORM_LOGO_URL`) e override OPCIONAL da primária
  (`PLATFORM_PRIMARY_COLOR` — vazio = neutro; quando definido, é injetado em
  runtime como `--brand` no `<head>`, sem rebuild, valendo para os 2 temas).
  Acesso tipado via `platform()`.

O showcase `/ui` abre com a seção **Tema** mostrando os tokens vivos e como
editá-los.
