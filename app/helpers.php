<?php

use Illuminate\Support\Facades\Context;

if (! function_exists('getTenantId')) {
    /**
     * Get the current tenant ID from context.
     *
     * @return string|null
     */
    function getTenantId(): ?string
    {
        return Context::get('tenant_id');
    }
}
