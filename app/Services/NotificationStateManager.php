<?php

namespace App\Services;

use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Repositories\Contracts\NotificationRepositoryContract;

/**
 * Manages notification state transitions, attempt tracking, and error recording.
 */
class NotificationStateManager
{
    public function __construct(
        protected NotificationRepositoryContract $repository,
    ) {
    }

    /**
     * Mark notification as processing and increment attempt count.
     */
    public function markAsProcessing(Notification $notification): void
    {
        $this->repository->update([
            'status' => NotificationStatus::Processing,
            'attempts' => $notification->attempts + 1,
        ], $notification->id);
    }

    /**
     * Mark notification as successfully sent.
     */
    public function markAsSent(Notification $notification): void
    {
        $this->repository->update([
            'status' => NotificationStatus::Sent,
            'processed_at' => now(),
        ], $notification->id);
    }

    /**
     * Record an error and check if attempts are exhausted.
     * Returns true if attempts are exhausted, false otherwise.
     */
    public function recordError(Notification $notification, \Throwable $error): bool
    {
        $this->repository->update([
            'last_error' => $error->getMessage(),
        ], $notification->id);

        // Reload notification to get fresh attempts value for exhaustion check
        $freshNotification = $this->repository->get($notification->id);
        
        return $this->isExhausted($freshNotification);
    }

    /**
     * Mark notification as failed (when attempts are exhausted).
     */
    public function markAsFailed(Notification $notification, \Throwable $error): void
    {
        $this->repository->update([
            'status' => NotificationStatus::Failed,
            'failed_at' => now(),
            'last_error' => $error->getMessage(),
        ], $notification->id);
    }

    /**
     * Check if notification has exhausted all retry attempts.
     */
    public function isExhausted(Notification $notification): bool
    {
        return $notification->attempts >= $notification->max_attempts;
    }

    /**
     * Check if notification is in a terminal state.
     */
    public function isTerminal(Notification $notification): bool
    {
        return $notification->status->isTerminal();
    }
}
