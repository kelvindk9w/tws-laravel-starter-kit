# Atualização: da 1.x para a 2.0, e entre versões da 2.0

Este guia é para quem tem um projeto criado com o kit e quer trazê-lo para a
2.0. O detalhe de cada versão continua na seção "Atenção ao atualizar" (ou
"Atualizando da …") dela no [CHANGELOG](../CHANGELOG.md); aqui está o
caminho inteiro, na ordem.

## O que muda na 2.0

Na **1.x**, o projeto era uma cópia do repositório do kit: todo o código do
kit morava no aplicativo (`app/Core/<Módulo>`, `app/Filament`, as views, as
traduções, a configuração) e cada correção do kit tinha de ser copiada à mão.

Na **2.0**, o kit vem em pacotes Composer:

| Pacote | O que era na 1.x |
| --- | --- |
| `twstec/kit-foundation` | `App\Core\{Identifiers,Money,Http,Security,Logging,Localization,Settings,Mail,Support,Backup,Audit}` → `Twstec\Kit\Foundation\…` |
| `twstec/kit-auth` | `App\Core\Auth\…` → `Twstec\Kit\Auth\…` |
| `twstec/kit-accounts` (opcional) | `App\Core\Tenancy\…` e `App\Core\ApiKeys\…` → `Twstec\Kit\Accounts\Tenancy\…` e `…\ApiKeys\…` |
| `twstec/kit-uploads` (opcional) | `App\Core\Uploads\…` → `Twstec\Kit\Uploads\…` |
| `twstec/kit-admin` (opcional) | `App\Filament\…` → `Twstec\Kit\Admin\…` |
| `twstec/kit-webhooks` (opcional) | novo na 2.0 |
| `twstec/kit-installer` (`require-dev`) | novo: `tws:install`, `tws:add`, `tws:upgrade` |

A demonstração (landings, `/ui`, catálogo, contato, contas demo) virou o
`twstec/kit-demo`, que vive só no monorepo e **não vai para o projeto
criado** nem para a produção.

O projeto criado guarda só o que é dele e o que **sobrescreve**: textos,
e-mails, configuração e telas do kit vêm dos pacotes, e o projeto só tem o
arquivo se mudar algo — e nesse arquivo, só o que muda (ver
[Personalização](personalizacao.md)). O que não pode morar num pacote
(Docker, CI, `.env*`, `scripts/`, Vite, testes, rotas, providers) continua
no projeto, e o `php artisan tws:upgrade --check` aponta o que a versão nova
mudou nesses arquivos.

Os nomes antigos (`App\Core\…`, `App\Filament\…`) **continuam resolvendo**
durante a 2.x, por apelidos preguiçosos dos pacotes (um job que estava na
fila no deploy, uma config ou um `use` que ainda não foi trocado). Os apelidos
saem na 3.0: troque os `use` do seu código.

## Antes de começar

1. **Backup do banco** e uma **janela de manutenção**: a migração das contas
   roda numa transação e trava projetos e chaves até terminar (ver "Riscos de
   deploy conhecidos" na 2.0.0-beta.1 do CHANGELOG).
2. Leia, no CHANGELOG, "A 2.0 em resumo", "Atualizando da 1.x para a 2.0
   (resumo)" e as duas seções "Quebra de compatibilidade" (contas com
   membros e uploads da conta) da 2.0.0-beta.1 — e o "Atenção ao atualizar"
   de cada beta seguinte.
3. Separe o que é **do produto** no projeto 1.x (models, migrations, rotas,
   telas, testes e traduções próprias) do que é **do kit** (tudo em
   `app/Core`, `app/Filament`, a demonstração e as views, traduções e
   configs que você não mudou).

## Caminho: um projeto 2.0 novo, com o código do produto por cima

Não há comando que converta uma cópia da 1.x no lugar: o código do kit que
está no projeto sai e os pacotes entram. O caminho mais curto é criar um
projeto 2.0 e trazer para ele o que é do produto.

### 1. Criar o projeto 2.0

```bash
composer create-project "twstec/kit:^2.0@beta" meu-projeto   # na 2.0.0 estável: twstec/kit
```

Ou só com o Docker (`docker compose run --rm instalar`), ou por um starter —
ver [Instalação](instalacao.md). Nas perguntas:

- **interface:** a 1.x era Livewire; escolha **Livewire** para levar as telas
  do produto como estão (React é outro front);
- **módulos opcionais:** os que o projeto usa (contas e API, uploads,
  `/admin`; webhooks é novo);
- **banco e Redis:** a 1.x era PostgreSQL com Redis e Horizon — mantenha
  **PostgreSQL** e **Redis** para usar o mesmo banco. A escolha
  (`--database=pgsql|sqlite`, `--redis`/`--no-redis`, ou `TWS_KIT_DATABASE`
  e `TWS_KIT_REDIS`) só vale na criação: num projeto que já existe, o
  instalador a ignora, com aviso.

O instalador grava `.tws/manifest.json` (versione-o): é a base que o
`tws:upgrade` usa daqui em diante.

### 2. Trazer o código do produto

