# twstec/kit-demo

A **demonstração** do TWS Laravel Starter Kit — tudo o que existe para
*mostrar* o kit, e não para ser a base de um produto:

- as landings **"Céu"** (`/`) e **"O Rastro"** (`/v2`; `/v3` → 301 para `/`),
  com o JS, o CSS e as imagens delas;
- a vitrine de componentes **`/ui`** (e o `POST /ui/form-demo`) e o
  formulário de **contato** (`POST /contato`);
- no `/admin`: o catálogo de **produtos** de exemplo, a caixa de
  **submissões** e o dashboard **"Conteúdo & Operação"**;
- as **contas demo** de credenciais públicas (`demo@tws.dev`, `admin@tws.dev`),
  protegidas contra alteração no model e no banco (gatilhos do PostgreSQL);
- os **seeders de dado fictício**.

> **Só no ambiente de desenvolvimento.** O starter declara este pacote em
> `require-dev`. A imagem de produção (`composer install --no-dev`) não o
> instala. Nunca o instale numa instalação com dado real: as senhas das
> contas demo são públicas.

- **Requisitos:** PHP 8.4+, Laravel 13, Filament 5, Livewire 4, os cinco
  pacotes do kit 2.x e o starter Livewire (as views da demo são páginas dele:
  usam os componentes Blade, o layout e o painel do aplicativo).
- **Licença:** MIT.

## Como se liga ao produto

O `Twstec\Kit\Demo\DemoServiceProvider` é descoberto automaticamente. Nenhum
arquivo do produto nomeia a demo (uma trava do starter reprova quem tentar);
ela responde aos pontos de extensão dele:

| Ponto de extensão | De quem | O que a demo registra |
| --- | --- | --- |
| `AccountProtection` | twstec/kit-auth | as contas demo protegidas (`DemoAccountProtection`) |
| `LoginPrefillProvider` | twstec/kit-auth | as credenciais demo no login do painel e do `/admin` |
| `MailPreviewGate` | twstec/kit-foundation | a galeria `/mail-preview` segue o modo demo |
| `audit.admin_extension_namespaces`, `dashboards.*`, plugin do Filament | twstec/kit-admin | as telas da demo no `/admin`, auditadas; a variante `content`; as últimas submissões na Visão geral |
| `security.headers.surfaces.landing_alt` | twstec/kit-foundation | a CSP própria de `/v2` |
| `SiteLinks` | starter | âncoras da landing, `/ui` e contato no cabeçalho e no rodapé |
| tag `database.seeders` (`DatabaseSeeder`) | starter | o `DemoSeeder` no `db:seed` |
| traduções | todos | as dela (o aplicativo vence) e o texto "de demo" das mensagens neutras de conta protegida |

Os pontos do **starter** (`SiteLinks` e o agregador de seeders) são os únicos
nomes do aplicativo que o pacote usa, junto com o model `App\Models\User` dos
seeders — lista fechada, conferida pela suíte do pacote. Numa aplicação sem
eles, a demo sobe sem essas duas ligações.

## O que o pacote traz

| Peça | O que é |
| --- | --- |
| `routes/web.php` | `/`, `/v3`, `/v2`, `POST /contato`, `/ui`, `POST /ui/form-demo` (mesmos nomes e middlewares de sempre) |
| `config/landing.php`, `config/ui.php` | números da landing (`LANDING_*`) e as flags da demo no grupo `ui` (`DEMO_LOGIN_ENABLED`, `UI_SHOWCASE_ENABLED`, `DEMO_*`, `DEMO_ALLOW_IN_PRODUCTION`); o `config/` do aplicativo vence |
| `database/migrations` | `products`, `form_submissions` (e as colunas acrescentadas depois) e os gatilhos das contas demo — com os **mesmos nomes de arquivo** de quando a demo morava no aplicativo, então um banco existente não vê migration pendente |
| `resources/views`, `lang/` | as landings, a vitrine, o contato e o layout público `<x-layouts.landing>`; traduções nos três idiomas |
| `resources/js`, `resources/css`, `resources/img` | os bundles das duas landings e as capturas de tela |
| `vite.js` | o que a demo acrescenta ao build do Vite do aplicativo (entradas, imagens, fontes do Tailwind) — carregado pelo `vite.config.js` do starter só quando o pacote está instalado |
| `Support\DemoSurface` | a regra **fail-closed**: em `APP_ENV=production` a demo não existe, qualquer que seja a flag, a não ser com `DEMO_ALLOW_IN_PRODUCTION=true` declarado (e avisado no log a cada boot) |
| `Accounts\…` | a proteção das contas demo (model e gatilhos do PostgreSQL) |
| `Console\UninstallDemo` | `php artisan demo:uninstall [--drop-tables]` — tira do banco o que a demo instalou, antes de remover o pacote |
| `Database\Seeders\…`, `Database\Factories\…` | a massa fictícia e as contas demo |

## Remover

```bash
php artisan demo:uninstall --drop-tables   # gatilhos das contas demo; tabelas e registro das migrations da demo
composer remove --dev twstec/kit-demo
npm run build
```

Detalhes (e por que o primeiro passo existe) em
[docs/demo.md](../../docs/demo.md#como-remover).

## Testes

A suíte do pacote (Pest + Orchestra Testbench) sobe uma aplicação Laravel
limpa com os cinco pacotes do kit, um painel que só registra o `AdminPlugin`
e a demo — nada do starter — e prova o que a demo liga sozinha: pontos de
extensão, configuração, rotas, migrations, traduções, a proteção das contas
demo, o fail-closed de produção, o `demo:uninstall` e as regras do produto
que só leem os arquivos do pacote (escrita fora dos eventos de model, recusa
sem trilha, traduções nos três idiomas, echo cru nas views).

```bash
docker compose exec -w /var/packages/demo app ./vendor/bin/pest
docker compose exec -w /var/packages/demo app ./vendor/bin/pint --test
```

O que depende das telas, dos componentes Blade e do painel do starter (as
landings renderizadas, a vitrine, o `/admin` da demo, o gatilho contra
PostgreSQL) roda na suíte do starter: `starters/livewire/tests/Demo` e os
casos marcados com `->group('demo')`, que **pulam sozinhos** quando o pacote
não está instalado.
