# Produção e deploy

`docker-compose.prod.yml` é autocontido: em um servidor com Docker instalado,
`docker compose -f docker-compose.prod.yml up -d --build` sobe tudo — app
PHP-FPM com OPcache (imagem imutável), nginx nas portas 80/443 (TLS com
certificado **autoassinado** embutido na imagem), migrate one-shot,
Horizon (filas), scheduler, PostgreSQL e Redis — **banco e Redis sem porta exposta no
host** (rede interna apenas). Nenhuma configuração de SO adicional é exigida
pelo projeto — firewall/DNS são responsabilidade de quem administra o servidor.

Para produção real:

1. `cp .env.prod.example .env.prod` e defina `APP_KEY` — **obrigatória**, e a
   MESMA para `app`, `migrate`, `horizon` e `scheduler` (ver
   [A chave da aplicação em produção](#a-chave-da-aplicação-em-produção)).
2. Senhas da stack: `PROD_POSTGRES_PASSWORD` e `PROD_REDIS_PASSWORD` **não têm
   valor padrão** — sem elas o Compose se recusa a resolver o arquivo. Defina
   `PROD_*` no shell ou no `.env` da raiz (o Compose interpola `${PROD_*}` dali
   — ver cabeçalho do `docker-compose.prod.yml` e `.env.prod.example`).
3. TLS real: monte seus certificados (`server.crt`/`server.key`) em
   `/etc/nginx/certs` — ver comentário no `docker-compose.prod.yml`.
4. **Não rode `db:seed`.** Todo seeder do kit cria dado de demonstração, e em
   `APP_ENV=production` eles se recusam a rodar (ver
   [Superfície de demonstração: fail-closed em produção](demo.md#superfície-de-demonstração-fail-closed-em-produção)).
   Não há dado estrutural a semear: as migrations bastam.

> **Copiar o `.env.example` para um servidor não abre a demonstração.** Aquele
> arquivo é de desenvolvimento e traz `DEMO_LOGIN_ENABLED=true`,
> `UI_SHOWCASE_ENABLED=true`, as credenciais demo e `APP_DEBUG=true` — mas com
> `APP_ENV=production` nada disso é obedecido: as rotas de demonstração
> respondem 404, as credenciais demo não aparecem em nenhum login, os seeders
> recusam e o `APP_DEBUG` é forçado para `false` (com aviso no log). A proteção é
> do ambiente, não da memória de quem faz o deploy.

> **Dados NUNCA se perdem ao reiniciar/recriar containers**: banco,
> Redis e assets públicos ficam em volumes nomeados. Nunca use `down -v`.

## A imagem de produção: como ela é construída e o que vai dentro

`docker/php/Dockerfile` (target `prod`) e `docker/nginx/Dockerfile` (target
`prod`) são construídos pelo CI a cada push e PR (job **Imagens de produção**
em `.github/workflows/ci.yml`, sem push para registry): imagem que não constrói
deixa o CI vermelho. A imagem do app ficou sem construir desde que o Horizon
entrou — o estágio que roda o `composer install` partia da imagem `composer`,
com outro PHP e sem `ext-pcntl` — e nada percebia, porque o CI nunca construía a
imagem.

**As dependências PHP são instaladas sobre o mesmo PHP da imagem final.** O
estágio `composer-prod` parte do estágio `base` (PHP 8.4 com todas as extensões
do projeto) e só copia o binário do Composer (versão pinada) da imagem oficial.
A checagem de plataforma do `composer install` vale, portanto, para o PHP que
roda em produção: faltou extensão, o build falha. Não há, nem deve haver,
`--ignore-platform-reqs`.

**Quem pode escrever o quê:** o processo (php-fpm, horizon, scheduler, migrate)
roda como `www-data`, e o código é de `root`. O processo lê o código mas não o
reescreve; só `storage/`, `bootstrap/cache` e o volume compartilhado com o nginx
são graváveis.

**O que NÃO vai para dentro da imagem** (`.dockerignore`): nenhum arquivo de
ambiente — nem o `.env.example`, que tem as flags de demo ligadas e as senhas
demo, e que a aplicação não lê em tempo de execução —; chaves e certificados;
`vendor` e `node_modules` locais (reinstalados no build, sem pacotes de dev); o
`storage/` inteiro e o banco SQLite local da máquina de quem constrói (uploads, **dumps de backup**,
sessões, cache, logs — a estrutura vazia é recriada no Dockerfile); testes,
`phpunit.xml`, config do Playwright e artefatos de desenvolvimento.

Conferir numa imagem construída:

```bash
docker build -f docker/php/Dockerfile --target prod -t tws-app:prod .
docker run --rm --entrypoint sh tws-app:prod -c \
  'id; ls -A /var/www/html; find /var/www/html/storage -type f | wc -l; ls -A /var/www/html/.env* 2>&1'
```

Esperado: `uid=82(www-data)`; na raiz só `app artisan bootstrap composer.json
composer.lock config database lang package*.json public resources routes
storage vendor vite.config.js` e os `.md`/`LICENSE`/`.npmrc`/`.dockerignore`; zero arquivo em
`storage/`; nenhum `.env*`.

## E-mail em produção: sem mailer de verdade, nenhum e-mail sai

O padrão de `MAIL_MAILER` é `log` (no `config/mail.php` e no
`docker-compose.prod.yml`). Em desenvolvimento isso é útil; em produção,
significava que **cada e-mail era gravado inteiro no arquivo de log** — o código
de verificação da ação sensível, o link de redefinição de senha com o token, a
mensagem de contato com nome e e-mail — sem nenhum erro. O `array` é o irmão
silencioso: descarta tudo.

Com `APP_ENV=production`, os transportes `log` e `array` **recusam o envio**
(`App\Core\Mail\NonDeliveringMailers`): o job de e-mail falha com uma mensagem
que diz o que configurar e aparece como falho no `/horizon`, e nada da mensagem
chega ao log. Vale também para o último recurso do mailer `failover`, que é o
`log`. A subida do `horizon`/`queue:work`/`schedule:run` avisa no log quando o
mailer padrão não entrega.

O que **não** é afetado, de propósito (critério do `CriticalSecrets`: recusa
onde há dano, aviso onde não há): o boot, o php-fpm, o `composer install`, o
`package:discover` e o `key:generate`. Sem `.env` o Laravel se considera em
produção com mailer `log`; a recusa mora no envio, então instalar e construir
continuam passando.

Configurar: `PROD_MAIL_MAILER`, `PROD_MAIL_HOST`, `PROD_MAIL_PORT`,
`PROD_MAIL_FROM_ADDRESS` (interpolados pelo compose) e `MAIL_USERNAME` /
`MAIL_PASSWORD` no `.env.prod` — ver `.env.prod.example`. Instalação
descartável sem servidor de e-mail: `MAIL_ALLOW_NON_DELIVERING_IN_PRODUCTION=true`
(aviso a cada boot).

## A chave da aplicação em produção

A `APP_KEY` protege os atributos com cast `encrypted` (hoje: o nome do
usuário), o cookie de sessão, as URLs assinadas e — por fallback — o pepper do
hash das chaves de API. Em produção ela é **pré-requisito**, não efeito
colateral da subida.

**O que mudou e por quê.** O entrypoint da imagem de produção GERAVA a chave
quando ela vinha vazia, e a escrevia no `.env` de dentro do container. Como a
imagem é a mesma para os quatro serviços PHP (e para cada réplica), isso dava
uma chave **diferente por container**; e como `.env` mora na camada gravável, a
chave também **não sobrevivia ao restart**. Nada disso produzia erro na subida —
o dado simplesmente deixava de descriptografar, o usuário caía deslogado ao
atender por outro container e as chaves de API paravam de verificar. Perda
silenciosa de dado é pior que serviço que não sobe, então hoje:

| Camada | Comportamento em `APP_ENV=production` |
| --- | --- |
| `docker/php/entrypoint-prod.sh` | Chave **ausente** → aborta com código **78** (`EX_CONFIG`) e imprime como gerar e onde colocar. Nunca escreve chave em arquivo. |
| `App\Core\Support\CriticalSecrets` (boot) | Chave ausente **ou com valor de exemplo/placeholder** → recusa o boot (`MissingApplicationKeyException`) nos processos que **servem tráfego ou processam trabalho**; nos comandos de instalação e manutenção, avisa em voz alta (log + stderr) e deixa passar. |
| `docker-compose.prod.yml` | `PROD_POSTGRES_PASSWORD` / `PROD_REDIS_PASSWORD` sem fallback (`${VAR:?…}`): o Compose não resolve o arquivo sem elas. |

Fora de produção o entrypoint continua conveniente: gera uma chave efêmera,
**só no ambiente do processo** (nunca em arquivo), e avisa em voz alta que ela
morre com o container.

**Por que o guard não recusa em todo lugar.** `config/app.php` resolve
`env('APP_ENV', 'production')`: **sem `.env`, a aplicação se considera em
produção**. E `composer install` dispara `artisan package:discover` no
`post-autoload-dump`. Logo, um guard de recusa larga derrubava a instalação de
dependências no CI (que instala antes de criar o `.env`), no build da imagem de
produção (que nunca tem `.env`) e no **primeiro `composer install` de quem
acabou de clonar o kit**. Fechar o vazamento não pode custar o caminho de
entrada do projeto. O critério, então, é o dano real:

| Processo | Resposta | Por quê |
| --- | --- | --- |
| Serve tráfego (php-fpm, Octane) | **Recusa** | É a requisição do usuário sendo atendida com o segredo errado. |
| Processa trabalho (`queue:work`, `horizon*`, `schedule:run`) | **Recusa** | Lê e grava dado real como o HTTP faz. Lista em `SECURITY_SECRETS_PROCESSING_COMMANDS`. |
| Instalação e manutenção (`package:discover`, `config:cache`, `vendor:publish`, `about`, `key:generate`) | **Aviso** | Não expõe nem grava dado. Recusar aqui só quebra build. |
| `migrate` | **Aviso** | Escreve **esquema**, não dado criptografado. É o primeiro container do compose de produção: o aviso dele é o alerta mais precoce, e o deploy segue para parar na porta que importa (`app`, que serve tráfego). |

A lista configurável é de quem **processa**, não de quem é liberado, porque uma
lista de liberados tem o padrão errado: todo comando de manutenção novo (do
Laravel, do Filament, do Horizon) voltaria a derrubar build até alguém lembrar
de incluí-lo. A lista de quem processa é definida pelo **deploy** — são os nomes
escritos no `docker-compose.prod.yml` — e por isso é estável.

O aviso vai para o log **e para o stderr** quando há console: um alerta que só
existe em `storage/logs` é invisível para quem está olhando a saída de um
`composer install`, e é justamente essa pessoa que pode corrigir.

**Procedimento.** Gere UMA chave, uma única vez:

```bash
# num container descartável da própria imagem
docker run --rm --entrypoint sh <imagem> -c 'php artisan key:generate --show'

# ou sem container nenhum
openssl rand -base64 32 | sed 's/^/base64:/'
```

Entregue o valor inteiro (com o prefixo `base64:`) como variável de ambiente
`APP_KEY` para todos os serviços PHP, pelo `.env.prod` ou pelo secret do
orquestrador — **nunca** num `.env` dentro do container. Guarde-a no mesmo cofre
das senhas do banco: ela é tão crítica quanto o backup, porque **sem ela o
backup não serve para nada**.

### Trocar a chave é irreversível sem a chave antiga

A chave **é** o dado. No instante em que ela muda, tudo que foi gravado com a
anterior deixa de ser legível, e não existe recuperação. Ao rotacionar, declare
a chave anterior em `APP_PREVIOUS_KEYS` (lista separada por vírgula, da mais
recente para a mais antiga): o Laravel tenta as antigas na **leitura** e grava
sempre com a atual. Só remova a antiga depois de reescrever os registros.

Duas coisas que o `APP_PREVIOUS_KEYS` **não** resgata:

- **Chaves de API** — o hash da secreta é HMAC com pepper, e o pepper usa
  apenas o valor **atual**. Como `API_KEYS_HASH_PEPPER` tem fallback para a
  `APP_KEY`, rotacionar a chave sem um pepper próprio invalida **permanentemente**
  toda chave de API já emitida (401, sem volta). Defina um
  `API_KEYS_HASH_PEPPER` dedicado **antes de emitir a primeira chave** e a
  rotação da `APP_KEY` deixa de afetá-las.
- **Sessões e cookies** — não é perda de dado, é logout: todos refazem o login.

### Segredos com valor de fachada

O mesmo princípio vale para as senhas: **senha padrão que funciona é o mesmo bug
da flag que já vem ligada**. O `docker-compose.prod.yml` oferecia
`troque-esta-senha` como fallback de Postgres e Redis; agora não oferece nada.

Para instalações que não sobem pelo compose do kit, o boot em produção grava
aviso no log quando `DB_PASSWORD`, `REDIS_PASSWORD`, `API_KEYS_HASH_PEPPER`,
`BACKUP_ARCHIVE_PASSWORD` ou `AWS_SECRET_ACCESS_KEY` estão com valor de
placeholder (vocabulário configurável em `SECURITY_SECRETS_PLACEHOLDERS`). Aqui
a resposta é **avisar, não recusar**, e o critério é o mesmo do `APP_DEBUG`:
para a chave não existe valor seguro a forçar, então a recusa é a única saída;
para uma senha já provisionada no Postgres, derrubar a aplicação não troca a
senha — só troca um problema de segurança por uma indisponibilidade, mantendo o
problema. O remédio é a rotação, e o aviso recorrente é o que impede que ela
seja esquecida.

**Conferir o comportamento** (container descartável, sem tocar na stack em uso):

```bash
# 1) produção sem chave → aborta com 78
docker compose run --rm --no-deps --entrypoint sh app \
  -c 'APP_ENV=production APP_KEY= sh /var/www/html/docker/php/entrypoint-prod.sh echo SUBIU; echo "[exit=$?]"'

# 2) produção com chave → segue (exit 0)
docker compose run --rm --no-deps --entrypoint sh app \
  -c 'APP_ENV=production APP_KEY=base64:$(openssl rand -base64 32 | tr -d "\n") sh /var/www/html/docker/php/entrypoint-prod.sh echo SUBIU; echo "[exit=$?]"'

# 3) fora de produção sem chave → chave efêmera em memória, nada escrito em arquivo
docker compose run --rm --no-deps --entrypoint sh app \
  -c 'APP_ENV=local APP_KEY= sh /var/www/html/docker/php/entrypoint-prod.sh sh -c "echo \$APP_KEY"'

# 4) compose de produção sem as senhas → não resolve
docker compose -f docker-compose.prod.yml --env-file /dev/null config

# 5) guard da aplicação
docker compose exec -T app ./vendor/bin/pest tests/Feature/Security/ApplicationKeyProductionTest.php

# 6) o caminho do `composer install`: sem .env e sem APP_ENV/APP_KEY no ambiente,
#    a app se considera em produção e o package:discover AINDA passa (exit 0)
docker compose run --rm --no-deps -v /dev/null:/var/www/html/.env --entrypoint sh app \
  -c 'env -u APP_ENV -u APP_KEY php artisan env; env -u APP_ENV -u APP_KEY php artisan package:discover --no-ansi >/dev/null; echo "[exit=$?]"'
```
