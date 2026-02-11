<?php

namespace App\Services\NotificationChannels;

use App\Contracts\NotificationChannelContract;
use App\Mail\NotificationEmail;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailNotification implements NotificationChannelContract
{
    public function send(Notification $notification): void
    {
        Log::info('EmailNotification: sending notification', ['notification' => $notification]);

        // Note: I have not actually implemented the email logic in the NotificationEmail class
        // That is not the purpose of this assessment
        Mail::to($notification->user->email)->send(new NotificationEmail($notification));
    }
}