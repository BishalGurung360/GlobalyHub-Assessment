<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class StoreNotificationRequest extends FormRequest
{
    /**
     * Prepare the data for validation.
     * Inject tenant_id from context (set by middleware) into request data.
     */
    protected function prepareForValidation(): void
    {
        if (!$this->has('tenant_id') && $tenantId = app('tenant_id')) {
            $this->merge(['tenant_id' => $tenantId]);
        }
    }

    public function rules(): array
    {
        $availableChannels = Arr::pluck(config('notification_channels'), "channel");
        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
            'channel' => [
                'required',
                'string',
                // This is static
                // Rule::in(['log', 'email', 'sms']),

                // This is dynamic
                // When you add a new channel to config, you don't need to update this rule
                // It will reflect automatically
                Rule::in($availableChannels),
            ],
            'tenant_id' => [
                'required',
                'string',
                'max:255',
            ],
            'title' => [
                'required',
                'string',
                'max:255',
            ],
            'body' => [
                'required',
                'string',
            ],
            'payload' => [
                'nullable',
                'array',
            ],
            'scheduled_at' => [
                'nullable',
                'date',
                'after_or_equal:now',
            ],
            'max_attempts' => [
                'nullable',
                'integer',
                'min:1',
                'max:10',
            ],
        ];
    }
}
