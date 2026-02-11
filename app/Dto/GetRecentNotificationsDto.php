<?php

namespace App\Dto;

/**
 * Data transfer object for GET /api/v1/notifications/recent query parameters.
 */
class GetRecentNotificationsDto extends AutoMappedDto
{
    public function __construct(
        public int $limit = 20,
        public int $page = 1,
        public ?int $userId = null,
        public ?string $channel = null,
        public ?string $status = null,
        public ?string $tenantId = null,
    ) {
    }
}
