<?php

use App\Mcp\Servers\OmniFocusServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::local('omnifocus', OmniFocusServer::class);
