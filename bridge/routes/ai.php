<?php

use App\Http\Middleware\EnsureMcpAuthToken;
use App\Mcp\Servers\OmniFocusServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::local('omnifocus', OmniFocusServer::class);

Mcp::web('/mcp', OmniFocusServer::class)
    ->middleware(EnsureMcpAuthToken::class);
