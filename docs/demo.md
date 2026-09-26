# Demonstração do kit (`twstec/kit-demo`)

## O que é

Tudo o que existe para **mostrar** o kit — e não para ser a base de um
produto — é o pacote **`twstec/kit-demo`** (`packages/demo`, namespace
`Twstec\Kit\Demo`):

- as landings **"Céu"** (`/`) e **"O Rastro"** (`/v2`, e `/v3` → 301 para `/`),
  com o JS, o CSS e as imagens delas;
- a vitrine de componentes **`/ui`** (e o `POST /ui/form-demo`) e o
  formulário de **contato** (`POST /contato`, com o e-mail na galeria
  `/mail-preview`);
- no `/admin`: o catálogo de **produtos** de exemplo, a caixa de
  **submissões** de formulário e o dashboard **"Conteúdo & Operação"**;
- as **contas demo** de credenciais públicas (`demo@tws.dev` no painel,
  `admin@tws.dev` no `/admin`), protegidas contra alteração — inclusive no
  banco;
- os **seeders de dado fictício** (40 pessoas, projetos, chaves, uploads,
  histórico dos dashboards).

O produto **não conhece a demo**: nenhum arquivo do starter nem dos cinco
pacotes do kit a nomeia (uma trava de arquitetura reprova quem tentar —
`tests/Unit/Architecture/ModuleDependenciesTest.php`). Ela entra pela
**descoberta automática de pacotes** do Laravel e responde aos **pontos de
extensão** do produto:

| Ponto de extensão | O que a demo registra |
| --- | --- |
| `AccountProtection` (twstec/kit-auth) | as contas demo protegidas |
| `LoginPrefillProvider` (twstec/kit-auth) | as credenciais demo pré-preenchidas no login do painel e do `/admin` |
| `MailPreviewGate` (twstec/kit-foundation) | a galeria `/mail-preview` segue o modo demo |
| `SiteLinks` (starter) | âncoras da landing, `/ui` e contato no cabeçalho e no rodapé |
| seeders por tag (`DatabaseSeeder`, starter) | o `DemoSeeder` roda no `db:seed` |
| variantes e widgets de dashboard (twstec/kit-admin) | a variante `content` (ligada no fim da lista quando `DASHBOARD_ENABLED` não é declarado) e as últimas submissões na Visão geral |
| namespaces auditados do `/admin` | as telas da demo entram na trilha de auditoria |
| superfície de CSP `landing_alt` | a CSP própria de `/v2` |
| plugin do Filament | produtos, submissões e o dashboard no `/admin` |
| traduções (o aplicativo vence) | as mensagens neutras de "conta protegida" do produto ganham o texto "de demo" |

Sem a demo, `/` mostra a página inicial mínima do produto (rota `home`),
nenhuma conta é protegida, o login não sugere credencial nenhuma e a galeria
`/mail-preview` abre só com `MAIL_PREVIEW_ENABLED` (nunca em produção).

## Como está instalada: só no desenvolvimento

**A demo vive só no monorepo.** Ela não é publicada no Packagist nem tem
repositório só-leitura: quem cria um projeto a partir do starter publicado
(`composer create-project twstec/starter-livewire` ou
`laravel new --using=twstec/starter-livewire`) recebe o starter **limpo** — o
`composer.json` publicado não a cita (`.github/release/prepare-composer.php`),
`/` é a página inicial do produto e os testes da demo pulam sozinhos. O CI
confere isso a cada push (simulação da instalação publicada).

No monorepo, o `composer.json` do starter declara a demo em **`require-dev`** (por path
repository, `../../packages/demo`). Quem clona o kit e roda `composer install`
recebe a demo — é o ambiente de desenvolvimento de sempre, com a landing em
`http://localhost:8180`, as contas demo e a massa fictícia.

A **imagem de produção** instala as dependências com `composer install
--no-dev`: a demo **não vai para produção** — nem o pacote, nem as rotas, nem
as entradas das landings e as imagens no build do frontend (o
`vite.config.js` só acrescenta os assets da demo quando
`vendor/twstec/kit-demo` existe). O código dela nem entra no contexto do
build (`packages/.dockerignore`). O CI confere isso na imagem construída
(job "Imagens de produção", passo "Conferência da imagem do app").

