<?php

declare(strict_types=1);

namespace Twstec\Kit\Auth\Contracts;

/**
 * QUEM é administrador, para a regra do segundo fator obrigatório
 * (`AUTH_TWO_FACTOR_REQUIRED=admins` — Support\TwoFactorRequirement).
 *
 * O pacote de autenticação não conhece o painel de administração; quem
 * conhece responde. Sem implementação registrada, vale a padrão
 * (Support\AdministratorFlag: a coluna `is_admin` da conta). O pacote
 * twstec/kit-admin registra a dele: quem entra no /admin (`is_admin`) OU tem
 * qualquer papel do painel (`admin_role`), conta ativa ou não — o critério
 * erra para o lado de exigir.
 */
interface IdentifiesAdministrators
{
    public function isAdministrator(AuthUser $user): bool;
}
