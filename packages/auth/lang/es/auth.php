<?php

declare(strict_types=1);

// Autenticación (es): los mensajes que devuelven las reglas del paquete
// twstec/kit-auth (rechazos, avisos, política de contraseña) y los textos de
// las pantallas de acceso, registro y verificación de los starters. La
// aplicación gana: la misma clave en su lang/ prevalece sobre esta (ver
// docs/personalizacao.md).

return [

    'failed' => 'Las credenciales ingresadas no coinciden con nuestros registros.',

    'throttle' => 'Demasiados intentos de inicio de sesión. Intenta de nuevo en :seconds segundos.',

    'account_inactive' => 'Esta cuenta no está activa. Contacta con soporte.',

    'registered' => 'Cuenta creada con éxito. ¡Bienvenido(a)!',

    'logged_out' => 'Sesión cerrada con éxito.',

    'session_ended' => 'Tu sesión se cerró porque la contraseña o la verificación en dos pasos de la cuenta cambió. Inicia sesión de nuevo.',

    'password_policy' => [
        'min' => 'mínimo :min caracteres',
        'with' => ':min, con :rules',
        'separator' => ', ',
        'letters' => 'al menos una letra',
        'mixed_case' => 'mayúscula y minúscula',
        'numbers' => 'al menos un número',
        'symbols' => 'al menos un símbolo',
    ],

    'email_verification' => [
        'sent' => 'Enviamos un nuevo enlace de confirmación a tu correo.',
        'registered' => 'Cuenta creada. Confirma tu correo para habilitar el panel.',
        'cooldown' => 'Espera :seconds segundos para solicitar un nuevo envío.',
        'verified' => 'Correo confirmado. ¡Bienvenido(a)!',
        'invalid_link' => 'Este enlace de confirmación no es válido o expiró. Solicita uno nuevo abajo.',
        'not_verified' => 'Confirma tu correo para continuar.',
        'wrong_account' => 'Este enlace es de otra cuenta. Cierra sesión e ingresa con la cuenta que recibió el correo.',

        // Verificación de correo en el registro (Twstec\Kit\Auth\Support\EmailVerification).
        'title' => 'Confirma tu correo',
        'intro' => 'Enviamos un enlace de confirmación a :email. Abre el correo y haz clic en el enlace para habilitar el panel.',
        'hint' => '¿No llegó? Revisa la carpeta de spam o solicita un nuevo envío.',
        'resend' => 'Reenviar correo',
    ],

    'password_confirmation' => [
        'locked' => 'Demasiados intentos con contraseña incorrecta. Por seguridad, espera :minutes minuto(s) e inténtalo de nuevo.',
    ],

    'transaction_password' => [
        'invalid' => 'La contraseña de transacción ingresada es incorrecta.',
        'current_invalid' => 'La contraseña de transacción actual es incorrecta.',
        'same_as_login' => 'La contraseña de transacción debe ser diferente de la contraseña de acceso.',
        'saved' => 'Contraseña de transacción guardada con éxito.',
    ],

    'verification_code' => [
        'sent' => 'Enviamos un código de verificación a tu correo.',
        'invalid' => 'El código ingresado no es válido.',
        'expired' => 'El código expiró o no existe. Solicita uno nuevo.',
        'resend_cooldown' => 'Espera :seconds segundos para solicitar un nuevo código.',
    ],

    'two_factor' => [
        'code_label' => 'Código de verificación',
        'invalid' => 'Código incorrecto. Revisa el último correo recibido.',
        'expired' => 'Este código expiró o ya fue usado. Solicita uno nuevo abajo.',
        'resend_cooldown' => 'Espera :seconds segundos para solicitar otro código.',
        'resent' => 'Enviamos un nuevo código a tu correo.',
        'cancelled' => 'Inicio de sesión cancelado. No se autenticó nada.',
        'challenge_expired' => 'La verificación expiró. Inicia sesión de nuevo con tu contraseña.',
        'locked' => 'Demasiados códigos incorrectos. Por seguridad, espera :minutes minuto(s) e inicia sesión de nuevo.',
        'unavailable' => 'La verificación en dos pasos no está disponible en esta instalación.',
        'account_protected' => 'No disponible en esta cuenta: está protegida y la verificación en dos pasos no puede activarse en ella.',
        // Nombre antiguo (hasta la 2.x), mismo texto: sale en la 3.0.
        'demo_blocked' => 'No disponible en esta cuenta: está protegida y la verificación en dos pasos no puede activarse en ella.',
        'requires_transaction_password' => 'Define primero tu contraseña de transacción: activar y desactivar la verificación en dos pasos son acciones sensibles.',
        'setup_required' => 'La verificación en dos pasos es obligatoria en esta instalación. Configúrala para continuar.',
        'required_cannot_disable' => 'La verificación en dos pasos es obligatoria en esta instalación y no se puede desactivar.',
        'setup_done' => 'Verificación en dos pasos activada. A partir del próximo inicio de sesión, te pediremos el código enviado a tu correo.',

        // Verificación en dos pasos en el INICIO DE SESIÓN (TwoFactorLogin):
        // pantalla del código, mensajes del flujo y los rechazos al activar/desactivar.
        'title' => 'Verificación en dos pasos',
        'intro' => 'Enviamos un código de 6 dígitos a :email. Escríbelo abajo para completar el inicio de sesión — es válido por :minutes minutos.',
        'submit' => 'Confirmar y entrar',
        'resend' => 'Enviar otro código',
        'resend_hint' => '¿No llegó? Revisa el spam. Un código nuevo invalida el anterior.',
        'cancel' => 'Volver al inicio de sesión',
        'enabled' => 'Verificación en dos pasos activada. Desde tu próximo inicio de sesión, pediremos el código enviado a tu correo.',
        'disabled' => 'Verificación en dos pasos desactivada. El inicio de sesión vuelve a pedir solo la contraseña.',
    ],

    'sensitive_action' => [
        'token_issued' => 'Acción sensible autorizada. Usa el token de inmediato — es de uso único.',
        'invalid_token' => 'Token de acción sensible ausente, inválido o expirado. Confirma la acción de nuevo.',
    ],

    'password' => 'La contraseña ingresada es incorrecta.',

    // Configuración de la verificación en dos pasos OBLIGATORIA (AUTH_TWO_FACTOR_REQUIRED).
    'two_factor_setup' => [
        'title' => 'Configura la verificación en dos pasos',
        'intro' => 'Esta instalación exige la verificación en dos pasos. Una vez configurada, cada inicio de sesión pide, además de la contraseña, un código enviado a :email.',
        'grace' => 'Puedes posponerlo hasta el :date. Después, el panel solo se abre con la verificación configurada.',
        'date_format' => 'd/m/Y',
        'transaction_password_heading' => 'Primero, define tu contraseña de transacción',
        'transaction_password_hint' => 'Activar la verificación es una acción sensible: se confirma con la contraseña de transacción (distinta de la de inicio de sesión) y un código por correo.',
        'confirm_heading' => 'Confirma con tu contraseña de transacción',
        'confirm_hint' => 'Enviaremos un código de 6 dígitos a tu correo.',
        'send_code' => 'Enviar código',
        'code_intro' => 'Enviamos un código de 6 dígitos a :email. Escríbelo abajo — es válido por :minutes minutos.',
        'submit' => 'Activar y continuar',
        'resend_hint' => '¿No llegó? Revisa el spam o pide otro código con tu contraseña de transacción.',
        'later' => 'Configurar después',
    ],

    // Cadenas de interfaz (formularios/pantallas de autenticación).
    'ui' => [
        'login_title' => 'Entrar',
        'login_submit' => 'Entrar',
        'login_link' => '¿Ya tienes cuenta? Entrar',
        'register_title' => 'Crear cuenta',
        'register_submit' => 'Crear cuenta',
        'register_link' => 'Crear cuenta',
        'name' => 'Nombre completo',
        'email' => 'Correo electrónico',
        'password' => 'Contraseña',
        'new_password' => 'Nueva contraseña',
        'password_confirmation' => 'Confirma la contraseña',
        'remember_me' => 'Mantener sesión iniciada',
        'forgot_password' => 'Olvidé mi contraseña',
        'forgot_title' => 'Recuperar contraseña',
        'forgot_subtitle' => 'Ingresa tu correo para recibir el enlace de restablecimiento.',
        'forgot_submit' => 'Enviar enlace de restablecimiento',
        'reset_title' => 'Restablecer contraseña',
        'reset_submit' => 'Restablecer contraseña',
        'logout' => 'Salir',
        'save' => 'Guardar',
        'transaction_password_title' => 'Contraseña de transacción',
        'transaction_password_subtitle' => 'Se usa para autorizar acciones sensibles (eliminación de la cuenta, claves de API). Debe ser diferente de la contraseña de acceso.',
        'current_transaction_password' => 'Contraseña de transacción actual',
        'new_transaction_password' => 'Nueva contraseña de transacción',
        'dashboard_title' => 'Panel',
        'dashboard_greeting' => 'Hola, :name',
        'dashboard_code' => 'Tu código de usuario',
    ],

];
