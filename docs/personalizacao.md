# Personalização: textos, e-mails, configuração e telas

O padrão dos textos, do corpo dos e-mails, da configuração e das telas do kit
**mora nos pacotes** `twstec/kit-*`. O projeto criado não recebe cópias: só tem o
arquivo se quiser mudar algo, e nesse arquivo escreve **só o que muda**.

Por quê: cópia parada no aplicativo vence o pacote para sempre. Com cópias, a
correção de um texto, de um e-mail ou de um valor padrão não chegava pelo
`composer update` — cada atualização pedia para copiar arquivos à mão. Sem
cópias, o que você não mudou acompanha o kit sozinho.

A regra geral é a mesma em todos os casos: **o aplicativo vence**.

## Textos (traduções)

Os grupos do kit (`auth`, `panel`, `mail`, `passwords`, `validation`,
`pagination`, `uploads`, `webhooks`, `admin`…) e os JSON (`pt_BR.json`,
`es.json`) vêm dos pacotes. Para mudar um texto, crie
`lang/<idioma>/<grupo>.php` com **só as chaves que mudam**:

```php
<?php

declare(strict_types=1);

// lang/pt_BR/auth.php — só o que este projeto muda.
return [
    'failed' => 'E-mail ou senha não conferem.',
];
```

A junção é **chave a chave, em qualquer profundidade**: as outras chaves do
grupo continuam vindo do pacote (e das atualizações dele). Vale também para o
JSON: `lang/pt_BR.json` do aplicativo com uma frase só troca aquela frase.
Chave nova, que o kit não tem, também pode morar ali.

## E-mails

O corpo de cada e-mail do kit (`mail.messages.<slug>`: código de verificação,
recuperação de senha, convite de conta, avisos de chave de API…) vem do
pacote dono do e-mail; o layout e os componentes vêm do `kit-foundation` (ver
[emails.md](emails.md)). Para mudar um corpo, copie **só aquele arquivo** do
pacote para `resources/views/mail/messages/<slug>.blade.php`: o arquivo de
mesmo nome no aplicativo vence; sem ele, vale o do pacote.

Um e-mail copiado deixa de receber as correções do pacote. Copie só se for
mudar o HTML; para mudar o texto, mude a tradução (as strings de todo e-mail
estão no grupo `mail`).

## Configuração

Cada pacote traz o seu `config/<arquivo>.php` com o padrão (`security`,
`audit`, `settings`, `platform`, `tracing`, `idempotency`, `database_roles`,
`auth`, `accounts`, `api_keys`, `uploads`, `webhooks`, `admin`,
`dashboards`…). Todo valor solto tem variável no `.env` — **esse é o caminho
para mudar um valor**.

Para mudar um bloco, crie `config/<arquivo>.php` com só as **chaves de
primeiro nível** que mudam. Cada uma **vence a do pacote inteira**; as que
você não escreve vêm do pacote (é a regra do `mergeConfigFrom` do Laravel):

```php
<?php

declare(strict_types=1);

// config/uploads.php — só PDF neste projeto. O bloco `types` inteiro é este;
// as outras chaves (disco, quarentena…) continuam vindo do pacote.
return [
    'types' => [
        'pdf' => [/* … copie o bloco do pacote e ajuste … */],
    ],
];
```

Por que inteira, e não chave a chave: muita configuração do kit é lista de
permissões em forma de mapa (tipos de upload aceitos, chaves editáveis no
`/admin`, escopos das chaves de API). Com junção profunda, o projeto que
restringiu um desses mapas ganharia de volta, em silêncio, o que tirou.

O `config/auth.php` é do framework e do kit ao mesmo tempo: o do aplicativo
traz as chaves do Laravel (`guards`, `providers`, `passwords`…) e só as do
kit que ele mudar.

Com `php artisan config:cache`, o cache guarda o resultado final da junção.

### O que um módulo acrescenta a outro

Alguns módulos opcionais acrescentam itens à configuração de outro pacote, sem
cópia no aplicativo:

| Módulo | Acrescenta |
| --- | --- |
| `kit-auth` | os códigos de verificação e os tokens de ação sensível em `audit.ignored_models` |
| `kit-accounts` | a expiração das chaves de API em `settings.overrides` (editável no `/admin`) |
| `kit-uploads` | os limites de tamanho em `settings.overrides`; com `kit-accounts`, o escopo `uploads` em `api_keys.scopes_catalog` |

A lista ganha os itens que faltam; o mapa, as chaves que faltam. **Se o
aplicativo escreveu aquela chave de primeiro nível** (`ignored_models`,
`overrides`, `scopes_catalog`), ela é dele inteira e o módulo não acrescenta
nada — inclua ali os itens do módulo que quiser manter.

