<?php

declare(strict_types=1);

// Super admin strings (Filament — ADR-011). Always via __().

return [

    'nav' => [
        'group_management' => 'Management',
        'group_catalog' => 'Catalog',
        'group_security' => 'Security and audit',
        'group_system' => 'System',
    ],

    'users' => [
        'label' => 'User',
        'plural' => 'Users',
        'code' => 'Code',
        'admin' => 'Admin',
        'blocked' => 'Blocked',
        'active' => 'Active',
        'pending' => 'Pending',
        'block' => 'Block',
        'unblock' => 'Unblock',
        'block_heading' => 'Block user',
        'block_warning' => 'The user immediately loses access to the dashboard and API keys remain valid only if the account is active. Block ":email"?',
        'unblock_heading' => 'Unblock user',
        'blocked_success' => 'User blocked.',
        'unblocked_success' => 'User unblocked.',
        'demo_protected' => 'Demo account protected: demo users cannot be blocked, edited or deleted.',
        'transaction_password' => 'Transaction password set',
        'created_at' => 'Registered at',
    ],

    'api_keys' => [
        'label' => 'API Key',
        'plural' => 'API Keys',
        'owner' => 'Owner',
        'public_key' => 'Public key',
        'scopes' => 'Scopes',
        'last_used' => 'Last used',
        'expires_at' => 'Expiration',
        'never' => 'Never',
        'no_expiration' => 'No expiration',
        'revoke' => 'Revoke',
        'revoke_heading' => 'Revoke API key',
        'revoke_warning' => 'Revocation is irreversible and immediate. Revoke the key ":key" owned by ":owner"?',
        'revoked' => 'Key revoked.',
        'status_active' => 'Active',
        'status_revoked' => 'Revoked',
        'status_expired' => 'Expired',
        'status_expired_inactivity' => 'Expired by inactivity',
        'status_rotated' => 'Rotated',
    ],

    'projects' => [
        'label' => 'Project',
        'plural' => 'Projects',
        'owner' => 'Owner',
        'linked_keys' => 'Linked keys',
        'status_active' => 'Active',
        'status_archived' => 'Archived',
    ],

    'products' => [
        'label' => 'Product',
        'plural' => 'Products',
        'image' => 'Photo',
        'image_hint' => 'PNG, JPG or WebP up to 2 MB. No photo = placeholder.',
        'title' => 'Title',
        'price' => 'Price',
        'price_hint' => 'Brazilian format: 1.234,56. Stored as cents (never float).',
        'price_invalid' => 'Enter a valid amount (e.g. 1.234,56).',
        'description' => 'Description',
        'created_at' => 'Created at',
        'filter_price' => 'Price range',
        'price_up_to_100' => 'Up to R$ 100',
        'price_100_to_500' => 'R$ 100 to R$ 500',
        'price_above_500' => 'Above R$ 500',
        'deleted' => 'Product deleted.',
    ],

    'request_logs' => [
        'label' => 'Request log',
        'plural' => 'Request logs',
        'tenant' => 'Tenant',
        'orphan' => 'NO TENANT',
        'orphan_hint' => 'Logs without a tenant = possible attack/bypass attempt (ADR-010).',
        'endpoint' => 'Endpoint',
        'response_status' => 'HTTP',
        'duration' => 'Duration',
        'ip' => 'IP',
        'payload' => 'Payload (sanitized)',
        'error' => 'Error',
        'filter_status' => 'Status',
        'filter_tenant' => 'Tenant (UUID)',
        'filter_endpoint' => 'Endpoint contains',
        'filter_from' => 'From',
        'filter_until' => 'Until',
        'only_orphans' => 'Orphans only',
        'status_INICIADA' => 'STARTED',
        'status_CONCLUIDA' => 'FINISHED',
        'status_ERRO' => 'ERROR',
        'status_BLOQUEADA' => 'BLOCKED',
    ],

    'uploads' => [
        'label' => 'Upload',
        'plural' => 'Uploads',
        'original_name' => 'File',
        'mime' => 'Type',
        'size' => 'Size',
        'owner' => 'Owner',
        'tenant' => 'Tenant (UUID)',
        'open' => 'Open file',
        'open_hint' => 'Opens in a new tab with a short-lived signed URL.',
    ],

    'settings' => [
        'label' => 'Settings',
        'heading' => 'System settings',
        'subheading' => 'Operational adjustments editable via UI — no .env changes. Empty field = current .env value.',
        'saved' => 'Settings saved.',
        // Keys: fieldName of the Settings screen (config key dots become
        // "_" — dots would break __() resolution).
        'key_api_keys_inactivity_months' => 'Months of inactivity to expire keys',
        'key_api_keys_inactivity_warning_days' => 'Days of advance email warning',
        'key_uploads_types_image_max_kb' => 'Maximum image size (KB)',
        'key_uploads_types_pdf_max_kb' => 'Maximum PDF size (KB)',
        'key_security_rate_limit_api' => 'API rate limit (req/min)',
        'key_security_rate_limit_sensitive' => 'Sensitive routes rate limit (req/min)',
        'env_fallback' => '.env default: :value',
        'overridden' => 'Customized',
    ],

    'command' => [
        'user_not_found' => 'User not found.',
        'admin_granted' => 'Super admin access granted to :email.',
        'admin_removed' => 'Super admin access revoked from :email.',
    ],

];
