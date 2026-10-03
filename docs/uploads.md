# Uploads seguros

Pacote **`twstec/kit-uploads`** ([`packages/uploads`](../packages/uploads)),
namespace `Twstec\Kit\Uploads\` (até a 1.x era `app/Core/Uploads`; os nomes
`App\Core\Uploads\…` continuam resolvendo até a 3.0). **Função global
única**: todo upload do sistema passa pelo `SecureUploadService::handle()` —
nenhum controller faz `store()` direto de arquivo.

> **Módulo opcional** (exige o `twstec/kit-accounts`: o upload pertence a uma
> conta). Sem ele, o perfil não tem foto e o `/admin` não tem a tela de
> uploads — ver [instalação e módulos](instalacao.md).

O pacote não tem telas. O formulário do perfil (Livewire), o campo de foto do
`/admin` (Filament) e a rota web do avatar são do starter; o pacote traz o
serviço, a validação, o model, a migration, a configuração, as traduções, a
foto de perfil (`HasAvatar`) e a rota `POST /api/v1/uploads`.

## Política de segurança (lei)

O arquivo é o que os **magic bytes** dizem, NUNCA a extensão declarada.
Pipeline, nesta ordem — **qualquer suspeita = rejeitado**:

1. **Formulário** (delegada ao chamador via Form Request): arquivo presente,
   `mimes:` (o Laravel sniffa o MIME real — corte grosseiro) e tamanho teto.
2. **Segurança do arquivo** (`FileSecurityValidator`):
   - Executável disfarçado (PE `MZ`, ELF, shebang, Java class) → fora;
   - MIME real via `finfo` contra a **allowlist por tipo** (config: imagens
     jpeg/png/webp + pdf por padrão). PDF é só PDF, imagem é só imagem;
   - Extensão declarada divergente do conteúdo real → fora;
   - Varredura de script embutido (`<?php`, `<?=`, `<script`, `#!`) → fora
     (polyglot);
   - PDF com JavaScript/ações automáticas (`/JS`, `/JavaScript`,
     `/OpenAction`, `/AA`) → fora (política do dono: suspeita = não aceita);
   - **Re-encode de imagem via GD: SIM** (decisão documentada). A imagem é
     decodificada e re-gerada do zero antes de persistir — metadados,
     comentários e trailing data (onde payloads se escondem) não sobrevivem.
     Custo irrelevante para os tamanhos do MVP (≤5 MB) e a GD já está no
     container; inclui teto de pixels contra decompression bomb. Falha de
     decode ou GD ausente = falha fechada (rejeita).
3. **Limite de tamanho por tipo** sobre o conteúdo FINAL (depois do
   re-encode) — o limite de segurança definitivo.
4. **Nome seguro**: `uuid` + extensão derivada do MIME REAL. O nome original
   NUNCA compõe o path (guardado sanitizado em `original_name`, só exibição).
5. **Persistência** no disco configurado + registro em `uploads` (uuid,
   `codigo_publico` UPL-xxxxxx, a conta e quem enviou, MIME real, tamanho,
   sha256 do conteúdo final) + log estruturado (`upload.stored` /
   `upload.rejected`, sem dados sensíveis, no canal padrão de log).

Arquivo rejeitado **não toca o disco nem o banco** — só o log. Essas regras
moram no domínio (serviço e validador) e valem em qualquer aplicação que
instale o pacote, sem configuração.

## Entrega por URL assinada

`Upload::url()` devolve uma **URL temporária assinada** (validade em
`UPLOADS_TEMPORARY_URL_MINUTES`, 15 minutos por padrão). No S3/R2 quem assina
é o armazenamento. No disco `local` é a entrega do Laravel (`serve`, rota
`/storage/{path}`), que recusa pedido sem assinatura, com assinatura
adulterada, de outro caminho ou vencido — a assinatura cobre o caminho
inteiro, então a URL de um arquivo não abre o de outro dono.

O **pacote liga essa entrega sozinho** no disco padrão de uploads quando ele
é local, mesmo que o disco da aplicação não a declare (esqueleto anterior) ou
a declare desligada: ela só entrega com assinatura válida, então ligá-la não
expõe nada. Ficam com a aplicação, com **aviso no log a cada boot**:

- `UPLOADS_PROTECTIONS=false` (`uploads.protections`) — opt-out explícito: o
  pacote não mexe no disco;
- disco de uploads com `visibility => public` — a entrega responde sem
  conferir a assinatura (uploads devem ir para disco privado);
- outro disco já entregue em `/storage` — ligar derrubaria o boot, então o
  pacote não liga.

Para um driver que não sabe assinar, `url()` cai na URL comum do disco (sem
assinatura), como na 1.x.

