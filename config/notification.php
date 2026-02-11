<?php

return [

    'rate_limit' => [
        'max_attempts' => env("NOTIFICATION_RATE_LIMIT_MAX_ATTEMPTS", 10),
        'decay_seconds' => env("NOTIFICATION_RATE_LIMIT_DECAY_SECONDS", 3600),
    ],
];