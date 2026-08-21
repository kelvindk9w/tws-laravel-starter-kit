<?php

declare(strict_types=1);

return [

    // Respuesta al cliente cuando la validación de seguridad bloquea la
    // petición. Mensaje deliberadamente genérico: no revela lo detectado.
    'blocked' => 'Solicitud rechazada por la política de seguridad.',

    // Mensaje interno grabado en el request log (metadato del intento).
    'blocked_log' => 'Payload malicioso detectado (:type).',

];
