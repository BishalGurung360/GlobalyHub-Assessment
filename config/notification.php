<?php

return [

    'rate_limit' => [
        'max_attempts' => (int) env("NOTIFICATION_RATE_LIMIT_MAX_ATTEMPTS", 10),
        'decay_seconds' => (int) env("NOTIFICATION_RATE_LIMIT_DECAY_SECONDS", 3600),
    ],
];