Assets: as entradas da landing (`landing.js`, `landing-v2.js` e os CSS), as
imagens e as pastas que o Tailwind precisa ler vêm do `vite.js` do pacote,
carregado pelo `vite.config.js` do starter só com a demo instalada. As
dependências de front das landings (GSAP, Lenis, Three.js e as fontes
Caveat/Instrument Serif) continuam no `package.json` do starter (são
`devDependencies`, só entram no build quando alguma entrada as importa).

## Como remover

```bash
# 1. Num banco que já rodou a demo: tirar os gatilhos das contas demo
#    (PostgreSQL). Com --drop-tables, também as tabelas da demo (products,
#    form_submissions) e o registro das migrations dela.
docker compose exec app php artisan demo:uninstall --drop-tables

# 2. Tirar o pacote.
composer remove --dev twstec/kit-demo

# 3. Refazer o build do frontend (sem as entradas da demo).
npm run build

# 4. (Desenvolvimento) Tirar a massa fictícia que os seeders gravaram nas
#    tabelas do produto.
docker compose exec app php artisan migrate:fresh
```

O passo 1 existe porque o gatilho fica no banco depois que o pacote sai, e
quem o desligava (com o modo demo desligado) era o próprio pacote: sem ele,
`demo@…` e `admin@…` continuariam intocáveis **no banco**. Num banco novo
(`migrate:fresh`), nada disso é necessário.

Sem a demo, a suíte do starter passa com o **`pest` de sempre**: os testes do
grupo `demo` (tudo em `tests/Demo` e os casos do produto marcados com
`->group('demo')`) **pulam sozinhos**, com o motivo (`tests/TestCase.php`). O
CI prova isso a cada push (job `Pest · SQLite`, passos "Sem a demo").

## Demo pública hospedada: banco efêmero e reset

A senha do admin demo é **pública** — está no `.env.example`, no README e
pré-preenchida no login do `/admin`. Qualquer visitante entra como super
admin e vê o que o anterior deixou: submissões de formulário com texto livre,
uploads, nomes. Por isso uma demo pública hospedada **nunca** é uma
instalação com dado real, e precisa de:

1. **Banco efêmero**: um banco só dela, descartável, sem nada que não seja
   dado de demonstração.
2. **Reset periódico**: recriar o banco do zero em intervalo curto (por
   exemplo, a cada hora: `php artisan migrate:fresh --seed --force` num
   agendamento, com a fila e o armazenamento de uploads limpos junto), para
   que o que um visitante escreveu não fique para o próximo.
3. **Opt-out declarado**: em `APP_ENV=production` a demonstração não existe
   (fail-closed, abaixo); a demo hospedada liga `DEMO_ALLOW_IN_PRODUCTION=true`
   e aceita o aviso no log a cada boot.
4. **Imagem própria**: a imagem de produção do starter não leva a demo
   (`--no-dev`). A demo hospedada é um deploy à parte, que instala o pacote
   de propósito.

## Login demo e admin demo (fricção zero em dev)

