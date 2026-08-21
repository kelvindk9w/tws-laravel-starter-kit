<?php

declare(strict_types=1);

// Cadenas del panel del usuario (Livewire — ADR-005/011).
// Toda cadena visible pasa por __() — ADR-007. NUNCA texto fijo en views.

return [

    // Navegación / layout.
    'nav' => [
        'dashboard' => 'Panel',
        'api_keys' => 'Claves de API',
        'projects' => 'Proyectos',
        'notifications' => 'Notificaciones',
        'profile' => 'Perfil',
        'toggle_theme' => 'Alternar tema claro/oscuro',
    ],

    'common' => [
        'save' => 'Guardar',
        'cancel' => 'Cancelar',
        'confirm' => 'Confirmar',
        'close' => 'Cerrar',
        'create' => 'Crear',
        'edit' => 'Editar',
        'delete' => 'Eliminar',
        'actions' => 'Acciones',
        'status' => 'Estado',
        'name' => 'Nombre',
        'created_at' => 'Creado el',
        'never' => 'Nunca',
        'none' => 'Ninguno',
        'saved' => 'Guardado con éxito.',
        'optional' => 'opcional',
    ],

    // Dashboard.
    'dashboard' => [
        'title' => 'Panel',
        'greeting' => 'Hola, :name',
        'user_code' => 'Tu código de usuario',
        'summary_keys' => 'Claves de API activas',
        'summary_projects' => 'Proyectos',
        'quick_actions' => 'Acciones rápidas',
        'new_api_key' => 'Crear clave de API',
        'new_project' => 'Crear proyecto',
        'manage_profile' => 'Mi perfil',
    ],

    // Perfil.
    'profile' => [
        'title' => 'Perfil',
        'data_heading' => 'Tus datos',
        'email_readonly' => 'El correo es la clave de acceso de la cuenta y no puede cambiarse por aquí.',
        'locale_label' => 'Idioma',
        'locale_hint' => 'Se usa en la interfaz y en los correos que recibes.',
        'avatar_heading' => 'Foto de perfil',
        'avatar_hint' => 'Imagen JPG, PNG o WebP. El archivo se valida por contenido y se reprocesa antes de guardarse.',
        'avatar_updated' => 'Foto de perfil actualizada.',
        'password_heading' => 'Contraseña de acceso',
        'current_password' => 'Contraseña actual',
        'password_updated' => 'Contraseña de acceso actualizada con éxito.',
        'current_password_invalid' => 'La contraseña actual ingresada es incorrecta.',
        'transaction_password_heading' => 'Contraseña de transacción',
        'transaction_password_hint' => 'Se usa para autorizar acciones sensibles (creación/rotación de claves de API). Debe ser diferente de la contraseña de acceso.',
        'transaction_password_set' => 'Definida',
        'transaction_password_not_set' => 'No definida — defínela para poder crear claves de API.',
    ],

    // Proyectos (ADR-005 — capa organizacional, solo nombre).
    'projects' => [
        'title' => 'Proyectos',
        'subtitle' => 'Los proyectos organizan tu cuenta: vincula claves de API a ellos para separar datos y vistas.',
        'new' => 'Nuevo proyecto',
        'edit' => 'Editar proyecto',
        'empty' => 'Aún no tienes proyectos. Crea el primero en esta pantalla.',
        'delete_title' => 'Eliminar proyecto',
        'delete_warning' => '¿Eliminar el proyecto ":name"? Las claves de API vinculadas a él pasan a ver toda la cuenta.',
        'created' => 'Proyecto creado con éxito.',
        'updated' => 'Proyecto actualizado con éxito.',
        'deleted' => 'Proyecto eliminado con éxito.',
        'status_active' => 'Activo',
        'status_archived' => 'Archivado',
        'linked_keys' => ':count clave(s) vinculada(s)',
    ],

    // Claves de API (ADR-006 — la pantalla más importante).
    'api_keys' => [
        'title' => 'Claves de API',
        'subtitle' => 'Pares de clave pública + secreta para tu integración. La secreta se muestra UNA única vez.',
        'new' => 'Nueva clave',
        'empty' => 'Aún no tienes claves de API. Crea la primera en esta pantalla.',
        'public_key' => 'Clave pública',
        'last_used' => 'Último uso',
        'expires_at' => 'Vigencia',
        'no_expiration' => 'Sin vigencia',
        'expires_hint' => 'Vacío = sin vigencia. El sistema nunca impone plazo (ADR-006).',
        'grace_hint' => 'La clave antigua puede morir de inmediato o seguir válida por un período, evitando downtime en el cambio.',

        'scopes_heading' => 'Permisos (scopes)',
        'scopes_all' => 'Todos los permisos',
        'scopes_all_hint' => 'Por defecto la clave puede todo. Desactívalo para restringir por recurso/acción (mínimo privilegio).',
        'scopes_hint' => 'Selecciona solo lo que la integración necesita.',

        'projects_heading' => 'Proyectos vinculados',
        'projects_hint' => 'Sin vínculo = la clave ve toda la cuenta. Con vínculo = restringida a los proyectos marcados.',
        'projects_empty' => 'Ningún proyecto aún — la clave verá toda la cuenta.',
        'whole_account' => 'Toda la cuenta',
        'edit_projects' => 'Proyectos',

        'create_heading' => 'Crear clave de API',
        'rotate' => 'Rotar',
        'rotate_title' => 'Rotar clave',
        'rotate_warning' => 'Se generará una nueva clave secreta. Elige cuándo la clave actual deja de funcionar.',
        'grace_immediate' => 'Inmediatamente',
        'grace_1h' => 'Después de 1 hora',
        'grace_24h' => 'Después de 24 horas',
        'grace_7d' => 'Después de 7 días',
        'revoke' => 'Revocar',
        'revoke_title' => 'Revocar clave',
        'revoke_warning' => '¿Revocar la clave ":name"? La acción es irreversible: las integraciones que usan esta clave se detienen de inmediato.',
        'revoked' => 'Clave revocada con éxito.',
        'projects_saved' => 'Vínculos de proyectos actualizados.',

        // Pantalla de visualización única de la secreta (ADR-006).
        'secret_heading' => 'Guarda tu clave secreta',
        'secret_warning' => 'Esta es la ÚNICA vez que la clave secreta se muestra. No hay recuperación: si la pierdes, rótala o crea una nueva.',
        'copy' => 'Copiar',
        'copied' => '¡Copiada!',
        'secret_done' => 'Ya guardé la clave de forma segura',

        // Flujo de acción sensible (contraseña de transacción + código por correo).
        'sensitive_heading' => 'Confirmación de seguridad',
        'sensitive_password_hint' => 'Ingresa tu contraseña de transacción para recibir un código de verificación por correo.',
        'sensitive_send_code' => 'Enviar código por correo',
        'sensitive_code_hint' => 'Enviamos un código de 6 dígitos a tu correo. Expira en pocos minutos.',
        'sensitive_code' => 'Código de verificación',
        'sensitive_confirm' => 'Confirmar y ejecutar',
        'sensitive_resend_in' => 'Reenviar en :seconds s',
        'sensitive_requires_password' => 'Define tu contraseña de transacción en el Perfil antes de crear claves.',

        'status_active' => 'Activa',
        'status_revoked' => 'Revocada',
        'status_expired' => 'Expirada',
        'status_expired_inactivity' => 'Expirada por inactividad',
        'status_rotated' => 'Rotada',
        'status_grace' => 'Rotada (en transición)',

        'scope_resource_api-keys' => 'Claves de API',
        'scope_resource_projects' => 'Proyectos',
        'scope_resource_uploads' => 'Uploads',
        'scope_action_read' => 'leer',
        'scope_action_create' => 'crear',
        'scope_action_update' => 'editar',
        'scope_action_delete' => 'eliminar',
        'scope_action_rotate' => 'rotar',
        'scope_action_revoke' => 'revocar',
        'scope_action_assign' => 'vincular proyectos',
    ],

    // Preferencias de notificación (esqueleto — ADR-009).
    'notifications' => [
        'title' => 'Notificaciones',
        'subtitle' => 'Elige qué correos quieres recibir. Las alertas de seguridad siempre se envían.',
        'saved' => 'Preferencias de notificación guardadas.',
        'pref_payment_confirmed' => 'Pago confirmado',
        'pref_payment_confirmed_hint' => 'Aviso por correo cuando un cobro tuyo sea pagado.',
        'pref_final_customer_receipt' => 'Recibo al cliente final',
        'pref_final_customer_receipt_hint' => 'Tu cliente final recibe un correo de confirmación con tu marca.',
        'pref_api_key_events' => 'Eventos de claves de API',
        'pref_api_key_events_hint' => 'Creación, rotación y avisos de expiración por inactividad.',
        'pref_security_alerts' => 'Alertas de seguridad',
        'pref_security_alerts_hint' => 'Inicios de sesión y acciones sensibles. Siempre activas — no se pueden desactivar.',
        'locked' => 'Siempre activo',
    ],

];
