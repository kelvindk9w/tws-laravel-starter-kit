<?php

declare(strict_types=1);

// User panel texts (en): navigation, home, profile, sensitive actions and
// notifications — the screens of the React and Livewire starters. Every
// string goes through __(). The application wins: the same key in its lang/
// takes precedence over this one (see docs/personalizacao.md).

return [

    // Navigation / layout.
    'nav' => [
        'dashboard' => 'Dashboard',
        'api_keys' => 'API Keys',
        'projects' => 'Projects',
        'webhooks' => 'Webhooks',
        'notifications' => 'Notifications',
        'profile' => 'Profile',
        'transaction_password' => 'Transaction password',
        'account' => 'Account & members',
        'toggle_theme' => 'Toggle light/dark theme',
        'menu' => 'Menu',
        'open_menu' => 'Open navigation menu',

        // Grupos do menu lateral "Minha conta" (<x-side-nav>).
        'groups' => [
            'overview' => 'Overview',
            'development' => 'Development',
            'account' => 'Account',
        ],
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
        'copy' => 'Copy',
        'copied' => 'Copied!',
        'more_actions' => 'More actions',
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

        // Métricas e tráfego real da conta (request_logs).
        'summary_requests' => 'Requests (:days days)',
        'summary_last_key_use' => 'Last key use',
        'last_key_use_empty' => 'No authenticated call yet.',
        'chart_title' => 'Requests per day (:days days)',
        'chart_hint' => 'Every authenticated API call from your account, grouped by day.',
        'chart_series' => 'Requests',
        'chart_empty_title' => 'No requests in this period',
        'chart_empty_description' => 'As soon as your integration starts calling the API, traffic shows up here.',
        'recent_calls_title' => 'Latest API calls',
        'recent_calls_hint' => 'The 5 most recent for this account.',
        'recent_calls_empty_title' => 'Your API has not received any calls yet',
        'recent_calls_empty_description' => 'Create an API key and make the first request to see the audit trail here.',
        'call_endpoint' => 'Endpoint',
        'call_when' => 'When',

        // Without the accounts package (twstec/kit-accounts): no keys,
        // projects or API — the panel opens with the account shortcuts.
        'essentials_title' => 'Your account',
        'essentials_hint' => 'The settings that protect and personalize your access.',
        'essentials_profile' => 'Name, language, login password and two-step verification.',
        'essentials_transaction_password' => 'Authorizes the sensitive actions of your account.',
        'essentials_notifications' => 'Choose which emails you want to receive.',
    ],

    // Profile.
    'profile' => [
        'title' => 'Profile',
        'data_heading' => 'Your details',
        'email_readonly' => 'The email is the account access key and cannot be changed here.',
        'locale_label' => 'Language',
        'locale_hint' => 'Used in the interface and in the emails you receive.',
        'theme_heading' => 'Appearance',
        'theme_hint' => 'System follows your device preference. The choice is saved on this device and on your account.',
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
        'subtitle' => 'Your data, appearance, photo and the two account passwords.',
        'password_hint' => 'Requires the current password. New password: :rules.',
        'choose_file' => 'Choose image',
        'avatar_uploading' => 'Uploading the image…',
        // Cada botão nomeia o próprio escopo (a tela salva 4 coisas).
        'save_data' => 'Save details',
        'save_avatar' => 'Save photo',
        'save_password' => 'Change login password',
        'save_transaction_password' => 'Change transaction password',
        'two_factor_heading' => 'Two-step verification',
        'two_factor_hint' => 'When signing in, besides your password, we ask for a code sent to :email. Someone who finds out your password still can\'t get in.',
        'two_factor_on' => 'On',
        'two_factor_off' => 'Off',
        'two_factor_enable' => 'Turn on two-step verification',
        'two_factor_disable' => 'Turn off two-step verification',
        'two_factor_recovery' => 'The code always goes to the account email: without access to it, there is no way to finish signing in. Keep that email secure.',
        'two_factor_confirm_enable' => 'To turn on two-step verification, confirm with your transaction password and the code sent by email.',
        'two_factor_confirm_disable' => 'To turn off two-step verification, confirm with your transaction password and the code sent by email.',
        'two_factor_required' => 'Required on this installation',
        'two_factor_grace' => 'Two-step verification is required on this installation. Set it up by :date — after that, the panel only opens with it.',
        'two_factor_grace_action' => 'Set up now',
    ],

    // Sensitive action confirmation (shared panel modal: transaction
    // password → code by email → execute).
    'sensitive' => [
        'heading' => 'Security confirmation',
        'password_hint' => 'Enter your transaction password to receive a verification code by email.',
        'send_code' => 'Send code by email',
        'code_hint' => 'We sent a 6-digit code to your email. It expires in a few minutes.',
        'code' => 'Verification code',
        'confirm' => 'Confirm and execute',
        'resend_in' => 'Resend in :seconds s',
    ],

    // Notification preferences (skeleton).
    'notifications' => [
        'title' => 'Notifications',
        'subtitle' => 'Choose which emails you want to receive. Security alerts are always sent.',
        'saved' => 'Notification preferences saved.',
        'pref_order_confirmed' => 'Order confirmed',
        'pref_order_confirmed_hint' => 'Email notice when one of your orders is confirmed.',
        'pref_final_customer_receipt' => 'Final customer receipt',
        'pref_final_customer_receipt_hint' => 'Your final customer receives a confirmation email with your brand.',
        'pref_api_key_events' => 'API key events',
        'pref_api_key_events_hint' => 'Creation, rotation and inactivity expiration warnings.',
        'pref_security_alerts' => 'Security alerts',
        'pref_security_alerts_hint' => 'Sign-ins and sensitive actions. Always on — they cannot be disabled.',
        'locked' => 'Always on',
        'emails_heading' => 'Emails you receive',
        'save' => 'Save preferences',
    ],

];
