<?php

namespace App\Services;

use Illuminate\Http\Request;

class TenantResolver
{
    /**
     * Resolve tenant ID from the request.
     * Reads from X-Tenant-ID header.
     *
     * @param  Request  $request
     * @return string|null
     */
    public static function resolve(Request $request): ?string
    {
        return $request->header('X-Tenant-ID');
    }
}
