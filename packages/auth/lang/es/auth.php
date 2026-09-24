<?php

declare(strict_types=1);

// Mensajes del dominio de autenticación (es) — los que devuelven las reglas del
// paquete twstec/kit-auth (rechazos, avisos, política de contraseña). Los
// textos de las PANTALLAS (títulos, etiquetas, botones) son del front. La
// aplicación gana: la misma clave en su lang/ prevalece sobre esta.

return [

    'failed' => 'Las credenciales ingresadas no coinciden con nuestros registros.',
    'throttle' => 'Demasiados intentos de inicio de sesión. Intenta de nuevo en :seconds segundos.',
    'account_inactive' => 'Esta cuenta no está activa. Contacta con soporte.',
    'registered' => 'Cuenta creada con éxito. ¡Bienvenido(a)!',
    'logged_out' => 'Sesión cerrada con éxito.',
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
        'demo_blocked' => 'No disponible en la cuenta de demostración: activar la verificación en dos pasos bloquearía la demo para los próximos visitantes.',
        'requires_transaction_password' => 'Define primero tu contraseña de transacción: activar y desactivar la verificación en dos pasos son acciones sensibles.',
    ],
    'sensitive_action' => [
        'token_issued' => 'Acción sensible autorizada. Usa el token de inmediato — es de uso único.',
        'invalid_token' => 'Token de acción sensible ausente, inválido o expirado. Confirma la acción de nuevo.',
    ],

];
