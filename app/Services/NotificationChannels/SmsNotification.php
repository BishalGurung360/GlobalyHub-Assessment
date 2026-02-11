<?php

namespace App\Services\NotificationChannels;

use App\Contracts\NotificationChannelContract;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class SmsNotification implements NotificationChannelContract
{
    public function send(Notification $notification): void
    {
        // I have made it easy to implement the SMS logic if required
        // You can add the SMS logic here of the 3rd service you use like Twilio, Nexmo, etc.
        Log::info('SmsNotification: sending notification', ['notification' => $notification]);
    }
}