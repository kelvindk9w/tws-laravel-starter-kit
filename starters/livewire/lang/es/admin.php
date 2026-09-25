<?php

declare(strict_types=1);

// Textos de las pantallas de la DEMOSTRACIÓN del kit en /admin (catálogo de
// productos, bandeja de envíos y el dashboard "Contenido y Operación"). Los
// del producto vienen del paquete twstec/kit-admin (packages/admin/lang) y se
// unen a estos en el mismo grupo `admin.*` — en una misma clave, gana este
// archivo. Salen junto con la demo.

return [

    'submissions' => [
        'label' => 'Submisión de formulario',
        'plural' => 'Submisiones de formulario',
        'nickname' => 'Apodo',
        'subject' => 'Asunto',
        'message' => 'Mensaje',
        'origin' => 'Origen',
        'origin_classic' => 'Clásico (POST)',
        'origin_livewire' => 'Livewire (AJAX)',
        'origin_contact' => 'Contacto (landing)',
        'sender_email' => 'Remitente',
        'security' => 'Seguridad',
        'accepted' => 'Aceptada',
        'blocked_attack' => 'Ataque bloqueado (:type)',
        'received_at' => 'Recibida en',
        'blocked_at' => 'Bloqueada en',
        'ip' => 'IP de origen',
        'no_sender' => 'Sin remitente (formulario anónimo)',
        'filter_blocked' => 'Solo bloqueadas',
        'view_evidence' => 'Ver evidencia',
        // El listado nunca muestra el payload: muestra el sello del ataque y
        // un fragmento neutralizado, con esta leyenda que dice qué se lee.
        'neutralized' => 'contenido neutralizado',
        'metadata_section' => 'Metadatos',
        'metadata_hint' => 'De dónde vino, cuándo llegó y qué decidió la plataforma.',
        'content_section' => 'Mensaje',
        'forensic_section' => 'Evidencia forense',
        'forensic_heading' => 'Contenido enviado por un tercero',
        'forensic_warning' => 'Abajo está el payload íntegro del intento, mostrado escapado para auditoría. Esta página nunca lo ejecuta — pero no lo copies fuera del panel.',
        'raw_nickname' => 'Apodo (payload íntegro)',
        'raw_subject' => 'Asunto (payload íntegro)',
        'raw_message' => 'Mensaje (payload íntegro)',
    ],

    'products' => [
        'label' => 'Producto',
        'plural' => 'Productos',
        'image' => 'Foto',
        'image_hint' => 'PNG, JPG o WebP hasta 2 MB. Sin foto = placeholder.',
        'title' => 'Título',
        'price' => 'Precio',
        'price_hint' => 'Use el formato 1.234,56. El valor mínimo es R$ 0,01.',
        'price_invalid' => 'Ingrese un valor válido (ej.: 1.234,56).',
        'price_positive' => 'El valor debe ser mayor que cero.',
        'description' => 'Descripción',
        'created_at' => 'Registrado en',
        'filter_price' => 'Rango de precio',
        'price_up_to_100' => 'Hasta R$ 100',
        'price_100_to_500' => 'R$ 100 a R$ 500',
        'price_above_500' => 'Más de R$ 500',
        'deleted' => 'Producto eliminado.',
    ],

    'audit' => [
        'type_product' => 'Producto',
        'type_form_submission' => 'Envío de formulario',
    ],

    'dashboards' => [

        'common' => [
            'received' => 'Recibida',
        ],

        'overview' => [
            'latest_submissions' => 'Últimos envíos',
            'submission_from' => 'De',
            'submission_state' => 'Situación',
        ],

        'content' => [
            'nav' => 'Contenido y Operación',
            'title' => 'Contenido y Operación',
            'subheading' => 'La cola de trabajo del día: catálogo, archivos que entraron, mensajes recibidos y lo que la seguridad bloqueó.',
            'products' => 'Productos',
            'products_hint' => 'creados en el período',
            'uploads' => 'Subidas',
            'uploads_hint' => 'archivos aceptados',
            'storage' => 'Volumen almacenado',
            'storage_hint' => 'sumado en el período',
            'blocked' => 'Bloqueadas',
            'blocked_hint' => 'intentos bloqueados',
            'chart_intake_heading' => 'Entrada por día',
            'chart_intake_uploads' => 'Subidas',
            'chart_intake_submissions' => 'Envíos',
            'chart_types_heading' => 'Tipos de archivo',
            'type_image' => 'Imagen',
            'type_pdf' => 'PDF',
            'type_document' => 'Documento',
            'type_other' => 'Otros',
            'latest_products' => 'Últimos productos',
            'product_title' => 'Producto',
            'product_price' => 'Precio',
            'inbox' => 'Cola de entrada',
            'inbox_from' => 'De',
            'inbox_subject' => 'Asunto',
            'inbox_state' => 'Situación',
        ],

    ],

];
