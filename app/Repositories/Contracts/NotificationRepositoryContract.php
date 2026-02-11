<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Contract for notification repository operations.
 */
interface NotificationRepositoryContract extends BaseRepositoryContract
{
    /**
     * Get recent notifications with optional filters, paginated.
     *
     * @param  int  $limit
     * @param  int  $page
     * @param  array{user_id?: int|string, channel?: string, status?: string, tenant_id?: string}  $filters
     * @return LengthAwarePaginator
     */
    public function getRecent(int $limit = 20, int $page = 1, array $filters = []): LengthAwarePaginator;

    /**
     * Get summary counts by status (and optionally by channel).
     *
     * @param  \DateTimeInterface|null  $since
     * @param  bool  $byChannel
     * @return array{counts_by_status: array<string, int>, total: int, by_channel?: array<string, array<string, int>>}
     */
    public function getSummary(?\DateTimeInterface $since = null, bool $byChannel = false): array;
}
