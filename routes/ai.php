<?php

use App\Mcp\Servers\DiddyVisorServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::local('diddyvisor', DiddyVisorServer::class);
