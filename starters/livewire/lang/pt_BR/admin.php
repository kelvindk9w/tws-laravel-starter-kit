<?php

declare(strict_types=1);

// Strings das telas da DEMONSTRAÇÃO do kit no /admin (catálogo de produtos,
// caixa de submissões e o dashboard "Conteúdo & Operação"). As do produto
// vêm do pacote twstec/kit-admin (packages/admin/lang) e se juntam a estas
// no mesmo grupo `admin.*` — numa mesma chave, o texto deste arquivo vence.
// Saem junto com a demo.

return [

    'submissions' => [
        'label' => 'Submissão de formulário',
        'plural' => 'Submissões de formulário',
        'nickname' => 'Apelido',
        'subject' => 'Assunto',
        'message' => 'Mensagem',
        'origin' => 'Origem',
        'origin_classic' => 'Clássico (POST)',
        'origin_livewire' => 'Livewire (AJAX)',
        'origin_contact' => 'Contato (landing)',
        'sender_email' => 'Remetente',
        'security' => 'Segurança',
        'accepted' => 'Aceita',
        'blocked_attack' => 'Ataque bloqueado (:type)',
        'received_at' => 'Recebida em',
        'blocked_at' => 'Bloqueada em',
        'ip' => 'IP de origem',
        'no_sender' => 'Sem remetente (formulário anônimo)',
        'filter_blocked' => 'Somente bloqueadas',
        'view_evidence' => 'Ver evidência',
        // A listagem nunca mostra payload: mostra o selo do ataque e um
        // trecho neutralizado, com esta legenda dizendo o que se está lendo.
        'neutralized' => 'conteúdo neutralizado',
        'metadata_section' => 'Metadados',
        'metadata_hint' => 'De onde veio, quando chegou e o que a plataforma decidiu.',
        'content_section' => 'Mensagem',
        'forensic_section' => 'Evidência forense',
        'forensic_heading' => 'Conteúdo enviado por terceiro',
        'forensic_warning' => 'Abaixo está o payload íntegro da tentativa, exibido escapado para auditoria. Ele nunca é executado por esta página — mas não o copie para fora do painel.',
        'raw_nickname' => 'Apelido (payload íntegro)',
        'raw_subject' => 'Assunto (payload íntegro)',
        'raw_message' => 'Mensagem (payload íntegro)',
    ],

    'products' => [
        'label' => 'Produto',
        'plural' => 'Produtos',
        'image' => 'Foto',
        'image_hint' => 'PNG, JPG ou WebP até 2 MB. Sem foto = placeholder.',
        'title' => 'Título',
        'price' => 'Valor',
        'price_hint' => 'Use o formato 1.234,56. O valor mínimo é R$ 0,01.',
        'price_invalid' => 'Informe um valor válido (ex.: 1.234,56).',
        'price_positive' => 'O valor deve ser maior que zero.',
        'description' => 'Descrição',
        'created_at' => 'Cadastrado em',
        'filter_price' => 'Faixa de preço',
        'price_up_to_100' => 'Até R$ 100',
        'price_100_to_500' => 'R$ 100 a R$ 500',
        'price_above_500' => 'Acima de R$ 500',
        'deleted' => 'Produto excluído.',
    ],

    'audit' => [
        'type_product' => 'Produto',
        'type_form_submission' => 'Submissão de formulário',
    ],

    'dashboards' => [

        'common' => [
            'received' => 'Recebida',
        ],

        'overview' => [
            'latest_submissions' => 'Últimas submissões',
            'submission_from' => 'De',
            'submission_state' => 'Situação',
        ],

        'content' => [
            'nav' => 'Conteúdo & Operação',
            'title' => 'Conteúdo & Operação',
            'subheading' => 'A fila de trabalho do dia: catálogo, arquivos que entraram, mensagens recebidas e o que a segurança barrou.',
            'products' => 'Produtos',
            'products_hint' => 'cadastrados no período',
            'uploads' => 'Uploads',
            'uploads_hint' => 'arquivos aceitos',
            'storage' => 'Volume armazenado',
            'storage_hint' => 'somado no período',
            'blocked' => 'Bloqueadas',
            'blocked_hint' => 'tentativas barradas',
            'chart_intake_heading' => 'Entrada por dia',
            'chart_intake_uploads' => 'Uploads',
            'chart_intake_submissions' => 'Submissões',
            'chart_types_heading' => 'Tipos de arquivo',
            'type_image' => 'Imagem',
            'type_pdf' => 'PDF',
            'type_document' => 'Documento',
            'type_other' => 'Outros',
            'latest_products' => 'Últimos produtos',
            'product_title' => 'Produto',
            'product_price' => 'Preço',
            'inbox' => 'Fila de entrada',
            'inbox_from' => 'De',
            'inbox_subject' => 'Assunto',
            'inbox_state' => 'Situação',
        ],

    ],

];