A exceção é a demonstração (`kit-demo`): a variante "Conteúdo & Operação" dos
dashboards, o widget dela, o escopo de auditoria das telas dela e a superfície
de CSP da landing alternativa entram mesmo numa chave que o aplicativo
escreveu, como sempre entraram (instalar a demo é pedir as telas dela).

## Telas React

No starter React, as páginas, os layouts, os componentes (inclusive os do
shadcn/ui em `components/ui`), os hooks, os tipos e o `lib/` das telas do kit
— entrada, cadastro, segundo fator, recuperação de senha, perfil, senha de
transação, notificações, conta e membros, convites, chaves de API, projetos e
webhooks — moram no `twstec/kit-foundation`, em `resources/react`, com a
**mesma árvore** de `resources/js`. O projeto criado tem só o que é dele:
`resources/js/app.tsx`, a página inicial (`pages/welcome.tsx`) e o dashboard
(`pages/dashboard.tsx`).

Para mudar uma tela ou um componente do kit, copie **só aquele arquivo** de
`vendor/twstec/kit-foundation/resources/react/` para o **mesmo caminho** em
`resources/js/`:

```bash
mkdir -p resources/js/pages/auth
cp vendor/twstec/kit-foundation/resources/react/pages/auth/login.tsx resources/js/pages/auth/
```

A cópia vale no lugar da do kit — também para as telas do kit que a importam
(copiar `components/ui/button.tsx` muda o botão de todas as telas). O que
você não copiou continua vindo do pacote e das atualizações dele. Uma tela
nova do produto é só criar o arquivo em `resources/js/pages`.

Quem aplica a regra:

- **`@/…` no Vite:** o plugin `vendor/twstec/kit-foundation/vite/react.js`
  (no `vite.config.ts`) procura primeiro em `resources/js` e, sem o arquivo,
  no pacote; ele também acrescenta a pasta das telas às fontes do Tailwind;
- **páginas do Inertia:** o `resolvePage` (`lib/pages.ts`, usado no
  `app.tsx`), na mesma ordem; no servidor, o documento raiz pede ao manifesto
  do Vite a página que vale (`ReactScreens::pageEntry`) e o
  `config/inertia.php` conhece as duas pastas (para os testes);
- **tipos:** o `tsconfig.json` tem os dois lugares no `paths` do `@/*`, nessa
  ordem, e inclui a pasta do pacote na checagem — uma tela do kit que importa
  um componente que você mudou é conferida contra a sua versão.

Uma tela copiada deixa de receber as correções do kit: confira o arquivo do
pacote a cada atualização (o `kit:overrides` lista as suas cópias).

## Telas Livewire

No starter Livewire, as views das telas do kit — entrada, cadastro, segundo
fator, recuperação de senha, senha de transação, convite, perfil,
notificações, conta e membros, chaves de API, projetos, webhooks, a página
429, a galeria de e-mails, o seletor de idioma do `/admin`, os layouts
(`layouts/app`, `layouts/auth`, `components/layouts/site`), os componentes do
design system (`<x-button>`, `<x-input>`, `<x-modal>`…) e as parciais —
moram no `twstec/kit-foundation`, em `resources/livewire`, com os **mesmos
nomes de view** de sempre (`auth.login`, `livewire.profile`,
`components.button`). O projeto criado tem só o que é dele:
`resources/views/home.blade.php` (a página inicial) e
`resources/views/livewire/dashboard.blade.php` (o dashboard).

Para mudar uma tela ou um componente do kit, copie **só aquele arquivo** de
`vendor/twstec/kit-foundation/resources/livewire/` para o **mesmo caminho** em
`resources/views/`:

```bash
mkdir -p resources/views/components
cp vendor/twstec/kit-foundation/resources/livewire/components/button.blade.php resources/views/components/
```

A cópia vale no lugar da do kit, também dentro das telas do kit (o
`<x-button>` copiado muda o botão de todas as telas). Quem liga as telas é o
`AppServiceProvider` do starter (`LivewireScreens::register`): a pasta do
pacote entra por último na busca das views, no `view.paths` (para as páginas
de erro) e no namespace `layouts::` (o layout das páginas Livewire). O
Tailwind lê a pasta pelo `@source` do `resources/css/app.css` (e do
`filament.css`, para o seletor do `/admin`).

As classes dos componentes Livewire (`app/Livewire`), os controllers das
telas e os apoios (`App\Livewire\Support\…`) continuam no aplicativo — as
views do kit os usam pelos nomes de sempre.

## O que o projeto sobrescreve: `kit:overrides`

```bash
php artisan kit:overrides
```

Compara cada arquivo do aplicativo que tem padrão no kit (traduções, views de
e-mail, configuração e telas) com esse padrão, sem alterar nada:

- **igual ao padrão do kit — pode apagar**: o arquivo é só cópia; apagado,
  nada muda e as atualizações passam a chegar;
