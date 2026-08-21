<?php

declare(strict_types=1);

// Landing (/) contact form — en (ADR-007).

return [

    'heading' => 'Talk to us',
    'subtitle' => 'Suggestion, complaint or anything else — your message reaches the team by email and we reply to the address you provide.',

    'form' => [
        'name' => 'Name',
        'name_placeholder' => 'Your name',
        'email' => 'Email',
        'email_placeholder' => 'you@example.com',
        'subject' => 'Subject',
        'message' => 'Message',
        'message_placeholder' => 'Tell the context in a few lines…',
        'submit' => 'Send message',
        'sent_title' => 'Message sent',
        'send_another' => 'Send another message',
        // Anti-spam honeypot (invisible to humans — do NOT translate the name).
        'honeypot_label' => 'Website',
    ],

    'subjects' => [
        'suggestion' => 'Suggestion',
        'complaint' => 'Complaint',
        'other' => 'Other',
    ],

    'sent' => 'Message sent! We will get back to you by email soon.',

    'mail' => [
        'subject_line' => ':platform — Contact: :subject',
        'intro' => 'New message from the landing contact form.',
        'from' => 'From',
        'subject_label' => 'Subject',
    ],

];
