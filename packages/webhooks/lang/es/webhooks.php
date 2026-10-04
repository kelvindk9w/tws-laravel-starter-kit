<?php

declare(strict_types=1);

// Webhooks salientes (twstec/kit-webhooks) — textos del dominio, de los
// correos y de las pantallas de los starters (las dos interfaces usan las
// mismas claves).

return [

    'status' => [
        'active' => 'Activo',
        'disabled' => 'Desactivado',
    ],

    'disabled_reason' => [
        'manual' => 'desactivado manualmente',
        'failures' => 'desactivado por fallos seguidos',
    ],

    'delivery_status' => [
        'pending' => 'Pendiente',
        'delivering' => 'Enviando',
        'retrying' => 'Nuevo intento programado',
        'succeeded' => 'Entregado',
        'failed' => 'Falló',
    ],

    'attempt_outcome' => [
        'succeeded' => 'Entregado',
        'failed' => 'Falló',
        'blocked' => 'Bloqueado',
    ],

    'events_all' => 'Todos los eventos',

    'events' => [
        'webhook' => [
            'ping' => 'Evento de prueba',
        ],
    ],

    'fields' => [
        'name' => 'nombre',
        'url' => 'URL',
        'events' => 'eventos',
        'project' => 'proyecto',
        'overlap' => 'convivencia',
    ],

    'destination' => [
        'invalid_url' => 'URL no válida. Use una dirección completa, como https://ejemplo.com/webhooks.',
        'scheme_not_allowed' => 'Solo se aceptan direcciones HTTPS.',
        'credentials_in_url' => 'La URL no puede contener usuario, contraseña ni el carácter @.',
        'invalid_host' => 'La dirección del servidor no es válida.',
        'unresolvable' => 'No se encontró este servidor (DNS).',
        'private_address' => 'Esta dirección apunta a una red interna o reservada y no está permitida.',
        'metadata_address' => 'Esta dirección es de un servicio interno de la nube y no está permitida.',
    ],

    'validation' => [
        'unknown_event' => 'Evento desconocido.',
        'events_required' => 'Elija al menos un evento.',
        'project_invalid' => 'Proyecto no encontrado en esta cuenta.',
    ],

    'errors' => [
        'endpoint_disabled' => 'El endpoint está desactivado. Reactívelo antes.',
        'redirect_not_followed' => 'El receptor respondió con una redirección (HTTP :status), que no se sigue.',
        'http_status' => 'El receptor respondió HTTP :status.',
        'connection_mismatch' => 'La conexión no fue a la dirección verificada y se interrumpió.',
        'sensitive_required' => 'Confirme la acción con la contraseña de transacción y el código enviado por correo.',
        'too_many' => 'Demasiadas solicitudes. Inténtelo de nuevo en :seconds segundos.',
        'in_progress' => 'Esta entrega ya se está enviando.',
    ],

    'mail' => [
        'endpoint_disabled' => [
            'subject' => 'Endpoint de webhook desactivado — :platform',
            'preheader' => 'Desactivado tras :failures intentos fallidos seguidos.',
            'heading' => 'Endpoint de webhook desactivado',
            'intro' => 'Dejamos de enviar eventos a este endpoint tras :failures intentos fallidos seguidos.',
            'account_label' => 'Cuenta',
            'name_label' => 'Endpoint',
            'host_label' => 'Servidor',
            'action' => 'Compruebe que el receptor está en línea y responde 2xx, y reactive el endpoint en la pantalla de webhooks. Las entregas fallidas pueden reenviarse desde allí.',
            'cta' => 'Abrir webhooks',
        ],
    ],

    'console' => [
        'requeued' => ':count entrega(s) de webhook en la cola.',
        'pruned' => ':count evento(s) de webhook antiguo(s) eliminado(s).',
    ],

    'ui' => [
        'title' => 'Webhooks',
        'subtitle' => 'Avise a otros sistemas cuando algo ocurre en esta cuenta. Cada envío se firma con el secreto del endpoint.',
        'new' => 'Nuevo endpoint',
        'empty' => 'Ningún endpoint registrado.',
        'empty_hint' => 'Registre la URL que recibirá los eventos. El secreto de firma se genera aquí y se muestra una vez.',
        'name' => 'Nombre',
        'url' => 'URL de destino',
        'url_hint' => 'Dirección pública, con HTTPS.',
        'events' => 'Eventos',
        'events_hint' => 'Los eventos que recibe este endpoint.',
        'project' => 'Proyecto',
        'project_all' => 'Toda la cuenta (todos los proyectos)',
        'save' => 'Guardar',
        'cancel' => 'Cancelar',
        'edit' => 'Editar',
        'delete' => 'Eliminar',
        'delete_confirm' => '¿Eliminar este endpoint? Sus entregas y su registro también se eliminan.',
        'created' => 'Endpoint creado.',
        'updated' => 'Endpoint actualizado.',
        'deleted' => 'Endpoint eliminado.',
        'enable' => 'Reactivar',
        'disable' => 'Desactivar',
        'enabled' => 'Endpoint reactivado.',
        'disabled' => 'Endpoint desactivado.',
        'send_test' => 'Enviar prueba',
        'test_sent' => 'Evento de prueba enviado a la cola.',
        'resend' => 'Reenviar',
        'resent' => 'Reenvío solicitado.',
        'reveal' => 'Revelar secreto',
        'rotate' => 'Rotar secreto',
        'rotate_overlap' => 'Convivencia del secreto anterior (minutos)',
        'rotate_hint' => 'Durante la convivencia cada envío lleva las dos firmas: cambie el secreto en el receptor sin perder eventos. Cero termina el anterior ahora.',
        'secret_title' => 'Secreto de firma',
        'secret_once' => 'Cópielo y guárdelo ahora: no se mostrará de nuevo.',
        'secret_saved' => 'Ya lo guardé',
        'copy' => 'Copiar',
        'copied' => 'Copiado',
        'deliveries' => 'Entregas',
        'deliveries_empty' => 'Ninguna entrega todavía.',
        'show_deliveries' => 'Ver entregas',
        'all_endpoints' => 'Todos los endpoints',
        'event' => 'Evento',
        'status' => 'Estado',
        'attempts' => 'Intentos',
        'attempt' => 'Intento :number',
        'manual' => 'reenvío manual',
        'response' => 'Respuesta',
        'duration' => ':ms ms',
        'next_attempt' => 'Próximo intento: :date',
        'last_success' => 'Última entrega: :date',
        'failures' => ':count fallo(s) seguido(s)',
        'disabled_by_failures' => 'Desactivado tras :count fallos seguidos. Corrija el receptor y reactívelo.',
        'previous_secret_until' => 'Secreto anterior aceptado hasta :date.',
        'sensitive_requires_password' => 'Defina antes la contraseña de transacción en el perfil: crear, cambiar y manejar el secreto de un endpoint requieren confirmación.',
        'read_only' => 'Solo el propietario y los administradores de la cuenta gestionan webhooks.',
        'signature_help' => 'Cada envío trae la cabecera X-Webhook-Signature (t=marca,v1=HMAC-SHA256 de "marca.cuerpo"). Verifíquela con el secreto, rechace marcas de más de 5 minutos y deduplique por el id del evento.',
        'created_at' => 'Creado el :date',
    ],

];
