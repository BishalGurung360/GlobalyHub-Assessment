<?php

namespace App\Services;

use App\Models\Notification;
use Illuminate\Support\Facades\Cache;

/**
 * Orchestrates the processing of notifications, including validation,
 * scheduling checks, locking, state management, and delivery coordination.
 */
class NotificationProcessor
{
    public function __construct(
        protected NotificationStateManager $stateManager,
        protected NotificationDeliveryService $deliveryService,
    ) {
    }

    /**
     * Process a notification: validate, check scheduling, acquire lock, deliver, and update state.
     *
     * @return ProcessResult Result containing whether processing should continue and optional release delay
     */
    public function process(Notification $notification): ProcessResult
    {
        // Check if notification is in terminal state
        // Terminal state means if the notification is sent, failed, or cancelled
        // No need to process the notification if it is in terminal state
        if ($this->stateManager->isTerminal($notification)) {
            return ProcessResult::skipped('Notification is in terminal state');
        }

        // Check if notification is scheduled for the future
        $scheduleResult = $this->checkSchedule($notification);
        if ($scheduleResult->shouldRelease()) {
            return $scheduleResult;
        }

        // Acquire lock to prevent concurrent processing from other queues
        $lock = Cache::lock($notification->lockKey(), 60);
        // If the lock cannot be acquired, we release the lock for 10 seconds
        if (!$lock->get()) {
            return ProcessResult::release(10, 'Could not acquire lock');
        }

        try {
            // Mark as processing and increment attempts
            $this->stateManager->markAsProcessing($notification);

            // Perform delivery
            $this->deliveryService->deliver($notification);

            // Mark as sent
            $this->stateManager->markAsSent($notification);

            return ProcessResult::success();
        } catch (\Throwable $e) {
            // Record error and check if exhausted
            $exhausted = $this->stateManager->recordError($notification, $e);

            if ($exhausted) {
                $this->stateManager->markAsFailed($notification, $e);
                return ProcessResult::success('Failed after exhausting attempts');
            }

            // Re-throw to trigger job retry
            throw $e;
        } finally {
            $lock->release();
        }
    }

    /**
     * Check if notification should be released due to scheduling.
     *
     * @return ProcessResult Result indicating whether to release and for how long
     */
    protected function checkSchedule(Notification $notification): ProcessResult
    {
        if ($notification->scheduled_at === null) {
            return ProcessResult::continue();
        }

        // If the notification is scheduled for the future,
        // we release the lock for the number of seconds until the notification is scheduled to be sent
        // This is to prevent the notification from being processed before it is scheduled to be sent
        if ($notification->scheduled_at->isFuture()) {
            $seconds = max(0, $notification->scheduled_at->getTimestamp() - now()->getTimestamp());
            return ProcessResult::release($seconds, 'Notification scheduled for future');
        }

        return ProcessResult::continue();
    }

    /**
     * Mark notification as failed (used when job exhausts retries).
     */
    public function markAsFailed(Notification $notification, \Throwable $error): void
    {
        if ($this->stateManager->isTerminal($notification)) {
            return;
        }

        $this->stateManager->markAsFailed($notification, $error);
    }
}
