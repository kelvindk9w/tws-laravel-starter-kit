<?php

declare(strict_types=1);

// Subject of the API key inactivity warning (en), built by the twstec/kit-accounts
// mail class. The e-mail body and its strings belong to the front. The
// application wins: the same key in its lang/ takes precedence.

return [

    'api_key_inactivity' => [
        'subject' => ':platform — Your API key will be deactivated due to inactivity',
    ],

];
