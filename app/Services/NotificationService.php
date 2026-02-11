<?php

namespace App\Services;

use App\Contracts\NotificationServiceContract;
use App\Dto\CreateNotificationDto;
use App\Enums\NotificationStatus;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Repositories\Contracts\NotificationRepositoryContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Default implementation of the NotificationService.
 *
 * Responsibilities:
 * - Creating notification records
 * - Enforcing basic rate limits
 * - Dispatching queue jobs
 * - Returning API-safe responses
 */
class NotificationService implements NotificationServiceContract
{
    public function __construct(
        protected NotificationRepositoryContract $notificationRepository,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function createAndQueue(CreateNotificationDto $dto): NotificationApiResponse
    {
        $this->enforceRateLimit($dto);

        $notification = DB::transaction(function () use ($dto) {
            return $this->createNotificationRecord($dto);
        });

        $this->dispatchNotificationJob($notification);

        return $this->buildApiResponse($notification);
    }

    /**
     * Enforce a simple per-tenant per-user rate limit for notification creation.
     *
     * Throws an exception if the client has exceeded the configured rate.
     */
    protected function enforceRateLimit(CreateNotificationDto $dto): void
    {
        $tenantId = $dto->tenantId ?? app('tenant_id');
        
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
        
        // Ensure tenant_id is set from context if not in DTO
        if (empty($createData['tenant_id']) && $tenantId = app('tenant_id')) {
            $createData['tenant_id'] = $tenantId;
        }
        
        if (empty($createData['max_attempts'])) {
            unset($createData['max_attempts']);
        }
        
        $notification = $this->notificationRepository->store($createData);
        return $notification;
    }

    /**
     * Dispatch the queue job responsible for processing the notification.
     */
    protected function dispatchNotificationJob(Notification $notification): void
    {
        SendNotificationJob::dispatch($notification->id, $notification->max_attempts);
    }

    /**
     * Build an API-safe representation of the notification resource.
     */
    protected function buildApiResponse(Notification $notification): NotificationApiResponse
    {
        return new NotificationApiResponse(
            uuid: $notification->uuid,
            status: $notification->status->value,
            scheduledAt: $notification->scheduled_at,
            createdAt: $notification->created_at,
        );
    }
}

