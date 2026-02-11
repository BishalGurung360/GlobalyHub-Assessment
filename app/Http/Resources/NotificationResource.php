<?php

namespace App\Http\Resources;

use App\Enums\NotificationStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'status' => $this->status instanceof NotificationStatus
                ? $this->status->value
                : $this->status,
            'channel' => $this->channel,
            'user_id' => $this->user_id,
            'title' => $this->title,
            'created_at' => $this->created_at?->toIso8601String(),
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
        ];
    }
}
