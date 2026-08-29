<?php

namespace App\Mcp\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Get a full overview of the OmniFocus database: counts, the folder tree, all tags, and a light list of every project (id, name, status, folder, task count). Call this first to orient yourself; it also serves as a health check.')]
class GetOverviewTool extends OmniFocusTool
{
    public function handle(Request $request): Response
    {
        return $this->respond(fn () => $this->client->overview());
    }
}
