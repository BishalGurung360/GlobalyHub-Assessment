<?php

use App\Services\NotificationChannels\EmailNotification;
use App\Services\NotificationChannels\LogNotification;
use App\Services\NotificationChannels\SmsNotification;

return [
    [
        "channel" => "log",
        "notification_class" => LogNotification::class,
    ],
    [
        "channel" => "email",
        "notification_class" => EmailNotification::class,
    ],
    [
        "channel" => "sms",
        "notification_class" => SmsNotification::class,
    ],
];
