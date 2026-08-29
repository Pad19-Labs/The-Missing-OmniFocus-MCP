<?php

use App\Mcp\Servers\OmniFocusServer;
use App\Mcp\Tools\CreateTaskTool;
use App\Mcp\Tools\DeleteItemTool;
use App\Mcp\Tools\GetOverviewTool;
use App\Mcp\Tools\GetTaskTool;
use App\Mcp\Tools\ListInboxTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\PromoteTaskToProjectTool;
use App\OmniFocus\Contracts\OmniJsRunner;
use Tests\Support\FakeOmniJsRunner;

beforeEach(function () {
    $this->runner = new FakeOmniJsRunner;
    $this->app->instance(OmniJsRunner::class, $this->runner);
});

function mcpTask(array $overrides = []): array
{
    return array_merge([
        'id' => 'task1', 'name' => 'Buy compressor oil', 'status' => 'available', 'flagged' => false,
        'in_inbox' => true, 'defer_date' => null, 'due_date' => null, 'completion_date' => null,
        'added' => null, 'modified' => null, 'tags' => [], 'project_id' => null, 'project' => null,
        'parent_id' => null, 'estimated_minutes' => null, 'has_repetition' => false, 'note' => null,
    ], $overrides);
}

it('serves the overview', function () {
    $this->runner->queueOk([
        'counts' => ['inbox' => 3, 'projects' => 2, 'tasks' => 10, 'tags' => 1, 'folders' => 1],
        'folders' => [], 'tags' => [], 'projects' => [],
    ]);

    OmniFocusServer::tool(GetOverviewTool::class)
        ->assertOk()
        ->assertSee('"inbox":3');
});

it('lists the inbox', function () {
    $this->runner->queueOk(['total' => 1, 'tasks' => [mcpTask()]]);

    OmniFocusServer::tool(ListInboxTool::class, ['limit' => 5])
        ->assertOk()
        ->assertSee('Buy compressor oil');

    expect($this->runner->lastScript())->toContain('"limit":5');
});

it('lists projects filtered by status', function () {
    $this->runner->queueOk(['total' => 0, 'projects' => []]);

    OmniFocusServer::tool(ListProjectsTool::class, ['status' => 'on_hold'])
        ->assertOk();

    expect($this->runner->lastScript())->toContain('"status":"on_hold"');
});

it('creates a task and audits it', function () {
    $this->runner->queueOk(['task' => mcpTask(['name' => 'Call the vendor'])]);

    OmniFocusServer::tool(CreateTaskTool::class, ['name' => 'Call the vendor', 'tags' => ['calls']])
        ->assertOk()
        ->assertSee('Call the vendor');

    $this->assertDatabaseHas('audit_logs', ['action' => 'create_task', 'status' => 'ok']);
});

it('requires a name to create a task', function () {
    OmniFocusServer::tool(CreateTaskTool::class, [])
        ->assertHasErrors();
});

it('promotes a task to a project', function () {
    $this->runner->queueOk(['project' => [
        'id' => 'proj9', 'name' => 'Big idea', 'status' => 'on_hold', 'folder_id' => null,
        'folder' => null, 'sequential' => false, 'contains_singleton_actions' => false,
        'defer_date' => null, 'due_date' => null, 'task_count' => 1, 'note' => null,
    ]]);

    OmniFocusServer::tool(PromoteTaskToProjectTool::class, ['task_id' => 'task1', 'status' => 'on_hold'])
        ->assertOk()
        ->assertSee('Big idea');
});

it('reports a missing task as a tool error, not an exception', function () {
    $this->runner->queueError('not_found', 'No task with id nope');

    OmniFocusServer::tool(GetTaskTool::class, ['id' => 'nope'])
        ->assertHasErrors(['No task with id nope']);
});

it('surfaces the cascade guard as a tool error the agent can react to', function () {
    $this->runner->queueError('cascade_confirmation_required', 'The folder contains 7 item(s). Pass confirm_cascade to delete anyway.');

    OmniFocusServer::tool(DeleteItemTool::class, ['type' => 'folder', 'id' => 'f1'])
        ->assertHasErrors(['The folder contains 7 item(s). Pass confirm_cascade to delete anyway.']);

    $this->assertDatabaseHas('audit_logs', ['action' => 'delete_item', 'status' => 'error']);
});

it('exposes clean kebab-case tool names without a -tool suffix', function () {
    expect(app(ListInboxTool::class)->name())->toBe('list-inbox')
        ->and(app(PromoteTaskToProjectTool::class)->name())->toBe('promote-task-to-project');
});

it('registers all sixteen tools on the server', function () {
    $tools = (new ReflectionClass(OmniFocusServer::class))->getDefaultProperties()['tools'];

    expect($tools)->toHaveCount(16);
});
