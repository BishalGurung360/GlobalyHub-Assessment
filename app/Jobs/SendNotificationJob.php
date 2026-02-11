<?php

namespace App\Jobs;

use App\Enums\NotificationStatus;
use App\Exceptions\NotificationChannelNotFound;
use App\Factories\NotificationChannelFactory;
use App\Models\Notification;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Job responsible for processing and delivering a notification.
 *
 * Reloads the notification from the DB, enforces scheduled_at and idempotency,
 * uses a lock to prevent concurrent processing, and updates status/attempts
 * with retry and exponential backoff.
 */
class SendNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Maximum number of job-level retries (used when notification max_attempts is missing).
     */
    private const DEFAULT_TRIES = 10;

    public int $tries;

    public function __construct(
        protected int $notificationId,
        protected ?int $maxAttempts = null,
    ) {
        $this->tries = $maxAttempts ?? self::DEFAULT_TRIES;
    }

    public function handle(): void
    {
        $notification = Notification::find($this->notificationId);

        if ($notification === null) {
            Log::info('SendNotificationJob: notification no longer exists', ['id' => $this->notificationId]);

            return;
        }

        if ($notification->status->isTerminal()) {
            return;
        }

        if ($notification->scheduled_at !== null && $notification->scheduled_at->isFuture()) {
            $seconds = max(0, $notification->scheduled_at->getTimestamp() - now()->getTimestamp());
            $this->release($seconds);

            return;
        }

        $lock = Cache::lock($notification->lockKey(), 60);

        if (! $lock->get()) {
            $this->release(10);

            return;
        }

        try {
            $notification->status = NotificationStatus::Processing;
            $notification->attempts = $notification->attempts + 1;
            $notification->save();

            $this->performDelivery($notification);

            $notification->status = NotificationStatus::Sent;
            $notification->processed_at = now();
            $notification->save();
        } catch (Throwable $e) {
            $notification->last_error = $e->getMessage();
            $notification->save();

            $exhausted = $notification->attempts >= $notification->max_attempts;

            if ($exhausted) {
                $notification->status = NotificationStatus::Failed;
                $notification->failed_at = now();
                $notification->save();
            } else {
                throw $e;
            }
        } finally {
            $lock->release();
        }
    }

    public function backoff(): array
    {
        $delays = [];
        for ($i = 1; $i <= $this->tries; $i++) {
            $delays[] = min(600, 3 ** $i);
        }
        return $delays;
    }

    /**
     * Called when the job has exhausted all retries at the queue level.
     */
    public function failed(Throwable $e): void
    {
        $notification = Notification::find($this->notificationId);

        if ($notification === null) {
            return;
        }

        if ($notification->status->isTerminal()) {
            return;
        }

        $notification->status = NotificationStatus::Failed;
        $notification->failed_at = now();
        $notification->last_error = $e->getMessage();
        $notification->save();
    }

    /**
     * Perform the actual delivery (e.g. email, SMS, push).
     * Override or replace with a channel-specific sender in a real implementation.
     */
    protected function performDelivery(Notification $notification): void
    {
        $notification->load('user');
        Log::info('SendNotificationJob: performing delivery', ['notification' => $notification]);
        try {
            $channel = NotificationChannelFactory::make($notification->channel);
            $channel->send($notification);
        } catch (NotificationChannelNotFound $e) {
            Log::error('SendNotificationJob: error performing delivery', ['error' => $e->getMessage()]);
            $this->failed($e);
        } catch (Throwable $e) {
            Log::error('SendNotificationJob: error performing delivery', ['error' => $e->getMessage()]);
        }
    }
}