- Copie o que é do produto (models, migrations próprias, rotas, controllers,
  componentes Livewire, views, testes, seeders). **Não copie** `app/Core`,
  `app/Filament` do kit nem a demonstração.
- No seu código, troque os `use` de `App\Core\…` e `App\Filament\…` pelos
  nomes da tabela acima (os apelidos seguram só até a 3.0).
- Ajuste o código às quebras da 2.0 (CHANGELOG, 2.0.0-beta.1): projetos,
  chaves e uploads são **da conta**, não da pessoa (`account_id` +
  `created_by`); consulta a esses dados fora de uma requisição precisa de
  `Accounts::asSystem('motivo', fn () => …)` ou
  `Accounts::actingAs($conta, fn () => …)`; `tenant()` devolve a conta; e os
  serviços que mudaram de nome. A trava de arquitetura do starter aponta
  consulta que pula o escopo da conta.
- O model de usuário é do aplicativo (`App\Models\User`, com o contrato e as
  traits do `twstec/kit-auth`): leve para o do projeto novo só as colunas e
  relações do produto.

### 3. Levar só o que você personalizou

Textos (`lang/<idioma>/<grupo>.php` e os JSON), corpos de e-mail
(`resources/views/mail/messages/<slug>.blade.php`), configuração
(`config/<arquivo>.php`) e telas (no Livewire, `resources/views/…`; no
React, `resources/js/…`): leve do projeto 1.x **só o que você mudou**, e
nesse arquivo só as chaves que mudam (na configuração, a chave de primeiro
nível inteira). Depois confira:

```bash
php artisan kit:overrides
```

Ele lista o que é só cópia do padrão (pode apagar), as chaves que o arquivo
personaliza e as telas copiadas. Cópia parada impede as correções do kit de
chegarem. Regras e exemplos: [Personalização](personalizacao.md).

### 4. O `.env`

Leve do `.env` da 1.x os **segredos** que já têm dado gravado com eles —
sem eles, o que foi cifrado ou assinado não abre mais:

