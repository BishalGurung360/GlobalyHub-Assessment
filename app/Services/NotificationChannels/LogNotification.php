<?php

namespace App\Services\NotificationChannels;

use App\Contracts\NotificationChannelContract;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class LogNotification implements NotificationChannelContract
{
    public function send(Notification $notification): void
    {
        Log::info('LogNotification: sending notification', ['notification' => $notification]);
    }
}