**Assinar é dar acesso a quem receber a URL**: `url()` só assina upload da
**conta atual** (ou em modo sistema declarado — o `/admin`, a leitura da foto
de perfil). Um upload de outra conta que chegue à mão por outro caminho (uma
relação carregada antes, um objeto guardado) é recusado com
`Access\UploadOutsideAccountException`.

## Como usar o serviço

```php
use Twstec\Kit\Uploads\Classification\UploadClassification;
use Twstec\Kit\Uploads\Services\SecureUploadService;

// Na conta atual (a da sessão na web, a da chave na API), com created_by =
// quem está agindo. Sem conta atual: MissingAccountContextException, antes
// de tocar no disco.
$upload = app(SecureUploadService::class)->handle(
    $request->file('file'),           // UploadedFile (Form Request já validou)
    disk: 's3',                        // opcional — default: config uploads.disk
    directory: 'documentos',           // opcional — default: uploads.directory
    allowedTypes: ['image', 'pdf'],    // opcional — default: uploads.allowed_types
    classification: UploadClassification::Confidential, // opcional — default: uploads.classification.default (private)
);

$upload->url();               // URL temporária assinada (bucket NUNCA público)
$upload->url(download: true); // confidencial: a mesma, para baixar (anexo)
```

Rejeições lançam `UploadRejectedException` (com `reason` estável para logs);
os controllers convertem em 422. Confidencial sem chave de cifra lança
`ConfidentialStorageUnavailableException` (503).

## Classificação por finalidade

Todo upload tem uma **classificação** (`classification`, enum
`Twstec\Kit\Uploads\Classification\UploadClassification`), que o
**projeto declara** em cada chamada do `SecureUploadService` (parâmetro
`classification`) — ou o padrão `UPLOADS_DEFAULT_CLASSIFICATION`:

| Classificação | Para quê | Armazenamento | Entrega |
| --- | --- | --- | --- |
| `public` | conteúdo que o projeto pode mostrar a quem tiver o link (a imagem de um produto) | como sempre | URL assinada de curta duração — o kit nunca usa bucket público; a classificação registra a intenção |
| `private` (padrão) | o resto — e todo upload anterior à classificação | como sempre | URL assinada de curta duração |
| `confidential` | documento de identificação, contrato, comprovante | **cifrado** antes de gravar | só pela rota da aplicação, que decifra em fluxo, **com trilha de acesso** |

A API (`POST /api/v1/uploads`) usa o padrão; o cliente da API não escolhe a
classificação. Valor desconhecido no padrão é erro — nunca um rebaixamento
silencioso para "em claro". O `/admin` mostra a classificação de cada linha,
com filtro.

## Uploads confidenciais

### Cifra em repouso

O conteúdo é **cifrado antes de ir ao armazenamento** (disco local, S3/R2):
o armazenamento só vê o objeto cifrado, com o caminho terminado em `.enc`.

- **Algoritmo:** libsodium *secretstream* (XChaCha20-Poly1305, AEAD), a API
  oficial do PHP (`sodium_crypto_secretstream_xchacha20poly1305_*`). Nonce
  aleatório de 192 bits por arquivo (o mesmo conteúdo enviado duas vezes vira
  dois objetos diferentes).
- **Em fluxo, em blocos de 64 KB:** o arquivo inteiro nunca precisa estar em
  memória para cifrar ou decifrar, e o **texto claro nunca vai para arquivo
  temporário** — o que passa pelo temporário (`php://temp`) antes de subir é o
  objeto já cifrado. A entrega lê o objeto em stream e devolve bloco a bloco.
- **Cada bloco é autenticado** com o cabeçalho e com o **uuid do registro**
  como dados adicionais. Trocar um bit, cortar o fim (falta a marca FINAL),
  acrescentar bytes depois dela, reordenar blocos ou pôr no lugar o objeto
  cifrado de **outro upload** (mesmo da mesma chave): não decifra
  (`UndecryptableUploadException`, com o motivo estável). Nada que não
  autentica é devolvido.
- **Formato do arquivo (versão 1):** `TWSUPENC` (8 bytes) · versão (1) · **id
  da chave** (8) · tamanho do bloco (4, big-endian) · cabeçalho do fluxo (24)
  · blocos (cada um com 17 bytes de autenticação; o último com a marca FINAL).
- `size` e `sha256` do registro são do conteúdo original (o que o cliente
  confere).

### A chave: própria, versionada, nunca a `APP_KEY`

O **instalador gera a chave** quando o módulo de uploads entra (`tws:install`,
`tws:add uploads`, o `composer create-project twstec/kit` e o caminho só com o
Docker, que passam pelo `tws:install`): `UPLOADS_ENCRYPTION_KEY` no `.env`,
nunca impressa, e a que já existe nunca é trocada. Sem `.env` nem
`.env.example`, ele avisa com a instrução (`php artisan uploads:encryption-key`).

