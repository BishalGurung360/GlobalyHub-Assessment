<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GetSummaryNotificationsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'since' => ['sometimes', 'nullable', 'date'],
            'by_channel' => ['sometimes', 'boolean'],
        ];
    }
}
