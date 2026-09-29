# twstec/kit — o comando único

> **Parte do [TWS Laravel Starter Kit](https://github.com/kelvindk9w/tws-laravel-starter-kit).** O código, as issues e os
> pull requests ficam no monorepo
> [kelvindk9w/tws-laravel-starter-kit](https://github.com/kelvindk9w/tws-laravel-starter-kit) (pasta `starters/kit`); este
> repositório é o espelho só-leitura publicado a cada versão.
> Documentação: [docs/](https://github.com/kelvindk9w/tws-laravel-starter-kit/tree/desenvolvimento/docs) · Segurança:
> [SECURITY.md](SECURITY.md) · Licença: MIT ([LICENSE](LICENSE)).

Um comando cria o projeto inteiro, com a interface e os módulos que você
escolher:

```bash
composer create-project "twstec/kit:^2.0@beta" meu-projeto   # durante o beta; na 2.0.0 estável: twstec/kit
```

Num terminal, um menu pergunta:

1. **A interface** — Livewire (`twstec/starter-livewire`) ou React
   (`twstec/starter-react`, Inertia + TypeScript + shadcn/ui). Os pacotes, as
   regras de segurança e o `/admin` são os mesmos nas duas.
2. **Os módulos opcionais** — contas com membros e API
   (`twstec/kit-accounts`), uploads seguros e foto de perfil
   (`twstec/kit-uploads`) e o painel `/admin` (`twstec/kit-admin`). A base
   (`twstec/kit-foundation`) e a autenticação (`twstec/kit-auth`) vêm sempre.
   Uploads precisa de contas: o menu não aceita uploads sem contas.
3. **A confirmação** do plano.

O resultado é o projeto do starter escolhido **só com os pacotes marcados**
(os desmarcados não chegam a ser instalados), com a `APP_KEY` e, com contas,
o pepper dedicado das chaves de API no `.env`, e o banco preparado. Se o banco
do `.env` não responder (o `.env.example` aponta para o PostgreSQL do Docker de
desenvolvimento), a mensagem final diz exatamente o que ajustar e o comando a
rodar. Este pacote não fica no projeto: ele dá lugar ao starter.

- **Requisitos:** PHP 8.4+ com `mbstring`, Composer 2, e as extensões que o
  starter pede (as do Laravel, mais `pcntl` do Horizon — ver abaixo, no
  Windows).
- **Licença:** MIT.

## Sem perguntas (CI, scripts)

O Composer não repassa opções próprias ao script do `create-project`; a
escolha vai por **variáveis de ambiente**, que funcionam igual no Linux, no
macOS e no Windows:

| Variável | Valores | Padrão |
| --- | --- | --- |
| `TWS_KIT_STACK` | `livewire` ou `react` | `livewire` |
| `TWS_KIT_WITHOUT` | módulos opcionais que ficam de fora, separados por vírgula (`accounts`, `uploads`, `admin`) | nenhum |
| `TWS_KIT_WITH` | módulos opcionais que entram (o padrão já é todos) | todos |
| `TWS_KIT_LOCALE` | idioma do menu: `pt_BR`, `en` ou `es` | o do sistema, se for um dos três; senão `pt_BR` |

```bash
# Linux / macOS
TWS_KIT_STACK=react TWS_KIT_WITHOUT=uploads composer create-project "twstec/kit:^2.0@beta" meu-projeto
```

```powershell
# Windows (PowerShell)
$env:TWS_KIT_STACK = "react"; $env:TWS_KIT_WITHOUT = "uploads"
composer create-project "twstec/kit:^2.0@beta" meu-projeto
```

Qualquer uma dessas variáveis (mesmo vazia) desliga o menu. Sem terminal
(`composer create-project -n`, CI) e sem variável nenhuma, vale o padrão
seguro: Livewire com todos os módulos. Uma escolha inválida (interface ou
módulo desconhecido, `foundation`/`auth` de fora, uploads sem contas) é
recusada **antes** de baixar qualquer coisa.

## Windows

Sem WSL, o menu sai em listas numeradas (o Question Helper do Symfony
Console), com as mesmas perguntas e a mesma validação. Nada depende de bash:
o comando é PHP puro. O starter usa o Horizon, que exige `ext-pcntl` — que o
PHP do Windows não tem. Fora do Docker ou do WSL, peça ao Composer para
ignorar essa extensão (a produção roda na imagem Docker do starter, que a
tem):

```powershell
$env:COMPOSER_IGNORE_PLATFORM_REQ = "ext-pcntl,ext-posix"
composer create-project "twstec/kit:^2.0@beta" meu-projeto
```

## Como funciona

O `post-create-project-cmd` deste pacote roda `kit-setup/create.php` com o
mesmo PHP e o mesmo Composer do `create-project`:

1. a escolha (menu ou ambiente);
2. o starter escolhido é baixado (`composer create-project --no-install
   --no-scripts`, com a mesma restrição de versão deste pacote e os mesmos
   repositórios — os do `--repository … --add-repository`, se você usou)
   numa pasta temporária **dentro** do projeto, e conferido;
3. os arquivos deste pacote dão lugar aos do starter;
4. o `composer.json` do starter perde os módulos não marcados, e rodam os
   passos do `create-project` do próprio starter: `post-root-package-install`
   (o `.env`), `composer update` e `post-create-project-cmd`, que chama o
   instalador do starter (`php artisan tws:install`) **com a escolha no
   ambiente** (`TWS_KIT_WITH`/`TWS_KIT_WITHOUT`): ele não pergunta de novo,
   gera as chaves e roda as migrations;
5. a conferência do banco e o resumo.

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
