<?php

declare(strict_types=1);

// User dashboard strings (Livewire — ADR-005/011).
// Every displayed string goes through __() — ADR-007. NEVER hardcoded text in views.

return [

    // Navigation / layout.
    'nav' => [
        'dashboard' => 'Dashboard',
        'api_keys' => 'API Keys',
        'projects' => 'Projects',
        'notifications' => 'Notifications',
        'profile' => 'Profile',
        'toggle_theme' => 'Toggle light/dark theme',
    ],

    'common' => [
        'save' => 'Save',
        'cancel' => 'Cancel',
        'confirm' => 'Confirm',
        'close' => 'Close',
        'create' => 'Create',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'actions' => 'Actions',
        'status' => 'Status',
        'name' => 'Name',
        'created_at' => 'Created at',
        'never' => 'Never',
        'none' => 'None',
        'saved' => 'Saved successfully.',
        'optional' => 'optional',
    ],

    // Dashboard.
    'dashboard' => [
        'title' => 'Dashboard',
        'greeting' => 'Hello, :name',
        'user_code' => 'Your user code',
        'summary_keys' => 'Active API keys',
        'summary_projects' => 'Projects',
        'quick_actions' => 'Quick actions',
        'new_api_key' => 'Create API key',
        'new_project' => 'Create project',
        'manage_profile' => 'My profile',
    ],

    // Profile.
    'profile' => [
        'title' => 'Profile',
        'data_heading' => 'Your details',
        'email_readonly' => 'The email is the account access key and cannot be changed here.',
        'locale_label' => 'Language',
        'locale_hint' => 'Used in the interface and in the emails you receive.',
        'avatar_heading' => 'Profile photo',
        'avatar_hint' => 'JPG, PNG or WebP image. The file is validated by content and reprocessed before being saved.',
        'avatar_updated' => 'Profile photo updated.',
        'password_heading' => 'Login password',
        'current_password' => 'Current password',
        'password_updated' => 'Login password updated successfully.',
        'current_password_invalid' => 'The provided current password is incorrect.',
        'transaction_password_heading' => 'Transaction password',
        'transaction_password_hint' => 'Used to authorize sensitive actions (API key creation/rotation). It must be different from the login password.',
        'transaction_password_set' => 'Set',
        'transaction_password_not_set' => 'Not set — define it to be able to create API keys.',
    ],

    // Projects (ADR-005 — organizational layer, name only).
    'projects' => [
        'title' => 'Projects',
        'subtitle' => 'Projects organize your account: link API keys to them to separate data and views.',
        'new' => 'New project',
        'edit' => 'Edit project',
        'empty' => 'You have no projects yet. Create the first one on this screen.',
        'delete_title' => 'Delete project',
        'delete_warning' => 'Delete the project ":name"? API keys linked to it will see the whole account.',
        'created' => 'Project created successfully.',
        'updated' => 'Project updated successfully.',
        'deleted' => 'Project deleted successfully.',
        'status_active' => 'Active',
        'status_archived' => 'Archived',
        'linked_keys' => ':count linked key(s)',
    ],

    // API keys (ADR-006 — the most important screen).
    'api_keys' => [
        'title' => 'API Keys',
        'subtitle' => 'Public + secret key pairs for your integration. The secret is shown only ONCE.',
        'new' => 'New key',
        'empty' => 'You have no API keys yet. Create the first one on this screen.',
        'public_key' => 'Public key',
        'last_used' => 'Last used',
        'expires_at' => 'Expiration',
        'no_expiration' => 'No expiration',
        'expires_hint' => 'Empty = no expiration. The system never imposes a deadline (ADR-006).',
        'grace_hint' => 'The old key can die immediately or stay valid for a period, avoiding downtime during the swap.',

        'scopes_heading' => 'Permissions (scopes)',
        'scopes_all' => 'All permissions',
        'scopes_all_hint' => 'Default: the key can do everything. Turn it off to restrict by resource/action (least privilege).',
        'scopes_hint' => 'Select only what the integration needs.',

        'projects_heading' => 'Linked projects',
        'projects_hint' => 'No link = the key sees the whole account. Linked = restricted to the checked projects.',
        'projects_empty' => 'No projects yet — the key will see the whole account.',
        'whole_account' => 'Whole account',
        'edit_projects' => 'Projects',

        'create_heading' => 'Create API key',
        'rotate' => 'Rotate',
        'rotate_title' => 'Rotate key',
        'rotate_warning' => 'A new secret key will be generated. Choose when the current key stops working.',
        'grace_immediate' => 'Immediately',
        'grace_1h' => 'After 1 hour',
        'grace_24h' => 'After 24 hours',
        'grace_7d' => 'After 7 days',
        'revoke' => 'Revoke',
        'revoke_title' => 'Revoke key',
        'revoke_warning' => 'Revoke the key ":name"? The action is irreversible: integrations using this key stop immediately.',
        'revoked' => 'Key revoked successfully.',
        'projects_saved' => 'Project links updated.',

        // One-time secret display screen (ADR-006).
        'secret_heading' => 'Store your secret key',
        'secret_warning' => 'This is the ONLY time the secret key is displayed. There is no recovery: if you lose it, rotate it or create a new one.',
        'copy' => 'Copy',
        'copied' => 'Copied!',
        'secret_done' => 'I have safely stored the key',

        // Sensitive action flow (transaction password + email code).
        'sensitive_heading' => 'Security confirmation',
        'sensitive_password_hint' => 'Enter your transaction password to receive a verification code by email.',
        'sensitive_send_code' => 'Send code by email',
        'sensitive_code_hint' => 'We sent a 6-digit code to your email. It expires in a few minutes.',
        'sensitive_code' => 'Verification code',
        'sensitive_confirm' => 'Confirm and execute',
        'sensitive_resend_in' => 'Resend in :seconds s',
        'sensitive_requires_password' => 'Set your transaction password in your Profile before creating keys.',

        'status_active' => 'Active',
        'status_revoked' => 'Revoked',
        'status_expired' => 'Expired',
        'status_expired_inactivity' => 'Expired by inactivity',
        'status_rotated' => 'Rotated',
        'status_grace' => 'Rotated (in transition)',

        'scope_resource_api-keys' => 'API keys',
        'scope_resource_projects' => 'Projects',
        'scope_resource_uploads' => 'Uploads',
        'scope_action_read' => 'read',
        'scope_action_create' => 'create',
        'scope_action_update' => 'update',
        'scope_action_delete' => 'delete',
        'scope_action_rotate' => 'rotate',
        'scope_action_revoke' => 'revoke',
        'scope_action_assign' => 'assign projects',
    ],

    // Notification preferences (skeleton — ADR-009).
    'notifications' => [
        'title' => 'Notifications',
        'subtitle' => 'Choose which emails you want to receive. Security alerts are always sent.',
        'saved' => 'Notification preferences saved.',
        'pref_payment_confirmed' => 'Payment confirmed',
        'pref_payment_confirmed_hint' => 'Email notice when one of your charges is paid.',
        'pref_final_customer_receipt' => 'Final customer receipt',
        'pref_final_customer_receipt_hint' => 'Your final customer receives a confirmation email with your brand.',
        'pref_api_key_events' => 'API key events',
        'pref_api_key_events_hint' => 'Creation, rotation and inactivity expiration warnings.',
        'pref_security_alerts' => 'Security alerts',
        'pref_security_alerts_hint' => 'Sign-ins and sensitive actions. Always on — they cannot be disabled.',
        'locked' => 'Always on',
    ],

];
