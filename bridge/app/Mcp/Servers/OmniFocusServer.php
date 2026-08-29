<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateFolderTool;
use App\Mcp\Tools\CreateProjectTool;
use App\Mcp\Tools\CreateTaskTool;
use App\Mcp\Tools\DeleteItemTool;
use App\Mcp\Tools\GetOverviewTool;
use App\Mcp\Tools\GetProjectTool;
use App\Mcp\Tools\GetTaskTool;
use App\Mcp\Tools\ListInboxTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\ListTasksTool;
use App\Mcp\Tools\MoveTaskTool;
use App\Mcp\Tools\PromoteTaskToProjectTool;
use App\Mcp\Tools\SearchTool;
use App\Mcp\Tools\UpdateFolderTool;
use App\Mcp\Tools\UpdateProjectTool;
use App\Mcp\Tools\UpdateTaskTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('OmniFocus')]
#[Version('0.1.0')]
#[Instructions(<<<'MD'
Full read/write access to the user's OmniFocus database (GTD task manager).
Start with get-overview to learn the folder/project structure. Ids are stable —
use them, not names, to address items. The database is live: the user may be
editing it at the same time, so re-fetch an item before mutating it if your
information is old. Every write is audit-logged. Deletes of items with children
require confirm_cascade; prefer status changes (dropped, on_hold) over deletion
when history matters. Writes reach the user's other devices when OmniFocus syncs.
MD)]
class OmniFocusServer extends Server
{
    protected array $tools = [
        GetOverviewTool::class,
        ListInboxTool::class,
        ListProjectsTool::class,
        ListTasksTool::class,
        SearchTool::class,
        GetTaskTool::class,
        GetProjectTool::class,
        CreateTaskTool::class,
        UpdateTaskTool::class,
        MoveTaskTool::class,
        PromoteTaskToProjectTool::class,
        CreateProjectTool::class,
        UpdateProjectTool::class,
        CreateFolderTool::class,
        UpdateFolderTool::class,
        DeleteItemTool::class,
    ];

    protected array $resources = [];

    protected array $prompts = [];
}
