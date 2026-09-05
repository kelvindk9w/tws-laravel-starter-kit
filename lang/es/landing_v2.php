<?php

declare(strict_types=1);

// Cadenas de la landing "El Rastro" (/v2) — español (ADR-007). Los fragmentos
// de código NO viven aquí: el código no es un idioma, y duplicarlo en tres
// archivos serían tres verdades que mantener — vienen de LandingV2Controller.

return [

    'meta' => [
        'title' => ':platform — todo lo que ocurre queda registrado',
        'description' => 'Base Laravel con auditoría, 2FA, claves de API y pruebas ya escritas. El rastro empieza en la primera petición.',
    ],

    'sound' => [
        'label' => 'Sonido de la página',
        'on' => 'Sonido activado — haz clic para silenciar',
        'off' => 'Sonido desactivado — haz clic para oír el pulso del log',
    ],

    'hero' => [
        'title' => 'Todo lo que ocurre queda registrado.',
        'subtitle' => 'Cuando llega la filtración, quien responde eres tú — y el rastro es la única defensa. Base Laravel con auditoría, 2FA, claves de API y pruebas ya escritas.',
        'live_label' => 'Escribe cualquier cosa',
        'live_hint' => 'Un documento fiscal, un correo, una tarjeta. La línea de log de abajo está escuchando.',
        'live_placeholder' => 'mi correo es marina.duarte@ejemplo.com',
        'live_empty' => 'esperando entrada…',
        'live_redacted' => 'redactado antes de llegar a la base de datos',
        'live_clean' => 'nada sensible reconocido',
        'clone' => 'Clonar',
        'clone_copy' => 'Copiar el comando',
        'clone_copied' => 'Comando copiado',
        'demo' => 'Entrar en la demo',
        'scroll' => 'Desplázate para leer el rastro',
        'canvas_alt' => 'Líneas del log de peticiones del kit cayendo por la pantalla hasta formar el titular.',
    ],

    'trust' => [
        'heading' => 'Números del repositorio',
        'plus' => ':value+',
        'tests' => 'pruebas Pest, en verde',
        'files' => 'archivos de prueba',
        'license' => 'licencia — clónalo, edítalo, véndelo',
        'version' => 'versión de la plataforma',
        'build' => 'último build del frontend',
    ],

    'mechanism' => [
        'pain' => 'Dos semanas montando login, colas y Docker — y el producto todavía no existe.',
        'heading' => 'Tres comandos hasta tu primera línea de log.',
        'subtitle' => 'Sin CLI propietaria, sin cuenta, sin clave de licencia. Lo que corre en tu máquina es lo que corre en producción.',
        'steps' => [
            [
                'title' => 'Clonar',
                'description' => 'Una copia tuya, en tu propio Git, desde el primer día.',
                'output' => 'Cloning into \'mi-proyecto\'... done.',
            ],
            [
                'title' => 'Levantar',
                'description' => 'Solo Docker en la máquina. App, PostgreSQL, Redis, colas y scheduler suben juntos.',
                'output' => 'Container app  Started   Container queue  Started   Container scheduler  Started',
            ],
            [
                'title' => 'Probar',
                'description' => 'La suite entera pasa antes de que escribas la primera línea de tu producto.',
                'output' => 'Tests:  :tests passed',
            ],
        ],
    ],

    'depth' => [
        'pain' => 'Cada módulo que aplazas por falta de tiempo se convierte en el incidente del trimestre siguiente.',
        'heading' => 'Lo que ya está escrito.',
        'subtitle' => 'Seis módulos que no vas a construir. Elige uno: el código real se abre aquí, listo para copiar.',
        'hint' => 'Usa las flechas para recorrer los módulos.',
        'cells' => [
            'api_keys' => [
                'title' => 'Claves de API con alcance y rotación',
                'description' => 'Par pk_/sk_, alcances granulares, rotación con periodo de gracia y desactivación por inactividad.',
            ],
            'audit' => [
                'title' => 'Cada petición se vuelve una línea',
                'description' => 'Tabla append-only con correlation id, payload redactado y ciclo de vida controlado.',
            ],
            'sensitive' => [
                'title' => 'Una acción sensible pide dos pruebas',
                'description' => 'Contraseña de transacción (hash aparte del de login) más código por correo, canjeados por un token de un solo uso.',
            ],
            'uploads' => [
                'title' => 'Subidas que reescriben el archivo',
                'description' => 'Firma real del archivo, re-encode en GD (el payload incrustado no sobrevive) y URL firmada de corta duración.',
            ],
            'tenancy' => [
                'title' => 'Aislamiento por proyecto',
                'description' => 'Global scope en el modelo y pruebas que intentan filtrar de un tenant a otro — y fallan.',
            ],
            'i18n' => [
                'title' => 'Tres idiomas, una clave',
                'description' => 'Cada cadena pasa por lang/. Una prueba de paridad reprueba la clave que se quedó atrás.',
            ],
        ],
        'copy' => 'Copiar',
        'copied' => 'Copiado',
    ],

    'thesis' => [
        'pain' => 'La contraseña de un cliente en una línea de log es una filtración que nadie puede deshacer.',
        'heading' => 'El rastro guarda el hecho, nunca el secreto.',
        'subtitle' => 'La redacción corre al recibir la petición, antes de cualquier procesamiento. Lo que ves abajo es la salida de la clase que corre en producción — pasa el cursor o toca para verla actuar.',
        'hint_pointer' => 'Pasa el cursor sobre la petición',
        'hint_touch' => 'Toca la petición',
        'redacting' => 'Redactando…',
        'redacted' => 'Redactado',
        'reset' => 'Ver el original',
        'rules' => [
            'key' => 'clave sensible → sustituida por completo',
            'email' => 'correo → primera letra y dominio',
            'document' => 'documento fiscal → tres primeros y dos últimos dígitos',
            'card' => 'tarjeta → solo los cuatro últimos dígitos',
            'none' => 'dato de negocio → preservado',
        ],
        'rules_heading' => 'Por qué cambió cada campo',
        'chain_heading' => 'Y la línea nunca se queda a medio camino.',
        'chain_subtitle' => 'El log nace INICIADA al recibirse y solo sale de ahí por transición controlada. Una línea que sigue INICIADA es un incidente — así encuentra el kit lo que se cayó.',
        'chain' => [
            'INICIADA' => 'Escrita antes de cualquier procesamiento de negocio. Si la petición muere aquí, la evidencia ya existe.',
            'CONCLUIDA' => 'La respuesta salió por debajo de 500. Duración y estado quedan en la misma línea.',
            'ERRO' => 'Error de servidor: el mensaje entra redactado, con el correlation id que une el log de base de datos y el de archivo.',
            'BLOQUEADA' => 'La validación de seguridad rechazó el payload. El intento queda registrado, inerte, para la vitrina del super admin.',
        ],
    ],

    'proof' => [
        'pain' => 'Toda landing de starter kit promete design system. Casi ninguna te deja tocarlo.',
        'heading' => 'No nos creas: tócalo.',
        'subtitle' => 'Los controles de abajo son los componentes reales del kit, en tu tema. Nada aquí es una imagen.',
        'theme_title' => 'Un tema, no una inversión',
        'theme_description' => 'Cambia el tema: las superficies vienen de los tokens semánticos y mantienen el MISMO orden de elevación en ambos. La página entera responde a la vez.',
        'components_title' => 'Componentes de verdad',
        'twofa_title' => 'Confirmación de acción sensible',
        'twofa_description' => 'Una simulación del flujo real: pedimos el código, lo escribes, el kit responde. Desde aquí no se envía ningún correo.',
        'twofa_send' => 'Enviar código',
        'twofa_sending' => 'Enviando…',
        'twofa_sent' => 'Código enviado al correo de la cuenta. En esta demostración, usa :code.',
        'twofa_label' => 'Código de verificación',
        'twofa_confirm' => 'Confirmar',
        'twofa_ok' => 'Acción confirmada. Token de un solo uso emitido, válido por 5 minutos.',
        'twofa_error' => 'Código inválido. Quedan :attempts intentos antes del bloqueo.',
        'twofa_reset' => 'Empezar de nuevo',
        'demo_field' => 'Nombre del proyecto',
        'demo_field_placeholder' => 'mi-proyecto',
        'demo_toggle' => 'Exigir contraseña de transacción',
        'demo_badge' => 'activa',
        'demo_button' => 'Guardar proyecto',
        'demo_saved' => 'Proyecto guardado.',
    ],

    'community' => [
        'pain' => 'Un kit cerrado es una decisión que no puedes auditar ni revertir.',
        'heading' => 'El repositorio es la documentación.',
        'subtitle' => 'Un README con las decisiones de arquitectura escritas por extenso: qué se eligió, qué se rechazó y por qué.',
        'repo' => 'Ver en GitHub',
        'stars' => 'estrellas',
        'license_note' => 'Licencia :license',
        'version_note' => 'Versión :version',
    ],

    'cta' => [
        'pain' => 'El primer incidente no pregunta si tuviste tiempo de instrumentar.',
        'heading' => 'Empieza con el rastro ya escrito.',
        'subtitle' => 'Clona, levanta y lee la primera línea de tu propio log en menos de cinco minutos.',
        'button' => 'Clonar el repositorio',
    ],

];