- `APP_KEY` (e as anteriores em `APP_PREVIOUS_KEYS`);
- **pepper das chaves de API:** `API_KEYS_HASH_PEPPER`. Se a 1.x não tinha
  pepper dedicado (usava o fallback da `APP_KEY`), defina um pepper novo e
  ponha a `APP_KEY` atual em `API_KEYS_PREVIOUS_HASH_PEPPERS`: as chaves
  emitidas continuam autenticando e migram de hash no primeiro uso. Se o
  `.env` tinha `API_KEYS_HASH_PEPPER=` vazio, ver
  [Pepper vazio](api.md#chaves-emitidas-com-pepper-vazio-legado);
- as credenciais de banco, Redis, e-mail e backup.

Variáveis que a 2.0 traz (o `.env.example` e o `.env.prod.example` do
projeto criado trazem cada uma, comentada):

| Variável | O que faz |
| --- | --- |
| `DB_MIGRATION_USERNAME` / `DB_MIGRATION_PASSWORD` | Papel de **migração** (dono do esquema). A aplicação passa a entrar com outro papel (`DB_USERNAME`/`DB_PASSWORD`, sem posse nem superusuário), que o `migrate` cria. Sem `DB_MIGRATION_USERNAME`, nada muda, e o `php artisan db:check-roles` aponta o problema. |
| `DB_MAINTENANCE_USERNAME` / `DB_MAINTENANCE_PASSWORD` | Papel de **manutenção**, opcional: as podas das trilhas (`audit:prune`, `outbound-http:prune`) rodam com ele, e o agendador não precisa da senha do dono. Em produção, `PROD_DB_MAINTENANCE_PASSWORD` (e `PROD_DB_MAINTENANCE_USERNAME`, padrão `tws_maintenance`). |
| `PLATFORM_VERSION` | Versão do **produto** (no `GET /api/health`), não a do kit. O projeto criado começa em `0.1.0`; sem a variável, `0.1.0`. |
| `PLATFORM_NAME`, `PLATFORM_AVAILABLE_LOCALES`, `PLATFORM_FAVICON_URL`, `PLATFORM_FAVICON_SVG_URL` | Identidade do projeto. Sem `PLATFORM_AVAILABLE_LOCALES`, só o idioma do `PLATFORM_LOCALE`; para os três do kit, `pt_BR,en,es`. Ver [Identidade](identidade.md). |
| `API_KEY_PREFIX` | Prefixo do produto nas chaves de API novas (`acme_pk_live_…`), opcional; vazio mantém `pk_…`/`sk_…`. As emitidas antes continuam valendo. |
| `UPLOADS_ENCRYPTION_KEY` | Chave dos uploads **confidenciais** (própria, nunca a `APP_KEY`): `php artisan uploads:encryption-key --show`. Sem ela, upload confidencial é recusado. |
| `WEBHOOKS_EVENTS` | Eventos que o aplicativo emite, com o `twstec/kit-webhooks`. |
| `AUTH_PASSWORD_MIN` | A senha mínima padrão passou de 6 para **8** caracteres (vale para senhas novas). |

Em produção (`docker-compose.prod.yml`), passam a ser obrigatórias
`PROD_PLATFORM_NAME`, `PROD_DB_APP_PASSWORD` e
`PROD_DB_MAINTENANCE_PASSWORD`; e `PROD_SERVER_NAMES` declara os nomes do
site além do host da `PROD_APP_URL` (o nginx recusa host desconhecido). Ver [Produção](producao.md).

### 5. O banco

As migrations da 1.x continuam com os **mesmos nomes** na 2.0 (nos pacotes
ou no starter; as da demonstração ficam só registradas no banco): apontado para o banco da 1.x, o `migrate` roda só as novas —
cria as contas, passa projetos, chaves e uploads para elas, e cria o que a
2.0 acrescentou.

1. **Esvazie a fila antes** (`php artisan horizon:pause`, espere zerar,
   `php artisan horizon:terminate`): um job da 1.x que rode depois da
   migração não conhece a conta e falha.
2. **Gatilhos da demonstração:** o banco da 1.x tem os gatilhos que blindam
   as contas demo (`demo@…`, `admin@…`), e o projeto 2.0 não tem a demo para
   tirá-los (o `demo:uninstall` é do `twstec/kit-demo`). Sem tirá-los, essas
   contas continuam intocáveis **no banco**. Com o papel dono do banco:

   ```sql
   DROP TRIGGER IF EXISTS users_demo_account_guard ON users;
   DROP FUNCTION IF EXISTS tws_protect_demo_users();
   DROP TRIGGER IF EXISTS users_demo_account_truncate_guard ON users;
   DROP FUNCTION IF EXISTS tws_protect_demo_users_truncate();
   ```

   As tabelas da demo (`products`, `form_submissions`) ficam; apague-as se o
   produto não as usa.
3. **Papéis do PostgreSQL:** o usuário que o `.env` da 1.x usava (dono do
   banco) vira o de migração (`DB_MIGRATION_*`), e `DB_USERNAME`/`DB_PASSWORD`
   passam a ser o papel novo da aplicação. Passo a passo, em desenvolvimento
   e em produção (inclusive banco gerenciado):
   [Atualizar um projeto que já existe](producao.md#atualizar-um-projeto-que-já-existe).
4. `php artisan migrate` (em produção, o container `migrate` roda
   `migrate --force` e depois o `db:check-roles`; com a separação de papéis
   quebrada, a aplicação não sobe). Depois, reinicie fila e agendador.

### 6. Conferir

- A suíte do projeto (`scripts/verificar`, ou o Pest direto — ver
  [Testes](testes.md)) e `php artisan db:check-roles`.
- `/horizon` passou a exigir a permissão `horizon.access` (de fábrica, os
  papéis `owner` e `operations` do `/admin`).
- **Segredos no repositório:** o projeto criado traz o `scripts/segredos`
  (gitleaks em container). Rode-o uma vez no histórico **antes de
  qualquer outra coisa**: um achado num commit antigo é um segredo que já
  vazou — rotacione-o no serviço. Valor fictício de teste entra no
  `.gitleaks.toml` pelo valor exato. `scripts/instalar-hooks` liga o hook
  `pre-commit` (só o que está staged) e o `pre-push`. Ver
  [Segredos no repositório](seguranca.md#segredos-no-repositório-gitleaks).

## Entre versões da 2.0

Num projeto já na 2.0, cada atualização é:

```bash
composer update 'twstec/*' --with-all-dependencies   # textos, e-mails, config e telas do kit
php artisan kit:overrides                            # o que o projeto sobrescreve
php artisan tws:upgrade --check                      # arquivos do projeto que o kit mudou
php artisan tws:upgrade --check --diff               # com a diferença de cada um
```

O `tws:upgrade --check` compara os arquivos de starter do projeto com o
**starter publicado da versão do kit instalada** (`twstec/starter-react` ou
`twstec/starter-livewire`, baixado pelo Composer) e, com o manifesto, separa
"desatualizado — pode substituir" de "personalizado — comparar à mão". Não
altera nada. Sem rede, `--from=<pasta com o starter publicado>`; sem
manifesto, ele deduz a interface, ou `--starter=react|livewire`. Saída `0`
nada a conferir, `1` há arquivos a conferir, `2` sem referência.

Leve as mudanças com o "Atenção ao atualizar" da versão ao lado e, depois de
conferir, grave a base nova:

```bash
php artisan tws:upgrade --record   # o único arquivo que o comando escreve: .tws/manifest.json
```

**Projeto criado antes do manifesto** (antes da 2.0.0-beta.20, sem
`.tws/manifest.json`): o `--check` só sabe dizer o que **difere** da
referência. Confira a lista uma vez, atualize o que for do kit e grave a base
com `--record`; daí em diante, só aparece o que o kit mudar. Detalhes em
[Personalização](personalizacao.md#o-que-fica-no-projeto-twsupgrade---check).

**Projeto criado antes de textos, e-mails, configuração e telas morarem nos
pacotes** (antes da 2.0.0-beta.19 e da 2.0.0-beta.20): as cópias continuam
vencendo o pacote. Para voltar a receber as correções, ver
[Projeto criado antes (com as cópias)](personalizacao.md#projeto-criado-antes-com-as-cópias).
