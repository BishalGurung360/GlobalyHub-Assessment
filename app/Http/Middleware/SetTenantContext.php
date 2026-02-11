<?php

namespace App\Http\Middleware;

use App\Services\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = TenantResolver::resolve($request);
        if (!$tenantId) {
            return response()->json([
                'error' => 'X-Tenant-ID header is required',
            ], 400);
        }

        // Store tenant_id in context for request-scoped access
        Context::add('tenant_id', $tenantId);
        // Merge into request for DTO mapping
        $request->merge(['tenant_id' => $tenantId]);

        return $next($request);
    }
}
