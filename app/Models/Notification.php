<?php

namespace App\Models;

use App\Enums\NotificationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Notification extends Model
{
    protected $fillable = [
        'uuid',
        'tenant_id',
        'user_id',
        'channel',
        'title',
        'body',
        'payload',
        'status',
        'attempts',
        'max_attempts',
        'scheduled_at',
        'processed_at',
        'failed_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'status' => NotificationStatus::class,
            'payload' => 'array',
            'scheduled_at' => 'datetime',
            'processed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /**
     * Boot the model and set uuid when creating.
     */
    protected static function booted(): void
    {
        // Global scope: automatically filter by tenant
        static::addGlobalScope('tenant', function (Builder $builder) {
            if ($tenantId = app('tenant_id')) {
                $builder->where('tenant_id', $tenantId);
            }
        });

        static::creating(function (Notification $notification) {
            if (empty($notification->uuid)) {
                $notification->uuid = (string) Str::uuid();
            }

            // Auto-set tenant_id from context if missing
            if (empty($notification->tenant_id) && $tenantId = app('tenant_id')) {
                $notification->tenant_id = $tenantId;
            }
        });
    }

    /**
     * Get a new query builder without the tenant scope.
     * Use with caution - only for admin operations.
     *
     * @return Builder
     */
    public static function withoutTenantScope(): Builder
    {
        return static::withoutGlobalScope('tenant');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Cache/distributed lock key for this notification (e.g. for job processing).
     */
    public function lockKey(): string
    {
        return 'notification:'.$this->getKey();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
