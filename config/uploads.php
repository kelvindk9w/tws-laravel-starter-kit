<?php

declare(strict_types=1);

// =============================================================================
// Uploads Seguros (Fase 5 — ADR-010 + checklist item 14).
//
// Política (lei): o arquivo é validado pelo CONTEÚDO (magic bytes via finfo),
// NUNCA pela extensão declarada. PDF é só PDF, imagem é só imagem.
// Executável, script embutido, polyglot ou qualquer suspeita = REJEITADO.
//
// Todos os valores são ajustáveis por .env — NUNCA hardcodar (ADR-007).
// =============================================================================

return [

    // Disco padrão de destino (Flysystem). Em produção: 's3' apontando para o
    // Cloudflare R2 (S3-compatível — ver seção AWS_* no .env.example).
    // Em dev/testes: 'local'. O SecureUploadService aceita disco por chamada;
    // este é apenas o default.
    'disk' => env('UPLOADS_DISK', env('FILESYSTEM_DISK', 'local')),

    // Diretório base dentro do disco quando o chamador não informa um.
    'directory' => env('UPLOADS_DIRECTORY', 'uploads'),

    // Validade das URLs temporárias assinadas (documentos NUNCA em bucket
    // público — acesso sempre por URL assinada de curta duração).
    'temporary_url_minutes' => (int) env('UPLOADS_TEMPORARY_URL_MINUTES', 15),

    // Tipos permitidos por padrão quando o chamador não restringe.
    // Valores: chaves do mapa `types` abaixo.
    'allowed_types' => array_filter(explode(',', (string) env('UPLOADS_ALLOWED_TYPES', 'image,pdf'))),

    // --- Catálogo de tipos -----------------------------------------------------
    // mimes: allowlist de MIME REAL (finfo) → extensão canônica gerada.
    //        A extensão final do arquivo NUNCA vem do nome original: é
    //        derivada do MIME real detectado.
    // max_kb: tamanho máximo POR TIPO (a validação de formulário é do
    //        chamador, via Form Request; este é o limite de segurança final).
    // reencode: re-gera a imagem via GD antes de persistir (elimina qualquer
    //        payload embutido em metadados/trailing data — decisão documentada
    //        no README, seção Uploads).
    // max_pixels: teto de largura×altura (proteção contra "decompression
    //        bomb" antes de decodificar com a GD).
    'types' => [
        'image' => [
            'mimes' => [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ],
            'max_kb' => (int) env('UPLOADS_IMAGE_MAX_KB', 5120),
            'reencode' => true,
            'max_pixels' => (int) env('UPLOADS_IMAGE_MAX_PIXELS', 25000000),
        ],
        'pdf' => [
            'mimes' => [
                'application/pdf' => 'pdf',
            ],
            'max_kb' => (int) env('UPLOADS_PDF_MAX_KB', 10240),
        ],
    ],

];
