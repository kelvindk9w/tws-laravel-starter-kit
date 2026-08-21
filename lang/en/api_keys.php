<?php

declare(strict_types=1);

// API Keys engine + Tenancy strings (en — ADR-007). Always via __().

return [

    // API authentication (ResolveTenant — ADR-010).
    'auth' => [
        // SINGLE, deliberately generic message: it does not reveal whether the
        // public key exists, whether the secret was wrong or whether the key
        // expired (non-oracular — security checklist).
        'invalid' => 'Missing, invalid or expired API credentials.',
    ],

    // Scope authorization (scope:resource:action middleware — ADR-006).
    'scopes' => [
        'denied' => 'This API key is not allowed for the ":scope" scope.',
        'invalid_format' => 'Each scope must be in the "resource:action" format (e.g.: customers:read, pix:create, withdrawals:*).',
    ],

    // Key engine operations.
    'keys' => [
        'created' => 'API key created. Store the secret key now — it will not be shown again.',
        'rotated' => 'Key rotated. Store the new secret key now — it will not be shown again.',
        'revoked' => 'API key revoked successfully.',
        'not_rotatable' => 'Only active keys can be rotated.',
        'projects_synced' => 'Projects linked to the key successfully.',
    ],

    // Projects (organizational layer — ADR-005).
    'projects' => [
        'created' => 'Project created successfully.',
        'updated' => 'Project updated successfully.',
        'deleted' => 'Project removed successfully.',
        'invalid' => 'One or more of the given projects do not exist in your account.',
    ],

];
