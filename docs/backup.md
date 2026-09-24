# Backup

**Estratégia em camadas** (a regra: *backup que não restaura não é backup*):

| Camada | Frequência | Ferramenta | Onde |
|---|---|---|---|
| Dump lógico do PostgreSQL | Cron via `.env` (padrão: 1/1h) | `backup:run --only-db` (pg_dump 18, gzip, zip AES-256) | R2 |
| **Validação cruzada produção→sandbox** | A cada dump bem-sucedido | Webhook nativo do pacote → endpoint privado no sandbox | produção→sandbox |
| Health check (idade/tamanho) | Diário (cron via `.env`) | `backup:monitor` | alerta e-mail + webhook |
| Retenção (7d todos → 16d diários → 8 sem. semanais → 4m mensais → 2a anuais) | Diário | `backup:clean` | R2 |
| **PITR/WAL archiving** | Contínuo | **Camada de INFRA** (pgBackRest/WAL-G → R2) — documentada, NÃO implementada na aplicação | R2 |

O dump lógico é portátil e restaurável no sandbox — é ele que alimenta a
validação cruzada. O PITR (RPO de segundos/minutos) é a camada de desastre
da infraestrutura: configurar `archive_command`/pgBackRest no PostgreSQL de
produção é responsabilidade de quem opera o servidor, não do app.

**Configuração** (tudo por `.env` — ver `.env.example`, seção Backups):

- `BACKUP_DISKS` — destino do dump. Dev: `local`. Produção: `backup`
  (disco Flysystem S3 do `config/filesystems.php` que reutiliza as
  credenciais R2 `AWS_*`; bucket dedicado opcional via `BACKUP_R2_BUCKET`).
- `BACKUP_ARCHIVE_PASSWORD` — senha da criptografia do zip (AES-256).
  **Obrigatória em produção** (o dump contém o banco inteiro) — e a
  aplicação cobra: ver *Backup sem criptografia é recusado em produção*,
  abaixo.
- `BACKUP_RUN_CRON` / `BACKUP_CLEAN_CRON` / `BACKUP_MONITOR_CRON` —
  frequências (UTC). Começar em 1h e reduzir quando o banco crescer (o PITR
  cobre o RPO).
- `BACKUP_ALERT_EMAIL` — destino dos alertas de falha (padrão:
  `PLATFORM_SUPPORT_EMAIL`).
- `BACKUP_WEBHOOK_URL` — URL do webhook da validação cruzada (abaixo).

**Backup sem criptografia é recusado em produção.** O pacote não reclama
de senha vazia: ele simplesmente monta o zip **em claro** — o dump do banco
inteiro indo para o R2 a cada hora, com webhook de sucesso e monitor
saudável. Por isso, com `APP_ENV=production`, o `backup:run` (o comando e o
agendamento, que roda o mesmo comando) **recusa** quando o zip sairia
desprotegido: `BACKUP_ARCHIVE_PASSWORD` vazia, com valor de placeholder
(`troque-esta-senha` e o resto do vocabulário de
`SECURITY_SECRETS_PLACEHOLDERS`) ou com a cifra desligada
(`encryption = none`, ou PHP sem AES na libzip). A recusa termina o comando
com erro, grava o motivo no log e dispara o evento de falha de backup — o
mesmo que já manda e-mail + webhook quando o dump quebra; nenhum dump é
gerado. **Em dev/local o backup roda** com um aviso no console: o dump é do
banco de desenvolvimento e vai para o disco `local`. A regra mora no comando
(`App\Core\Backup\BackupEncryption`), nunca no boot — `composer install`,
`package:discover` e o php-fpm não fazem backup e não são afetados.
Opt-out consciente (confidencialidade garantida por outra camada — bucket
cifrado **e** de acesso restrito): `BACKUP_ALLOW_UNENCRYPTED_IN_PRODUCTION=true`,
que grava aviso no log a cada execução.

**Política de notificações** (decisão documentada): sucesso do dump → SÓ
webhook (é o gatilho da validação cruzada; e-mail de sucesso é ruído);
falhas e backup não saudável → e-mail + webhook; rotina boa é silenciosa.

**Comandos** (no container: `docker compose exec app php artisan ...`):

```bash
php artisan backup:run --only-db   # dump manual
php artisan backup:list            # backups existentes, saúde e espaço
php artisan backup:monitor         # health check (idade/tamanho)
```

**Restauração manual** (desastre ou restore no sandbox):

```bash
# 1) Localize o zip (backup:list) e baixe do R2 (ou copie de storage/app/private/<APP_NAME>/)
# 2) Descriptografe/descompacte (a senha é BACKUP_ARCHIVE_PASSWORD):
unzip -P "$BACKUP_ARCHIVE_PASSWORD" backup.zip -d restore/
# 3) Restaure o dump (db-dumps/<database>.sql.gz) em um PostgreSQL 18 vazio:
gunzip -c restore/db-dumps/*.sql.gz | psql -h <host> -U <user> <database>
```

**Contrato do webhook de validação cruzada (produção→sandbox):** a cada
evento, o app faz `POST BACKUP_WEBHOOK_URL` com JSON:

```json
{
  "type": "backup_successful",
  "application_name": "TWS Starter Kit (production)",
  "disk_name": "backup",
  "backup_name": "TWS Starter Kit"
}
```

- `type`: `backup_successful` | `backup_failed` | `cleanup_successful` |
  `cleanup_failed` | `healthy_backup_found` | `unhealthy_backup_found`.
- `backup_failed`/`cleanup_failed` incluem `exception` (mensagem do erro);
  `unhealthy_backup_found` inclui `failures` (lista de motivos).
- O gatilho da validação cruzada é `backup_successful`: o sandbox deve
  baixar o zip mais recente do R2, descriptografar, restaurar em banco
  descartável e rodar as checagens (contagens, integridade) — **o endpoint
  receptor no sandbox NÃO faz parte deste kit** (implementação no projeto
  filho; proteger com segredo compartilhado + IP allowlist — não é rota
  pública comum). URL vazia = webhook desativado (nenhuma chamada é feita).

## Testes

Suíte Pest (Feature): configuração de backup (disco de destino, disco R2,
compressão/criptografia, política de notificações, health check,
agendamentos com `onOneServer`+`withoutOverlapping`) e o contrato do
webhook com `Http::fake` (payload de sucesso + nenhuma chamada com URL
vazia) — sem chamadas reais ao R2.
