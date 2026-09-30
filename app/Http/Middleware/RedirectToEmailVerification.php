<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectToEmailVerification
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('filament.app.tenant') && ! $request->user()->hasVerifiedEmail()) {
            return redirect(Filament::getCurrentOrDefaultPanel()->getEmailVerificationPromptUrl());
        }

        return $next($request);
    }
}
