<?php

declare(strict_types=1);

// Component showcase (/ui) strings — en (ADR-007). NEVER hardcoded text in views.

return [

    'title' => 'UI Components',
    'subtitle' => 'Living documentation of the kit’s Blade components. Copy and use: <x-button>, <x-alert> and friends.',

    'snippets' => [
        'copy' => 'Copy',
        'copied' => 'Copied!',
        'copied_toast' => 'Snippet copied to the clipboard.',
    ],

    'categories' => [
        'buttons' => 'Buttons',
        'alerts' => 'Alerts',
        'badges' => 'Badges',
        'forms' => 'Forms',
        'cards' => 'Cards',
        'modal' => 'Modal',
        'toast' => 'Toast',
        'empty_state' => 'Empty state',
        'loading' => 'Loading',
    ],

    'buttons' => [
        'variants' => 'Variants',
        'sizes' => 'Sizes',
        'states' => 'States',
        'primary' => 'Primary',
        'secondary' => 'Secondary',
        'ghost' => 'Ghost',
        'danger' => 'Danger',
        'small' => 'Small',
        'medium' => 'Medium',
        'large' => 'Large',
        'disabled' => 'Disabled',
        'loading' => 'Loading',
        'as_link' => 'As link',
    ],

    'alerts' => [
        'success_title' => 'All set',
        'success' => 'Your change was saved successfully.',
        'warning_title' => 'Attention',
        'warning' => 'Your API key expires in 7 days due to inactivity.',
        'error_title' => 'Operation failed',
        'error' => 'The request could not be processed. Please try again.',
        'info_title' => 'Information',
        'info' => 'A new platform version will be available soon.',
    ],

    'badges' => [
        'active' => 'Active',
        'pending' => 'Pending',
        'blocked' => 'Blocked',
        'beta' => 'Beta',
        'brand' => 'Brand',
        'neutral' => 'Neutral',
    ],

    'forms' => [
        'text_label' => 'Project name',
        'text_placeholder' => 'My store',
        'text_hint' => 'Can be changed later.',
        'with_error_label' => 'Email',
        'with_error_message' => 'Enter a valid email address.',
        'disabled_label' => 'Disabled field',
        'select_label' => 'Plan',
        'select_option_1' => 'Free',
        'select_option_2' => 'Pro',
        'select_option_3' => 'Enterprise',
        'checkbox' => 'I accept the terms of use',
        'checkbox_checked' => 'Receive news by email',
        'toggle' => 'Email notifications',
        'toggle_on' => 'Mandatory 2FA',
        'usage' => 'Usage: <x-input>, <x-select>, <x-checkbox>, <x-toggle> — label, hint and error state built in.',
    ],

    'cards' => [
        'simple_title' => 'Simple card',
        'simple_body' => 'Card body with supporting text. Use it to group related information.',
        'footer_title' => 'Card with footer',
        'footer_body' => 'The footer is an optional slot, ideal for actions.',
        'footer_action' => 'Save',
    ],

    'modal' => [
        'open' => 'Open modal',
        'title' => 'Confirm action',
        'body' => 'This modal is a real Blade component (<x-modal>): it opens via data-modal-open and closes via backdrop, button or Esc. The animation is a CSS transition (interruptible) and the JS lives in resources/js/ui.js, served by Vite.',
        'cancel' => 'Cancel',
        'confirm' => 'Confirm',
    ],

    'toast' => [
        'demo_button' => 'Trigger toast',
        'demo_message' => 'Preferences saved successfully.',
        'flash_note' => 'For session flash, render <x-toast> with session(\'status\') in your layout. The behavior (open, auto-hide) lives in resources/js/ui.js.',
    ],

    'clipboard_toast' => 'Snippet copied to the clipboard.',

    'empty_state' => [
        'title' => 'No projects yet',
        'description' => 'Projects group your API keys and uploads. Create the first one to get started.',
        'action' => 'Create project',
    ],

    'loading' => [
        'sizes' => 'Sizes',
        'in_button' => 'In buttons',
        'saving' => 'Saving…',
    ],

    'components' => [
        'spinner_label' => 'Loading',
    ],

];
