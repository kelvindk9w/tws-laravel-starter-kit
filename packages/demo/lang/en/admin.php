<?php

declare(strict_types=1);

// Strings of the kit DEMO screens in /admin (sample product catalog,
// submissions inbox and the "Content & Operations" dashboard). The product's
// strings come from the twstec/kit-admin package (packages/admin/lang) and
// merge with these in the same `admin.*` group: on the same key, this file
// beats twstec/kit-admin, and the application's lang/ beats both.

return [

    'submissions' => [
        'label' => 'Form submission',
        'plural' => 'Form submissions',
        'nickname' => 'Nickname',
        'subject' => 'Subject',
        'message' => 'Message',
        'origin' => 'Origin',
        'origin_classic' => 'Classic (POST)',
        'origin_livewire' => 'Livewire (AJAX)',
        'origin_contact' => 'Contact (landing)',
        'sender_email' => 'Sender',
        'security' => 'Security',
        'accepted' => 'Accepted',
        'blocked_attack' => 'Blocked attack (:type)',
        'received_at' => 'Received at',
        'blocked_at' => 'Blocked at',
        'ip' => 'Source IP',
        'no_sender' => 'No sender (anonymous form)',
        'filter_blocked' => 'Blocked only',
        'view_evidence' => 'View evidence',
        // The listing never shows a payload: it shows the attack badge and a
        // neutralised excerpt, with this caption naming what is being read.
        'neutralized' => 'neutralised content',
        'metadata_section' => 'Metadata',
        'metadata_hint' => 'Where it came from, when it arrived and what the platform decided.',
        'content_section' => 'Message',
        'forensic_section' => 'Forensic evidence',
        'forensic_heading' => 'Content submitted by a third party',
        'forensic_warning' => 'Below is the full payload of the attempt, displayed escaped for auditing. This page never executes it — but do not copy it outside the panel.',
        'raw_nickname' => 'Nickname (full payload)',
        'raw_subject' => 'Subject (full payload)',
        'raw_message' => 'Message (full payload)',
    ],

    'products' => [
        'label' => 'Product',
        'plural' => 'Products',
        'image' => 'Photo',
        'image_hint' => 'PNG, JPG or WebP up to 2 MB. No photo = placeholder.',
        'title' => 'Title',
        'price' => 'Price',
        'price_hint' => 'Use the format 1.234,56. The minimum amount is R$ 0.01.',
        'price_invalid' => 'Enter a valid amount (e.g. 1.234,56).',
        'price_positive' => 'The amount must be greater than zero.',
        'description' => 'Description',
        'created_at' => 'Created at',
        'filter_price' => 'Price range',
        'price_up_to_100' => 'Up to R$ 100',
        'price_100_to_500' => 'R$ 100 to R$ 500',
        'price_above_500' => 'Above R$ 500',
        'deleted' => 'Product deleted.',
    ],

    'audit' => [
        'type_product' => 'Product',
        'type_form_submission' => 'Form submission',
    ],

    'dashboards' => [

        'common' => [
            'received' => 'Received',
        ],

        'overview' => [
            'latest_submissions' => 'Latest submissions',
            'submission_from' => 'From',
            'submission_state' => 'State',
        ],

        'content' => [
            'nav' => 'Content & Ops',
            'title' => 'Content & Ops',
            'subheading' => 'The day-to-day queue: catalogue, files that came in, messages received and whatever security blocked.',
            'products' => 'Products',
            'products_hint' => 'created in the period',
            'uploads' => 'Uploads',
            'uploads_hint' => 'accepted files',
            'storage' => 'Stored volume',
            'storage_hint' => 'added in the period',
            'blocked' => 'Blocked',
            'blocked_hint' => 'blocked attempts',
            'chart_intake_heading' => 'Intake per day',
            'chart_intake_uploads' => 'Uploads',
            'chart_intake_submissions' => 'Submissions',
            'chart_types_heading' => 'File types',
            'type_image' => 'Image',
            'type_pdf' => 'PDF',
            'type_document' => 'Document',
            'type_other' => 'Other',
            'latest_products' => 'Latest products',
            'product_title' => 'Product',
            'product_price' => 'Price',
            'inbox' => 'Inbox',
            'inbox_from' => 'From',
            'inbox_subject' => 'Subject',
            'inbox_state' => 'State',
        ],

    ],

    // "Demo" wording of the product's NEUTRAL protected-account messages
    // (twstec/kit-admin): with the demo installed, the protected account is the
    // demo account. Without it, the package's neutral text applies.
    'users' => [
        'account_protected' => 'Demo account protected: demo users cannot be blocked, edited or deleted.',
        'demo_protected' => 'Demo account protected: demo users cannot be blocked, edited or deleted.',
    ],

    'command' => [
        'account_protected' => 'Protected demo account: the command will not change :email.',
        'demo_protected' => 'Protected demo account: the command will not change :email.',
    ],

    'profile' => [
        'email_readonly_note' => 'The e-mail cannot be changed in this demo — changing the demo account login would break access for the next visitors.',
        'password_note' => 'Password change is unavailable in the demo. This section is a UI preview — no field is submitted.',
    ],

];
