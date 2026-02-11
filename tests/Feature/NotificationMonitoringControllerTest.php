<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class NotificationMonitoringControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        // Disable caching in tests to avoid stale paginator data
        resolve(\App\Repositories\NotificationRepository::class)->isCached(false);
    }

    public function test_can_get_recent_notifications(): void
    {
        $user = User::factory()->create();
        $tenantId = 'test-tenant-123';

        Notification::factory()->count(5)->forTenant($tenantId)->forUser($user)->create();

        $response = $this->withHeaders([
            'X-Tenant-ID' => $tenantId,
        ])->getJson('/api/v1/notifications/recent');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'uuid',
                        'status',
                        'channel',
                        'user_id',
                        'title',
                        'created_at',
                        'scheduled_at',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                ],
            ]);

        $this->assertCount(5, $response->json('data'));
    }

    public function test_recent_notifications_filtered_by_user_id(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $tenantId = 'test-tenant-123';

        Notification::factory()->count(3)->forTenant($tenantId)->forUser($user1)->create();
        Notification::factory()->count(2)->forTenant($tenantId)->forUser($user2)->create();

        $response = $this->withHeaders([
            'X-Tenant-ID' => $tenantId,
        ])->getJson('/api/v1/notifications/recent?user_id=' . $user1->id);

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_recent_notifications_tenant_scoped(): void
    {
        $user = User::factory()->create();
        $tenantId1 = 'tenant-1';
        $tenantId2 = 'tenant-2';

        Notification::factory()->count(3)->forTenant($tenantId1)->forUser($user)->create();
        Notification::factory()->count(2)->forTenant($tenantId2)->forUser($user)->create();

        // Request with tenant 1 header - should only see tenant 1 notifications
        $response = $this->withHeaders([
            'X-Tenant-ID' => $tenantId1,
        ])->getJson('/api/v1/notifications/recent');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));

        // Request with tenant 2 header - should only see tenant 2 notifications
        $response = $this->withHeaders([
            'X-Tenant-ID' => $tenantId2,
        ])->getJson('/api/v1/notifications/recent');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_recent_notifications_cache_invalidated_on_create(): void
    {
        $user = User::factory()->create();
        $tenantId = 'test-tenant-123';

        Notification::factory()->count(3)->forTenant($tenantId)->forUser($user)->create();

        // First request
        $response1 = $this->withHeaders([
            'X-Tenant-ID' => $tenantId,
        ])->getJson('/api/v1/notifications/recent');

        $response1->assertStatus(200);
        $this->assertCount(3, $response1->json('data'));

        // Create a new notification via API (should invalidate cache)
        $this->withHeaders([
            'X-Tenant-ID' => $tenantId,
        ])->postJson('/api/v1/notifications', [
            'user_id' => $user->id,
            'channel' => 'log',
            'title' => 'New Notification',
            'body' => 'This is a new notification',
        ]);

        // Second request - should see the new notification
        $response2 = $this->withHeaders([
            'X-Tenant-ID' => $tenantId,
        ])->getJson('/api/v1/notifications/recent');

        $response2->assertStatus(200);
        $this->assertCount(4, $response2->json('data'));
    }

    public function test_can_get_summary_statistics(): void
    {
        $user = User::factory()->create();
        $tenantId = 'test-tenant-123';

        Notification::factory()->count(5)->forTenant($tenantId)->forUser($user)->sent()->create();
        Notification::factory()->count(3)->forTenant($tenantId)->forUser($user)->failed()->create();
        Notification::factory()->count(2)->forTenant($tenantId)->forUser($user)->create(); // Pending

        $response = $this->withHeaders([
            'X-Tenant-ID' => $tenantId,
        ])->getJson('/api/v1/notifications/summary');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'counts_by_status',
                    'total',
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals(10, $data['total']);
        $this->assertEquals(5, $data['counts_by_status']['sent']);
        $this->assertEquals(3, $data['counts_by_status']['failed']);
        $this->assertEquals(2, $data['counts_by_status']['pending']);
    }

    public function test_summary_by_channel_breakdown(): void
    {
        $user = User::factory()->create();
        $tenantId = 'test-tenant-123';

        Notification::factory()->count(3)->forTenant($tenantId)->forUser($user)->channel('email')->sent()->create();
        Notification::factory()->count(2)->forTenant($tenantId)->forUser($user)->channel('email')->failed()->create();
        Notification::factory()->count(2)->forTenant($tenantId)->forUser($user)->channel('sms')->sent()->create();
        Notification::factory()->count(1)->forTenant($tenantId)->forUser($user)->channel('sms')->pending()->create();

        $response = $this->withHeaders([
            'X-Tenant-ID' => $tenantId,
        ])->getJson('/api/v1/notifications/summary?by_channel=true');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'counts_by_status',
                    'total',
                    'by_channel',
                ],
            ]);

        $data = $response->json('data');
        $this->assertArrayHasKey('email', $data['by_channel']);
        $this->assertArrayHasKey('sms', $data['by_channel']);
        $this->assertEquals(3, $data['by_channel']['email']['sent']);
        $this->assertEquals(2, $data['by_channel']['email']['failed']);
        $this->assertEquals(2, $data['by_channel']['sms']['sent']);
        $this->assertEquals(1, $data['by_channel']['sms']['pending']);
    }
}
