<?php

declare(strict_types=1);

// Outgoing webhooks (twstec/kit-webhooks) — domain, e-mail and starter screen
// texts (both interfaces use the same keys).

return [

    'status' => [
        'active' => 'Active',
        'disabled' => 'Disabled',
    ],

    'disabled_reason' => [
        'manual' => 'disabled manually',
        'failures' => 'disabled after consecutive failures',
    ],

    'delivery_status' => [
        'pending' => 'Pending',
        'delivering' => 'Sending',
        'retrying' => 'Retry scheduled',
        'succeeded' => 'Delivered',
        'failed' => 'Failed',
    ],

    'attempt_outcome' => [
        'succeeded' => 'Delivered',
        'failed' => 'Failed',
        'blocked' => 'Blocked',
    ],

    'events_all' => 'All events',

    'events' => [
        'webhook' => [
            'ping' => 'Test event',
        ],
    ],

    'fields' => [
        'name' => 'name',
        'url' => 'URL',
        'events' => 'events',
        'project' => 'project',
        'overlap' => 'overlap',
    ],

    'destination' => [
        'invalid_url' => 'Invalid URL. Use a full address, such as https://example.com/webhooks.',
        'scheme_not_allowed' => 'Only HTTPS addresses are accepted.',
        'credentials_in_url' => 'The URL cannot contain a username, a password or the @ character.',
        'invalid_host' => 'The server address is not valid.',
        'unresolvable' => 'This server could not be found (DNS).',
        'private_address' => 'This address points to an internal or reserved network and is not allowed.',
        'metadata_address' => 'This address belongs to an internal cloud service and is not allowed.',
    ],

    'validation' => [
        'unknown_event' => 'Unknown event.',
        'events_required' => 'Choose at least one event.',
        'project_invalid' => 'Project not found in this account.',
    ],

    'errors' => [
        'endpoint_disabled' => 'The endpoint is disabled. Enable it first.',
        'redirect_not_followed' => 'The receiver answered with a redirect (HTTP :status), which is not followed.',
        'http_status' => 'The receiver answered HTTP :status.',
        'connection_mismatch' => 'The connection did not go to the verified address and was aborted.',
        'sensitive_required' => 'Confirm the action with your transaction password and the code sent by e-mail.',
        'too_many' => 'Too many requests. Try again in :seconds seconds.',
        'in_progress' => 'This delivery is already being sent.',
    ],

    'mail' => [
        'endpoint_disabled' => [
            'subject' => 'Webhook endpoint disabled — :platform',
            'preheader' => 'Disabled after :failures consecutive failed attempts.',
            'heading' => 'Webhook endpoint disabled',
            'intro' => 'We stopped sending events to this endpoint after :failures consecutive failed attempts.',
            'account_label' => 'Account',
            'name_label' => 'Endpoint',
            'host_label' => 'Server',
            'action' => 'Check that the receiver is up and answering 2xx, then enable the endpoint again on the webhooks screen. Failed deliveries can be resent from there.',
            'cta' => 'Open webhooks',
        ],
    ],

    'console' => [
        'requeued' => ':count webhook delivery(ies) queued.',
        'pruned' => ':count old webhook event(s) deleted.',
    ],

    'ui' => [
        'title' => 'Webhooks',
        'subtitle' => 'Notify other systems when something happens in this account. Every request is signed with the endpoint secret.',
        'new' => 'New endpoint',
        'empty' => 'No endpoints yet.',
        'empty_hint' => 'Register the URL that will receive the events. The signing secret is generated here and shown once.',
        'name' => 'Name',
        'url' => 'Destination URL',
        'url_hint' => 'Public address, with HTTPS.',
        'events' => 'Events',
        'events_hint' => 'The events this endpoint receives.',
        'project' => 'Project',
        'project_all' => 'Whole account (all projects)',
        'save' => 'Save',
        'cancel' => 'Cancel',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'delete_confirm' => 'Delete this endpoint? Its deliveries and log are removed too.',
        'created' => 'Endpoint created.',
        'updated' => 'Endpoint updated.',
        'deleted' => 'Endpoint deleted.',
        'enable' => 'Enable',
        'disable' => 'Disable',
        'enabled' => 'Endpoint enabled.',
        'disabled' => 'Endpoint disabled.',
        'send_test' => 'Send test',
        'test_sent' => 'Test event queued.',
        'resend' => 'Resend',
        'resent' => 'Resend requested.',
        'reveal' => 'Reveal secret',
        'rotate' => 'Rotate secret',
        'rotate_overlap' => 'Previous secret overlap (minutes)',
        'rotate_hint' => 'During the overlap every request carries both signatures: change the secret on the receiver without losing events. Zero ends the previous one now.',
        'secret_title' => 'Signing secret',
        'secret_once' => 'Copy and store it now: it will not be shown again.',
        'secret_saved' => 'I have stored it',
        'copy' => 'Copy',
        'copied' => 'Copied',
        'deliveries' => 'Deliveries',
        'deliveries_empty' => 'No deliveries yet.',
        'show_deliveries' => 'Show deliveries',
        'all_endpoints' => 'All endpoints',
        'event' => 'Event',
        'status' => 'Status',
        'attempts' => 'Attempts',
        'attempt' => 'Attempt :number',
        'manual' => 'manual resend',
        'response' => 'Response',
        'duration' => ':ms ms',
        'next_attempt' => 'Next attempt: :date',
        'last_success' => 'Last delivery: :date',
        'failures' => ':count consecutive failure(s)',
        'disabled_by_failures' => 'Disabled after :count consecutive failures. Fix the receiver and enable it again.',
        'previous_secret_until' => 'Previous secret still accepted until :date.',
        'sensitive_requires_password' => 'Set your transaction password on your profile first: creating, changing and handling the secret of an endpoint require confirmation.',
        'read_only' => 'Only the account owner and administrators manage webhooks.',
        'signature_help' => 'Every request carries the X-Webhook-Signature header (t=timestamp,v1=HMAC-SHA256 of "timestamp.body"). Verify it with the secret, reject timestamps older than 5 minutes and deduplicate by the event id.',
        'created_at' => 'Created on :date',
    ],

];
