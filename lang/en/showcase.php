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
        'theme' => 'Theme',
        'buttons' => 'Buttons',
        'alerts' => 'Alerts',
        'badges' => 'Badges',
        'forms' => 'Forms',
        'cards' => 'Cards',
        'modal' => 'Modal',
        'toast' => 'Toast',
        'empty_state' => 'Empty state',
        'loading' => 'Loading',
        'form_patterns' => 'Form patterns',
    ],

    'theme_tokens' => [
        'guide' => 'The visual identity lives in ONE file: resources/css/theme.css (Tailwind 4 @theme block: colors, fonts, radii, motion) + config/platform.php fed by the .env (name, logo, primary color). To rebrand: edit both and the whole kit — landing, dashboard, admin and emails — reflects it.',
        'brand' => 'Brand color',
        'brand_hint' => 'PLATFORM_PRIMARY_COLOR in the .env becomes --brand in the <head> (no rebuild) and --color-brand in utilities (bg-brand, text-brand).',
        'fonts' => 'Typography',
        'font_display_sample' => 'Display (Space Grotesk) — headings',
        'font_body_sample' => 'Body (Instrument Sans) — text and UI',
        'fonts_hint' => '--font-display and --font-sans in theme.css; font-display / font-sans classes.',
        'radii' => 'Corner radii',
        'radii_hint' => '--radius-lg / --radius-xl in theme.css — the language uses rounded-lg and rounded-xl.',
        'motion' => 'Motion',
        'motion_hint' => 'Strong --ease-out / --ease-in-out; UI under 300ms; everything honors prefers-reduced-motion.',
        'modes' => 'Light, dark or system',
        'modes_hint' => '3-state toggle at the top of this page. Default = OS preference, no flash on load; choice persisted on the device and on the account.',
    ],

    'buttons' => [
        'guide' => 'When to use: the block’s main action = primary (at most one per block); supporting = outline or secondary; quiet navigation = ghost; destructive = danger. A11y: visible focus and press feedback on all; on submit, disable and show the spinner inside the button.',
        'variants' => 'Variants',
        'sizes' => 'Sizes',
        'states' => 'States',
        'primary' => 'Primary',
        'secondary' => 'Secondary',
        'outline' => 'Outline',
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
        'guide' => 'When to use: persistent feedback in the content context (does not auto-dismiss — use a toast for that). A11y: role="alert" makes screen readers announce it immediately.',
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
        'guide' => 'Short, scannable statuses. Do not use as a button or for long text; brand for brand highlights, neutral as the default.',
        'active' => 'Active',
        'pending' => 'Pending',
        'blocked' => 'Blocked',
        'beta' => 'Beta',
        'brand' => 'Brand',
        'neutral' => 'Neutral',
    ],

    'forms' => [
        'guide' => 'Label always visible (never a placeholder as label), hint for the expected format and error next to the field. type="password" ships the built-in eye button (reveal/hide).',
        'password_label' => 'Password',
        'password_hint' => 'Click the eye to reveal.',
        'message_label' => 'Message',
        'message_placeholder' => 'Tell the context in a few lines…',
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
        'guide' => 'Groups related content; the footer is an optional slot for actions. Avoid nesting cards.',
        'simple_title' => 'Simple card',
        'simple_body' => 'Card body with supporting text. Use it to group related information.',
        'footer_title' => 'Card with footer',
        'footer_body' => 'The footer is an optional slot, ideal for actions.',
        'footer_action' => 'Save',
    ],

    'modal' => [
        'guide' => 'Confirmations and short flows without leaving the screen. Closes via Esc, backdrop or button; the entrance is an interruptible transition (scale 0.95 + fade).',
        'open' => 'Open modal',
        'title' => 'Confirm action',
        'body' => 'This modal is a real Blade component (<x-modal>): it opens via data-modal-open and closes via backdrop, button or Esc. The animation is a CSS transition (interruptible) and the JS lives in resources/js/ui.js, served by Vite.',
        'cancel' => 'Cancel',
        'confirm' => 'Confirm',
    ],

    'toast' => [
        'guide' => 'Ephemeral feedback for a completed action — auto-dismisses. Do not use for errors that require a user decision (use <x-alert>).',
        'demo_button' => 'Trigger toast',
        'demo_message' => 'Preferences saved successfully.',
        'flash_note' => 'For session flash, render <x-toast> with session(\'status\') in your layout. The behavior (open, auto-hide) lives in resources/js/ui.js.',
    ],

    'clipboard_toast' => 'Snippet copied to the clipboard.',

    'empty_state' => [
        'guide' => 'First experience of an empty area: say what it is, why it matters and what the next action is.',
        'title' => 'No projects yet',
        'description' => 'Projects group your API keys and uploads. Create the first one to get started.',
        'action' => 'Create project',
    ],

    'loading' => [
        'guide' => 'Waiting hierarchy: spinner inside the button for submissions; skeleton for incoming content (lists, cards); fullscreen overlay is the LAST resort.',
        'sizes' => 'Sizes',
        'in_button' => 'In buttons',
        'saving' => 'Saving…',
        'skeleton_heading' => 'Skeleton (incoming content)',
        'skeleton_hint' => 'Shows the STRUCTURE that is coming — feels faster than a spinner. Subtle shimmer, disabled with prefers-reduced-motion. In the dashboard it pairs with wire:loading (see Projects).',
        'overlay_heading' => 'Fullscreen overlay (restricted use)',
        'overlay_restriction' => 'ONLY for the initial load of an entire area or long, rare actions (e.g.: generating a heavy report). It blocks the whole screen — for everything else use skeleton or the button spinner.',
        'overlay_demo' => 'Preview for 1.5 s',
    ],

    'form_patterns' => [
        'guide' => 'Two canonical patterns — do not invent a third (manual fetch in plain Blade is redundant with Livewire): classic Blade (POST + redirect + old()) for simple public forms; Livewire (wire:submit, AJAX) for rich interactions in the panel. Errors are always componentized: <x-form-errors> + field_error(), with the strategy in config/ui.php → error_display.',
        'strategies_heading' => 'Error display strategies',
        'strategies_guide' => 'Set in config/ui.php → error_display, with per-form override (<x-form-errors display="…"> and field_error(\'field\', \'…\')). Security rule: passwords and secrets are NEVER repopulated with old().',
        'strategy_inline' => 'inline (default)',
        'strategy_inline_hint' => 'Error below each field. Best for short forms: the error shows up where the fix happens.',
        'strategy_summary' => 'summary',
        'strategy_summary_hint' => 'Only the <x-form-errors> summary on top, with anchors that scroll to the field. Useful in long forms.',
        'strategy_toast' => 'toast',
        'strategy_toast_hint' => 'Errors fire the kit toast (ephemeral feedback). Avoid in long forms — the summary disappears on its own.',
        'strategy_both' => 'both',
        'strategy_both_hint' => 'Inline + summary: stronger accessibility (summary announced by screen readers + error in the field context).',
        'demo_error_message' => 'The message must be at least 10 characters.',
        'classic_heading' => 'Classic Blade — POST + redirect (functional)',
        'classic_guide' => 'When to use: simple public forms (contact, login, registration). State repopulated with old() in every field — EXCEPT passwords. Submit empty (or switch the strategy) to see real errors.',
        'display_field' => 'Error display strategy for this form',
        'display_field_hint' => 'Per-form override: becomes the display prop of <x-form-errors> and field_error().',
        'demo_name' => 'Name',
        'demo_email' => 'E-mail',
        'demo_password' => 'Password',
        'demo_password_hint' => 'Fail on purpose and notice: the password is NOT filled back.',
        'demo_message' => 'Message',
        'demo_message_placeholder' => 'At least 10 characters…',
        'demo_submit' => 'Send demo',
        'demo_sent' => 'Demo sent! The session flash became this toast.',
        'ajax_heading' => 'Livewire — wire:submit (AJAX, functional)',
        'ajax_guide' => 'When to use: rich interactions without reload (panel, modals, stateful forms). Automatic server-side validation and preserved fields (there is no old() in Livewire). This is the SAME submission as the landing contact form: validation, honeypot and queued e-mail.',
    ],

    'components' => [
        'spinner_label' => 'Loading',
        'loading_label' => 'Loading content',
    ],

];
