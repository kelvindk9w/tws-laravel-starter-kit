<?php

declare(strict_types=1);

use Illuminate\Notifications\Messages\MailMessage;
use Twstec\Kit\Auth\Contracts\AuthUser;
use Twstec\Kit\Auth\Enums\VerificationPurpose;
use Twstec\Kit\Auth\Mail\VerificationCodeMail;
use Twstec\Kit\Auth\Notifications\ResetPasswordNotification;
use Twstec\Kit\Auth\Notifications\VerifyEmailNotification;
use Twstec\Kit\Auth\Support\UserModel;
use Twstec\Kit\Foundation\Mail\MailPreview;

// =============================================================================
// E-mails do módulo de autenticação na galeria /mail-preview (ver
// Twstec\Kit\Foundation\Mail\MailPreview). Carregado via composer.json → autoload.files.
//
// Os modelos de exemplo NÃO são salvos: é pré-visualização, não seed.
// =============================================================================

$sampleUser = static function (string $locale): AuthUser {
    $user = UserModel::make();
    $user->name = 'Marina Duarte';
    $user->email = 'marina.duarte@example.com';
    $user->locale = $locale;

    return $user;
};

// A verificação de e-mail é Notification. O usuário de exemplo ganha um uuid
// fixo porque o link assinado é montado com ele.
MailPreview::register('email-verification', static function (string $locale) use ($sampleUser): MailMessage {
    $user = $sampleUser($locale);
    $user->uuid = '01990000-0000-7000-8000-000000000000';

    return (new VerifyEmailNotification)->toMail($user);
});

MailPreview::register('verification-code', static fn (): VerificationCodeMail => new VerificationCodeMail('482913', VerificationPurpose::SensitiveAction));

MailPreview::register('login-code', static fn (): VerificationCodeMail => new VerificationCodeMail('570264', VerificationPurpose::LoginChallenge));

// A recuperação de senha também é Notification: o corpo é a mesma view do
// layout único, montada pelo KitMailMessage.
MailPreview::register('password-reset', static fn (string $locale): MailMessage => (new ResetPasswordNotification(str_repeat('a1b2c3d4', 8)))->toMail($sampleUser($locale)));
