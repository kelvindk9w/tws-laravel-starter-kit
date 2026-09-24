<?php

declare(strict_types=1);

// Cadenas de autenticación (es). Toda cadena de UI pasa por __().

return [

    'failed' => 'Las credenciales ingresadas no coinciden con nuestros registros.',
    'password' => 'La contraseña ingresada es incorrecta.',
    'throttle' => 'Demasiados intentos de inicio de sesión. Intenta de nuevo en :seconds segundos.',

    // Pista de la política de contraseña, armada por PasswordPolicy::hint()
    // solo con las reglas ACTIVAS (config/auth.php → password_rules).
    'password_policy' => [
        'min' => 'mínimo :min caracteres',
        'with' => ':min, con :rules',
        'separator' => ', ',
        'letters' => 'al menos una letra',
        'mixed_case' => 'mayúscula y minúscula',
        'numbers' => 'al menos un número',
        'symbols' => 'al menos un símbolo',
    ],

    'account_inactive' => 'Esta cuenta no está activa. Contacta con soporte.',
    'registered' => 'Cuenta creada con éxito. ¡Bienvenido(a)!',
    'logged_out' => 'Sesión cerrada con éxito.',

    // Verificación de correo en el registro (App\Core\Auth\Support\EmailVerification).
    'email_verification' => [
        'title' => 'Confirma tu correo',
        'intro' => 'Enviamos un enlace de confirmación a :email. Abre el correo y haz clic en el enlace para habilitar el panel.',
        'hint' => '¿No llegó? Revisa la carpeta de spam o solicita un nuevo envío.',
        'resend' => 'Reenviar correo',
        'sent' => 'Enviamos un nuevo enlace de confirmación a tu correo.',
        'registered' => 'Cuenta creada. Confirma tu correo para habilitar el panel.',
        'cooldown' => 'Espera :seconds segundos para solicitar un nuevo envío.',
        'verified' => 'Correo confirmado. ¡Bienvenido(a)!',
        'invalid_link' => 'Este enlace de confirmación no es válido o expiró. Solicita uno nuevo abajo.',
        'not_verified' => 'Confirma tu correo para continuar.',
        'wrong_account' => 'Este enlace es de otra cuenta. Cierra sesión e ingresa con la cuenta que recibió el correo.',
    ],

    // Contraseña de transacción (separada de la de acceso).
    'transaction_password' => [
        'invalid' => 'La contraseña de transacción ingresada es incorrecta.',
        'current_invalid' => 'La contraseña de transacción actual es incorrecta.',
        'same_as_login' => 'La contraseña de transacción debe ser diferente de la contraseña de acceso.',
        'saved' => 'Contraseña de transacción guardada con éxito.',
    ],

    // Código de verificación (2FA por correo).
    'verification_code' => [
        'sent' => 'Enviamos un código de verificación a tu correo.',
        'invalid' => 'El código ingresado no es válido.',
        'expired' => 'El código expiró o no existe. Solicita uno nuevo.',
        'resend_cooldown' => 'Espera :seconds segundos para solicitar un nuevo código.',
    ],

    // Verificación en dos pasos en el INICIO DE SESIÓN (TwoFactorLogin):
    // pantalla del código, mensajes del flujo y los rechazos al activar/desactivar.
    'two_factor' => [
        'title' => 'Verificación en dos pasos',
        'intro' => 'Enviamos un código de 6 dígitos a :email. Escríbelo abajo para completar el inicio de sesión — es válido por :minutes minutos.',
        'code_label' => 'Código de verificación',
        'submit' => 'Confirmar y entrar',
        'resend' => 'Enviar otro código',
        'resend_hint' => '¿No llegó? Revisa el spam. Un código nuevo invalida el anterior.',
        'cancel' => 'Volver al inicio de sesión',
        'invalid' => 'Código incorrecto. Revisa el último correo recibido.',
        'expired' => 'Este código expiró o ya fue usado. Solicita uno nuevo abajo.',
        'resend_cooldown' => 'Espera :seconds segundos para solicitar otro código.',
        'resent' => 'Enviamos un nuevo código a tu correo.',
        'cancelled' => 'Inicio de sesión cancelado. No se autenticó nada.',
        'challenge_expired' => 'La verificación expiró. Inicia sesión de nuevo con tu contraseña.',
        'locked' => 'Demasiados códigos incorrectos. Por seguridad, espera :minutes minuto(s) e inicia sesión de nuevo.',
        'unavailable' => 'La verificación en dos pasos no está disponible en esta instalación.',
        'demo_blocked' => 'No disponible en la cuenta de demostración: activar la verificación en dos pasos bloquearía la demo para los próximos visitantes.',
        'requires_transaction_password' => 'Define primero tu contraseña de transacción: activar y desactivar la verificación en dos pasos son acciones sensibles.',
        'enabled' => 'Verificación en dos pasos activada. Desde tu próximo inicio de sesión, pediremos el código enviado a tu correo.',
        'disabled' => 'Verificación en dos pasos desactivada. El inicio de sesión vuelve a pedir solo la contraseña.',
    ],

    // Token de acción sensible (uso único, corta duración).
    'sensitive_action' => [
        'token_issued' => 'Acción sensible autorizada. Usa el token de inmediato — es de uso único.',
        'invalid_token' => 'Token de acción sensible ausente, inválido o expirado. Confirma la acción de nuevo.',
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
        'transaction_password_subtitle' => 'Se usa para autorizar acciones sensibles (retiros, claves de API). Debe ser diferente de la contraseña de acceso.',
        'current_transaction_password' => 'Contraseña de transacción actual',
        'new_transaction_password' => 'Nueva contraseña de transacción',
        'dashboard_title' => 'Panel',
        'dashboard_greeting' => 'Hola, :name',
        'dashboard_code' => 'Tu código de usuario',
    ],

];
