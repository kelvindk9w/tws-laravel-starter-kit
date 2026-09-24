# Filas (Horizon)

- **Dashboard `/horizon`**: restrito a `is_admin` **com conta ativa** (gate
  `viewHorizon` no `HorizonServiceProvider`, o mesmo critério do
  `canAccessPanel` do `/admin` — fora do ambiente `local`, guest, usuário
  comum e admin desativado recebem 403). O gate olhava só `is_admin`, então
  desativar um administrador tirava o `/admin` e **não** tirava o
  `/horizon`: com a sessão viva ele seguia operando a fila. O middleware
  `Authenticate` do pacote está declarado no `config/horizon.php` em vez de
  depender do construtor do controller base do vendor. Vale também a MESMA
  barreira de origem do /admin (`EnsureAdminIpAllowed`). CSP própria: a SPA Vue do
  dashboard precisa de `unsafe-eval` e das fontes do fonts.bunny.net —
  liberados SOMENTE nas rotas do Horizon (configurável por
  `SECURITY_CSP_HORIZON`); o resto da aplicação segue com a CSP estrita.
- **Dev**: o serviço `queue` do `docker-compose.yml` segue com
  `queue:work` simples; para ver o dashboard com dados reais, troque o
  comando do serviço por `php artisan horizon` (opcional).
- **Produção**: o serviço `horizon` do `docker-compose.prod.yml` roda
  `php artisan horizon` (supervisor com balanceamento; processos via
  `HORIZON_MAX_PROCESSES`). `tries=1` por padrão — fintech não faz retry
  cego em job financeiro (retry que repete efeito de dinheiro duplica o efeito).
- Assets: o Horizon serve CSS/JS inline (não exige publicação em
  `public/vendor`).
- **O que a fila guarda, e por quanto tempo.** O job de e-mail carrega o que o
  e-mail vai dizer: destinatário, código de verificação, token de redefinição
  de senha, mensagem de contato. O Horizon guarda o payload de job concluído e
  falho no Redis e o exibe no dashboard; a tabela `failed_jobs` também o guarda.
  Por isso todo e-mail do kit (`KitMailable`) e a `ResetPasswordNotification`
  implementam `ShouldBeEncrypted`: o payload sai **criptografado com a
  `APP_KEY`**, ilegível no Redis, na `failed_jobs` e no `/horizon` — só o
  worker o abre. Um teste de arquitetura exige isso de todo `Mailable` e
  `Notification` enfileirável novo. Retenção: job concluído sai do Redis em
  1 h (`HORIZON_TRIM_RECENT_MINUTES`), job falho em 7 dias
  (`HORIZON_TRIM_FAILED_MINUTES`), e a `failed_jobs` — que antes guardava para
  sempre, com o texto da exceção — é podada diariamente na mesma janela
  (`queue:prune-failed`, `QUEUE_FAILED_RETENTION_HOURS`, padrão 168).
- **API do Horizon** (`/horizon/api/*`, de onde o dashboard lê os payloads):
  está no mesmo grupo de middleware do dashboard — allowlist de IP + gate de
  admin ativo — e os testes cobrem guest, usuário comum, admin
  bloqueado/pendente e admin fora da allowlist.
- **Sem root:** `horizon` e `scheduler` usam a imagem de produção, que roda como
  `www-data`.

## Testes

Suíte Pest (Feature) — os testes de backup estão em [Backup](backup.md#testes).
Horizon: gating (guest/usuário comum =
403, admin = 200), IP allowlist aplicada às rotas (dashboard e API), CSP
dedicada, supervisores por ambiente, payload criptografado dos jobs de e-mail,
retenção e poda da `failed_jobs`
(`tests/Feature/Horizon/QueuePayloadConfidentialityTest.php`). E-mail em
produção: `tests/Feature/Mail/NonDeliveringMailerProductionTest.php`.
