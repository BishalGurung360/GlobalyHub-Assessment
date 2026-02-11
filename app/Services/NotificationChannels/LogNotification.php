<?php

namespace App\Services\NotificationChannels;

use App\Contracts\NotificationChannelContract;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class LogNotification implements NotificationChannelContract
{
    public function send(Notification $notification): void
    {
        // Simple log notification as requested in the pdf
        Log::info('LogNotification: sending notification', ['notification' => $notification]);
    }
}