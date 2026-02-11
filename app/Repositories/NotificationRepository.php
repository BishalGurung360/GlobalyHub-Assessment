<?php

namespace App\Repositories;

use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Repositories\Contracts\NotificationRepositoryContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class NotificationRepository extends BaseRepository implements NotificationRepositoryContract
{
    private const RECENT_CACHE_TTL_SECONDS = 120;

    private const SUMMARY_CACHE_TTL_SECONDS = 300;

    private const MAX_PER_PAGE = 100;

    public function __construct(
        Notification $notification
    ) {
        $this->model = $notification;
        parent::__construct();
    }

    /**
     * Get recent notifications with optional filters, paginated.
     * Cached with short TTL; invalidated on notification write via flushAllCache().
     * Automatically scoped by tenant from context (global scope).
     *
     * @param  array{user_id?: int|string, channel?: string, status?: string, tenant_id?: string}  $filters
     * @return LengthAwarePaginator<Notification>
     */
    public function getRecent(int $limit = 20, int $page = 1, array $filters = []): LengthAwarePaginator
    {
        $perPage = min(max(1, $limit), self::MAX_PER_PAGE);

        // Automatically add tenant_id from context to filters for cache key
        if (empty($filters['tenant_id']) && $tenantId = app('tenant_id')) {
            $filters['tenant_id'] = $tenantId;
        }

        $this->cacheManager->setTTl(self::RECENT_CACHE_TTL_SECONDS);

        $result = $this->cacheManager->make(
            relates: [],
            callback: function () use ($perPage, $page, $filters) {
                // Global scope automatically filters by tenant_id
                $query = $this->model->newQuery()
                    ->select(['id', 'uuid', 'status', 'channel', 'user_id', 'title', 'created_at', 'scheduled_at'])
                    ->orderByDesc('created_at');

                if (! empty($filters['user_id'])) {
                    $query->where('user_id', $filters['user_id']);
                }
                if (! empty($filters['channel'])) {
                    $query->where('channel', $filters['channel']);
                }
                if (! empty($filters['status'])) {
                    $query->where('status', $filters['status']);
                }
                // tenant_id filtering is handled by global scope

                return $query->paginate($perPage, ['*'], 'page', $page);
            },
            isCached: $this->isCached,
            identifier: ['recent', $limit, $page, $filters]
        );

        $this->cacheManager->setTTl($this->cacheTTl * 60);

        return $result;
    }

    /**
     * Get summary counts by status (and optionally by channel).
     * Cached with longer TTL; invalidated on notification write via flushAllCache().
     * Automatically scoped by tenant from context (global scope).
     *
     * @return array{counts_by_status: array<string, int>, total: int, by_channel?: array<string, array<string, int>>}
     */
    public function getSummary(?\DateTimeInterface $since = null, bool $byChannel = false): array
    {
        // Include tenant_id in cache identifier for tenant-specific caching
        $tenantId = app('tenant_id');

        $this->cacheManager->setTTl(self::SUMMARY_CACHE_TTL_SECONDS);

        $result = $this->cacheManager->make(
            relates: [],
            callback: function () use ($since, $byChannel) {
                // Global scope automatically filters by tenant_id
                $statusQuery = $this->model->newQuery();
                if ($since !== null) {
                    $statusQuery->where('created_at', '>=', $since);
                }
                $countsByStatus = $statusQuery
                    ->selectRaw('status, count(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status')
                    ->all();

                $countsByStatus = $this->normalizeCountsByStatus($countsByStatus);

                $total = array_sum($countsByStatus);

                $out = [
                    'counts_by_status' => $countsByStatus,
                    'total' => $total,
                ];

                if ($byChannel) {
                    // Global scope automatically filters by tenant_id
                    $channelQuery = $this->model->newQuery();
                    if ($since !== null) {
                        $channelQuery->where('created_at', '>=', $since);
                    }
                    $rows = $channelQuery->selectRaw('channel, status, count(*) as count')
                        ->groupBy('channel', 'status')
                        ->get();

                    $byChannelMap = [];
                    foreach ($rows as $row) {
                        $ch = $row->channel;
                        if (! isset($byChannelMap[$ch])) {
                            $byChannelMap[$ch] = $this->normalizeCountsByStatus([]);
                        }
                        $byChannelMap[$ch][$row->status->value] = (int) $row->count;
                    }
                    $out['by_channel'] = $byChannelMap;
                }

                return $out;
            },
            isCached: $this->isCached,
            identifier: ['summary', $tenantId, $since?->getTimestamp(), $byChannel]
        );

        $this->cacheManager->setTTl($this->cacheTTl * 60);

        return $result;
    }

    /**
     * Ensure all NotificationStatus values exist in the map with 0 where missing.
     *
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    private function normalizeCountsByStatus(array $counts): array
    {
        $normalized = [];
        foreach (NotificationStatus::cases() as $status) {
            $normalized[$status->value] = isset($counts[$status->value])
                ? (int) $counts[$status->value]
                : 0;
        }

        return $normalized;
    }
}