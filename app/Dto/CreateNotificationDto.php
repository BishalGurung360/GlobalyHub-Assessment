<?php

namespace App\Dto;

/**
 * Data transfer object representing the validated payload
 * for POST /api/notifications.
 */
class CreateNotificationDto extends AutoMappedDto
{
    public function __construct(
        public int|string $userId,
        public string $channel,
        public string $tenantId,
        public string $title,
        public string $body,
        public ?array $payload = null,
        public ?\DateTimeInterface $scheduledAt = null,
        public ?int $maxAttempts = null,
    ) {
    }
}