- **personaliza N chaves**: as chaves que diferem do padrão (na configuração,
  as de primeiro nível); as chaves **iguais ao padrão podem sair** do arquivo;
- **chaves que o kit não tem**: são do próprio projeto e ficam. Arquivo só
  com chaves do projeto nem aparece;
- **view ou tela personalizada**: confira o arquivo do pacote a cada
  atualização.

Projeto criado depois desta mudança, sem nada personalizado, ouve que nenhum
arquivo sobrescreve o kit.

## O que fica no projeto: `tws:upgrade --check`

Docker (`compose.yaml`, `docker-compose.prod.yml`, `docker/`), CI
(`.github/`), `.env.example`/`.env.prod.example`, `scripts/`, a configuração
do Vite e do TypeScript, o `package.json`, os testes, as rotas e os providers
**não podem morar num pacote**: são do projeto desde a criação. Quando o kit
muda um deles, a mudança continua sendo aplicada à mão — mas sem garimpar:

```bash
composer update 'twstec/*' --with-all-dependencies
php artisan tws:upgrade --check          # só lista; não altera nada
php artisan tws:upgrade --check --diff   # com a diferença de cada arquivo
```

O comando compara cada arquivo de starter do projeto com o do **starter
publicado da versão do kit instalada** (`twstec/starter-react` ou
`twstec/starter-livewire`, baixado pelo Composer sem instalar nada e guardado
em `storage/framework/cache/tws-starters/`). Sem rede, ou no monorepo, aponte
uma pasta com o starter publicado: `--from=<pasta>`. Ficam fora da conta o que é
gerado (`vendor`, `node_modules`, `storage`, build), os lockfiles, o `.env`, o
`composer.json` (é do projeto desde a criação; as dependências do kit sobem
pelo `composer update`, e mudança de script vem no CHANGELOG), o README, o
SECURITY e a licença.

A **base** fica em `.tws/manifest.json` (versione junto com o projeto): o
`tws:install` grava, na criação, o hash de cada arquivo do starter como chegou
e do projeto como o instalador o deixou. Com ela, cada arquivo que o kit mudou
desde a base sai como:

- **desatualizado — pode substituir**: você não mexeu e o arquivo era o do
  kit: copie o da referência;
- **personalizado — comparar à mão**: o seu é diferente (você mexeu, ou o
  instalador o adaptou na criação — nome, banco, identidade): leve a mudança
  do kit para o seu, com o CHANGELOG ao lado;
- **novo no kit** / **o kit deixou de trazer**: copie, ou veja no CHANGELOG se
  pode apagar.

O que o kit **não** mudou desde a base não aparece, mesmo que você tenha
personalizado. Arquivo que o projeto não tinha na base (módulo que ficou de
fora, arquivo apagado de propósito) também não.

Depois de conferir e atualizar, grave a base nova — é o único arquivo que o
comando escreve, e só com essa opção:

```bash
php artisan tws:upgrade --record
```

Saída: `0` nada a conferir, `1` há arquivos a conferir, `2` sem referência
(sem rede e sem `--from`, ou versão do kit que não é publicada).

**Projeto criado antes desta versão (sem `.tws/manifest.json`):** o comando só
consegue dizer o que **difere** da referência — sem saber se é personalização
ou atraso. Confira a lista uma vez, atualize o que for do kit e grave a base
com `--record`; a partir daí, só aparece o que o kit mudar.

## Projeto criado antes (com as cópias)

Continua funcionando igual: as cópias completas vencem o pacote como sempre.
Para voltar a receber as correções, rode `php artisan kit:overrides`, apague
os arquivos marcados como iguais ao padrão e, nos personalizados, deixe só as
chaves que você mudou. Rode a suíte de testes depois.

**Telas React:** antes de apagar qualquer cópia de `resources/js`, o projeto
precisa saber achar as telas no pacote — copie do starter React da versão
nova o `vite.config.ts` (o `kitReact()`), o `tsconfig.json` (o `paths` e o
`include` com a pasta do pacote, e o `preserveSymlinks`), o `app.tsx` (o
`resolve: resolvePage`), o `resources/views/app.blade.php` (a entrada da
página pelo `ReactScreens::pageEntry`), o `config/inertia.php` (a pasta de
páginas do pacote) e, no `compose.yaml` de desenvolvimento, o `depends_on` do
serviço `vite`. Depois apague o que o `kit:overrides` marcar como igual ao
padrão e rode `npm run types:check`, `npm run build` e a suíte.

**Telas Livewire:** antes de apagar as cópias de `resources/views`, copie do
starter Livewire da versão nova a linha `LivewireScreens::register($this->app)`
do `AppServiceProvider` e os `@source` das telas do kit em
`resources/css/app.css` e `resources/css/filament.css`. Depois apague o que o
`kit:overrides` marcar como igual ao padrão e rode `npm run build` e a suíte.
