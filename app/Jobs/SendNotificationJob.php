<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Repositories\Contracts\NotificationRepositoryContract;
use App\Services\NotificationProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Job responsible for processing and delivering a notification.
 *
 * Delegates processing logic to NotificationProcessor for better separation of concerns.
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
        public int $notificationId,
        protected ?int $maxAttempts = null,
    ) {
        $this->tries = $maxAttempts ?? self::DEFAULT_TRIES;
    }

    public function handle(NotificationProcessor $processor, NotificationRepositoryContract $notificationRepository): void
    {
        // Just uncomment this line below to test for failure handling and exponential backoff
        // throw new \Exception('test');
        $notification = $notificationRepository->get($this->notificationId);

        /**
         * If the notification no longer exists, we log and return
         * We don't throw an exception because we want to avoid the job being marked as failed
         * If the jobs is marked as failed, it will be retried according to the retry configuration
         * And there is no point in retrying the job if the notification no longer exists
         */
        if ($notification === null) {
            Log::info('SendNotificationJob: notification no longer exists', [
                'id' => $this->notificationId,
            ]);
            return;
        }

        $result = $processor->process($notification);

        if ($result->shouldRelease()) {
            // If the notification is scheduled for the future,
            // we release the lock for the number of seconds until the notification is scheduled to be sent
            // This is to prevent the notification from being processed before it is scheduled to be sent
            $this->release($result->releaseSeconds);
            return;
        }

        if (!$result->shouldContinue) {
            Log::info('SendNotificationJob: processing skipped', [
                'notification_id' => $this->notificationId,
                'reason' => $result->reason,
            ]);
        }
    }

    /**
     * Exponential backoff formula: 600 seconds (10 minutes) is the maximum delay
     * 3 to the power of $i is the exponential growth
     */
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

        $processor = resolve(NotificationProcessor::class);
        $processor->markAsFailed($notification, $e);
    }
}
