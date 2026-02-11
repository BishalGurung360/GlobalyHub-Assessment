<?php

namespace App\Dto;

/**
 * Data transfer object for GET /api/v1/notifications/summary query parameters.
 */
class GetSummaryNotificationsDto extends AutoMappedDto
{
    public function __construct(
        public ?\DateTimeInterface $since = null,
        public bool $byChannel = false,
    ) {
    }
}
