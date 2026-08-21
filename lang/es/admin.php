<?php

declare(strict_types=1);

// Cadenas del super admin (Filament — ADR-011). Siempre via __().

return [

    'nav' => [
        'group_management' => 'Gestión',
        'group_security' => 'Seguridad y auditoría',
        'group_system' => 'Sistema',
    ],

    'users' => [
        'label' => 'Usuario',
        'plural' => 'Usuarios',
        'code' => 'Código',
        'admin' => 'Admin',
        'blocked' => 'Bloqueado',
        'active' => 'Activo',
        'pending' => 'Pendiente',
        'block' => 'Bloquear',
        'unblock' => 'Desbloquear',
        'block_heading' => 'Bloquear usuario',
        'block_warning' => 'El usuario pierde inmediatamente el acceso al panel y las claves de API siguen válidas solo si la cuenta está activa. ¿Bloquear a ":email"?',
        'unblock_heading' => 'Desbloquear usuario',
        'blocked_success' => 'Usuario bloqueado.',
        'unblocked_success' => 'Usuario desbloqueado.',
        'demo_protected' => 'Cuenta de demostración protegida: los usuarios demo no pueden ser bloqueados, editados ni eliminados.',
        'transaction_password' => 'Contraseña de transacción definida',
        'created_at' => 'Registrado el',
    ],

    'api_keys' => [
        'label' => 'Clave de API',
        'plural' => 'Claves de API',
        'owner' => 'Dueño',
        'public_key' => 'Clave pública',
        'scopes' => 'Scopes',
        'last_used' => 'Último uso',
        'expires_at' => 'Vigencia',
        'never' => 'Nunca',
        'no_expiration' => 'Sin vigencia',
        'revoke' => 'Revocar',
        'revoke_heading' => 'Revocar clave de API',
        'revoke_warning' => 'La revocación es irreversible e inmediata. ¿Revocar la clave ":key" de ":owner"?',
        'revoked' => 'Clave revocada.',
        'status_active' => 'Activa',
        'status_revoked' => 'Revocada',
        'status_expired' => 'Expirada',
        'status_expired_inactivity' => 'Expirada por inactividad',
        'status_rotated' => 'Rotada',
    ],

    'projects' => [
        'label' => 'Proyecto',
        'plural' => 'Proyectos',
        'owner' => 'Dueño',
        'linked_keys' => 'Claves vinculadas',
        'status_active' => 'Activo',
        'status_archived' => 'Archivado',
    ],

    'request_logs' => [
        'label' => 'Log de petición',
        'plural' => 'Logs de petición',
        'tenant' => 'Tenant',
        'orphan' => 'SIN TENANT',
        'orphan_hint' => 'Logs sin tenant = posible ataque/intento de evasión (ADR-010).',
        'endpoint' => 'Endpoint',
        'response_status' => 'HTTP',
        'duration' => 'Duración',
        'ip' => 'IP',
        'payload' => 'Payload (sanitizado)',
        'error' => 'Error',
        'filter_status' => 'Estado',
        'filter_tenant' => 'Tenant (UUID)',
        'filter_endpoint' => 'Endpoint contiene',
        'filter_from' => 'Desde',
        'filter_until' => 'Hasta',
        'only_orphans' => 'Solo huérfanos',
        'status_INICIADA' => 'INICIADA',
        'status_CONCLUIDA' => 'CONCLUIDA',
        'status_ERRO' => 'ERROR',
        'status_BLOQUEADA' => 'BLOQUEADA',
    ],

    'uploads' => [
        'label' => 'Upload',
        'plural' => 'Uploads',
        'original_name' => 'Archivo',
        'mime' => 'Tipo',
        'size' => 'Tamaño',
        'owner' => 'Dueño',
        'tenant' => 'Tenant (UUID)',
        'open' => 'Abrir archivo',
        'open_hint' => 'Abre en nueva pestaña con URL firmada de corta duración.',
    ],

    'settings' => [
        'label' => 'Configuraciones',
        'heading' => 'Configuraciones del sistema',
        'subheading' => 'Ajustes operacionales editables por la UI — sin tocar el .env. Campo vacío = valor vigente del .env.',
        'saved' => 'Configuraciones guardadas.',
        // Claves: fieldName de la pantalla de Settings (los puntos de la clave
        // de config se vuelven "_" — los puntos romperían la resolución de __()).
        'key_api_keys_inactivity_months' => 'Meses de inactividad para expirar claves',
        'key_api_keys_inactivity_warning_days' => 'Días de aviso previo por correo',
        'key_uploads_types_image_max_kb' => 'Tamaño máximo de imagen (KB)',
        'key_uploads_types_pdf_max_kb' => 'Tamaño máximo de PDF (KB)',
        'key_security_rate_limit_api' => 'Rate limit de la API (req/min)',
        'key_security_rate_limit_sensitive' => 'Rate limit de rutas sensibles (req/min)',
        'env_fallback' => 'Por defecto del .env: :value',
        'overridden' => 'Personalizado',
    ],

    'command' => [
        'user_not_found' => 'Usuario no encontrado.',
        'admin_granted' => 'Acceso de super admin concedido a :email.',
        'admin_removed' => 'Acceso de super admin revocado de :email.',
    ],

];
