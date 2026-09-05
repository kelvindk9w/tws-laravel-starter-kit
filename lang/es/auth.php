<?php

declare(strict_types=1);

// Cadenas de autenticación (es). Toda cadena de UI pasa por __() — ADR-007.

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

    // Contraseña de transacción (ADR-006 — separada de la de acceso).
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

    // Token de acción sensible (uso único, corta duración — ADR-006/010).
    'sensitive_action' => [
        'token_issued' => 'Acción sensible autorizada. Usa el token de inmediato — es de uso único.',
        'invalid_token' => 'Token de acción sensible ausente, inválido o expirado. Confirma la acción de nuevo.',
    ],

    // Cadenas de interfaz (formularios/pantallas de autenticación).
    // Blindaje de las cuentas de demostración (DemoAccountGuard): mensajes de
    // quien intenta tocarlas fuera de la interfaz del super admin — tinker,
    // comando artisan, job. Ver el README, "Las cuentas demo son intocables".
    'demo_account' => [
        'update_blocked' => 'Cuenta de demostración protegida: ":email" no acepta cambios en :fields. Nombre, foto, idioma y tema siguen editables.',
        'delete_blocked' => 'Cuenta de demostración protegida: ":email" no se puede eliminar.',
    ],

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

        // Login demo: solo cuando config('ui.demo_login.enabled') — local/dev.
        'demo_notice' => 'Entorno de demostración: las credenciales ya vienen completadas, solo entra.',
        'demo_credentials' => 'Usuario demo',
    ],

];
