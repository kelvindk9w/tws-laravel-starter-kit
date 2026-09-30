# twstec/kit — o comando único

> **Parte do [TWS Laravel Starter Kit](https://github.com/kelvindk9w/tws-laravel-starter-kit).** O código, as issues e os
> pull requests ficam no monorepo
> [kelvindk9w/tws-laravel-starter-kit](https://github.com/kelvindk9w/tws-laravel-starter-kit) (pasta `starters/kit`); este
> repositório é o espelho só-leitura publicado a cada versão.
> Documentação: [docs/](https://github.com/kelvindk9w/tws-laravel-starter-kit/tree/desenvolvimento/docs) · Segurança:
> [SECURITY.md](SECURITY.md) · Licença: MIT ([LICENSE](LICENSE)).

Um projeto Laravel completo — login, cadastro, verificação de e-mail,
segundo fator, contas com membros e API, uploads, painel `/admin` — com o
ambiente de desenvolvimento em Docker já pronto. Dois caminhos:

- **Só com o Docker Desktop** (sem PHP, Composer nem Node na máquina): baixe
  este repositório e rode `docker compose run --rm instalar`. Veja os
  [primeiros passos](#primeiros-passos-para-iniciantes-só-com-o-docker).
- **Com PHP 8.4 e Composer na máquina:** `composer create-project
  "twstec/kit:^2.0@beta" meu-projeto` (ver [Com PHP na
  máquina](#com-php-na-máquina-composer-create-project)).

Os dois fazem as mesmas perguntas e entregam o mesmo projeto, com o Docker de
desenvolvimento pronto (`docker compose up -d`) e a configuração do Dev
Container do VS Code.

## Primeiros passos para iniciantes (só com o Docker)

Você só precisa do **Docker Desktop**. Todo o resto (PHP, Composer, Node,
banco de dados) roda dentro de containers.

> **Windows sem Docker?** Dá para criar o projeto com o PHP do Windows
> (`composer create-project`, abaixo): o Horizon fica de fora (ver [Windows e
> extensões](#windows-e-extensões-do-php)). Com o Docker, nada fica de fora.

### 1. Instale o Docker Desktop

Baixe em [docker.com/products/docker-desktop](https://www.docker.com/products/docker-desktop/),
instale e abra. Espere ele mostrar que está rodando ("Engine running").

- **Windows:** o Docker Desktop usa o WSL 2 e oferece a instalação dele
  durante a instalação. Aceite.
- **macOS e Linux:** instale e abra, sem nada a mais.

### 2. Baixe o kit

Nesta página do GitHub, clique no botão verde **Code** e depois em
**Download ZIP**. Descompacte o arquivo e **renomeie a pasta com o nome do seu
projeto** — por exemplo, `loja-da-maria`. O instalador sugere esse nome.

Quem usa git pode clonar direto com o nome do projeto:

```bash
git clone https://github.com/kelvindk9w/twstec-kit.git loja-da-maria
```

> **No Windows**, o projeto fica mais rápido **dentro do WSL** (no Explorador
> de Arquivos: "Linux" → Ubuntu → `home` → seu usuário). Numa pasta comum do
> Windows também funciona, só que mais devagar, e o instalador já liga a
> conferência periódica de arquivos que a recarga ao vivo precisa lá.

### 3. Abra um terminal dentro da pasta

- **Windows:** clique com o botão direito na pasta → **Abrir no Terminal**.
- **macOS:** abra o Terminal, digite `cd ` (com espaço) e arraste a pasta para
  a janela; tecle Enter.
- **Linux:** abra o terminal na pasta.

### 4. Rode o instalador

```bash
docker compose run --rm instalar
```

Na primeira vez, ele demora alguns minutos (prepara as ferramentas; nas
próximas, é rápido). Depois faz cinco perguntas. **Todas já vêm com uma
resposta sugerida — Enter aceita:**

1. **Nome do projeto** — letras minúsculas, números e hífen (`loja-da-maria`).
   Vira o endereço do site (`http://loja-da-maria.localhost:…`), o nome dos
   containers e o do banco. Se já existir outro projeto Docker com esse nome
   na máquina, o instalador avisa e sugere outro.
2. **Número do projeto (0 a 9)** — define **todas as portas** do projeto, com
   o mesmo final: o site na `808N`, os e-mails na `802N`, o Vite na `803N`, o
   banco na `804N`. O instalador sugere o primeiro número com as quatro portas
   livres e mostra quem usa os outros ("0 já é usado por loja-da-maria").
3. **A interface** — Livewire ou React.
4. **Os módulos** — contas com membros e API, uploads e foto de perfil, painel
   `/admin`. Espaço marca e desmarca.
5. **A confirmação.**

O projeto é montado **nesta mesma pasta**: os arquivos do kit dão lugar aos do
projeto. Ao final, o instalador mostra o endereço do site.

O projeto já nasce **seu**: o `composer.json` com o nome `app/<nome>` e a
licença `proprietary` (troque à vontade), a licença MIT do kit guardada como
aviso em `NOTICE-KIT-MIT.txt`, o banco de teste `<nome>_test`, um CI base em
`.github/workflows/ci.yml` (roda à mão e uma vez por semana) e o
`scripts/verificar`, que roda o mesmo conjunto do CI na sua máquina, pelo
Docker. Ver [docs/instalacao.md](https://github.com/kelvindk9w/tws-laravel-starter-kit/blob/desenvolvimento/docs/instalacao.md#o-docker-de-desenvolvimento-do-projeto-criado).

### 5. Suba o projeto

```bash
docker compose up -d
```

Na primeira vez, demora alguns minutos (constrói as imagens e cria o banco).
Sobem: o site (nginx + PHP), o banco (PostgreSQL), o Redis, o Mailpit (os
e-mails), a fila, o agendador e o Vite (a recarga ao vivo do front).

### 6. Abra no navegador

Abra o endereço que o instalador mostrou, por exemplo
**http://loja-da-maria.localhost:8080**. Chrome, Edge e Firefox abrem
endereços `.localhost` sozinhos (ver [Problemas](#problemas-comuns) se o seu
não abrir).

Crie a sua conta em **Cadastrar**. O e-mail de confirmação não sai para a
internet: ele aparece no **Mailpit**, em
**http://loja-da-maria.localhost:8020** (a porta `802N` do seu número).
Todo e-mail que o projeto enviar aparece lá.

### 7. Programe no VS Code

1. Abra a pasta do projeto no VS Code.
2. Instale a extensão **Dev Containers** (da Microsoft).
3. Tecle F1 e escolha **Dev Containers: Reopen in Container**.

O VS Code passa a rodar **dentro do container** do projeto: o terminal dele já
tem PHP, Composer e git, e as extensões de Laravel e Tailwind vêm instaladas.
Salve um arquivo e a página no navegador recarrega sozinha.

### No dia a dia

Rode na pasta do projeto (ou no terminal do VS Code, fora do container):

| Para… | Comando |
| --- | --- |
| subir | `docker compose up -d` |
| parar (os dados ficam) | `docker compose stop` |
| ver o que está rodando | `docker compose ps` |
| ver os logs | `docker compose logs -f` (ou `… logs -f app`) |
| rodar os testes | `docker compose exec app php artisan test` |
| conferir tudo o que o CI confere | `scripts/verificar` |
| um comando do Laravel | `docker compose exec app php artisan migrate` |
| um pacote PHP | `docker compose exec app composer require vendor/pacote` |
| um pacote do front | `docker compose exec vite npm install pacote` |
| apagar tudo, **inclusive o banco** | `docker compose down -v` |

Dentro do Dev Container, os comandos `php`, `composer` e `php artisan` vão
direto, sem o `docker compose exec app`.

### Os números e as portas

| Número | Site | E-mails (Mailpit) | Vite | Banco (só se publicado) |
| --- | --- | --- | --- | --- |
| 0 | 8080 | 8020 | 8030 | 8040 |
| 1 | 8081 | 8021 | 8031 | 8041 |
| N (0–9) | 808N | 802N | 803N | 804N |
| 10 | 8180 | 8120 | 8130 | 8140 |
| 1N (10–19) | 818N | 812N | 813N | 814N |

De 0 a 9 cabem dez projetos. Se os dez estiverem em uso, o instalador segue
para a **centena seguinte** com o mesmo padrão: 10 é `8180/8120/8130/8140`,
11 é `8181/…`, e assim até 99 (`8989/8929/8939/8949`).

Tudo fica no `.env` do projeto (`COMPOSE_PROJECT_NAME`, `DEV_SITE_PORT`,
`DEV_MAIL_PORT`, `DEV_VITE_PORT`, `DEV_DB_PORT`, `APP_URL`…). **Para trocar
depois**, edite o `.env` e rode `docker compose up -d` — trocando a porta do
site, troque também a do `APP_URL` e a do `PLATFORM_OFFICIAL_URL`. Trocar o
nome (`COMPOSE_PROJECT_NAME`) cria containers e volumes novos, com o banco
vazio.

Tudo é publicado só em `127.0.0.1`: nada fica aberto para a rede. O Redis
nunca é publicado. O banco só é publicado se você pedir: `COMPOSE_PROFILES=db-port`
no `.env` (ou `TWS_KIT_EXPOSE_DB=1` na instalação) e `docker compose up -d` —
ele fica em `127.0.0.1:804N`, com o usuário e a senha do `.env` (gerada para o
projeto).

### Problemas comuns

- **"port is already allocated" ou "ports are not available" no
  `docker compose up -d`:** outra coisa passou a usar uma porta do seu número
  depois da instalação. Escolha outro número livre: troque as quatro portas no
  `.env` (e o `APP_URL`) e rode `docker compose up -d`. O instalador vê as
  portas dos outros projetos Docker e dos programas do Windows, do macOS e do
  Linux; um programa que escuta só dentro da distribuição WSL (fora do Docker)
  não é visto.
- **O endereço `.localhost` não abre:** alguns navegadores (Safari antigo) não
  resolvem `.localhost`. Acrescente ao arquivo de hosts
  (`C:\Windows\System32\drivers\etc\hosts` no Windows, `/etc/hosts` no
  macOS e no Linux) a linha `127.0.0.1 loja-da-maria.localhost`. Abrir
  `http://localhost:8080` leva ao endereço do projeto sozinho — cada projeto
  no seu endereço, para as sessões (login) de dois projetos não se misturarem.
- **A página mostra erro do Vite, sem estilo:** na primeira subida o Vite
  ainda está instalando as dependências do front. Acompanhe com
  `docker compose logs -f vite` e recarregue a página.
- **A página não recarrega sozinha (Windows, pasta do Windows):** confira
  `DEV_VITE_POLLING=true` no `.env` e rode `docker compose up -d`.
- **"all predefined address pools have been fully subnetted":** o Docker não
  tem mais faixa de rede livre (muitos projetos antigos). `docker network
  prune` apaga as redes que nenhum container usa.
- **Recomeçar do zero:** `docker compose down -v` apaga os containers e o
  banco do projeto; baixe o ZIP de novo numa pasta nova.

### Sem perguntas (scripts, CI)

As escolhas também vão por variável de ambiente (ver a tabela em [Sem
perguntas](#sem-perguntas-ci-scripts)), com `-T` (sem terminal):

```bash
# Linux / macOS
TWS_KIT_NAME=loja TWS_KIT_SLOT=2 TWS_KIT_STACK=react docker compose run --rm -T instalar
```

```powershell
# Windows (PowerShell)
$env:TWS_KIT_NAME = "loja"; $env:TWS_KIT_SLOT = "2"; $env:TWS_KIT_STACK = "react"
docker compose run --rm -T instalar
```

### O que o instalador em container faz

O `compose.yaml` desta pasta tem um só serviço, `instalar`: uma imagem com
PHP 8.4, Composer, Node 24 e a linha de comando do Docker
(`twstec-kit-instalar`, a mesma para todos os projetos da máquina). Ele monta
esta pasta, roda o **mesmo** menu do `composer create-project twstec/kit`, e
compila o front. Os arquivos saem com o **seu** usuário como dono (o do dono
da pasta), nunca root. O socket do Docker é montado **só nesse container, só
durante a instalação**: é por ele que o menu vê os outros projetos e as
portas em uso — e, para conferir se uma porta está livre na máquina, ele sobe
um container descartável com a porta publicada e o apaga em seguida. Nada
fica para trás além da imagem `twstec-kit-instalar` (`docker image rm
twstec-kit-instalar:1` a apaga; a próxima instalação a constrói de novo).

## Com PHP na máquina (`composer create-project`)

Um comando cria o projeto inteiro, com a interface e os módulos que você
escolher:

```bash
composer create-project "twstec/kit:^2.0@beta" meu-projeto   # durante o beta; na 2.0.0 estável: twstec/kit
```

Num terminal, um menu pergunta — as duas primeiras perguntas são as mesmas
do caminho só com o Docker, o nome e o número do projeto (com PHP na máquina,
o número sugerido é conferido abrindo as portas aqui mesmo, e os projetos
Docker pela linha de comando `docker`, se houver):

1. **O nome e o número do projeto** (ver acima).
2. **A interface** — Livewire (`twstec/starter-livewire`) ou React
   (`twstec/starter-react`, Inertia + TypeScript + shadcn/ui). Os pacotes, as
   regras de segurança e o `/admin` são os mesmos nas duas.
3. **Os módulos opcionais** — contas com membros e API
   (`twstec/kit-accounts`), uploads seguros e foto de perfil
   (`twstec/kit-uploads`) e o painel `/admin` (`twstec/kit-admin`). A base
   (`twstec/kit-foundation`) e a autenticação (`twstec/kit-auth`) vêm sempre.
   Uploads precisa de contas: o menu não aceita uploads sem contas.
4. **A confirmação** do plano.

O resultado é o projeto do starter escolhido **só com os pacotes marcados**
(os desmarcados não chegam a ser instalados), com a `APP_KEY` e, com contas,
o pepper dedicado das chaves de API no `.env`, e o **Docker de
desenvolvimento pronto**: `cd meu-projeto && docker compose up -d` sobe tudo
(o banco é criado e as migrations rodam na primeira subida), com o Dev
Container do VS Code em `.devcontainer/`. Sem Docker, ajuste as variáveis
`DB_*` do `.env` (ou use SQLite) e rode `php artisan migrate`. Este pacote não
fica no projeto: ele dá lugar ao starter.

- **Requisitos:** PHP 8.4+ com `mbstring`, Composer 2, e as extensões que o
  starter pede (as do Laravel, `bcmath`, `intl`, `zip`, `gd` com uploads, e
  `pcntl`/`posix` do Horizon — dispensadas no Windows; ver [Windows e
  extensões](#windows-e-extensões-do-php)).
- **Licença:** MIT.

## Sem perguntas (CI, scripts)

O Composer não repassa opções próprias ao script do `create-project`; a
escolha vai por **variáveis de ambiente**, que funcionam igual no Linux, no
macOS e no Windows:

| Variável | Valores | Padrão |
| --- | --- | --- |
| `TWS_KIT_NAME` | nome do projeto: letras minúsculas, números e hífen, começando por letra (2 a 40) | o da pasta (a do ZIP vira `meu-projeto`), com `-2`, `-3`… se já existir projeto Docker com ele |
| `TWS_KIT_SLOT` | número do projeto, `0` a `99` (as portas `808N`, `802N`, `803N`, `804N`; de 10 em diante, a centena seguinte) | o primeiro com as quatro portas livres |
| `TWS_KIT_EXPOSE_DB` | `1` publica o banco em `127.0.0.1:804N`; `0` não | `0` |
| `TWS_KIT_STACK` | `livewire` ou `react` | `livewire` |
| `TWS_KIT_WITHOUT` | módulos opcionais que ficam de fora, separados por vírgula (`accounts`, `uploads`, `admin`) | nenhum |
| `TWS_KIT_WITH` | módulos opcionais que entram (o padrão já é todos) | todos |
| `TWS_KIT_LOCALE` | idioma do menu: `pt_BR`, `en` ou `es` | o do sistema, se for um dos três; senão `pt_BR` |
| `TWS_KIT_VENDOR` | o vendor do nome do pacote no `composer.json` do projeto (`<vendor>/<nome>`): letras minúsculas, números e hífen | `app` |
| `TWS_KIT_LICENSE` | a licença do projeto no `composer.json`: um identificador SPDX (`MIT`, `Apache-2.0`…) ou `proprietary` | `proprietary` |

```bash
# Linux / macOS
TWS_KIT_STACK=react TWS_KIT_WITHOUT=uploads composer create-project "twstec/kit:^2.0@beta" meu-projeto
```

```powershell
# Windows (PowerShell)
$env:TWS_KIT_STACK = "react"; $env:TWS_KIT_WITHOUT = "uploads"
composer create-project "twstec/kit:^2.0@beta" meu-projeto
```

Qualquer uma dessas variáveis (mesmo vazia) desliga o menu — menos
`TWS_KIT_VENDOR` e `TWS_KIT_LICENSE`, que o menu não pergunta (as duas se
trocam depois no `composer.json`). Sem terminal
(`composer create-project -n`, CI) e sem variável nenhuma, vale o padrão
seguro: o nome da pasta, o primeiro número livre, Livewire com todos os
módulos. Uma escolha inválida (interface ou módulo desconhecido,
`foundation`/`auth` de fora, uploads sem contas, nome de um projeto Docker que
já existe, número com alguma porta ocupada) é recusada **antes** de baixar
qualquer coisa, com o motivo e a sugestão.

## Windows e extensões do PHP

Sem WSL, o menu sai em listas numeradas (o Question Helper do Symfony
Console), com as mesmas perguntas e a mesma validação. Nada depende de bash:
o comando é PHP puro.

**O Horizon no Windows.** O starter usa o Horizon (o painel das filas), que
exige as extensões `pcntl` e `posix` — e o PHP do Windows não as tem. No
Windows, o instalador **ignora só essas duas**, sozinho, em todas as chamadas
ao Composer (a criação e os `tws:install`/`tws:add` depois), e o resumo final
avisa. O resto do aplicativo não depende delas: o site, o login, a fila (com
`php artisan queue:work` no lugar do `php artisan horizon`) e o agendador
funcionam normalmente, e a suíte passa. Só o comando `php artisan horizon` não
roda. Nos seus próximos comandos do Composer no projeto, defina antes:

```powershell
$env:COMPOSER_IGNORE_PLATFORM_REQ = "ext-pcntl,ext-posix"
```

O caminho só com o Docker (`docker compose run --rm instalar`) roda tudo,
inclusive o Horizon; a produção roda na imagem Docker do starter, que tem as
duas extensões.

**Outra extensão faltando** (bcmath, gd, intl, zip…) **não é ignorada**: a
instalação para no `composer update` com a lista do que falta e duas saídas —
instalar a extensão (no Windows, tirar o `;` da linha `extension=…` no
`php.ini`; no Ubuntu/Debian, `sudo apt install php8.4-<extensão>`; no macOS, o
PHP do Homebrew já as traz) e terminar com os comandos que a mensagem mostra,
ou usar o caminho só com o Docker.

**Opções de plataforma que você der** — `--ignore-platform-req=…` e
`--ignore-platform-reqs` no `composer create-project`, ou as variáveis
`COMPOSER_IGNORE_PLATFORM_REQ` / `COMPOSER_IGNORE_PLATFORM_REQS` — valem
também para o Composer que o instalador roda por dentro. (As opções da linha de
comando são lidas no Linux e no WSL; no Windows, use as variáveis.)

## Como funciona

O `post-create-project-cmd` deste pacote roda `kit-setup/create.php` com o
mesmo PHP e o mesmo Composer do `create-project`:

1. a escolha (menu ou ambiente): o nome e o número do projeto, a interface e
   os módulos;
2. o starter escolhido é baixado (`composer create-project --no-install
   --no-scripts`, com a mesma restrição de versão deste pacote e os mesmos
   repositórios — os do `--repository … --add-repository`, se você usou)
   numa pasta temporária **dentro** do projeto, e conferido;
3. os arquivos deste pacote dão lugar aos do starter;
4. o `composer.json` do starter perde os módulos não marcados, e rodam os
   passos do `create-project` do próprio starter: `post-root-package-install`
   (o `.env`), `composer update` e `post-create-project-cmd`, que chama o
   instalador do starter (`php artisan tws:install`) **com a escolha no
   ambiente** (`TWS_KIT_WITH`/`TWS_KIT_WITHOUT`, e o nome e o número em
   `TWS_KIT_NAME`/`TWS_KIT_SLOT`): ele não pergunta de novo, gera as chaves e
   grava no `.env` o Docker de desenvolvimento do projeto — o nome, as portas,
   o endereço `http://<nome>.localhost:<porta>`, o cookie de sessão, o banco e
   as senhas geradas do banco e do Redis — e deixa o projeto com a identidade
   dele (o `.env.example`, o banco de teste, o `composer.json`, a licença do kit
   em `NOTICE-KIT-MIT.txt` e o CI base);
5. o resumo, com o endereço e o `docker compose up -d` (que cria o banco e
   roda as migrations).

**Falha limpa:** se algo falha antes do passo 3 (escolha inválida, starter
que não baixa), nada foi instalado — a mensagem diz para apagar a pasta e
rodar de novo. Depois dele, a mensagem diz o passo que falhou e os comandos
exatos para terminar sem recomeçar. O código de saída do `create-project` é
diferente de 0.

Os comandos por starter continuam valendo
(`composer create-project "twstec/starter-livewire:^2.0@beta"` ou
`twstec/starter-react`, e `laravel new --using=`), e aceitam as mesmas
`TWS_KIT_WITH`/`TWS_KIT_WITHOUT`. Para um aplicativo Laravel que já existe,
use `php artisan tws:add` (pacote `twstec/kit-installer`). Tudo em
[docs/instalacao.md](https://github.com/kelvindk9w/tws-laravel-starter-kit/blob/desenvolvimento/docs/instalacao.md).

## Testes (monorepo)

```bash
docker run --rm --user $(id -u):$(id -g) -e HOME=/tmp -v $(pwd):/repo -w /repo/starters/kit \
  --entrypoint ./vendor/bin/pest tws-laravel-starter-kit-app
```

A suíte não baixa nada: o Composer é de mentira (`Contracts\Runner`), o
projeto é uma pasta temporária e o menu roda com as teclas de mentira do
Laravel Prompts. Prova o menu (inclusive a recusa de uploads sem contas e o
caminho do Windows), a escolha pelo ambiente, a troca dos arquivos, a ordem
dos passos, as falhas limpas e que o catálogo dos módulos é o mesmo do
foundation. O `create-project` de verdade, a partir dos pacotes empacotados,
é simulado no CI (`.github/release/simulate-install.sh`, `STARTER=kit`).
