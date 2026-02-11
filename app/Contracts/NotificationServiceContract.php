<?php

namespace App\Contracts;

use App\Models\Notification;
use App\Dto\CreateNotificationDto;
use App\Services\NotificationService;
use App\Services\NotificationApiResponse;

/**
 * Service responsible for creating notification records,
 * enforcing rate limits, dispatching queue jobs, and
 * returning API-safe responses.
 * @see NotificationService
 */
interface NotificationServiceContract
{
    /**
     * Create a notification from the given DTO, enforce any
     * applicable rate limits, dispatch the delivery job, and
     * return an API-safe representation of the notification.
     */
    public function createAndQueue(CreateNotificationDto $dto): NotificationApiResponse;
}

