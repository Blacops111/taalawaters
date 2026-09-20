<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Delivery confirmation
    |--------------------------------------------------------------------------
    |
    | The six-digit confirmation code is short-lived and can be regenerated
    | before delivery. The plain code is never stored in the database.
    |
    */

    'confirmation_code_ttl_minutes' => (int) env(
        'DELIVERY_CONFIRMATION_CODE_TTL_MINUTES',
        180
    ),

    'confirmation_code_max_attempts' => (int) env(
        'DELIVERY_CONFIRMATION_CODE_MAX_ATTEMPTS',
        5
    ),
];
