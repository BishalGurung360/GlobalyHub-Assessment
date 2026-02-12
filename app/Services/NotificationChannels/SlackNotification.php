<?php

namespace App\Services\NotificationChannels;

use App\Contracts\NotificationChannelContract;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class SlackNotification implements NotificationChannelContract
{
    public function send(Notification $notification): void
    {
        // I have made it easy to implement the Slack logic if required
        // You can add the Slack logic here of the 3rd service you use like Slack, etc.
        Log::info('SlackNotification: sending notification', ['notification' => $notification]);
    }
}