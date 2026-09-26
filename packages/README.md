# Pacotes do kit

Os pacotes reutilizáveis do kit — o backend, sem interface, e o painel de
administração (o único com telas: é o mesmo nos dois starters). Cada pacote é
extraído do aplicativo em [`starters/livewire`](../starters/livewire) numa fase
própria, com a suíte de testes verde antes e depois.

| Pacote | Nome no Composer | O que traz | Depende de |
| --- | --- | --- | --- |
| [`foundation`](foundation) | `twstec/kit-foundation` | Segurança (filtro de ataques, limites, cabeçalhos, hosts e proxies), trilha de requisições e trilha de auditoria com redação LGPD, identificadores, dinheiro, idioma, configurações editáveis no banco, infraestrutura de e-mail e template, guarda de segredos e de backup | Laravel |
| [`auth`](auth) | `twstec/kit-auth` | Autenticação sem telas: login com bloqueio por tentativas, cadastro, verificação de e-mail, segundo fator por e-mail, senha de transação, ação sensível, política de senha, status da conta e contratos de resposta para qualquer front | foundation |
| [`accounts`](accounts) | `twstec/kit-accounts` | Contas e API sem telas: projetos, chaves de API (par pública/secreta, hash com pepper, escopos, vínculo com projetos, rotação com graça, expiração por inatividade), a autenticação e os limites da API, o envelope de erro e a API v1 | foundation, auth |
| [`uploads`](uploads) | `twstec/kit-uploads` | Uploads seguros sem telas: validação pelo conteúdo real, limite por tipo, re-encode de imagem, nome seguro, entrega por URL assinada, foto de perfil e o `POST /api/v1/uploads` | foundation, auth, accounts |
| [`admin`](admin) | `twstec/kit-admin` | O painel de super admin como plugin do Filament 5: usuários, logs de requisição, trilha de auditoria e configurações, login com segundo fator por e-mail, variantes de dashboard, trilha de auditoria de toda ação (falha fechada) e as proteções de acesso ligadas pelo pacote — e as telas de contas, chaves de API, projetos e uploads **quando esses pacotes estão instalados** | foundation, auth, Filament (accounts e uploads **sugeridos**: o painel se adapta ao que existe) |
| [`installer`](installer) | `twstec/kit-installer` | O instalador `php artisan tws:install`: escolhe os módulos opcionais (accounts, uploads, admin) e a demonstração, e aplica — Composer, migrations, `APP_KEY` e pepper. Ferramenta de desenvolvimento (`require-dev` dos starters) | foundation |
| [`demo`](demo) | `twstec/kit-demo` | A **demonstração** do kit, só para o ambiente de desenvolvimento (`require-dev` do starter): landings, vitrine `/ui`, contato, catálogo e submissões no `/admin`, contas demo protegidas e seeders de dado fictício. O produto não a nomeia — ela entra pela descoberta de pacotes e pelos pontos de extensão | foundation, auth, accounts, uploads, admin, Filament (e o starter, pelos pontos de extensão dele) |

## Como o starter usa os pacotes

Em desenvolvimento, o starter instala cada pacote **direto desta pasta**, por
*path repository* do Composer (`starters/livewire/composer.json` →
`repositories`). O `vendor/` do starter guarda um link para cá, então uma
edição no pacote vale na hora, sem reinstalar. Nos containers de
desenvolvimento esta pasta é montada em `/var/packages` (é para onde o
caminho `../../packages` aponta, visto de `/var/www/html`).

Na imagem de produção o pacote é **copiado** para `vendor/` (ver
`starters/livewire/docker/php/Dockerfile` e o `.dockerignore` desta pasta).
A demonstração (`demo`) e o instalador (`installer`) são `require-dev`: a
imagem de produção (`composer install --no-dev`) não os instala, e o
`.dockerignore` desta pasta os deixa fora do contexto do build.

**Módulos opcionais.** `foundation` e `auth` vêm sempre; `accounts`,
`uploads` e `admin` são opcionais (`uploads` exige `accounts`). A detecção é
um ponto só, `Twstec\Kit\Foundation\Kit::has()`, e é o que o starter e o
`admin` perguntam antes de registrar uma tela, rota ou menu de módulo
opcional. Ver [docs/instalacao.md](../docs/instalacao.md).

**Publicação.** O Packagist (e os repositórios só-leitura de cada pacote,
`kelvindk9w/twstec-kit-<pacote>` — todos menos a `demo`, que vive só neste
monorepo e não é publicada) chega na fase F10b; o workflow de split
(`.github/workflows/split.yml`) está pronto e desligado. Até lá, o path
repository é o caminho. Cada `composer.json` daqui já tem o que o Packagist
lê (nome, descrição, licença, palavras-chave, autoload, `extra.laravel`,
requisitos, `branch-alias` de `dev-main` para `2.x-dev`); na cópia publicada,
`.github/release/prepare-composer.php` tira os path repositories e troca o
`2.x-dev` dos pacotes do kit por `^2.0`. O `.gitattributes` de cada pacote
deixa testes e arquivos de desenvolvimento fora do ZIP que o Composer baixa —
o CI instala o starter a partir desses ZIPs a cada push
(`.github/release/simulate-install.sh`).

## Testes de cada pacote

Cada pacote tem a própria suíte (Pest + Orchestra Testbench), que sobe uma
aplicação Laravel mínima só com os providers dele. No container de
desenvolvimento:

```bash
docker compose exec -w /var/packages/foundation app ./vendor/bin/pest
docker compose exec -w /var/packages/foundation app ./vendor/bin/pint --test
docker compose exec -w /var/packages/auth app ./vendor/bin/pest
docker compose exec -w /var/packages/auth app ./vendor/bin/pint --test
docker compose exec -w /var/packages/accounts app ./vendor/bin/pest
docker compose exec -w /var/packages/accounts app ./vendor/bin/pint --test
docker compose exec -w /var/packages/uploads app ./vendor/bin/pest
docker compose exec -w /var/packages/uploads app ./vendor/bin/pint --test
docker compose exec -w /var/packages/admin app ./vendor/bin/pest
docker compose exec -w /var/packages/admin app ./vendor/bin/pint --test
docker compose exec -w /var/packages/demo app ./vendor/bin/pest
docker compose exec -w /var/packages/demo app ./vendor/bin/pint --test
docker compose exec -w /var/packages/installer app ./vendor/bin/pest
docker compose exec -w /var/packages/installer app ./vendor/bin/pint --test
```

As dependências de desenvolvimento do pacote (`packages/<pacote>/vendor`, fora
do git) são instaladas com o Composer em container, da raiz do repositório
(troque `foundation` pelo pacote; o `auth`, o `accounts`, o `uploads`, o
`admin` e o `demo` acham os irmãos de que dependem pelos path repositories do
próprio `composer.json`; no `uploads`, no `admin` e no `demo`, acrescente
`--ignore-platform-req=ext-gd`, porque a imagem do Composer não traz a GD — a
suíte roda no container do app, que traz):

```bash
docker run --rm --user $(id -u):$(id -g) -e HOME=/tmp -v $(pwd):/repo \
  -w /repo/packages/foundation composer:2 composer update --no-interaction \
  --ignore-platform-req=ext-bcmath --ignore-platform-req=ext-intl --ignore-platform-req=ext-pcntl
```

No CI, a suíte e o Pint de cada pacote rodam como passos dentro dos jobs de
teste do starter.