> **Em produção nada disto existe**, e isso não depende de ninguém lembrar de
> desligar flag nenhuma: ver
> [Superfície de demonstração: fail-closed em produção](#superfície-de-demonstração-fail-closed-em-produção).

Quando `DEMO_LOGIN_ENABLED=true` (**padrão só em `APP_ENV=local`**), a tela de
login mostra um aviso e vem com as credenciais demo pré-preenchidas — basta
clicar em "Entrar" (padrão demo.filamentphp.com). A MESMA flag ativa o **admin
demo**: usuário com `is_admin` e credenciais pré-preenchidas em `/admin/login`
(página própria `Twstec\Kit\Admin\Pages\Auth\Login`, do pacote twstec/kit-admin), com link "Ver admin demo" na
landing. Os dois usuários são criados pelo `DemoUserSeeder` + `DemoAdminSeeder`,
chamados automaticamente pelo `DemoSeeder` (que o `DatabaseSeeder` roda) quando
o flag está ligado:

```bash
docker compose exec app php artisan migrate --seed   # cria os usuários demo
# painel:  demo@tws.dev / Demo-password1        (DEMO_USER_EMAIL/PASSWORD)  → "Cliente Demo"
# /admin:  admin@tws.dev / Demo-admin-password1 (DEMO_ADMIN_EMAIL/PASSWORD) → "Admin Demo"
```

As senhas demo obedecem à **mesma política de senha do app** e passam
mesmo com todas as regras ligadas — senha de demonstração que a própria
validação do produto recusaria é armadilha, não conveniência. Um teste
(`tests/Feature/Auth/PasswordPolicyTest.php`) prova isso e que o login com
elas funciona.

## Superfície de demonstração: fail-closed em produção

O kit nasce com a demonstração inteira ligada — login demo de um clique com as
credenciais impressas na tela, super admin demo (`admin@tws.dev`, senha
publicada no `.env.example`), vitrine `/ui`, galeria `/mail-preview` e seeders
que enchem o banco de dado fictício. Isso é excelente em desenvolvimento e é uma
**porta dos fundos** em produção.

Até a versão anterior, a única barreira era a flag (`DEMO_LOGIN_ENABLED`,
`UI_SHOWCASE_ENABLED`) — e o `.env.example` entrega as duas ligadas. Ou seja: o
caminho normal de um starter kit (`cp .env.example .env`, ajustar, subir) levava
a demonstração toda para produção. **Esquecer não pode ser o mesmo que
autorizar.**

Quem decide é `Twstec\Kit\Demo\Support\DemoSurface` (`packages/demo/src/Support/DemoSurface.php`), e a regra é: em
`APP_ENV=production` a superfície de demonstração **não existe**,
independentemente do que as flags disserem. As flags continuam valendo — mas
como **segunda** barreira, para desligar a demo fora de produção.

Em produção, portanto:

| Superfície | O que acontece | Por que assim |
| --- | --- | --- |
| `/ui`, `POST /ui/form-demo`, `/mail-preview` | **404** | 403 confirmaria que a rota existe e está a uma flag de distância de abrir; 404 é indistinguível de rota que nunca foi escrita. As rotas seguem **registradas** (o rodapé e o menu do site geram `route('ui.showcase')` incondicionalmente — desregistrar derrubaria a home com `RouteNotFoundException`) |
| Credenciais demo no login do painel e do `/admin` | não aparecem e não são pré-preenchidas | entregar `admin@tws.dev` com a senha pública já digitada no login do super admin é a forma mais curta de perder a instalação |
| Seeder demo chamado **direto** (`db:seed --class='Twstec\Kit\Demo\Database\Seeders\DemoAdminSeeder'`) | **lança exceção** | quem chamou aquele seeder **pediu** aquela conta; terminar com "DONE" sem criar nada faria a pessoa acreditar que ela existe |
| `db:seed` (o agregador `DatabaseSeeder`, que roda o `DemoSeeder`) | **avisa no console e segue** | é o que um script de deploy roda; derrubar o deploy por causa de dado de demonstração trocaria uma armadilha por outra |
| `APP_DEBUG=true` | forçado para `false`, com aviso no log | fechar o vazamento sem derrubar o site: recusar o boot transformaria uma configuração errada em site fora do ar |

> **Em produção não se roda `db:seed`.** O kit não tem seeder de dado
> estrutural (papéis, permissões, planos e configuração vêm das migrations e do
> `.env`), então tudo o que a semeadura faria é dado de demonstração. O
> container `migrate` do `docker-compose.prod.yml` roda apenas
> `migrate --force`, sem `--seed`.

### Escape hatch: demo pública hospedada (`DEMO_ALLOW_IN_PRODUCTION`)

O roadmap prevê uma **demo pública hospedada com reset automático** — que é,
legitimamente, uma demonstração rodando em produção. Esse caso tem um caminho, e
ele é **declarado**:

```bash
DEMO_ALLOW_IN_PRODUCTION=true
```

A variável não tem valor padrão verdadeiro, não aparece descomentada em nenhum
`.env` de exemplo e, enquanto estiver ligada em produção, a aplicação grava um
aviso no log **a cada boot** (`DemoServiceProvider`): um opt-out de segurança que
ninguém vê deixa de ser decisão e volta a ser esquecimento. **Silêncio nunca
significa permitido.**

Ligar isso é dizer "este banco é descartável e estas credenciais são públicas".
Nunca numa instalação com dado real.

Cobertura: `tests/Demo/Feature/Security/DemoSurfaceProductionTest.php` (starter) e `packages/demo/tests/Protections/DemoProtectionsTest.php` (suíte do pacote, numa aplicação limpa).

## Contas demo são intocáveis: como e por quê

> **Verificação de e-mail**: as contas demo nascem com o e-mail confirmado e,
> enquanto o modo demo está ligado, contam como confirmadas mesmo que alguém
> zere `email_verified_at` (campo que a blindagem deixa livre) — senão o
> próximo visitante ficaria preso na tela de aviso esperando um e-mail que
> ninguém recebe. Ver
> [Verificação de e-mail](autenticacao.md#verificação-de-e-mail-no-cadastro).

As duas contas demo (`demo@tws.dev` e `admin@tws.dev` — e-mails do
`config/ui.php` do pacote, chaves `ui.demo_login.email` e `ui.demo_admin.email`) são a porta de entrada de quem está avaliando o kit. Se um
visitante troca a senha, o e-mail, a flag de admin ou a situação de uma
delas, ele não quebra a demo dele: quebra a de **todo mundo que chegar
depois**, e alguém precisa de shell no servidor para consertar.

Antes, só a interface do Filament recusava (`UserAdminGuard`). Isso
protege a demo do visitante, não de um `php artisan tinker` com três
linhas. Agora a proteção tem **três camadas**, e cada uma cobre o buraco da
anterior:

| Camada | Onde | Pega |
| --- | --- | --- |
| 1. UI | `UserAdminGuard` (Filament) | o clique no painel — esconde a ação e recusa no servidor, com mensagem amigável |
| 2. Model | eventos `updating`/`deleting` do `User` (`DemoAccountGuard`) | tinker, comando artisan, job, importação — tudo que passa por Eloquent; lança `DemoAccountProtectedException` |
| 3. Banco | triggers no PostgreSQL (`DemoAccountTrigger`, migrations) | o que **não** dispara evento: `User::where(...)->delete()`, `->update([...])`, `TRUNCATE users` (inclusive o `TRUNCATE ... CASCADE` de outra tabela que arrasta `users`), SQL cru, cliente externo |

**Por que trigger e não scope global.** Um scope global resolveria o
update/delete em massa — mas ao preço de **esconder** as contas demo de toda
leitura, inclusive do login, que é justamente o que a demo precisa fazer.
Seria trocar um buraco por outro. O único lugar de onde nada escapa é o
próprio banco.

**Por que dois triggers.** O trigger de linha (`BEFORE UPDATE OR DELETE ...
FOR EACH ROW`) nunca é chamado por um `TRUNCATE`: o TRUNCATE descarta o
armazenamento da tabela de uma vez, sem passar por linha, e o PostgreSQL só
oferece trigger de TRUNCATE no nível da **sentença**. Por isso existe um
segundo, `BEFORE TRUNCATE ... FOR EACH STATEMENT`, que recusa o TRUNCATE de
`users` enquanto houver conta demo na tabela. Ele obedece à mesma flag de
sessão e, portanto, à mesma porta de serviço (`withoutProtection()`). Não
atrapalha o desenvolvimento: `migrate:fresh`/`db:wipe` (e o `RefreshDatabase`
dos testes) apagam a tabela com `DROP`, não com TRUNCATE.

**O que nenhum trigger cobre.** Quem é **dono** da tabela pode desligar
trigger (`ALTER TABLE ... DISABLE TRIGGER`) ou apagá-la (`DROP`). A camada 3
fecha o acidente e o atalho — o update/delete/truncate distraído de código,
tinker ou psql —, não um atacante com acesso de dono ao banco; contra esse, a
defesa é a aplicação conectar com uma role que não é dona do esquema, decisão
de infraestrutura.

**O que é bloqueado e o que continua livre.** Bloquear tudo transformaria a
demo numa vitrine congelada: quem entra precisa conseguir trocar o nome,
subir uma foto, mudar idioma e tema — é isso que se está demonstrando. O
corte é por **consequência**:

| | Campos | Por quê |
| --- | --- | --- |
| **Bloqueado** | `email`, `password`, `is_admin`, `status`, `two_factor_enabled_at` | mudam **quem entra e com qual poder**; trocar qualquer um derruba o acesso do próximo visitante (o último é a verificação em duas etapas do login: ligada na demo, o código iria para uma caixa que ninguém lê) |
| **Livre** | `name`, `avatar_upload_id`, `locale`, `theme`, `notification_preferences`, `transaction_password` (+ timestamps e controle) | mudam **aparência e preferências**; o pior caso é a demo aparecer com o nome que o último visitante escreveu — e o seeder devolve o original |

A lista vive em `DemoAccountGuard::SENSITIVE_ATTRIBUTES` e é a **mesma nas
três camadas** (o trigger é gerado a partir dela).

**Quando vale**: só com o modo demo ligado (`DemoSurface::loginEnabled()` — a
flag `DEMO_LOGIN_ENABLED` somada ao fail-closed de produção). Em produção o modo
está desligado e as contas demo não deveriam existir — apagar `demo@…` de um
banco de produção tem de continuar possível, **inclusive quando alguém deixou a
flag ligada por engano**; é por isso que a pergunta passa pelo `DemoSurface` e
não pela flag crua. Fora do
PostgreSQL (o SQLite da suíte de testes) a camada 3 não existe e as camadas
1 e 2 seguem valendo.

**O trigger segue o modo demo da aplicação, não o da instalação.** O trigger
é instalado (ou removido) conforme o modo demo no momento da migration ou do
seed. Uma base que teve o modo demo ligado e passou a rodar com ele desligado
continuaria com o trigger — e o `migrate` do deploy não mexe nisso, porque as
migrations que o instalaram já rodaram. Para o banco não contradizer a
aplicação, a aplicação é a fonte da verdade (`DemoAccountSession`): com o
modo demo **desligado**, toda conexão da aplicação com o PostgreSQL desliga a
proteção na própria sessão, com a mesma flag que o trigger consulta
(`tws.demo_guard`) e que a porta de serviço abaixo usa. Com o modo demo
**ligado**, nada muda: a sessão nasce sem a flag e o trigger vale.

- A decisão é tomada quando a conexão física abre (o Laravel abre o PDO de
  forma preguiçosa — configurar uma conexão não abre socket, então
  `composer install` e `config:cache` continuam rodando sem banco), com o
  mesmo critério da camada 2 (`DemoSurface::loginEnabled()`, com o
  fail-closed de produção).
- Reconexão (`DB::reconnect()`, conexão perdida, `DB::purge()`) abre uma
  sessão nova, que recebe a flag de novo.
- **Limite:** com um pooler em modo transação (PgBouncer
  `pool_mode=transaction`) a variável de sessão não acompanha a aplicação de
  uma transação para a outra. Nesse caso, com o modo demo desligado, rode uma
  vez `php artisan tinker --execute="Twstec\Kit\Demo\Accounts\DemoAccountTrigger::install()"`
  — com o modo desligado, o `install()` remove o trigger do banco.

**A porta de serviço**: `DemoAccountGuard::withoutProtection(fn () => ...)`
suspende as três camadas — é o que os seeders demo usam para criar e
atualizar as próprias contas. Não use isso em código de aplicação: se a
operação precisa mudar a senha da conta demo, ela está errada.

Comandos que tocam usuários respeitam a regra: `user:make-admin` recusa
promover o cliente demo e rebaixar o admin demo, com erro legível no
console em vez de stack trace.

Testes: `tests/Demo/Feature/Admin/DemoAccountHardeningTest.php` e
`tests/Demo/Feature/Admin/DemoAccountSessionTest.php` (os testes do
trigger só rodam quando a suíte aponta para o PostgreSQL — o CI roda assim;
no `pest` local em SQLite aparecem como `skip`).
