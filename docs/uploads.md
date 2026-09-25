# Uploads seguros

Pacote **`twstec/kit-uploads`** ([`packages/uploads`](../packages/uploads)),
namespace `Twstec\Kit\Uploads\` (até a 1.x era `app/Core/Uploads`; os nomes
`App\Core\Uploads\…` continuam resolvendo até a 3.0). **Função global
única**: todo upload do sistema passa pelo `SecureUploadService::handle()` —
nenhum controller faz `store()` direto de arquivo.

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
   `codigo_publico` UPL-xxxxxx, tenant_uuid/user_id, MIME real, tamanho,
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

## Como usar o serviço

```php
use Twstec\Kit\Uploads\Services\SecureUploadService;

$upload = app(SecureUploadService::class)->handle(
    $request->file('file'),           // UploadedFile (Form Request já validou)
    disk: 's3',                        // opcional — default: config uploads.disk
    directory: 'documentos',           // opcional — default: uploads.directory
    allowedTypes: ['image', 'pdf'],    // opcional — default: uploads.allowed_types
);

$upload->url();  // URL temporária assinada (bucket NUNCA público)
```

Rejeições lançam `UploadRejectedException` (com `reason` estável para logs);
os controllers convertem em 422.

## Quem é o dono do upload (como está hoje)

O dono é **a pessoa**, gravado de **dois jeitos**, conforme a entrada:

| Entrada | Coluna preenchida | Outra coluna | Observação |
| --- | --- | --- | --- |
| API (`POST /api/v1/uploads`, `resolve.tenant` ativo) | `tenant_uuid` = uuid do dono da chave | `user_id` nulo | sem chave estrangeira: excluir a pessoa não mexe no registro |
| Web autenticada (avatar no perfil, `/admin`, `POST /settings/avatar`) | `user_id` = id da pessoa da sessão | `tenant_uuid` nulo | chave estrangeira com `nullOnDelete`: excluir a pessoa deixa o registro sem dono |
| Sem nenhum dos dois (ex.: comando, seeder) | nenhuma | — | `tenant_uuid` e `user_id` nulos |

A relação `Upload::owner()` só enxerga o dono web (`user_id`). Nenhuma rota
lista ou entrega upload por dono — o acesso ao arquivo é sempre pela URL
assinada que o sistema gera para o registro —, então a diferença não abre o
arquivo de uma pessoa para outra. Ela é uma **incoerência de modelo**, mantida
como está na 2.0 enquanto o dono for a pessoa: a fase de **contas com
membros** passa uploads a pertencer à conta e unifica as duas formas.

## Foto de perfil (os dois painéis usam a mesma função)

O model de usuário do aplicativo usa a trait `Twstec\Kit\Uploads\Concerns\HasAvatar`
(`avatar()`, `avatarUrl()`) e tem a coluna `avatar_upload_id` (migration de
usuários do aplicativo).

| Onde | Componente | Caminho |
| --- | --- | --- |
| Painel do cliente (`/profile`) | Livewire (`App\Livewire\Profile::updateAvatar`) | `SecureUploadService` → `users.avatar_upload_id` |
| Super admin (`/admin/users`, `/admin/profile`) | `Twstec\Kit\Admin\Support\AvatarUpload` (pacote twstec/kit-admin) | `saveUploadedFileUsing` → `SecureUploadService` → `users.avatar_upload_id` (só upload da própria conta ou enviado agora no formulário) |

Nenhum dos dois grava arquivo por conta própria: os dois chamam o service, e
por isso a mesma lei vale nos dois (conteúdo validado, re-encode GD, nome do
arquivo derivado do MIME real, registro em `uploads`, URL assinada).

A validação de segurança também existe como **regra de validação**
(`Twstec\Kit\Uploads\Rules\SafeFile`) para os formulários que não são Form
Request — hoje os do Filament e o do perfil. Ela não substitui o service (que
revalida ao persistir): existe para o erro aparecer embaixo do campo, na hora,
em vez de estourar depois do "Salvar".

## Endpoints

- `POST /api/v1/uploads` (scope `uploads:create`) — **registrado pelo
  pacote**, no mesmo grupo das rotas v1 do `twstec/kit-accounts` (prefixo,
  `api` + `resolve.tenant`, nomes `api.v1.*`, limite por chave). Campo `file`,
  opcional `directory`. Retorna o `UploadResource` padronizado (uuid, path,
  url, MIME real, tamanho, sha256). Para registrar a rota você mesmo:
  `UPLOADS_API_ROUTES=false` e `Twstec\Kit\Uploads\Http\UploadRoutes::register()`
  (a autenticação por chave entra sempre).
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
Limites por tipo, tipos permitidos, teto de pixels e validade das URLs
assinadas: seção *Uploads* do `.env.example` → `config/uploads.php`. A imagem
de produção já traz as extensões `fileinfo` e `gd`, que o pacote exige.

## Testes

- **Pacote** (`packages/uploads`, Testbench, aplicação Laravel limpa, sem
  nada do starter): conteúdo falso com extensão de imagem, polyglot,
  extensão divergente, executável, PDF com JavaScript, tamanho acima do
  limite e decompression bomb recusados; imagem reprocessada; URL assinada
  entrega o arquivo, e sem assinatura, adulterada, vencida ou com o caminho
  de outro dono é recusada; o pacote liga a entrega num disco que não a tem;
  opt-out e disco público com aviso; rota, escopo e credencial; traduções com
  o aplicativo vencendo; apelidos; arquitetura.
- **Starter** (`tests/Feature/Uploads/`, com fixtures programáticas em
  `tests/Fixtures/uploads.php` — nada de binário commitado): a mesma lei
  pela API e pelo avatar, com o banco e as telas do aplicativo;
  `tests/Feature/Panel/ProfileTest.php` (painel do cliente),
  `tests/Feature/Uploads/AdminAvatarTest.php` (super admin) e os E2E
  `tests/e2e/panel.spec.js` / `tests/e2e/admin.spec.js`.
