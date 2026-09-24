# Uploads seguros

Módulo em `app/Core/Uploads/`. **Função global única**: todo upload do
sistema passa pelo `SecureUploadService::handle()` — nenhum controller faz
`store()` direto de arquivo.

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
3. **Nome seguro**: `uuid` + extensão derivada do MIME REAL. O nome original
   NUNCA compõe o path (guardado sanitizado em `original_name`, só exibição).
4. **Persistência** no disco configurado + registro em `uploads` (uuid,
   `codigo_publico` UPL-xxxxxx, tenant_uuid/user_id, MIME real, tamanho,
   sha256 do conteúdo final) + log estruturado (`upload.stored` /
   `upload.rejected`, sem dados sensíveis).

Arquivo rejeitado **não toca o disco nem o banco** — só o log.

## Como usar o serviço

```php
use App\Core\Uploads\Services\SecureUploadService;

$upload = app(SecureUploadService::class)->handle(
    $request->file('file'),           // UploadedFile (Form Request já validou)
    disk: 's3',                        // opcional — default: config uploads.disk
    directory: 'documentos',           // opcional — default: uploads.directory
    allowedTypes: ['image', 'pdf'],    // opcional — default: uploads.allowed_types
);

$upload->url();  // URL temporária assinada (bucket NUNCA público)
```

Rejeições lançam `UploadRejectedException` (com `reason` estável para logs);
os controllers convertem em 422. Vínculo automático: na API (ResolveTenant)
o registro sai com `tenant_uuid`; na web autenticada, com `user_id`.

## Foto de perfil (os dois painéis usam a mesma função)

| Onde | Componente | Caminho |
| --- | --- | --- |
| Painel do cliente (`/profile`) | Livewire (`App\Livewire\Profile::updateAvatar`) | `SecureUploadService` → `users.avatar_upload_id` |
| Super admin (`/admin/users`, `/admin/profile`) | `App\Filament\Support\AvatarUpload` | `saveUploadedFileUsing` → `SecureUploadService` → `users.avatar_upload_id` |

Nenhum dos dois grava arquivo por conta própria: os dois chamam o service, e
por isso a mesma lei vale nos dois (conteúdo validado, re-encode GD, nome do
arquivo derivado do MIME real, registro em `uploads`, URL assinada).

A validação de segurança também existe como **regra de validação**
(`App\Core\Uploads\Rules\SafeFile`) para os formulários que não são Form
Request — hoje os do Filament. Ela não substitui o service (que revalida ao
persistir): existe para o erro aparecer embaixo do campo, na hora, em vez de
estourar depois do "Salvar".

Testes: `tests/Feature/Uploads/AvatarTest.php` (endpoint),
`tests/Feature/Panel/ProfileTest.php` (painel do cliente),
`tests/Feature/Uploads/AdminAvatarTest.php` (super admin: criar com foto,
trocar, remover, arquivo inválido, listagem/detalhe e URL assinada) e os
E2E `tests/e2e/panel.spec.js` / `tests/e2e/admin.spec.js`.

## Endpoints de exemplo (prova de reuso)

- `POST /api/v1/uploads` (scope `uploads:create`) — campo `file`, opcional
  `directory`. Retorna o `UploadResource` padronizado (uuid, path, url,
  MIME real, tamanho, sha256).
- `POST /settings/avatar` (web autenticada) — campo `avatar`, restrito a
  imagens (`avatars/`, re-encode GD obrigatório).

## Configuração (Cloudflare R2 — S3-compatível)

O R2 usa o driver `s3` nativo (Flysystem) com `endpoint` customizado —
config padrão do Laravel 13 em `config/filesystems.php`. Em produção:

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
assinadas: seção *Uploads* do `.env.example` → `config/uploads.php`.

## Testes

`tests/Feature/Uploads/` (Pest) com fixtures programáticas em
`tests/Fixtures/uploads.php` (PDF mínimo, PNG 1×1 real, ELF fake — nada de
binário commitado): PDF/imagem legítimos aceitos; PDF com `/JavaScript`,
ELF renomeado `.pdf`, polyglot com PHP embutido, texto disfarçado,
extensão divergente, tamanho acima do limite e decompression bomb
rejeitados; nome seguro sem nome original; sha256 do retorno confere com o
disco; re-encode elimina trailing payload; vínculo tenant (API) vs user_id
(web); scope `uploads:create` exigido.
