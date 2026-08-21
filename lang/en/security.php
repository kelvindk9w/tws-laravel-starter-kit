<?php

declare(strict_types=1);

return [

    // Client response when security validation blocks the request.
    // Deliberately generic: it does not reveal what was detected.
    'blocked' => 'Request rejected by the security policy.',

    // Internal message recorded in the request log (attempt metadata).
    'blocked_log' => 'Malicious payload detected (:type).',

];
