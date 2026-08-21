<?php

declare(strict_types=1);

// Formulario de contacto de la landing (/) — es (ADR-007).

return [

    'heading' => 'Habla con nosotros',
    'subtitle' => 'Sugerencia, reclamo u otro asunto — tu mensaje llega por correo al equipo y respondemos a la dirección informada.',

    'form' => [
        'name' => 'Nombre',
        'name_placeholder' => 'Tu nombre',
        'email' => 'Correo electrónico',
        'email_placeholder' => 'tu@ejemplo.com',
        'subject' => 'Asunto',
        'message' => 'Mensaje',
        'message_placeholder' => 'Cuenta el contexto en pocas líneas…',
        'submit' => 'Enviar mensaje',
        // Honeypot anti-spam (invisible para humanos — NO traducir el name).
        'honeypot_label' => 'Website',
    ],

    'subjects' => [
        'suggestion' => 'Sugerencia',
        'complaint' => 'Reclamo',
        'other' => 'Otro',
    ],

    'sent' => '¡Mensaje enviado! Te respondemos pronto por correo.',

    'mail' => [
        'subject_line' => ':platform — Contacto: :subject',
        'intro' => 'Nuevo mensaje del formulario de contacto de la landing.',
        'from' => 'De',
        'subject_label' => 'Asunto',
    ],

];
