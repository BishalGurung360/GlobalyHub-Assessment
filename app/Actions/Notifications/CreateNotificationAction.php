<?php

namespace App\Actions\Notifications;

use App\Dto\CreateNotificationDto;
use App\Enums\NotificationStatus;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Repositories\Contracts\NotificationRepositoryContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class CreateNotificationAction
{
    public function __construct(
        protected NotificationRepositoryContract $notificationRepository
    ) {
    }

    /**
     * Execute the action to create and queue a notification.
     *
     * @return Notification
     */
    public function execute(CreateNotificationDto $dto): Notification
    {
        $this->enforceRateLimit($dto);

        $notification = DB::transaction(function () use ($dto) {
            return $this->createNotificationRecord($dto);
        });

        $this->dispatchNotificationJob($notification);

        return $notification;
    }

    /**
     * Enforce a simple per-tenant per-user rate limit for notification creation.
     *
     * Throws an exception if the client has exceeded the configured rate.
     */
    protected function enforceRateLimit(CreateNotificationDto $dto): void
    {
        $tenantId = $dto->tenantId ?? getTenantId();
        
        if (!$tenantId) {
            throw new \RuntimeException('Tenant ID is required for rate limiting.');
        }

        $key = sprintf(
            'notifications:%s:%s',
            $tenantId,
            $dto->userId
        );

        $maxAttempts = config('notification.rate_limit.max_attempts');
        $decaySeconds = config('notification.rate_limit.decay_seconds');

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw new \RuntimeException('Notification rate limit exceeded.');
        }

        RateLimiter::hit($key, $decaySeconds);
    }

    /**
     * Persist a new notification record from the DTO.
     */
    protected function createNotificationRecord(CreateNotificationDto $dto): Notification
    {
        $createData = $dto->toArray();
        $createData['status'] = NotificationStatus::Pending;
        
        if (empty($createData['max_attempts'])) {
            unset($createData['max_attempts']);
        }
        
        return $this->notificationRepository->store($createData);
    }

    /**
     * Dispatch the queue job responsible for processing the notification.
     */
    protected function dispatchNotificationJob(Notification $notification): void
    {
        SendNotificationJob::dispatch($notification->id, $notification->max_attempts);
    }
}
