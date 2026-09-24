<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;

/**
 * O que o pacote de autenticação espera do model de usuário da aplicação.
 *
 * O model é da APLICAÇÃO (no starter, `App\Models\User`), não do pacote: é
 * ela que decide a tabela, as relações, o painel e as outras capacidades da
 * conta. O pacote trabalha com o model configurado em
 * `auth.providers.users.model` (ver Support\UserModel) e pede só isto:
 *
 * - ser um model Eloquent autenticável, com recuperação de senha e
 *   verificação de e-mail (os contratos do framework);
 * - responder às perguntas de conta que as regras do pacote fazem.
 *
 * A implementação pronta está na trait Models\Concerns\KitAuthenticatable
 * (status da conta, senha de transação, verificação em duas etapas, contas
 * protegidas, verificação de e-mail e recuperação de senha com os e-mails do
 * kit, idioma preferido). O model aplica a trait e implementa esta interface.
 *
 * Colunas que o pacote lê e grava (criadas pelas migrations dele sobre a
 * tabela `users` do esqueleto Laravel): `uuid`, `codigo_publico`, `status`,
 * `transaction_password`, `transaction_password_set_at`,
 * `two_factor_enabled_at`, `locale`, além de `email`, `password`,
 * `email_verified_at` e `remember_token`.
 */
interface AuthUser extends Authenticatable, CanResetPassword, MustVerifyEmail
{
    /**
     * A conta está ativa? Login, sessão web e API são deny-by-default: só
     * conta ativa opera.
     */
    public function isActive(): bool;

    /**
     * O usuário já definiu a senha de transação?
     */
    public function hasTransactionPassword(): bool;

    /**
     * Conta reservada por uma extensão de proteção (ver AccountProtection)?
     */
    public function isReservedAccount(): bool;
}
