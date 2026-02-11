<?php

namespace App\Factories;

use App\Contracts\NotificationChannelContract;
use App\Exceptions\NotificationChannelNotFound;
use Illuminate\Support\Arr;

class NotificationChannelFactory
{
    public static function make(string $channel): NotificationChannelContract
    {
        // Notification channels are configured in the config/notification_channels.php file
        // This is because we want to keep the notification channels configuration separate from the code
        $notificationChannels = config("notification_channels");
        $selectedChannel = Arr::first($notificationChannels, function ($notificationChannel) use ($channel) {
            return $notificationChannel["channel"] === $channel;
        });
        if (is_null($selectedChannel)) {
            throw new NotificationChannelNotFound("Notification channel not found: {$channel}");
        }
        return resolve($selectedChannel["notification_class"]);
    }
}