<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenantContext = app(TenantContext::class);
        $tenantContext->clear();

        $user = backpack_user();

        if (config('app.is_tenant') && $user instanceof User) {
            $organization = $user->organization;

            abort_unless($organization !== null, Response::HTTP_FORBIDDEN);

            $tenantContext->set($organization);
        }

        return $next($request);
    }
}
