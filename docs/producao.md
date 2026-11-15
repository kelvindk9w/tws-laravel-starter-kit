# Produção e deploy

> **Monorepo:** o deploy de produção é do starter. O `docker-compose.prod.yml`,
> o `.env.prod.example` e o contexto das imagens vivem em `starters/livewire/`
> (e em `starters/react/`), e todos os comandos desta página rodam **de dentro
> dessa pasta** (é ela que vai para o servidor). O `docker-compose.yml` da raiz do repositório é só o
> ambiente de desenvolvimento.

`docker-compose.prod.yml` é autocontido: em um servidor com Docker instalado,
depois de criar o volume do banco (servidor novo, uma vez; item 3 abaixo),
`docker compose -f docker-compose.prod.yml up -d --build` sobe tudo — app
PHP-FPM com OPcache (imagem imutável), nginx nas portas 80/443 (TLS com
certificado **autoassinado** embutido na imagem), migrate one-shot,
Horizon (filas), scheduler, PostgreSQL e Redis — **banco e Redis sem porta exposta no
host** (rede interna apenas). Projeto criado sem Redis (escolha da criação —
ver [instalação](instalacao.md#sem-redis)): sem o `redis`, e o worker `queue`
(`php artisan queue:work`) no lugar do `horizon`. Nenhuma configuração de SO adicional é exigida
pelo projeto — firewall/DNS são responsabilidade de quem administra o servidor.

Para produção real:

1. `cp .env.prod.example .env.prod` e defina `APP_KEY` — **obrigatória**, e a
   MESMA para `app`, `migrate`, `horizon` e `scheduler` (ver
   [A chave da aplicação em produção](#a-chave-da-aplicação-em-produção)).
2. O nome do produto e as senhas da stack: `PROD_PLATFORM_NAME` (vira o
   `APP_NAME` e o `PLATFORM_NAME` de todos os containers), `PROD_POSTGRES_PASSWORD`,
   `PROD_DB_APP_PASSWORD`, `PROD_DB_MAINTENANCE_PASSWORD` e `PROD_REDIS_PASSWORD`
   **não têm valor padrão** — sem elas o Compose se recusa a resolver o
   arquivo. As três do banco são de **papéis diferentes**
   (ver [Papéis do PostgreSQL](#papéis-do-postgresql-migração--aplicação)). Defina
   `PROD_*` no shell ou no `.env` ao lado do `docker-compose.prod.yml` (o Compose interpola `${PROD_*}` dali
   — ver cabeçalho do `docker-compose.prod.yml` e `.env.prod.example`).
3. **Servidor novo — o volume do banco, uma vez, antes do primeiro `up`:**
   `docker volume create <nome>`, com o nome do `PROD_POSTGRES_VOLUME`
   (padrão: `<nome do projeto>_postgres_data`; o instalador grava). O volume é
   externo: sem ele, o `up` falha, em vez de subir um banco vazio. O nome do
   projeto também é fixo (`PROD_COMPOSE_PROJECT_NAME`, padrão: o nome do
   projeto). **Servidor que já rodava o arquivo anterior: NÃO crie volume** — o banco já
   está no volume de hoje; ache o nome pelo passo 1 de
   [Migrar uma instalação que já roda](#migrar-uma-instalação-que-já-roda) e
   defina `PROD_COMPOSE_PROJECT_NAME`/`PROD_POSTGRES_VOLUME` com ele. Criar um
   volume ali sobe uma segunda stack, com um banco vazio.
   Ver [Volumes e o banco em produção](#volumes-e-o-banco-em-produção).
4. TLS real: monte seus certificados (`server.crt`/`server.key`) em
   `/etc/nginx/certs` — ver comentário no `docker-compose.prod.yml`.
   O nginx só atende os **nomes do site**: o host da `PROD_APP_URL` ou, com
   mais de um (o domínio com e sem `www`), os de `PROD_SERVER_NAMES`
   (separados por espaço). Outro `Host` (o IP do servidor, um domínio alheio)
   leva 444 no HTTP e o TLS recusado no HTTPS — ver
   [Segurança](seguranca.md#na-borda-nginx-só-o-indexphp-executa-e-nenhuma-versão-é-anunciada).
5. **Não rode `db:seed`.** Todo seeder do kit cria dado de demonstração, e em
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

> **Dados NUNCA se perdem ao reiniciar/recriar containers**: o banco fica num
> volume **externo, de nome fixo**, que o Compose nunca cria nem apaga (nem no
> `down -v`); o Redis e os assets públicos, em volumes do projeto, que também
> tem nome fixo. Subir de outra pasta é a mesma stack. Nunca use `down -v` (o
> Redis, com a fila e as sessões, iria junto). Ver
> [Volumes e o banco em produção](#volumes-e-o-banco-em-produção).

**Ordem de subida e healthchecks:** o PostgreSQL (`pg_isready`), o Redis
(`redis-cli ping`, com a senha), o app (o php-fpm aceitando conexão na porta 9000) e o nginx (um endereço interno,
`127.0.0.1:8081/nginx-health`, que não é publicado) têm healthcheck; o app só
sobe com o Redis e o PostgreSQL prontos e as migrations concluídas, e o nginx,
o Horizon e o scheduler só sobem com o app pronto.

## Papéis do PostgreSQL: migração × aplicação

As trilhas só-acréscimo (`audit_events`, `request_logs`,
`outbound_http_logs` e as que o projeto declarar) são protegidas por model,
builder e gatilho no banco — mas o **dono** de uma tabela desliga o gatilho
(`ALTER TABLE … DISABLE TRIGGER`) ou apaga a tabela, e o **superusuário**
passa por cima de tudo. Por isso a aplicação conecta com um papel que não é
nenhum dos dois:

| Papel | Variáveis | Quem usa | O que pode |
| --- | --- | --- | --- |
| **migração** | `DB_MIGRATION_USERNAME` / `DB_MIGRATION_PASSWORD` | `migrate*`, `db:wipe`, `schema:dump` (e as podas, quando não há papel de manutenção) | dono do esquema e das tabelas |
| **aplicação** | `DB_USERNAME` / `DB_PASSWORD` (a conexão padrão) | web, fila, agendador | `SELECT`/`INSERT`/`UPDATE`/`DELETE` nas tabelas comuns; nas só-acréscimo, só `SELECT` e `INSERT` (e `UPDATE` nas colunas liberadas); nada de `CREATE` no esquema, `TRUNCATE` ou `TRIGGER` |
| **manutenção** (opcional; obrigatório no `docker-compose.prod.yml`) | `DB_MAINTENANCE_USERNAME` / `DB_MAINTENANCE_PASSWORD` | as podas das trilhas (`audit:prune`, `outbound-http:prune`), no agendador | `SELECT` e `DELETE` só nas trilhas com poda (e `INSERT` em `audit_events`, onde a poda grava o rastro); nenhuma outra tabela |

**Como o kit aplica** (`twstec/kit-foundation`, `Twstec\Kit\Foundation\Database`):

- Com `DB_MIGRATION_USERNAME` definido (e diferente do `DB_USERNAME`), os
  comandos acima rodam com a conexão de migração — sem `--database`, sem
  mudar nada no `config/database.php` (o pacote monta a conexão `migration` a
  partir da padrão; se o `config/database.php` já tiver uma conexão
  `migration`, vale a dele). O `php artisan migrate` de sempre continua
  funcionando.
- **Todo `migrate` termina acertando o papel da aplicação** (com ou sem
  migration pendente): cria o papel se faltar e o dono puder (superusuário
  ou `CREATEROLE`), com a senha do `DB_PASSWORD`; dá os privilégios em todas
  as tabelas e sequências e os mesmos **privilégios padrão** para as tabelas
  futuras do dono; deixa a tabela `migrations` só para leitura; e, em cada
  tabela só-acréscimo, revoga tudo e devolve só `SELECT`, `INSERT` e o
  `UPDATE` das colunas liberadas. Idempotente.
- `php artisan db:check-roles` confere a conexão da aplicação (ou a de
  `--connection=`) e sai com **1** se o papel for superusuário, puder criar
  papéis, ignorar o Row-Level Security, for dono (ou membro do dono) do banco,
  do esquema ou de uma tabela registrada, puder criar objetos no esquema, ou
  tiver `UPDATE` (na tabela ou numa coluna não liberada), `DELETE`,
  `TRUNCATE` ou `TRIGGER` numa tabela registrada — ou se faltar `SELECT`/
  `INSERT` nela. **0** = papéis separados; **2** = não deu para conferir
  (conexão que não é PostgreSQL, banco fora do ar). Serve para o deploy e
  para o monitoramento.
- Sem `DB_MIGRATION_USERNAME`, nada muda (tudo roda com a conexão padrão, como
  antes) — e o `db:check-roles` aponta o problema.
- **Papel de manutenção.** Com `DB_MAINTENANCE_USERNAME` definido (diferente
  dos outros dois), as podas (`database_roles.maintenance_commands`) rodam
  com a conexão `maintenance` — montada como a de migração — em vez do dono:
  o processo que as agenda não precisa da senha do dono. Todo `migrate` (com
  o papel de migração e o de manutenção definidos) cria o papel se faltar e
  acerta os privilégios dele **de forma exata**: revoga tudo no esquema e
  devolve só `SELECT` e `DELETE` nas trilhas com poda (`INSERT` em
  `audit_events` e o uso da sequência dela, para o rastro da poda), sem
  privilégio padrão para tabelas futuras. Uma poda do projeto entra em
  `maintenance_commands` e a tabela dela em `maintenance_tables`. Com o papel
  definido, o `db:check-roles` também o confere — pelo nome, sem a senha
  dele — e sai com **1** se ele não existir, tiver atributo especial, for
  dono de algo, puder criar no esquema, tiver qualquer privilégio fora da
  lista (inclusive `UPDATE` de coluna, em qualquer tabela) ou faltar um da
  lista. Sem o papel, as podas continuam com o de migração, como antes.

**No `docker-compose.prod.yml`** os três papéis já vêm separados: o
`PROD_POSTGRES_USER` (superusuário do container e dono do banco) só chega ao
`migrate`; o `scheduler` recebe o papel da aplicação e o de manutenção
(`PROD_DB_MAINTENANCE_USERNAME`, padrão `tws_maintenance`, e
`PROD_DB_MAINTENANCE_PASSWORD`), para as podas; o `app` e o `horizon`
recebem só o papel da aplicação (`PROD_DB_APP_USERNAME`, padrão `tws_app`, e
`PROD_DB_APP_PASSWORD`). O `migrate` roda `php artisan migrate --force &&
php artisan db:check-roles`: se a separação não estiver de pé — inclusive um
papel de manutenção com privilégio a mais —, ele falha e o `app` não sobe.

**Tabelas só-acréscimo do projeto** entram em `config/database_roles.php` do
aplicativo, só com a chave `append_only_tables` (ver
[personalizacao.md](personalizacao.md)):

```php
'append_only_tables' => [
    'ledger_entries',
    'payment_events' => ['updatable_columns' => ['status']],
],
```

As do kit não saem da lista. Depois de declarar, rode o `migrate` (ele acerta
os privilégios mesmo sem migration pendente).

### Banco gerenciado ou papéis criados à mão

Quando o administrador do banco cria os papéis (RDS, Cloud SQL, Supabase,
um PostgreSQL compartilhado), o SQL é este — `meuapp_owner` é o papel de
migração, `meuapp_app` o da aplicação; troque os nomes e as senhas:

```sql
-- Como administrador do cluster:
CREATE ROLE meuapp_owner LOGIN PASSWORD 'senha-do-dono';
CREATE ROLE meuapp_app LOGIN PASSWORD 'senha-da-aplicacao'
    NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS;
CREATE ROLE meuapp_maintenance LOGIN PASSWORD 'senha-da-manutencao'
    NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS;
CREATE DATABASE meuapp OWNER meuapp_owner;

-- Conectado no banco meuapp (\c meuapp), como administrador ou meuapp_owner:
REVOKE ALL ON DATABASE meuapp FROM PUBLIC;
GRANT CONNECT, TEMPORARY ON DATABASE meuapp TO meuapp_app;
GRANT CONNECT ON DATABASE meuapp TO meuapp_maintenance;
REVOKE CREATE ON SCHEMA public FROM PUBLIC;
GRANT USAGE ON SCHEMA public TO meuapp_app, meuapp_maintenance;

-- Como meuapp_owner (os privilégios padrão valem para o que ELE criar):
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO meuapp_app;
ALTER DEFAULT PRIVILEGES IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO meuapp_app;
```

Com isso, o `migrate` (com `DB_MIGRATION_USERNAME=meuapp_owner`) faz o resto a
cada deploy. O que ele aplica, para conferir ou repetir à mão depois das
migrations:

```sql
-- Como meuapp_owner:
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO meuapp_app;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO meuapp_app;
REVOKE INSERT, UPDATE, DELETE, TRUNCATE, TRIGGER ON migrations FROM meuapp_app;

-- Tabelas só-acréscimo: só leitura e inserção…
REVOKE ALL ON audit_events, outbound_http_logs, request_logs FROM meuapp_app;
GRANT SELECT, INSERT ON audit_events, outbound_http_logs, request_logs TO meuapp_app;
-- …e o ciclo de vida de request_logs (RequestLog::markFinished/bindTenant):
GRANT UPDATE (status, http_status_response, error_message, duration_ms, tenant_uuid)
    ON request_logs TO meuapp_app;
-- (repita o REVOKE/GRANT para cada tabela de database_roles.append_only_tables)

-- Papel de manutenção (com DB_MAINTENANCE_USERNAME=meuapp_maintenance): nada
-- no esquema, exceto ler e apagar nas trilhas com poda…
REVOKE ALL ON ALL TABLES IN SCHEMA public FROM meuapp_maintenance;
REVOKE ALL ON ALL SEQUENCES IN SCHEMA public FROM meuapp_maintenance;
GRANT SELECT, DELETE ON audit_events, outbound_http_logs TO meuapp_maintenance;
-- …e gravar o rastro da poda na auditoria.
GRANT INSERT ON audit_events TO meuapp_maintenance;
GRANT USAGE ON SEQUENCE audit_events_id_seq TO meuapp_maintenance;
-- (e SELECT, DELETE em cada tabela de database_roles.maintenance_tables)
```

Conferir, com as credenciais da aplicação:

```bash
php artisan db:check-roles          # 0 = separado; 1 = a lista do que falta
```

**As podas apagam com o papel de manutenção.** `audit:prune` e
`outbound-http:prune` removem linhas das trilhas, onde o papel da aplicação
não tem `DELETE`. O processo que as roda (o `scheduler`) fica de pé o tempo
todo e roda código da aplicação: com a senha do dono, um agendador
comprometido apagaria tabelas, desligaria gatilhos e se daria privilégio.
Com o papel de manutenção, o pior que ele faz é podar as trilhas antes da
hora. Sem `DB_MAINTENANCE_USERNAME`, as podas voltam a exigir o papel de
migração no processo que as roda (o comportamento até a 2.0.0-beta.20).

### Atualizar um projeto que já existe

O pacote chega pelo Composer, mas o Docker e os exemplos de ambiente são
arquivos do projeto:

1. **Desenvolvimento** (`compose.yaml` do projeto criado): no `.env`, o papel
   que existe hoje (`DB_USERNAME`, superusuário do container) vira o de
   migração e entra um papel novo para a aplicação:

   ```dotenv
   DB_MIGRATION_USERNAME=tws                 # o DB_USERNAME de antes
   DB_MIGRATION_PASSWORD=<o DB_PASSWORD de antes>
   DB_USERNAME=tws_app
   DB_PASSWORD=<uma senha nova, ex.: openssl rand -hex 16>
   ```

   No `compose.yaml`, o `postgres` (`POSTGRES_USER`/`POSTGRES_PASSWORD`) e o
   `db-init` (`PGUSER`/`PGPASSWORD`) passam a usar `DB_MIGRATION_USERNAME`/
   `DB_MIGRATION_PASSWORD` (copie do `docker/dev/compose.yaml` do starter). Na
   `tests/TestCase.php`, o `runAsMigrationRole()` (a suíte PostgreSQL roda com
   o dono do banco de teste). No CI do projeto (`.github/workflows/ci.yml`),
   `DB_MIGRATION_USERNAME`/`DB_MIGRATION_PASSWORD` iguais ao usuário do
   serviço. Depois: `docker compose up -d` (o `init` roda o `migrate`, que
   cria o `tws_app`) e `docker compose exec app php artisan db:check-roles`.
   Com o `compose.yaml` novo e o `.env` antigo, o Compose recusa subir e diz
   qual variável falta.
2. **Produção** (`docker-compose.prod.yml`): copie do starter o
   `x-migration-env`, o `x-maintenance-env`, o `DB_USERNAME`/`DB_PASSWORD` do
   `x-app-env` e o `command`/`environment` do `migrate` e do `scheduler`;
   defina `PROD_DB_APP_PASSWORD` e `PROD_DB_MAINTENANCE_PASSWORD`. No
   primeiro deploy, o `migrate` cria o `tws_app`, o `tws_maintenance` e os
   privilégios, e o `db:check-roles` confirma. O `php artisan tws:upgrade
   --check` aponta o `docker-compose.prod.yml` e o `.env.prod.example`
   diferentes do starter.
3. **Papel de manutenção num projeto que já separava dois papéis**: no
   `docker-compose.prod.yml`, o `x-maintenance-env` novo, o `migrate` com
   `<<: [*app-env, *migration-env, *maintenance-env]` e o `scheduler` com
   `<<: [*app-env, *maintenance-env]` (sem o `*migration-env`); defina
   `PROD_DB_MAINTENANCE_PASSWORD` (`openssl rand -hex 24`) e faça o deploy. O
   `migrate` cria o papel; a partir daí o `scheduler` não tem mais a senha do
   dono. Banco gerenciado: crie o papel antes, com o SQL acima.
4. **O banco que não some** (nome do projeto e volume do banco fixos, o
   layout do PostgreSQL 18, a trava de um servidor por volume): siga
   [Migrar uma instalação que já roda](#migrar-uma-instalação-que-já-roda),
   **mantendo o nome do projeto de hoje**.

### Arquivos estáticos e o disco `public`

O nginx de produção não tem o código: ele serve o `public/` que o container
`app` **sincroniza** no volume `app_public` a cada subida
(`docker/php/entrypoint-prod.sh`). É uma sincronia, não uma cópia por cima: o
que a versão nova não tem mais (os assets do build anterior, com outro hash)
sai do volume — primeiro entra o novo, depois sai o que sobrou, para o site
nunca ficar sem assets no meio do deploy —, e uma falha aqui **impede o app de
subir**, em vez de deixar o site com os arquivos da versão anterior.

**O disco `public` do Laravel (`storage/app/public`) não é servido pelo nginx
nesta stack.** O link `public/storage` vai junto para o volume, mas aponta para
`/var/www/html/storage/app/public`, que só existe no container `app` (e, sem
volume, não sobrevive à recriação). Os uploads do kit não dependem dele (ficam
no disco privado e saem por URL assinada da aplicação). Para servir arquivos
do disco `public`: um armazenamento externo (S3 e afins, com a URL pública
dele) ou um volume nomeado montado em `/var/www/html/storage/app/public` no
`app` **e** no `nginx` (no nginx, só leitura).

## Comandos que apagam o banco: recusados em produção

Em `APP_ENV=production`, `db:wipe`, `migrate:fresh`, `migrate:refresh`,
`migrate:reset` e `migrate:rollback` são **recusados, mesmo com `--force`**:
saem com código 1 e `This command is prohibited from running in this
environment.`, sem tocar no banco. A saída diz como liberar e a tentativa
grava aviso no log. O `php artisan migrate` de sempre (que só acrescenta)
continua rodando. Fora de produção nada muda (o desenvolvimento e a suíte de
testes, que usa `migrate:fresh`, seguem como antes). Quem aplica é o
`twstec/kit-foundation`, sozinho
(`Twstec\Kit\Foundation\Database\DestructiveCommands`, com o
`DB::prohibitDestructiveCommands()` do Laravel); desligar as guardas de
produção (`SECURITY_PRODUCTION_GUARDS=false`) não libera.

**Por que o banco precisava disso.** O kit desvia esses comandos para o papel
de migração, o dono do banco (ver
[Papéis do PostgreSQL](#papéis-do-postgresql-migração--aplicação)). Nos
containers de longa duração (`app`, `horizon`, `scheduler`) o próprio
PostgreSQL recusa, porque o papel da aplicação não é dono de nada. Mas o
container `migrate` recebe a senha do dono: ali, um `migrate:fresh --force`
apagava todas as tabelas sem perguntar.

**Nunca rode outro comando no container `migrate`** (`docker compose -f
docker-compose.prod.yml run migrate <outro comando>`): tudo o que roda nele
roda como dono do banco. Para consultar o banco, entre no PostgreSQL que já
está no ar (ver [Volumes e o banco em produção](#volumes-e-o-banco-em-produção)).

**A única exceção, planejada: desfazer uma migration.** Primeiro um backup;
depois, o opt-out só neste comando, que grava aviso no log (só `true` liga:
`1`, `yes` ou um valor torto não liberam):

```bash
docker compose -f docker-compose.prod.yml run --rm \
  -e DB_ALLOW_DESTRUCTIVE_COMMANDS_IN_PRODUCTION=true \
  migrate php artisan migrate:rollback --step=1 --force
```

**Com a configuração em cache, o `-e` não tem efeito.** Se a sua imagem roda
`php artisan config:cache` (ou `optimize`) no build ou no entrypoint — a do kit
não roda —, vale o valor guardado no cache, e o comando continua recusado
(falha fechada, mas confunde). Limpe o cache no próprio container descartável,
no mesmo comando (só ele perde o cache; a imagem e os outros containers não
mudam):

```bash
docker compose -f docker-compose.prod.yml run --rm \
  -e DB_ALLOW_DESTRUCTIVE_COMMANDS_IN_PRODUCTION=true \
  migrate sh -c 'php artisan config:clear && php artisan migrate:rollback --step=1 --force'
```

Nunca ponha `DB_ALLOW_DESTRUCTIVE_COMMANDS_IN_PRODUCTION=true` no `.env.prod`
de uma instalação com dado real. A instalação descartável que recria o banco
de propósito (a [demo hospedada](demo.md#demo-pública-hospedada-banco-efêmero-e-reset))
é a que liga o opt-out de vez.

## Volumes e o banco em produção

O `docker-compose.prod.yml` fecha quatro caminhos pelos quais o banco de
produção "sumia" sem nenhum erro — o PostgreSQL subia um banco novo e vazio, e
o de verdade ficava esquecido num volume:

- **O nome do projeto vinha da pasta.** Subir de outra pasta (um clone novo, a
  pasta renomeada, uma pasta por versão) criava outra stack, com outro volume.
  Agora o nome é fixo: `name:` no arquivo, pelo `PROD_COMPOSE_PROJECT_NAME`
  (padrão: o nome do projeto, que o instalador grava). De qualquer pasta, a
  mesma stack.
- **O Compose criava o volume que faltava.** Nome errado era banco novo, sem
  aviso. Agora o volume do banco é **externo**, com nome fixo
  (`PROD_POSTGRES_VOLUME`, padrão `<nome do projeto>_postgres_data` — o nome
  que o Compose dava a ele quando o projeto tinha esse nome): o Compose não o
  cria nem o apaga, nem no `down -v` ("Networks and volumes defined as external
  are never removed", na
  [referência do `docker compose down`](https://docs.docker.com/reference/cli/docker/compose/down/)).
  Sem ele, o `up` falha com `external volume "<nome>" not found`, e nada sobe.
  **Num servidor que já roda, esse erro quer dizer nome errado**, não volume
  faltando: o banco está no volume de hoje, com outro nome (a pasta com
  maiúsculas ou pontos, que o Compose normalizava; um `-p`; um
  `COMPOSE_PROJECT_NAME`; o padrão antigo do arquivo). **Não crie volume**:
  crie e o `up` sobe uma segunda stack, com um banco vazio que o `migrate`
  preenche com o esquema — e quem derruba a stack antiga para liberar as portas
  passa a servir o banco vazio. Ache o nome pelo passo 1 de
  [Migrar uma instalação que já roda](#migrar-uma-instalação-que-já-roda); se
  já criou o volume e subiu, veja
  [Desfazer uma troca errada](#desfazer-uma-troca-errada).
- **O layout do PostgreSQL 18.** A imagem 18 declara o volume em
  `/var/lib/postgresql` (e não mais em `/var/lib/postgresql/data`; ver a
  [página da imagem](https://hub.docker.com/_/postgres)). Montado no caminho
  antigo, cada container recriado ganhava também um volume anônimo. Agora o
  volume é montado em `/var/lib/postgresql` e os dados ficam em
  `<volume>/pgdata` (`PGDATA=/var/lib/postgresql/pgdata`) — **o mesmo
  diretório de antes**, então o volume que já existe sobe sem mover nada.
- **Dois servidores no mesmo volume** (`run postgres` sem comando,
  `--scale postgres=2`, uma segunda stack apontando para o volume) subiam os
  dois, e dois PostgreSQL nos mesmos arquivos corrompem o banco. Agora uma
  trava recusa o segundo (ver [Um servidor por volume](#um-servidor-por-volume)).

O volume do Redis continua do projeto (o nome do projeto, agora fixo, fixa o
dele também): ali ficam fila, sessões e cache, não o registro de nada — perder
o Redis desconecta os usuários e perde jobs pendentes, não dados do banco. O
`app_public` não guarda estado (o `app` o sincroniza a cada subida).

### Servidor novo

**Só para servidor que nunca rodou o projeto.** Servidor que já rodava o
arquivo anterior: **NÃO crie volume** — o banco já está no volume de hoje;
siga [Migrar uma instalação que já roda](#migrar-uma-instalação-que-já-roda)
(o passo 1 acha o nome; defina `PROD_COMPOSE_PROJECT_NAME` e
`PROD_POSTGRES_VOLUME` com ele).

```bash
# uma vez, com o nome do PROD_POSTGRES_VOLUME (padrão: <nome do projeto>_postgres_data)
docker volume create meu-projeto_postgres_data
docker compose -f docker-compose.prod.yml up -d --build
```

Outro nome? Defina `PROD_POSTGRES_VOLUME` (e, se quiser, o
`PROD_COMPOSE_PROJECT_NAME`) **antes** do primeiro `up`, no shell ou no `.env`
ao lado do arquivo, como os outros `PROD_*`. Depois do primeiro deploy,
**nunca mude**: outro nome de volume é outro banco; outro nome de projeto é
outra stack. No servidor, com o arquivo novo, nada de `-p` nem de
`COMPOSE_PROJECT_NAME` (no shell ou no `.env`): os dois passam por cima do
`name:` do arquivo.

Para ver o que o arquivo vai usar, antes de qualquer `up`:

```bash
docker compose -f docker-compose.prod.yml config | sed -n -e '/^name:/p' -e '/^  postgres_data:/,/external/p'
```

**Na máquina de desenvolvimento, não rode o compose de produção com os
padrões.** Os padrões de produção (`<nome do projeto>` e
`<nome do projeto>_postgres_data`) são os mesmos nomes da stack e do volume de
desenvolvimento: o `up` assume os containers de desenvolvimento e sobe com o
banco de desenvolvimento. E ali o `PROD_COMPOSE_PROJECT_NAME` não basta para
fugir disso: o `.env` de desenvolvimento, na mesma pasta, tem
`COMPOSE_PROJECT_NAME=<nome>` (o instalador grava), que vence o `name:` do
arquivo ([precedência do nome do projeto](https://docs.docker.com/compose/how-tos/project-name/)).
Para testar a produção localmente, troque o nome com `-p` — ali ele não faz
mal; no servidor, nunca — e o volume com um só para o teste, os dois em
**todo** comando desse teste. Não confie num `export` da variável: ele vale só
naquele terminal, e num terminal novo o `-p` sem ela sobe o `postgres` de
teste no volume de desenvolvimento, ao lado do `postgres` de lá, que não tem a
trava — dois servidores no mesmo banco:

```bash
docker volume create meu-projeto-prod-local_postgres_data
PROD_POSTGRES_VOLUME=meu-projeto-prod-local_postgres_data docker compose -p meu-projeto-prod-local -f docker-compose.prod.yml config | sed -n -e '/^name:/p' -e '/^  postgres_data:/,/external/p'
PROD_POSTGRES_VOLUME=meu-projeto-prod-local_postgres_data docker compose -p meu-projeto-prod-local -f docker-compose.prod.yml up -d --build
```

### Migrar uma instalação que já roda

Para o projeto que roda com o `docker-compose.prod.yml` anterior (volume do
banco criado pelo Compose e montado em `/var/lib/postgresql/data`). Os dados
não se movem: o arquivo novo lê o mesmo volume, no mesmo diretório.

**Antes de começar, confira o estado do servidor.** O passo a passo supõe o
estado normal: o arquivo anterior na pasta e a stack de hoje servindo. São
três comandos, só de leitura, na pasta da produção. O primeiro diz qual
arquivo está ali (`0`: o anterior; `1`: o novo):

```bash
grep -c 'external: true' docker-compose.prod.yml
```

O segundo olha os containers `postgres` do Compose, no ar ou parados:

```bash
docker ps -a --filter label=com.docker.compose.service=postgres --format '{{.ID}}' \
  | xargs -r docker inspect --format '{{ index .Config.Labels "com.docker.compose.project" }} {{ $v := "" }}{{ range .Mounts }}{{ if eq .Destination "/var/lib/postgresql/data" }}{{ $v = or .Name .Source }}{{ end }}{{ end }}{{ if not $v }}{{ range .Mounts }}{{ if eq .Destination "/var/lib/postgresql" }}{{ $v = or .Name .Source }}{{ end }}{{ end }}{{ end }}{{ $v }} {{ index .Config.Labels "com.docker.compose.project.working_dir" }} {{ .State.Status }}'
```

Sai uma linha por container: `<projeto> <volume> <pasta> <estado>`, em geral
`meu-projeto meu-projeto_postgres_data /srv/meu-projeto running`. O volume é o
montado em `/var/lib/postgresql/data` (o arquivo anterior) ou, só quando não
há montagem nenhuma ali, o de `/var/lib/postgresql` (o arquivo novo); num bind
mount, sai o caminho no host. Um container avulso de `run` repete a linha.

O terceiro olha os volumes que o Compose criou para um `postgres_data`, de
todos os projetos do servidor, uma linha por volume (`<projeto> <volume>`). O
volume que o arquivo anterior criou tem esses rótulos; um volume de
`docker volume create` não tem, e o arquivo novo não cria o volume do banco
(só os do Redis e do `app_public`):

```bash
docker volume ls --filter label=com.docker.compose.volume=postgres_data --format '{{.Label "com.docker.compose.project"}} {{.Name}}'
```

O estado é o normal quando, ao mesmo tempo:

- o primeiro dá `0`;
- o segundo dá **uma linha só** na pasta da produção, e ela está `running`;
- o volume dessa linha aparece no terceiro, **com o mesmo projeto**;
- o terceiro não mostra outro volume do seu projeto (ou mostra, e o conteúdo
  confirma o da linha; veja abaixo).

Um volume sem rótulo que nenhuma linha usa (o criado à mão, depois de
desfeita uma troca errada) não atrapalha. Se tudo bate, mas a única linha da
pasta está parada, suba a produção de hoje com o arquivo anterior
(`docker compose -p <projeto da linha> -f docker-compose.prod.yml up -d`) e
confira de novo. Qualquer outra resposta — o arquivo novo já na pasta, duas
linhas na mesma pasta, o volume da linha fora do terceiro comando ou com
outro projeto — quer dizer que uma troca já foi tentada: **não siga daqui**;
vá para [Desfazer uma troca errada](#desfazer-uma-troca-errada).

**Outro volume do seu projeto no terceiro comando** (a pasta foi renomeada,
ou um clone noutra pasta subiu o arquivo anterior, e o Compose criou um
segundo volume, vazio, com outro nome de projeto): veja o que cada um tem. O
comando abre cada volume num container descartável, sem rede e com o volume
só leitura; o container cria e apaga um volume anônimo temporário (o que a
imagem declara) e não baixa imagem (`--pull never`: usa a do seu `postgres`).
Ele mostra a criação do volume (no fuso do servidor, com o deslocamento no
fim), o tamanho dos dados e a última escrita do PostgreSQL (o `pg_control`,
gravado a cada checkpoint; em UTC):

```bash
for v in $(docker volume ls -q --filter label=com.docker.compose.volume=postgres_data); do
  docker run --rm --pull never --network none -v "$v:/v:ro" postgres:18-alpine sh -c \
    'echo "$0 criado $1 dados $(du -sh /v/pgdata/base 2>/dev/null | cut -f1) escrita $(date -u -r /v/pgdata/global/pg_control "+%F %T UTC" 2>/dev/null)"' \
    "$v" "$(docker volume inspect --format '{{.CreatedAt}}' "$v")"
done
```

O volume da linha tem que ser o mais antigo e com os dados; o outro, mais
novo e pequeno (só o esquema que o `migrate` cria, uns 30 MB de dados). Se for
o contrário — a stack que serve hoje está no volume novo —, ou se não ficar
claro, **pare e confira antes de seguir**: os dois volumes foram criados pelo
Compose, e a stack de hoje pode ter recebido uso de verdade, com dados dos
dois lados; juntar os dados fica fora deste documento. Não suba nada com o
outro volume e não apague nenhum.

1. **Anote o projeto e o volume da linha da conferência** — os dois da
   **mesma** linha. É esse o volume do banco: **não crie outro**. Esse
   projeto vale nos passos 2 e 3, no `-p` dos comandos com o arquivo anterior
   (ele não tem `name:`; sem o `-p`, uma pasta com nome diferente do projeto
   — a produção subia com `-p` ou `COMPOSE_PROJECT_NAME` — faz o Compose
   procurar outro projeto: o `exec` falha e o `stop` sai sem erro, sem parar
   nada), e no passo 5, no `PROD_COMPOSE_PROJECT_NAME` (com o arquivo novo,
   nunca `-p`). O volume vale no passo 5.
2. **Um backup antes** (recomendado). O `umask 077` faz o arquivo nascer
   legível só por você: ele tem todos os dados, inclusive pessoais. Guarde-o
   fora do repositório e apague quando não precisar mais:

   ```bash
   (umask 077 && docker compose -p <projeto do passo 1> -f docker-compose.prod.yml exec -T postgres pg_dumpall -U <PROD_POSTGRES_USER> > antes-da-migracao.sql)
   ```

3. **Pare o PostgreSQL antigo, ainda com o arquivo antigo** (sem `-v` e sem
   `down`):

   ```bash
   docker compose -p <projeto do passo 1> -f docker-compose.prod.yml stop -t 120 postgres
   docker compose -p <projeto do passo 1> -f docker-compose.prod.yml logs --tail 3 postgres
   ```

   O log tem que terminar em `database system is shut down`. O container
   antigo só tem os 10 s padrão do Docker para desligar; o `-t 120` dá tempo
   para o checkpoint. E, parado, ele não fica no ar ao lado do novo se o nome
   do projeto sair errado — dois servidores no mesmo volume corrompem o banco,
   e o antigo não tem a trava. Daqui até o passo 7 o site fica sem banco: faça
   numa janela de manutenção.
4. **Traga os arquivos novos do starter** (a lista está no `CHANGELOG.md`, na
   versão desta mudança), reaplicando as suas mudanças locais.
5. **Fixe o nome do projeto e o do volume com os valores do passo 1**, no
   `.env` ao lado do arquivo (onde ficam os outros `PROD_*`):

   ```dotenv
   PROD_COMPOSE_PROJECT_NAME=<projeto do passo 1>
   PROD_POSTGRES_VOLUME=<volume do passo 1>
   ```

   Se esse `.env` tiver `COMPOSE_PROJECT_NAME`, tire (ele passaria por cima).
   **O nome do projeto tem que ser o de hoje**: assim o Compose recria os
   containers no lugar. Com outro nome, ele subiria uma segunda stack ao lado
   da antiga (o app antigo ainda no ar, nas mesmas portas).
6. **Confira**: o `name:` do projeto e o do `postgres_data` têm que ser os do
   passo 1, com `external: true`:

   ```bash
   docker compose -f docker-compose.prod.yml config | sed -n -e '/^name:/p' -e '/^  postgres_data:/,/external/p'
   ```

7. **Suba**: `docker compose -f docker-compose.prod.yml up -d --build`.
8. **Confirme que o banco é o de antes**:

   ```bash
   docker compose -f docker-compose.prod.yml logs postgres | grep -E 'Skipping initialization|init process complete'
   ```

   Tem que aparecer `Skipping initialization` e **nunca** `init process
   complete`. Se aparecer `init process complete`, pare na hora
   (`docker compose -f docker-compose.prod.yml stop`): o PostgreSQL criou um
   banco novo, porque o volume ou o `PGDATA` não são os de antes. O banco de
   verdade continua intacto no volume do passo 1 — corrija o arquivo e suba de
   novo.

**Voltar atrás:** pare o `postgres` com o arquivo novo
(`docker compose -f docker-compose.prod.yml stop postgres`, que já espera
2 min), ponha o arquivo anterior de volta, na mesma pasta, e suba com o
projeto do passo 1
(`docker compose -p <projeto do passo 1> -f docker-compose.prod.yml up -d`).
Ele lê o mesmo volume (os dados estão no mesmo diretório). Se tirou um
`COMPOSE_PROJECT_NAME` no passo 5, devolva-o, para os comandos sem `-p`. **O
volume anônimo** que o layout antigo deixava (só o diretório vazio `data`, sem
dado) pode ficar.

**Desenvolvimento** (o `compose.yaml` do projeto criado): só o layout muda —
copie o serviço `postgres` do `docker/dev/compose.yaml` do starter (a montagem
**e** o `PGDATA`) e `docker compose up -d`; o banco de desenvolvimento sobe com
os dados.

**Editando à mão, mude a montagem e o `PGDATA` juntos.** Só um dos dois cria
um banco **novo e vazio**, sem erro (o log mostra `init process complete`):

- montagem nova (`/var/lib/postgresql`) com o `PGDATA` antigo
  (`/var/lib/postgresql/data/pgdata`): o PostgreSQL procura em
  `<volume>/data/pgdata`, não acha nada e inicializa ali;
- montagem nova sem `PGDATA`: vale o padrão da imagem 18,
  `/var/lib/postgresql/18/docker`, e o banco novo nasce em
  `<volume>/18/docker`.

Nos dois casos o banco de verdade continua intacto em `<volume>/pgdata`:
corrija o arquivo e suba de novo.

### Desfazer uma troca errada

Para quando a conferência de
[Migrar uma instalação que já roda](#migrar-uma-instalação-que-já-roda) mostra
que o arquivo novo já subiu uma stack com outro nome de projeto — em geral
depois de um `external volume ... not found` resolvido com um
`docker volume create`. Essa stack errada usa, em geral, o volume criado à
mão, que nasceu vazio; o banco de verdade continua no volume com o rótulo do
Compose. Os passos abaixo voltam ao estado normal sem nunca pôr dois
PostgreSQL no volume de verdade e sem apagar nada.

1. **Anote os projetos**, pelos comandos da conferência. O **projeto de
   hoje** e o **volume de hoje** são os do terceiro comando: o volume do seu
   projeto com o rótulo do Compose, e o projeto do rótulo, que é o da stack
   antiga. O **projeto errado** é o de qualquer outra linha da pasta no
   segundo comando — em geral com o volume criado à mão, que não aparece no
   terceiro, mas também com o volume de hoje, se o arquivo novo subiu com ele
   e outro nome de projeto. Sem a linha do projeto errado,
   a stack errada não está no servidor (não subiu, ou já foi derrubada): pule
   para o passo 4 — mas, se ela chegou a servir o site, pare, como no passo 2.
2. **Veja se o site passou pela stack errada**, antes de derrubá-la (o
   `docker rm` do passo 3 apaga a pista):

   ```bash
   docker ps -a --filter label=com.docker.compose.project=<projeto errado> --format '{{.Label "com.docker.compose.service"}} {{.Status}}'
   ```

   Se o `nginx` aparece `Up` ou `Exited`, ele chegou a rodar com as portas da
   produção e serviu o site a partir do banco vazio: trate como uso de
   verdade (alguém pode ter se cadastrado ou pago ali). O mesmo se a stack
   errada já foi derrubada e o site ficou no ar com ela. Nesse caso, **pare
   aqui**: há dados dos dois lados. Não apague nenhum volume e não suba a
   stack antiga ao lado da errada; juntar os dados fica fora deste
   documento. Com o `nginx` só em `Created` (as portas estavam ocupadas pela
   stack antiga) ou sem `nginx`, o site não passou por ela: siga.
3. **Derrube a stack errada** pelos rótulos (vale com qualquer arquivo na
   pasta; sem `-v`), tire a rede dela e confira que nenhum container dela
   ficou — o último comando não pode mostrar nada:

   ```bash
   docker ps -q --filter label=com.docker.compose.project=<projeto errado> | xargs -r docker stop -t 120
   docker ps -aq --filter label=com.docker.compose.project=<projeto errado> | xargs -r docker rm
   docker network ls -q --filter label=com.docker.compose.project=<projeto errado> | xargs -r docker network rm
   docker ps -a --filter label=com.docker.compose.project=<projeto errado> --format '{{.Names}}'
   ```

   Os volumes dela ficam: o criado à mão e os do Redis e do `app_public`
   dela.
4. **Ponha o arquivo anterior de volta**, na mesma pasta.
5. **Suba a stack de hoje com ele**, pelo projeto de hoje. O `-p` acerta o
   nome com qualquer pasta e com ou sem `COMPOSE_PROJECT_NAME`, e o arquivo
   anterior monta o volume de hoje (`<projeto de hoje>_postgres_data`). O
   `stop` não faz nada se o `postgres` de hoje está parado ou não existe; se
   está no ar, ele desliga com tempo para o checkpoint e volta no `up`. O
   `desde` guarda a hora, para o passo 6 ler só o log desta subida (um
   container que só estava parado guarda o log de sempre, com o
   `init process complete` do primeiro dia):

   ```bash
   desde=$(date -u +%Y-%m-%dT%H:%M:%SZ)
   docker compose -p <projeto de hoje> -f docker-compose.prod.yml stop -t 120 postgres
   docker compose -p <projeto de hoje> -f docker-compose.prod.yml up -d
   ```

6. **Confira** que só um PostgreSQL usa o volume de hoje e que o banco é o de
   antes:

   ```bash
   docker ps --filter volume=<volume de hoje> --format '{{.Names}} {{.Status}}'
   docker compose -p <projeto de hoje> -f docker-compose.prod.yml logs --since "$desde" postgres | grep -E 'Skipping initialization|init process complete'
   ```

   O primeiro mostra uma linha só, a do `postgres` de hoje; o segundo (no
   mesmo terminal do passo 5), `Skipping initialization` e **nunca**
   `init process complete`. E o site mostra os dados de antes.

   **Se a linha do projeto errado tinha o volume de hoje**, dois PostgreSQL
   abriram o mesmo banco ao mesmo tempo (o arquivo anterior não tem a trava).
   O reinício do passo 5 volta a um servidor só, mas o banco pode ter se
   corrompido nesse tempo: procure também `PANIC`, `invalid` e `corrupt` no
   log, confira os dados e, na dúvida, pare e restaure de um backup antes de
   seguir.
7. **Recomece** pela conferência de
   [Migrar uma instalação que já roda](#migrar-uma-instalação-que-já-roda):
   agora ela passa.

**O volume criado à mão** fica até o passo 8 da migração confirmar o banco.
Depois, se o site não passou pela stack errada (passo 2), ele só tem o
esquema e o que o app gravou sozinho, e pode ser apagado, junto com os do
Redis e do `app_public` da stack errada:
`docker volume rm <volume criado à mão> <projeto errado>_redis_data <projeto errado>_app_public`.
Confira os nomes antes, um por um: nunca o volume do passo 1. O Docker recusa
apagar um volume que um container usa, mas não protege um volume livre.

### Um servidor por volume

O serviço `postgres` começa por uma trava (`flock`) no arquivo
`.postgres-server.lock`, na raiz do volume, e só então chama o entrypoint da
imagem. O PostgreSQL herda a trava e a segura enquanto roda; ela some com o
processo (não sobra trava velha depois de um `docker kill` ou de uma queda do
servidor). Um segundo servidor no mesmo volume sai na hora, com código 1 e,
no log:

```text
[postgres] RECUSADO: outro servidor PostgreSQL ja usa este volume (docs/producao.md).
```

Sem a trava, o PostgreSQL não se protege disso no Docker: cada container tem o
próprio espaço de processos, e o `postmaster.pid` não serve de trava entre
containers. Comando que não é o servidor (`psql`, `pg_dump`) passa direto. Não
apague o arquivo da trava. A trava só vale entre containers que a têm — o
`postgres` do arquivo anterior não a segura; por isso a migração mantém o nome
do projeto.

### Acessar o banco, e o que nunca fazer

- **Consultar ou exportar:** sempre no PostgreSQL que já está no ar —
  `docker compose -f docker-compose.prod.yml exec postgres psql -U <PROD_POSTGRES_USER> -d <PROD_POSTGRES_DB>`
  (ou `exec -T postgres pg_dump ...`).
- **Nunca `docker compose run postgres` sem comando**: é um segundo servidor
  no mesmo volume (a trava recusa).
- **Nunca `--scale postgres=2`, nem duas stacks no mesmo volume** (outra pasta
  com outro `PROD_COMPOSE_PROJECT_NAME`, `-p`, `COMPOSE_PROJECT_NAME`): a
  trava recusa o segundo, que fica reiniciando com o aviso no log.
- **Nunca `run migrate <outro comando>`** (ver
  [Comandos que apagam o banco](#comandos-que-apagam-o-banco-recusados-em-produção)).
- **Nunca `down -v`**: o volume do banco fica (é externo), mas o do Redis vai
  junto. Nunca `docker volume rm` no volume do banco, nem
  `docker volume prune --all`, que apaga também **volume nomeado** sem
  container — o do banco, com a stack parada
  ([referência](https://docs.docker.com/reference/cli/docker/volume/prune/)).
- **`docker kill` (ou `docker stop`) no `postgres` não volta sozinho**, nem
  depois de reiniciar o servidor: com `restart: unless-stopped`, parada manual
  suspende o reinício
  ([política de reinício](https://docs.docker.com/engine/containers/start-containers-automatically/)).
  `docker compose -f docker-compose.prod.yml up -d` o traz. Depois de um
  `kill`, o log mostra `database system was not properly shut down; automatic
  recovery in progress`: o PostgreSQL se recupera pelo WAL, mas a parada
  limpa é o `stop`/`down`, que espera o `stop_grace_period`.

### O serviço postgres

Ajustes do `docker-compose.prod.yml`, cada um por variável (no shell ou no
`.env` ao lado do arquivo; os padrões estão no `.env.prod.example`):

- **Desligamento limpo** — `stop_grace_period`, `PROD_POSTGRES_STOP_GRACE_PERIOD`
  (padrão `2m`). A imagem para o PostgreSQL com SIGINT, o *fast shutdown*, que
  grava um checkpoint antes de sair
  ([desligar o servidor](https://www.postgresql.org/docs/current/server-shutdown.html)).
  O padrão do Docker, 10 s, mata o processo no meio de um checkpoint grande, e
  a subida seguinte passa pela recuperação de falha; o próprio Dockerfile da
  imagem recomenda ajustar esse tempo e lembra que, segundo a documentação do
  PostgreSQL, nem 90 s bastam em muitos casos. No desligamento do servidor, o
  Docker espera o maior tempo de parada dos containers, mas o systemd espera o
  Docker só o `TimeoutStopSec` do serviço (padrão do systemd: 90 s; confira com
  `systemctl show docker -p TimeoutStopUSec`): para valer ali também, aumente-o
  com `systemctl edit docker`.
- **`/dev/shm`** — `shm_size`, `PROD_POSTGRES_SHM_SIZE` (padrão `256mb`). O
  padrão do Docker é 64 MB; o PostgreSQL usa memória compartilhada POSIX nas
  consultas paralelas e, sem espaço, falha com `could not resize shared memory
  segment ... No space left on device`. O valor é o que a
  [documentação da imagem](https://hub.docker.com/_/postgres) sugere (seção
  *Caveats*).
- **OOM killer** — `oom_score_adj`, `PROD_POSTGRES_OOM_SCORE_ADJ` (padrão
  `-1000`), com `PG_OOM_ADJUST_FILE=/proc/self/oom_score_adj` e
  `PG_OOM_ADJUST_VALUE=0`. Com a memória do servidor acabando, o kernel mata
  um processo; se for o processo principal (o postmaster), o banco cai. `-1000`
  o tira da lista, e as variáveis devolvem os processos filhos (as conexões) ao
  normal, para o kernel ainda poder escolhê-los — exatamente o que a
  documentação do PostgreSQL indica
  ([Linux Memory Overcommit](https://www.postgresql.org/docs/current/kernel-resources.html#LINUX-MEMORY-OVERCOMMIT)).
  **Os processos do `docker compose exec` no `postgres` herdam o `-1000`** — o
  healthcheck (`pg_isready`), o `exec psql`, o `exec pg_dump` — e também ficam
  fora do OOM killer. O psql guarda o resultado inteiro de uma consulta na
  memória: um `SELECT` enorme pelo `exec` cresce sem poder ser escolhido, e o
  kernel mata **outro** processo (uma conexão do banco, o PHP). Para consulta
  grande, busque em partes (`\set FETCH_COUNT 1000` no psql) ou exporte com
  `\copy`. **Docker rootless** ignora o valor e usa o do próprio daemon
  ([`specconv` do moby](https://github.com/moby/moby/blob/master/daemon/internal/rootless/specconv/specconv_linux.go)):
  o `up` não quebra, a proteção só não tem efeito.
- **Sem teto de memória no `postgres`**, de propósito: no teto, o kernel
  mataria processos do banco. O teto vai nos serviços PHP (abaixo).

### Memória dos serviços PHP

`PROD_APP_MEMORY_LIMIT`, `PROD_WORKER_MEMORY_LIMIT` (o `horizon` ou o `queue`)
e `PROD_SCHEDULER_MEMORY_LIMIT` viram o `mem_limit` de cada container (`512m`,
`2g`...). O padrão é **`0`, sem teto**: o kit não sabe o tamanho do servidor,
e um teto abaixo do pico mata requisição e job legítimos.

Com teto, o processo que estoura morre **dentro do container dele** (o php-fpm
repõe o processo; o worker reinicia), e o OOM killer do servidor não entra em
cena — nem chega perto do banco. Sem teto, um vazamento no PHP pode levar o
servidor inteiro ao limite, e aí o kernel escolhe: o postmaster está protegido
(acima), mas uma conexão do banco pode ser a escolhida, e o PostgreSQL
reinicia todas as conexões para se recuperar.

Para escolher o valor, parta do pico de cada um, com folga:

- **app**: `pm.max_children` do php-fpm (5, o padrão da imagem oficial) ×
  `memory_limit` do PHP (256M, no `docker/php/php-prod.ini`), mais o OPcache;
- **worker**: processos de fila (`HORIZON_MAX_PROCESSES`, ou 1 no
  `queue:work`) × 256M, mais o processo mestre;
- **scheduler**: o `schedule:run` e os comandos que ele dispara.

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
# no monorepo (de starters/livewire ou starters/react)
docker build --build-context packages=../../packages -f docker/php/Dockerfile --target prod -t tws-app:prod .
# num projeto criado pelo create-project (sem a pasta packages/)
docker build -f docker/php/Dockerfile --target prod -t tws-app:prod .

docker run --rm --entrypoint sh tws-app:prod -c \
  'id; ls -A /var/www/html; find /var/www/html/storage -type f | wc -l; ls -A /var/www/html/.env* 2>&1'
```

**O mesmo Dockerfile nos dois cenários.** No monorepo, o
`--build-context packages=../../packages` entrega à imagem a pasta `packages/` da raiz do
repositório: o estágio do Composer instala dali os pacotes do kit (path repository),
**copiando** cada um para `vendor/` (`COMPOSER_MIRROR_PATH_REPOS=1`) — a imagem final não tem link
para fora dela nem a pasta `/packages`. O `packages/.dockerignore` deixa de fora o que é de
desenvolvimento do pacote (`vendor/`, `tests/`, `composer.lock`, `phpunit.xml`), a demonstração e
o instalador. O `docker-compose.prod.yml` do monorepo já passa esse contexto
(`additional_contexts`, Compose 2.17+). Num projeto criado pelo `create-project`, o contexto não
existe: o Dockerfile tem um estágio `packages` **vazio** (só um arquivo-marcador, para o cache do
BuildKit nunca confundir as duas origens), que o contexto nomeado substitui quando
é passado (regra do BuildKit); sem ele, `/packages` fica vazio e o Composer instala os pacotes do
kit do Packagist, como qualquer dependência. O `docker-compose.prod.yml` **publicado** sai sem as
linhas `additional_contexts` (o `.github/release/prepare-composer.php` as tira na publicação). Se
um `composer.json` com path repository for construído sem o contexto, o build para com a
instrução. A simulação da instalação publicada, no CI, constrói as imagens (app e nginx) do
projeto criado e passa as mesmas conferências de imagem limpa do job de imagens.

Esperado: `uid=82(www-data)`; na raiz só `app artisan bootstrap composer.json
composer.lock config database lang package*.json phpstan.modules.php
phpstan.neon.dist public resources routes storage vendor vite.config.js` (no
React, `vite.config.ts`, `tsconfig.json` e `components.json`), o `README.md`, o `LICENSE` e o `SECURITY.md` do starter,
`.npmrc` e `.dockerignore` (o contexto do build é a pasta do starter: `docs/` e os demais `.md`
da raiz do repositório não entram); zero arquivo em `storage/`; nenhum `.env*`.

## E-mail em produção: sem mailer de verdade, nenhum e-mail sai

O padrão de `MAIL_MAILER` é `log` (no `config/mail.php` e no
`docker-compose.prod.yml`). Em desenvolvimento isso é útil; em produção,
significava que **cada e-mail era gravado inteiro no arquivo de log** — o código
de verificação da ação sensível, o link de redefinição de senha com o token, a
mensagem de contato com nome e e-mail — sem nenhum erro. O `array` é o irmão
silencioso: descarta tudo.

Com `APP_ENV=production`, os transportes `log` e `array` **recusam o envio**
(`Twstec\Kit\Foundation\Mail\NonDeliveringMailers`): o job de e-mail falha com uma mensagem
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
| `Twstec\Kit\Foundation\Support\CriticalSecrets` (boot) | Chave ausente **ou com valor de exemplo/placeholder** → recusa o boot (`MissingApplicationKeyException`) nos processos que **servem tráfego ou processam trabalho**; nos comandos de instalação e manutenção, avisa em voz alta (log + stderr) e deixa passar. |
| `docker-compose.prod.yml` | `PROD_PLATFORM_NAME`, `PROD_POSTGRES_PASSWORD`, `PROD_DB_APP_PASSWORD`, `PROD_DB_MAINTENANCE_PASSWORD` / `PROD_REDIS_PASSWORD` sem fallback (`${VAR:?…}`): o Compose não resolve o arquivo sem elas. |

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

- **Chaves de API** — o hash da secreta é HMAC com pepper, e o
  `APP_PREVIOUS_KEYS` não participa dele. Como `API_KEYS_HASH_PEPPER` tem
  fallback para a `APP_KEY` (e **vazio conta como ausente** — a linha
  `API_KEYS_HASH_PEPPER=` sem valor também cai na `APP_KEY`), rotacionar a
  chave sem um pepper próprio invalida toda chave de API já emitida (401) —
  a menos que a `APP_KEY` antiga seja declarada em
  `API_KEYS_PREVIOUS_HASH_PEPPERS`, que faz a chave migrar para o pepper atual
  no primeiro uso. Defina um `API_KEYS_HASH_PEPPER` dedicado **antes de emitir
  a primeira chave** e a rotação da `APP_KEY` deixa de afetá-las. Em
  `APP_ENV=production` o boot avisa no log enquanto não houver pepper
  dedicado e enquanto `API_KEYS_ACCEPT_EMPTY_PEPPER_LEGACY` estiver ligada.
  Transição de uma instalação que já emitiu chaves com pepper vazio:
  [docs/api.md](api.md#roteiro-de-transição).
- **Sessões e cookies** — não é perda de dado, é logout: todos refazem o login.

### A chave dos uploads confidenciais é outra

Uploads `confidential` (documentos de identificação, contratos) são cifrados
com uma chave **própria**, `UPLOADS_ENCRYPTION_KEY` — a `APP_KEY` não serve
(igual a ela conta como ausente), e trocar a `APP_KEY` não mexe neles. Gere com
`php artisan uploads:encryption-key --show`, guarde no **mesmo cofre** da
`APP_KEY` e entregue a todos os serviços PHP pelo `.env.prod` ou pelo secret do
orquestrador. Sem ela, o upload confidencial é recusado e o boot avisa no log
a cada subida. Perder a chave é perder os arquivos (o backup do armazenamento
guarda só o cifrado). Trocar: a nova em `UPLOADS_ENCRYPTION_KEY`, a antiga em
`UPLOADS_ENCRYPTION_PREVIOUS_KEYS`, `php artisan uploads:reencrypt` (sem tirar
nada do ar) e só então tire a antiga — ver
[docs/uploads.md](uploads.md#rotação-sem-indisponibilidade).

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