```bash
php artisan uploads:encryption-key            # gera e grava UPLOADS_ENCRYPTION_KEY no .env
php artisan uploads:encryption-key --show     # só mostra (cofre de segredos, orquestrador)
php artisan uploads:encryption-key --rotate   # troca: a nova vira a atual, a antiga vai para as anteriores
php artisan uploads:reencrypt                 # recifra os arquivos com a atual, sem indisponibilidade
```

- `UPLOADS_ENCRYPTION_KEY`: a atual (`base64:` + 32 bytes). Dela saem, por
  derivação com contexto próprio (`sodium_crypto_kdf_derive_from_key`), a
  chave que cifra e o **identificador da versão** (16 caracteres hex),
  gravado no cabeçalho de cada arquivo e em `uploads.encryption_key_id`.
- `UPLOADS_ENCRYPTION_PREVIOUS_KEYS`: as anteriores, separadas por vírgula —
  só decifram.
- **Falha fechada:** sem a extensão sodium, sem chave, com chave em formato
  inválido ou **igual à `APP_KEY`** (ou a uma `APP_PREVIOUS_KEYS`), o upload
  confidencial é **recusado** antes de ler o arquivo (nada vai para o disco nem
  para o banco; `ConfidentialStorageUnavailableException`, 503 na API) e a
  entrega responde 503. Em **produção**, o motivo vai para o log **a cada
  boot** — o motivo, nunca a chave. A chave não aparece em log, dump nem fila
  (o objeto da chave esconde o material e recusa serialização).
- **Perder a chave é perder os arquivos.** Guarde-a no cofre de segredos.
- **Extensão `sodium` do PHP (`ext-sodium`).** Vem no PHP oficial e na imagem
  do kit, mas **não** está declarada no `composer.json` do pacote (declarar
  exigiria refazer os locks dos starters): é conferida em tempo de execução.
  Sem ela, o upload confidencial é recusado com a mensagem "falta a extensão
  sodium do PHP (ext-sodium)", o boot de produção avisa para instalá-la e o
  `uploads:encryption-key` falha dizendo isso. Os uploads comuns não precisam
  dela.
- O comando recusa sobrescrever uma chave existente sem `--rotate` (trocar sem
  guardar a antiga deixaria os arquivos sem leitura); em produção, pede
  confirmação (ou `--force`).

### Rotação sem indisponibilidade

1. `php artisan uploads:encryption-key --rotate` (ou: a chave nova em
   `UPLOADS_ENCRYPTION_KEY` e a antiga no começo de
   `UPLOADS_ENCRYPTION_PREVIOUS_KEYS`) e recarregue a configuração. Daqui em
   diante, arquivo novo nasce na chave nova, e os antigos **continuam
   abrindo** com a anterior.
2. `php artisan uploads:reencrypt` (`--dry-run` só conta; `--chunk=100`). Para
   cada confidencial fora da chave atual: decifra e cifra de novo, bloco a
   bloco, num objeto **novo**; confere o sha256 do conteúdo; troca o registro
   (caminho + versão) numa atualização condicional; apaga o objeto antigo. O
   antigo continua sendo entregue até a troca; uma entrega que pegou o
   registro um instante antes relê o registro antes de desistir.
   **Idempotente** (só pega o que falta) e **retomável** (parar no meio deixa
   cada arquivo no estado antigo ou no novo; objeto que sobrou de uma queda
   sai na limpeza como "arquivo sem registro"). O que falha (objeto
   corrompido, armazenamento fora do ar) fica como estava, vai para o log
   (`upload.reencrypt_failed`, sem conteúdo) e o comando sai com erro; a
   rodada seguinte retoma. Uma rodada por vez (trava no cache). A rodada vai
   para a trilha (`upload.reencrypted`, contagens e o id da chave).
3. Quando o comando disser que nenhum arquivo usa mais as chaves anteriores,
   tire-as de `UPLOADS_ENCRYPTION_PREVIOUS_KEYS`.

### Entrega e trilha de acesso

`Upload::url()` de um confidencial devolve a URL da rota
**`uploads.confidential`** (`GET /uploads/confidential/{uuid}`, registrada
pelo pacote), nunca a do armazenamento. A URL é **assinada** e amarra o
upload, a **conta** em nome da qual foi gerada (ou "sistema", no `/admin`),
**quem a gerou**, o contexto e se é para baixar; vale
`UPLOADS_CONFIDENTIAL_URL_MINUTES` (5). Na entrega, além da assinatura e da
validade, a regra é conferida **de novo**: o upload ainda é daquela conta e
quem gerou ainda existe, está ativo e ainda é membro dela (no `/admin`: ainda é
admin). A rota não usa sessão (serve ao painel, à API e ao `/admin`) e é
limitada por IP (`UPLOADS_CONFIDENTIAL_RATE_LIMIT`, 60/min). Resposta: o tipo
real, o tamanho exato (`Content-Length`), `inline` para visualizar ou
`attachment` para baixar (nome original saneado), `nosniff`, `no-store`,
`no-referrer`.

