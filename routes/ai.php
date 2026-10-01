<?php

use App\Mcp\Servers\DiddyVisorServer;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Registrar;
use Laravel\Passport\Http\Middleware\CheckTokenForAnyScope;

Mcp::web('/mcp', DiddyVisorServer::class)
    ->middleware(['auth:api', CheckTokenForAnyScope::using(Registrar::OAUTH_SCOPE), 'throttle:60,1']);

Mcp::local('diddyvisor', DiddyVisorServer::class);

Mcp::oauthRoutes();
