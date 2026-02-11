<?php

namespace App\Contracts;

use App\Models\Notification;

interface NotificationChannelContract
{
    public function send(Notification $notification): void;
}