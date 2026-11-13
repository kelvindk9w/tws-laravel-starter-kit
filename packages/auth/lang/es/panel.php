<?php

declare(strict_types=1);

// Textos del panel del usuario (es): navegación, inicio, perfil, acciones
// sensibles y notificaciones — las pantallas de los starters React y
// Livewire. Todo texto pasa por __(). La aplicación gana: la misma clave en
// su lang/ prevalece sobre esta (ver docs/personalizacao.md).

return [

    // Navegación / layout.
    'nav' => [
        'dashboard' => 'Panel',
        'api_keys' => 'Claves de API',
        'projects' => 'Proyectos',
        'webhooks' => 'Webhooks',
        'notifications' => 'Notificaciones',
        'profile' => 'Perfil',
        'transaction_password' => 'Contraseña de transacción',
        'account' => 'Cuenta y miembros',
        'toggle_theme' => 'Alternar tema claro/oscuro',
        'menu' => 'Menú',
        'open_menu' => 'Abrir menú de navegación',

        // Grupos do menu lateral "Minha conta" (<x-side-nav>).
        'groups' => [
            'overview' => 'Resumen',
            'development' => 'Desarrollo',
            'account' => 'Cuenta',
        ],
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
        'copy' => 'Copiar',
        'copied' => '¡Copiado!',
        'more_actions' => 'Más acciones',
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

        // Métricas e tráfego real da conta (request_logs).
        'summary_requests' => 'Solicitudes (:days días)',
        'summary_last_key_use' => 'Último uso de clave',
        'last_key_use_empty' => 'Todavía no hay llamadas autenticadas.',
        'chart_title' => 'Solicitudes por día (:days días)',
        'chart_hint' => 'Cada llamada autenticada de tu cuenta a la API, agrupada por día.',
        'chart_series' => 'Solicitudes',
        'chart_empty_title' => 'Sin solicitudes en el período',
        'chart_empty_description' => 'En cuanto tu integración empiece a llamar a la API, el tráfico aparece aquí.',
        'recent_calls_title' => 'Últimas llamadas a la API',
        'recent_calls_hint' => 'Las 5 más recientes de esta cuenta.',
        'recent_calls_empty_title' => 'Tu API aún no ha recibido llamadas',
        'recent_calls_empty_description' => 'Crea una clave de API y haz la primera solicitud para ver la traza de auditoría aquí.',
        'call_endpoint' => 'Endpoint',
        'call_when' => 'Cuándo',

        // Sin el paquete de cuentas (twstec/kit-accounts): no hay claves,
        // proyectos ni API — el panel abre con los atajos de la cuenta.
        'essentials_title' => 'Tu cuenta',
        'essentials_hint' => 'Los ajustes que protegen y personalizan tu acceso.',
        'essentials_profile' => 'Nombre, idioma, contraseña de acceso y verificación en dos pasos.',
        'essentials_transaction_password' => 'Autoriza las acciones sensibles de tu cuenta.',
        'essentials_notifications' => 'Elige qué correos quieres recibir.',
    ],

    // Perfil.
    'profile' => [
        'title' => 'Perfil',
        'data_heading' => 'Tus datos',
        'email_readonly' => 'El correo es la clave de acceso de la cuenta y no puede cambiarse por aquí.',
        'locale_label' => 'Idioma',
        'locale_hint' => 'Se usa en la interfaz y en los correos que recibes.',
        'theme_heading' => 'Apariencia',
        'theme_hint' => 'Sistema sigue la preferencia del dispositivo. La elección se guarda en este dispositivo y en tu cuenta.',
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
        'subtitle' => 'Tus datos, apariencia, foto y las dos contraseñas de la cuenta.',
        'password_hint' => 'Requiere la contraseña actual. Nueva contraseña: :rules.',
        'choose_file' => 'Elegir imagen',
        'avatar_uploading' => 'Subiendo la imagen…',
        // Cada botão nomeia o próprio escopo (a tela salva 4 coisas).
        'save_data' => 'Guardar datos',
        'save_avatar' => 'Guardar foto',
        'save_password' => 'Cambiar contraseña de acceso',
        'save_transaction_password' => 'Cambiar contraseña de transacción',
        'two_factor_heading' => 'Verificación en dos pasos',
        'two_factor_hint' => 'Al iniciar sesión, además de la contraseña, pedimos un código enviado a :email. Quien descubra tu contraseña todavía no entra.',
        'two_factor_on' => 'Activada',
        'two_factor_off' => 'Desactivada',
        'two_factor_enable' => 'Activar verificación en dos pasos',
        'two_factor_disable' => 'Desactivar verificación en dos pasos',
        'two_factor_recovery' => 'El código siempre llega al correo de la cuenta: sin acceso a él, no hay forma de completar el inicio de sesión. Mantén ese correo seguro.',
        'two_factor_confirm_enable' => 'Para activar la verificación en dos pasos, confirma con tu contraseña de transacción y el código enviado por correo.',
        'two_factor_confirm_disable' => 'Para desactivar la verificación en dos pasos, confirma con tu contraseña de transacción y el código enviado por correo.',
        'two_factor_required' => 'Obligatoria en esta instalación',
        'two_factor_grace' => 'La verificación en dos pasos es obligatoria en esta instalación. Configúrala antes del :date — después, el panel solo se abre con ella.',
        'two_factor_grace_action' => 'Configurar ahora',
    ],

    // Confirmación de acción sensible (modal compartido del panel: contraseña
    // de transacción → código por correo → ejecuta).
    'sensitive' => [
        'heading' => 'Confirmación de seguridad',
        'password_hint' => 'Ingresa tu contraseña de transacción para recibir un código de verificación por correo.',
        'send_code' => 'Enviar código por correo',
        'code_hint' => 'Enviamos un código de 6 dígitos a tu correo. Expira en pocos minutos.',
        'code_only_hint' => 'Para confirmar, enviaremos un código de verificación a tu correo electrónico.',
        'code' => 'Código de verificación',
        'confirm' => 'Confirmar y ejecutar',
        'resend_in' => 'Reenviar en :seconds s',
    ],

    // Preferencias de notificación (esqueleto).
    'notifications' => [
        'title' => 'Notificaciones',
        'subtitle' => 'Elige qué correos quieres recibir. Las alertas de seguridad siempre se envían.',
        'saved' => 'Preferencias de notificación guardadas.',
        'pref_order_confirmed' => 'Pedido confirmado',
        'pref_order_confirmed_hint' => 'Aviso por correo cuando un pedido tuyo sea confirmado.',
        'pref_final_customer_receipt' => 'Recibo al cliente final',
        'pref_final_customer_receipt_hint' => 'Tu cliente final recibe un correo de confirmación con tu marca.',
        'pref_api_key_events' => 'Eventos de claves de API',
        'pref_api_key_events_hint' => 'Creación, rotación y avisos de expiración por inactividad.',
        'pref_security_alerts' => 'Alertas de seguridad',
        'pref_security_alerts_hint' => 'Inicios de sesión y acciones sensibles. Siempre activas — no se pueden desactivar.',
        'locked' => 'Siempre activo',
        'emails_heading' => 'Correos que recibes',
        'save' => 'Guardar preferencias',
    ],

];
