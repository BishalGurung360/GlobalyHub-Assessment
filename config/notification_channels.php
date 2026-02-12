<?php

use App\Services\NotificationChannels\EmailNotification;
use App\Services\NotificationChannels\LogNotification;
use App\Services\NotificationChannels\SlackNotification;
use App\Services\NotificationChannels\SmsNotification;


/**
 * In the future, if we need more notification channels,
 * we can simply add a new class and register it in the config file
 * And the NotificationChannelFactory will automatically use the new channel
 * Also the StoreNotificationRequest validation rule will automatically reflect the new channel in its $availableChannels array
 */
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
    // [
    //     "channel" => "slack",
    //     "notification_class" => SlackNotification::class,
    // ],
];
