<?php

namespace App\Http\Requests;

use App\Enums\NotificationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetRecentNotificationsRequest extends FormRequest
{
    /**
     * Prepare the data for validation.
     * Inject tenant_id from context (set by middleware) into request data.
     */
    protected function prepareForValidation(): void
    {
        if (!$this->has('tenant_id') && $tenantId = getTenantId()) {
            $this->merge(['tenant_id' => $tenantId]);
        }
    }

    public function rules(): array
    {
        $statuses = array_map(fn ($case) => $case->value, NotificationStatus::cases());

        return [
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'channel' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', Rule::in($statuses)],
            // tenant_id comes from X-Tenant-ID header, not request body
        ];
    }
}
