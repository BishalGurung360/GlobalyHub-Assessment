<?php

namespace Tests\Feature;

use App\Enums\NotificationStatus;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        RateLimiter::clear('notifications:*');
    }

    public function test_can_create_notification_successfully(): void
    {
        $user = User::factory()->create();
        $tenantId = 'test-tenant-123';

        $response = $this->withHeaders([
            'X-Tenant-ID' => $tenantId,
        ])->postJson('/api/v1/notifications', [
            'user_id' => $user->id,
            'channel' => 'log',
            'title' => 'Test Notification',
            'body' => 'This is a test notification body',
        ]);

        $response->assertStatus(202)
            ->assertJsonStructure([
                'data' => [
                    'uuid',
                    'status',
                    'channel',
                    'user_id',
                    'title',
                    'created_at',
                    'scheduled_at',
                ],
            ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'channel' => 'log',
            'title' => 'Test Notification',
            'body' => 'This is a test notification body',
            'status' => NotificationStatus::Pending->value,
        ]);

        Queue::assertPushed(SendNotificationJob::class);
    }

    public function test_notification_requires_tenant_id_header(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/notifications', [
            'user_id' => $user->id,
            'channel' => 'log',
            'title' => 'Test Notification',
            'body' => 'This is a test notification body',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'error' => 'X-Tenant-ID header is required',
            ]);
    }

    public function test_notification_validation_errors(): void
    {
        $tenantId = 'test-tenant-123';

        $response = $this->withHeaders([
            'X-Tenant-ID' => $tenantId,
        ])->postJson('/api/v1/notifications', [
            'user_id' => 99999, // Non-existent user
            'channel' => 'invalid-channel',
            'title' => '',
            'body' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id', 'channel', 'title', 'body']);
    }

    public function test_notification_rate_limit_enforced(): void
    {
        $user = User::factory()->create();
        $tenantId = 'test-tenant-123';
        $maxAttempts = config('notification.rate_limit.max_attempts', 10);

        // Create notifications up to the limit
        for ($i = 0; $i < $maxAttempts; $i++) {
            $this->withHeaders([
                'X-Tenant-ID' => $tenantId,
            ])->postJson('/api/v1/notifications', [
                'user_id' => $user->id,
                'channel' => 'log',
                'title' => "Test Notification {$i}",
                'body' => 'This is a test notification body',
            ]);
        }

        // Attempt to create one more notification (should fail)
        $response = $this->withHeaders([
            'X-Tenant-ID' => $tenantId,
        ])->postJson('/api/v1/notifications', [
            'user_id' => $user->id,
            'channel' => 'log',
            'title' => 'Test Notification Over Limit',
            'body' => 'This should fail due to rate limit',
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'message' => 'Notification rate limit exceeded.',
            ]);
    }

    public function test_notification_rate_limit_resets_after_hour(): void
    {
        $user = User::factory()->create();
        $tenantId = 'test-tenant-123';
        $maxAttempts = config('notification.rate_limit.max_attempts', 10);

        // Create notifications up to the limit
        for ($i = 0; $i < $maxAttempts; $i++) {
            $this->withHeaders([
                'X-Tenant-ID' => $tenantId,
            ])->postJson('/api/v1/notifications', [
                'user_id' => $user->id,
                'channel' => 'log',
                'title' => "Test Notification {$i}",
                'body' => 'This is a test notification body',
            ]);
        }

        // Clear the rate limiter to simulate time passing
        $key = sprintf('notifications:%s:%s', $tenantId, $user->id);
        RateLimiter::clear($key);

        // Now should be able to create another notification
        $response = $this->withHeaders([
            'X-Tenant-ID' => $tenantId,
        ])->postJson('/api/v1/notifications', [
            'user_id' => $user->id,
            'channel' => 'log',
            'title' => 'Test Notification After Reset',
            'body' => 'This should succeed after rate limit reset',
        ]);

        $response->assertStatus(202);
    }

    public function test_notification_dispatched_to_queue(): void
    {
        $user = User::factory()->create();
        $tenantId = 'test-tenant-123';

        $this->withHeaders([
            'X-Tenant-ID' => $tenantId,
        ])->postJson('/api/v1/notifications', [
            'user_id' => $user->id,
            'channel' => 'log',
            'title' => 'Test Notification',
            'body' => 'This is a test notification body',
        ]);

        // This test doesn't just validate that the job was dispatched
        // But also that the notification was created in the database with the correct status and user id
        Queue::assertPushed(SendNotificationJob::class, function ($job) use ($user) {
            $notification = Notification::find($job->notificationId);
            return $notification !== null
                && $notification->user_id === $user->id
                && $notification->status === NotificationStatus::Pending;
        });
    }
}
