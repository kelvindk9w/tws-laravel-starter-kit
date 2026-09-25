<?php

declare(strict_types=1);

return [
    'personal_account' => 'Cuenta personal',

    'roles' => [
        'owner' => 'Propietario',
        'admin' => 'Administrador',
        'member' => 'Miembro',
    ],

    'context' => [
        'missing' => 'Consulta de :model sin cuenta actual. Los datos de cuenta solo se leen con una cuenta definida (sesión del panel o clave de API) o en modo sistema explícito: Accounts::asSystem(\'motivo\', fn () => ...) o Accounts::actingAs($cuenta, fn () => ...).',
        'system_write_without_account' => 'En modo sistema, :model debe guardarse con la cuenta informada (account_id).',
        'cross_account_write' => 'Se rechazó guardar :model en una cuenta que no es la actual.',
        'account_change' => 'La cuenta de :model no cambia después de guardada.',
    ],

    'ownership' => [
        'second_owner' => 'La cuenta ya tiene un propietario. La propiedad cambia por transferencia.',
        'role_change' => 'El rol de propietario no se concede ni se retira aquí. La propiedad cambia por transferencia.',
        'owner_leaves' => 'El propietario no sale de la cuenta. Transfiera la propiedad antes.',
        'moves_account' => 'Un vínculo de miembro no cambia de cuenta.',
    ],

    'members' => [
        'already_member' => 'Esta persona ya es miembro de la cuenta.',
    ],

    'deletion' => [
        'owner_has_members' => '{1} Esta persona es propietaria de una cuenta con otros miembros (:accounts). Transfiera la propiedad antes de eliminarla.|[2,*] Esta persona es propietaria de cuentas con otros miembros (:accounts). Transfiera la propiedad antes de eliminarla.',
    ],

    'authorization' => [
        'denied' => 'Su rol en esta cuenta no permite esta acción.',
        'not_member' => 'Usted no es miembro de esta cuenta.',
    ],
];
