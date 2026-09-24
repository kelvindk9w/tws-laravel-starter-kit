<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Models\Concerns;

/**
 * Tudo o que o pacote de autenticação precisa do model de usuário, numa
 * trait só. O model da aplicação aplica esta trait e implementa
 * Twstec\Kit\Auth\Contracts\AuthUser (e HasLocalePreference, para os e-mails
 * saírem no idioma da conta):
 *
 *     class User extends Authenticatable implements AuthUser, HasLocalePreference
 *     {
 *         use HasPublicCode, HasUuids, KitAuthenticatable, Notifiable, RoutesByUuid;
 *     }
 *
 * Cada parte também existe sozinha, para quem preferir compor à mão.
 */
trait KitAuthenticatable
{
    use HasAccountStatus;
    use HasPreferredLocale;
    use HasTransactionPassword;
    use HasTwoFactor;
    use ProtectsAccounts;
    use SendsPasswordResetNotification;
    use VerifiesEmail;
}
