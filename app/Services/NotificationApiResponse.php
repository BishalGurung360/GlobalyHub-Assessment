<?php

namespace App\Services;

use DateTimeInterface;

/**
 * API-safe representation of a notification resource.
 *
 * Intended to be serialized by the controller layer when
 * returning responses from POST /api/notifications.
 */
class NotificationApiResponse
{
    public function __construct(
        public string $uuid,
        public string $status,
        public ?DateTimeInterface $scheduledAt,
        public ?DateTimeInterface $createdAt,
    ) {
    }

    /**
     * Convert the response into an array suitable for JSON serialization.
     */
    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status,
            'scheduled_at' => $this->scheduledAt?->format(DateTimeInterface::ATOM),
            'created_at' => $this->createdAt?->format(DateTimeInterface::ATOM),
        ];
    }
}

