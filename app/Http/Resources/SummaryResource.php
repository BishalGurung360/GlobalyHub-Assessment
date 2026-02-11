<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'counts_by_status' => $this->resource['counts_by_status'] ?? [],
            'total' => $this->resource['total'] ?? 0,
            'by_channel' => $this->resource['by_channel'] ?? null,
        ];
    }
}
