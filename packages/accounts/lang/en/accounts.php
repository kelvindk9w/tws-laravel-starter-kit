<?php

declare(strict_types=1);

return [
    'personal_account' => 'Personal account',

    'roles' => [
        'owner' => 'Owner',
        'admin' => 'Administrator',
        'member' => 'Member',
    ],

    'context' => [
        'missing' => ':model queried without a current account. Account data is only read with an account in context (panel session or API key) or in explicit system mode: Accounts::asSystem(\'reason\', fn () => ...) or Accounts::actingAs($account, fn () => ...).',
        'system_write_without_account' => 'In system mode, :model must be saved with an explicit account (account_id).',
        'cross_account_write' => 'Saving :model into an account other than the current one was refused.',
        'account_change' => 'The account of :model cannot change once saved.',
    ],

    'ownership' => [
        'second_owner' => 'The account already has an owner. Ownership changes by transfer.',
        'role_change' => 'The owner role is neither granted nor removed here. Ownership changes by transfer.',
        'owner_leaves' => 'The owner cannot leave the account. Transfer ownership first.',
        'moves_account' => 'A membership never moves to another account.',
    ],

    'members' => [
        'already_member' => 'This person is already a member of the account.',
    ],

    'deletion' => [
        'owner_has_members' => '{1} This person owns an account that has other members (:accounts). Transfer ownership before deleting them.|[2,*] This person owns accounts that have other members (:accounts). Transfer ownership before deleting them.',
    ],

    'authorization' => [
        'denied' => 'Your role in this account does not allow this action.',
        'not_member' => 'You are not a member of this account.',
    ],
];
