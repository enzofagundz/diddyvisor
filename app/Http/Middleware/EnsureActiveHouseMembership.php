<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveHouseMembership
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->canAccessTenant(Filament::getTenant()), 403);

        return $next($request);
    }
}