Tudo vai para a **trilha de auditoria no banco** (`audit_events`), com ator,
conta (`tenant_uuid`), arquivo (`subject_uuid`) e contexto (`panel`, `api`,
`admin`), IP e User-Agent:

| Evento | Quando | Recusas (`outcome = denied`, com o motivo) |
| --- | --- | --- |
| `upload.confidential_url_issued` | gerar a URL — gravado **antes** de a URL existir | upload de outra conta (`UploadOutsideAccountException`); ninguém agindo (job, comando: 403) |
| `upload.confidential_viewed` / `upload.confidential_downloaded` | visualizar / baixar — gravado **antes** do primeiro byte | assinatura inválida ou vencida (403 — a linha não confia em nada da URL); quem gerou perdeu o acesso, conta da URL não é a do upload (**404**, o mesmo de "não existe"); sem chave (503); arquivo ausente (404) |

**De outra conta, continua 404:** a consulta não acha (escopo da conta), a URL
não sai (recusa na trilha) e a URL de quem saiu da conta ou foi bloqueado
deixa de abrir.

**Telas:** só o `/admin` tem telas para confidenciais e guarda legal (abrir,
pôr e tirar a guarda). Os painéis do cliente (Livewire e React) não listam
uploads nem abrem confidenciais — o projeto monta essas telas sobre a API do
pacote (`Upload::url()`, `LegalHold`); está no backlog do
[roadmap](roadmap.md#depois-da-200-backlog).

No `/admin`, abrir um confidencial é uma ação à parte ("Abrir documento
confidencial"), com permissão própria **`uploads.view_confidential`** — fora
de `*.view`: o papel `auditor` vê a lista, não o documento. A URL nasce no
clique (nunca ao desenhar a tabela), e a geração e a visualização ficam na
trilha com o contexto `admin` e o operador.

Os parâmetros da URL assinada (inclusive `signature`) passam a ser
**mascarados** na trilha de requisições (`request_logs`): a assinatura, dentro
da validade, abre o recurso.

## De quem é o upload: da conta

Como projetos e chaves de API, o upload **pertence a uma conta**
(`account_id`) e guarda **quem enviou** (`created_by` — pode sair da conta ou
ser excluído; o upload continua da conta). A web e a API gravam do **mesmo
jeito**: a conta atual e quem está agindo. Quem decide é o escopo da conta
(`BelongsToAccount`, do `twstec/kit-accounts`), não o serviço:

| Onde | Conta gravada | `created_by` |
| --- | --- | --- |
| API (`POST /api/v1/uploads`) | a conta da chave | a pessoa por trás da chave |
| Web (tela que chame `handle()`) | a conta selecionada na sessão (padrão: a pessoal) | a pessoa logada |
| Foto de perfil | **nenhuma** — é pessoal (abaixo) | quem enviou (a pessoa; o operador no `/admin`) |

**Isolamento automático:** toda consulta a `Upload` sai filtrada pela conta
atual — lista, contagem, busca por uuid, `update`/`delete` em massa — e,
**sem conta atual, dá erro** em vez de devolver tudo. A mesma pessoa em duas
contas (a pessoal e a de uma empresa) não lista, não acha e não assina o
upload da outra, nem pela web nem pela API; a conta de um upload não muda. O
`/admin` opera em modo sistema (vê todas as contas, com a conta de cada
linha). Uma trava de arquitetura (no pacote e no starter) reprova consulta que
pule o escopo, query builder cru na tabela `uploads` e modo sistema fora da
lista revisada.

Dois casos ficam **sem conta** (`account_id` nulo) e fora de qualquer
consulta de conta:

- a **foto pessoal** (`personal`), abaixo — o único registro que o escopo
  deixa nascer sem conta, e só em modo sistema declarado (outra trava lista os
  models que abrem essa exceção: hoje, só o upload);
- o **órfão** da migração (`orphaned_at`): registro antigo cujo dono não
  existia mais — sai na limpeza.

## Foto de perfil: da pessoa, não da conta

O model de usuário do aplicativo usa a trait `Twstec\Kit\Uploads\Concerns\HasAvatar`
(`avatarUpload()`, `avatarUrl()`) e tem a coluna `avatar_upload_id` (migration de
usuários do aplicativo).

A foto aparece em **todas as contas da pessoa** (e para quem divide uma conta
com ela, na lista de membros). Por isso ela é um **upload pessoal**: sem conta
(`personal`), gravado por `SecureUploadService::handlePersonal()` num modo
sistema de um ponto só, e lido **só** pela foto de perfil — também num modo
sistema de um ponto só, **restrito**: busca exatamente o upload que
`avatar_upload_id` aponta, e só se ele for uma foto pessoal ou um upload da
**conta pessoal** da própria pessoa. A foto nunca abre upload de conta de
empresa nem de outra pessoa, mesmo com o vínculo forçado no banco (a foto
simplesmente não aparece). A relação crua `avatar()` passa pelo escopo da
conta e não enxerga a foto pessoal: para ler a foto, `avatarUpload()` /
`avatarUrl()`.

| Onde | Componente | Caminho |
| --- | --- | --- |
| Painel do cliente (`/profile`) | Livewire (`App\Livewire\Profile::updateAvatar`) | `Avatar\AvatarService` → `SecureUploadService::handlePersonal` → `users.avatar_upload_id` |
| Painel do cliente React (`/profile`) | `App\Http\Controllers\Panel\ProfilePhotoController` (starter React) | idem; tirar a foto: `AvatarService::remove()` |
| Rota web `POST /settings/avatar` | `AvatarController` (pacote) | idem |
| Super admin (`/admin/users`, `/admin/profile`) | `Twstec\Kit\Admin\Support\AvatarUpload` (pacote twstec/kit-admin) | `saveUploadedFileUsing` → `SecureUploadService::handlePersonal` (enviado pelo operador) → `users.avatar_upload_id`. Só vira foto de uma pessoa o upload **dela** (foto pessoal que ela enviou ou upload da conta pessoal dela), a foto atual ou o enviado agora, neste formulário |

Nenhum dos caminhos grava arquivo por conta própria: todos chamam o service, e
por isso a mesma lei vale em todos (conteúdo validado, re-encode GD, nome do
arquivo derivado do MIME real, registro em `uploads`, URL assinada). A foto
trocada fica sem vínculo e sai na limpeza, depois do prazo — não na hora,
para não quebrar a imagem que ainda está na tela de quem trocou. Tirar a foto
(`AvatarService::remove()`, usado pelo starter React) segue a mesma regra: só
desfaz o vínculo, e a pessoa volta às iniciais.

A validação de segurança também existe como **regra de validação**
(`Twstec\Kit\Uploads\Rules\SafeFile`) para os formulários que não são Form
Request — hoje os do Filament e o do perfil. Ela não substitui o service (que
revalida ao persistir): existe para o erro aparecer embaixo do campo, na hora,
em vez de estourar depois do "Salvar".

## Exclusão: o arquivo sai junto (LGPD)

Instalado pelo pacote, sem opção para desligar, ligado aos eventos de exclusão
do `twstec/kit-accounts` (`PersonDeleting`, `PersonDeleted`,
`AccountDeleting`):

| Excluir… | Sai (banco **e** disco) | Fica |
| --- | --- | --- |
| a **pessoa** (qualquer caminho: `/admin`, comando, model) | a foto de perfil dela; as fotos pessoais que ela enviou (menos a que é a foto de outra pessoa); os uploads das contas que somem junto com ela (a pessoal e as de que era a única dona) | os uploads que ela criou em contas de **outras pessoas** — são daquela conta, como as chaves de API (`created_by` fica vazio); a foto que ela enviou e é a de outra pessoa |
| uma **conta** (página da conta, `AccountService::deleteAccount`) | os uploads da conta | os das outras contas |

**Em dois tempos**, para que exclusão recusada ou desfeita não apague nada:

1. os **registros** saem dentro da transação da exclusão (se ela for
   desfeita, voltam) e a trilha de auditoria ganha `upload.erased` — quantos e
   por quê (`person_deleted` / `account_deleted`), a conta quando é exclusão
   de conta, **nunca** caminho nem conteúdo;
2. os **arquivos** saem pelo job `Jobs\DeleteUploadFiles`, na fila,
   disparado **depois do commit**, com **nova tentativa** (5, com espera
   crescente): arquivo que continua no disco faz o job falhar e voltar; o que
   já não existe conta como removido. Ao terminar, `upload.files_deleted` na
   trilha (contagens). Esgotadas as tentativas, `upload.files_delete_failed`
   no log (só contagens) — e o arquivo que sobrou sai na limpeza seguinte,
   como "arquivo sem registro".

A exclusão **recusada** (dona de conta com outros membros, impedimento
declarado — ver [Impedimentos de exclusão](tenancy.md#impedimentos-de-exclusão)
—, ou qualquer guarda do aplicativo que recuse depois) não apaga nada: o
pacote só **lê** o que vai sair no `deleting` da pessoa e apaga no `deleted`.

### Exclusão × apagamento

São conceitos separados, na documentação e no código:

- **Exclusão** é o pedido sobre o **titular**: excluir a pessoa, excluir a
  conta (`twstec/kit-accounts`). É o que a LGPD pede quando o titular sai.
- **Apagamento** é sumir com o **registro e o arquivo** de um upload
  (`Erasure\UploadEraser`, `Jobs\DeleteUploadFiles`).

A exclusão do titular pede o apagamento do que é dele — **menos o que está sob
guarda legal**, que é desvinculado e mantido até o prazo (abaixo). A chave
estrangeira `uploads.account_id` passou de `CASCADE` para `SET NULL`: o banco
não leva junto o que está guardado (no PostgreSQL a conta da pessoa excluída
sai na mesma sentença, por gatilho); quem apaga é sempre o pacote,
explicitamente. Upload que ficou sem conta sem passar pelo pacote (a conta
apagada direto no banco) conta como órfão na limpeza.

## Retenção legal ("guardar até")

Um upload pode receber uma **guarda legal**: uma data "guardar até" e o
motivo (a lei, o contrato) — `Twstec\Kit\Uploads\Retention\LegalHold`:

```php
app(LegalHold::class)->place($upload, now()->addYears(5), 'Contrato: guarda de 5 anos');
app(LegalHold::class)->release($upload, 'Processo encerrado'); // tirar antes do prazo, com o motivo
```

No `/admin`, as ações "Guarda legal" e "Tirar a guarda legal" (permissão
**`uploads.legal_hold`**). Pôr, mudar e tirar vão para a trilha
(`upload.legal_hold_placed`, `upload.legal_hold_released`) com o antes e o
depois.

Enquanto a guarda vale:

| Caminho | O que acontece |
| --- | --- |
| **Excluir o dono** (a pessoa ou a conta) | a exclusão **segue**: o que pode sair sai; o upload guardado fica **desvinculado** — sem conta, sem autor, o nome original trocado pelo código público (`UPL-XXXXXX.pdf`), `detached_at` preenchido — e a **recusa de apagá-lo** vai para a trilha: `upload.erasure_refused`, `denied`, com o código, o prazo, o motivo e a conta de onde saiu. No `/admin` ele aparece como "Retido (guarda legal)" |
| `$upload->delete()` direto | **recusado** (`UploadUnderLegalHoldException`), com a recusa na trilha (`upload.deleted`, `denied`) |
| Limpeza (`uploads:prune-orphans`) | não toca o que está sob guarda nem o desvinculado |
| **Prazo vencido** | o comando agendado **`uploads:erase-expired-holds`** apaga o desvinculado (registro e arquivo, `upload.erased` com o motivo `legal_hold_expired`). O pacote agenda sozinho (`UPLOADS_LEGAL_HOLD_SCHEDULE`, `50 3 * * *` em UTC; vazio desliga, com aviso no log a cada boot) |

Guarda vencida não segura nada: a exclusão apaga como sempre.

**Recusar a exclusão inteira** em vez de desvincular:
`UPLOADS_LEGAL_HOLD_BLOCKS_DELETION=true` faz da guarda um
[impedimento de exclusão](tenancy.md#impedimentos-de-exclusão) — a exclusão da
pessoa ou da conta é recusada enquanto houver upload dela (das contas que
sairiam, ou foto pessoal que ela enviou) sob guarda, com a mensagem ao usuário
e a recusa na trilha.

## Limpeza: `uploads:prune-orphans`

Apaga registro **e** arquivo de:

1. **órfãos** da migração para contas (`orphaned_at`), depois de
   `UPLOADS_PRUNE_ORPHANS_AFTER_DAYS` (30);
2. **fotos pessoais que não são a foto de ninguém** (a trocada, a de um
   formulário do `/admin` que não foi salvo), depois de
   `UPLOADS_PRUNE_PERSONAL_AFTER_HOURS` (24);
3. **arquivos no disco sem registro** nas pastas de upload
   (`UPLOADS_PRUNE_DIRECTORIES`, `uploads,avatars`; discos em
   `UPLOADS_PRUNE_DISKS`, padrão só o `UPLOADS_DISK`), mais velhos que
   `UPLOADS_PRUNE_STRAY_FILES_AFTER_HOURS` (24) — o prazo protege o envio em
   andamento (o arquivo vai para o disco um instante antes do registro).

Nunca toca o que está sob guarda legal nem o desvinculado pela guarda; upload
sem conta que não é foto pessoal nem desvinculado (a conta apagada direto no
banco) entra como órfão.

`--dry-run` só conta e mostra. Sem ele, cada rodada que apaga algo grava
`upload.orphans_pruned` na trilha (contexto `console`, só contagens). O
**pacote agenda** o comando sozinho (`UPLOADS_PRUNE_SCHEDULE`, cron em UTC,
padrão `40 3 * * *`, sem sobreposição e num servidor só); vazio desliga o
agendamento, com aviso no log a cada boot.

## Migração dos uploads antigos

A migration `2026_09_28_000001_move_uploads_to_accounts` (do pacote) roda no
`php artisan migrate`. Para cada registro, nesta ordem:

1. **foto de perfil em uso** (`users.avatar_upload_id` aponta para ela)
   enviada pela web ou sem dono → **foto pessoal**, `created_by` = quem
   enviou (a que veio pela API de uma conta que existe segue a regra 3);
2. `user_id` → a **conta pessoal** dessa pessoa, `created_by` = `user_id`;
3. `tenant_uuid` → a **conta com esse uuid** (a conta pessoal tem o uuid da
   pessoa; a API gravava o uuid da conta da chave). `created_by` fica vazio:
   o registro antigo não guardava quem, por trás da chave, enviou;
4. nada disso (dono excluído, uuid que não aponta para conta nenhuma,
   registro sem dono) → **órfão** (`orphaned_at`), sem conta — **nunca** uma
   conta qualquer. A limpeza apaga depois do prazo.

Confere no fim que nenhum registro ficou sem destino (senão falha e, no
PostgreSQL, nada muda) e tira `user_id` e `tenant_uuid` (nada mais os lê: a
trilha de requisições tem o próprio `tenant_uuid`; o `/admin` mostra a conta).
Em lotes por faixa de id (`UPLOADS_MIGRATION_CHUNK`, 1000), idempotente e
reversível: o `migrate:rollback` devolve `user_id` à foto pessoal e ao upload
da conta pessoal de quem enviou, e `tenant_uuid` ao resto (o jeito da API); o
órfão volta sem dono (o uuid antigo não apontava para ninguém e não é
guardado). **Os arquivos não mudam de lugar** (`disk`/`path` iguais): a URL
assinada de um arquivo antigo continua abrindo o mesmo conteúdo. No SQLite,
onde pôr chave estrangeira recria a tabela, a migration guarda e devolve
`users.avatar_upload_id`.

## Endpoints

- `POST /api/v1/uploads` (scope `uploads:create`) — **registrado pelo
  pacote**, no mesmo grupo das rotas v1 do `twstec/kit-accounts` (prefixo,
  `api` + `resolve.tenant`, nomes `api.v1.*`, limite por chave). Campo `file`,
  opcional `directory`. Retorna o `UploadResource` padronizado (uuid, path,
  url, MIME real, tamanho, sha256). Para registrar a rota você mesmo:
  `UPLOADS_API_ROUTES=false` e `Twstec\Kit\Uploads\Http\UploadRoutes::register()`
  (a autenticação por chave entra sempre).

  **Idempotência** (`Idempotency-Key`, opcional — ver
  [docs/api.md](api.md#idempotência-idempotency-key)):
  - o reenvio do **mesmo arquivo** com a mesma chave não grava de novo, nem no
    banco nem no armazenamento;
  - **outro arquivo** com a mesma chave (o conteúdo conta, não o nome) é
    recusado com `422 idempotency_key_reused`;
  - a **URL assinada é credencial** enquanto vale, então segue a regra das
    secretas (`withhold`): ela não é guardada, nem cifrada, e não volta na
    repetição;
  - a repetição devolve o status original, `Idempotent-Replayed: true` e
    `data.uuid`, `data.codigo_publico`, `data.mime`, `data.size`,
    `data.sha256` e `data.status`, com `idempotency.body_withheld: true`.
    Nunca o caminho no armazenamento nem o nome original do arquivo.

  **URL nova depois de um replay:** a API v1 **não tem** rota de consulta de
  upload; o pacote só registra o `POST`. A URL assinada nasce fresca toda vez
  que o aplicativo serializa o upload com o `UploadResource` (a validade
  conta a partir dali). O cliente que perdeu a resposta original guarda o
  `uuid` do replay e pede a URL pelo caminho que o aplicativo oferecer. Se o
  projeto precisar disso na API, registre a consulta no mesmo grupo
  autenticado, filtrada pela conta da chave:

  ```php
  Route::get('uploads/{uuid}', fn (string $uuid) => UploadResource::make(
      Upload::query()->where('uuid', $uuid)->firstOrFail(), // o escopo da conta filtra
  ))->middleware('scope:uploads:read');
  ```

- `GET /uploads/confidential/{uuid}` (rota `uploads.confidential`) —
  **registrada pelo pacote**, sempre: a entrega dos confidenciais (URL
  assinada, sem sessão, limitada por IP). Prefixo em
  `uploads.confidential.route.prefix`.
- `POST /settings/avatar` (web autenticada) — rota do **starter**
  (`routes/web.php`, `throttle:sensitive`) apontando para o
  `AvatarController` do pacote. Campo `avatar`, restrito a imagens
  (`avatars/`, re-encode GD obrigatório).

## Configuração (Cloudflare R2 — S3-compatível)

A configuração padrão vem do pacote (`config('uploads')`); a cópia do starter
em `config/uploads.php` prevalece chave a chave de primeiro nível
(`vendor:publish --tag=uploads-config` publica a do pacote). O R2 usa o
driver `s3` nativo (Flysystem) com `endpoint` customizado. Em produção:

```dotenv
UPLOADS_DISK=s3
AWS_ACCESS_KEY_ID=<r2_access_key>
AWS_SECRET_ACCESS_KEY=<r2_secret>
AWS_DEFAULT_REGION=auto
AWS_BUCKET=<bucket>
AWS_ENDPOINT=https://<accountid>.r2.cloudflarestorage.com
```

Em dev, `UPLOADS_DISK=local` (ou MinIO apontando o mesmo disco `s3`).

Confidenciais e guarda legal: `UPLOADS_DEFAULT_CLASSIFICATION`,
`UPLOADS_ENCRYPTION_KEY`, `UPLOADS_ENCRYPTION_PREVIOUS_KEYS`,
`UPLOADS_CONFIDENTIAL_URL_MINUTES`, `UPLOADS_CONFIDENTIAL_RATE_LIMIT`,
`UPLOADS_LEGAL_HOLD_BLOCKS_DELETION`, `UPLOADS_LEGAL_HOLD_SCHEDULE` (seção
*Uploads* do `.env.example`; a chave de produção no `.env.prod.example`).
Limites por tipo, tipos permitidos, teto de pixels e validade das URLs
assinadas: seção *Uploads* do `.env.example` → `config/uploads.php`. A imagem
de produção já traz as extensões `fileinfo` e `gd`, que o pacote exige.

## Testes

- **Pacote** (`packages/uploads`, Testbench, aplicação Laravel limpa, sem
  nada do starter): isolamento por conta (pessoa em duas contas pela web e
  pela API, URL assinada só da conta atual, erro sem conta), exclusão com os
  arquivos (banco e disco; recusada e desfeita não apagam; job depois do
  commit e com nova tentativa; exclusão de conta), limpeza com e sem
  `--dry-run` e o agendamento, a migração (ida, idempotência, volta e ida);
  conteúdo falso com extensão de imagem, polyglot,
  extensão divergente, executável, PDF com JavaScript, tamanho acima do
  limite e decompression bomb recusados; imagem reprocessada; URL assinada
  entrega o arquivo, e sem assinatura, adulterada, vencida ou com o caminho
  de outro dono é recusada; o pacote liga a entrega num disco que não a tem;
  opt-out e disco público com aviso; rota, escopo e credencial; traduções com
  o aplicativo vencendo; apelidos; arquitetura. Confidenciais
  (`ConfidentialUploadsTest`, `ConfidentialAccessTest`, `KeyRotationTest`): o
  objeto cru não é o original nem contém trecho dele; vários blocos; bit
  trocado, corte, sobra e troca de objeto entre uploads não decifram; sem
  chave (ausente, inválida, igual à APP_KEY) o upload é recusado e nada grava,
  503 na API, aviso no boot em produção; a chave fora de log e dump; trilha de
  gerar, ver e baixar (painel, API, modo sistema); outra conta, quem perdeu o
  acesso, URL adulterada ou vencida, sem chave na entrega; limite por IP;
  rotação com os antigos legíveis, idempotente e retomável; o comando da
  chave. Guarda legal (`LegalHoldTest`): exclusão da conta e da pessoa com
  upload guardado, o comando depois do prazo e o agendamento, `delete()`
  direto, a limpeza, a trilha de pôr e tirar, o modo que impede a exclusão. A
  migration da classificação (`ClassificationMigrationTest`).
- **Starter** (`tests/Feature/Uploads/`, com fixtures programáticas em
  `tests/Fixtures/uploads.php` — nada de binário commitado): a mesma lei
  pela API e pelo avatar, com o banco e as telas do aplicativo; uploads da
  conta, foto em todas as contas e exclusão pelo `/admin` e pela página da
  conta (`UploadAccountsTest`), a migração no esquema completo, SQLite e
  PostgreSQL (`UploadsMigrationTest`); confidencial pela API e pelo `/admin`,
  exclusão com upload sob guarda e as recusas limpas por chave estrangeira
  `RESTRICT` do aplicativo (`ConfidentialAndRetentionTest`, SQLite e
  PostgreSQL);
  `tests/Feature/Panel/ProfileTest.php` (painel do cliente),
  `tests/Feature/Uploads/AdminAvatarTest.php` (super admin) e os E2E
  `tests/e2e/panel.spec.js` / `tests/e2e/admin.spec.js`.
