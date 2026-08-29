<?php

use App\Models\AuditLog;
use App\OmniFocus\Contracts\OmniJsRunner;
use App\OmniFocus\Data\FolderData;
use App\OmniFocus\Data\ProjectData;
use App\OmniFocus\Data\TaskData;
use App\OmniFocus\Enums\ProjectStatus;
use App\OmniFocus\Enums\TaskStatus;
use App\OmniFocus\Exceptions\CascadeConfirmationRequired;
use App\OmniFocus\Exceptions\NotFoundException;
use App\OmniFocus\OmniFocusClient;
use Tests\Support\FakeOmniJsRunner;

beforeEach(function () {
    $this->runner = new FakeOmniJsRunner;
    $this->app->instance(OmniJsRunner::class, $this->runner);
    $this->client = $this->app->make(OmniFocusClient::class);
});

function fakeTask(array $overrides = []): array
{
    return array_merge([
        'id' => 'task1', 'name' => 'New task', 'status' => 'available', 'flagged' => false,
        'in_inbox' => true, 'defer_date' => null, 'due_date' => null, 'completion_date' => null,
        'added' => null, 'modified' => null, 'tags' => [], 'project_id' => null, 'project' => null,
        'parent_id' => null, 'estimated_minutes' => null, 'has_repetition' => false, 'note' => null,
    ], $overrides);
}

function fakeProject(array $overrides = []): array
{
    return array_merge([
        'id' => 'proj1', 'name' => 'New project', 'status' => 'active', 'folder_id' => null,
        'folder' => null, 'sequential' => false, 'contains_singleton_actions' => false,
        'defer_date' => null, 'due_date' => null, 'task_count' => 0, 'note' => null,
    ], $overrides);
}

it('creates a task and records an audit row', function () {
    $this->runner->queueOk(['task' => fakeTask(['name' => 'Call the vendor', 'flagged' => true])]);

    $task = $this->client->createTask(name: 'Call the vendor', flagged: true, tags: ['calls']);

    expect($task)->toBeInstanceOf(TaskData::class)
        ->and($task->name)->toBe('Call the vendor')
        ->and($this->runner->lastScript())->toContain('"name":"Call the vendor"')
        ->toContain('"flagged":true')
        ->toContain('"tags":["calls"]');

    $this->assertDatabaseHas('audit_logs', ['action' => 'create_task', 'status' => 'ok']);
    expect(AuditLog::sole()->duration_ms)->toBeGreaterThanOrEqual(0);
});

it('updates a task including completion status', function () {
    $this->runner->queueOk(['task' => fakeTask(['status' => 'completed', 'completion_date' => '2026-08-29T12:00:00.000Z'])]);

    $task = $this->client->updateTask('task1', ['status' => 'completed']);

    expect($task->status)->toBe(TaskStatus::Completed)
        ->and($this->runner->lastScript())->toContain('"status":"completed"');

    $this->assertDatabaseHas('audit_logs', ['action' => 'update_task', 'status' => 'ok']);
});

it('moves a task to a project', function () {
    $this->runner->queueOk(['task' => fakeTask(['in_inbox' => false, 'project_id' => 'proj1'])]);

    $task = $this->client->moveTask('task1', projectId: 'proj1');

    expect($task->inInbox)->toBeFalse()
        ->and($this->runner->lastScript())->toContain('"project_id":"proj1"');
});

it('moves a task back to the inbox', function () {
    $this->runner->queueOk(['task' => fakeTask(['in_inbox' => true])]);

    $task = $this->client->moveTask('task1', toInbox: true);

    expect($task->inInbox)->toBeTrue()
        ->and($this->runner->lastScript())->toContain('"to_inbox":true');
});

it('promotes a task to a project', function () {
    $this->runner->queueOk(['project' => fakeProject(['name' => 'Big idea', 'status' => 'on_hold'])]);

    $project = $this->client->promoteTaskToProject('task1', folderId: 'f1', status: ProjectStatus::OnHold);

    expect($project)->toBeInstanceOf(ProjectData::class)
        ->and($project->status)->toBe(ProjectStatus::OnHold)
        ->and($this->runner->lastScript())->toContain('"folder_id":"f1"');

    $this->assertDatabaseHas('audit_logs', ['action' => 'promote_task_to_project', 'status' => 'ok']);
});

it('creates a project in a folder', function () {
    $this->runner->queueOk(['project' => fakeProject(['folder_id' => 'f1'])]);

    $project = $this->client->createProject('New project', folderId: 'f1', sequential: true);

    expect($project->folderId)->toBe('f1')
        ->and($this->runner->lastScript())->toContain('"sequential":true');
});

it('updates a project including a folder move', function () {
    $this->runner->queueOk(['project' => fakeProject(['folder_id' => 'f2', 'status' => 'on_hold'])]);

    $project = $this->client->updateProject('proj1', ['folder_id' => 'f2', 'status' => 'on_hold']);

    expect($project->status)->toBe(ProjectStatus::OnHold)
        ->and($this->runner->lastScript())->toContain('"folder_id":"f2"');
});

it('creates and updates folders', function () {
    $this->runner->queueOk(['folder' => ['id' => 'f9', 'name' => 'Someday', 'parent_id' => null, 'status' => 'active']]);
    $folder = $this->client->createFolder('Someday');
    expect($folder)->toBeInstanceOf(FolderData::class)->and($folder->name)->toBe('Someday');

    $this->runner->queueOk(['folder' => ['id' => 'f9', 'name' => 'Someday/Maybe', 'parent_id' => 'f1', 'status' => 'active']]);
    $folder = $this->client->updateFolder('f9', ['name' => 'Someday/Maybe', 'parent_folder_id' => 'f1']);
    expect($folder->parentId)->toBe('f1');
});

it('refuses to delete an item with children without confirmation', function () {
    $this->runner->queueError('cascade_confirmation_required', 'The folder contains 7 item(s). Pass confirm_cascade to delete anyway.');

    try {
        $this->client->deleteItem('folder', 'f1');
        $this->fail('Expected CascadeConfirmationRequired');
    } catch (CascadeConfirmationRequired $e) {
        expect($e->getMessage())->toContain('7 item(s)');
    }

    $this->assertDatabaseHas('audit_logs', ['action' => 'delete_item', 'status' => 'error']);
});

it('deletes with confirm_cascade and records the audit row', function () {
    $this->runner->queueOk(['id' => 'f1', 'type' => 'folder', 'name' => 'Old area', 'children' => 7]);

    $result = $this->client->deleteItem('folder', 'f1', confirmCascade: true);

    expect($result['children'])->toBe(7)
        ->and($this->runner->lastScript())->toContain('"confirm_cascade":true');

    $this->assertDatabaseHas('audit_logs', ['action' => 'delete_item', 'status' => 'ok']);
});

it('records failed mutations in the audit log and rethrows', function () {
    $this->runner->queueError('not_found', 'No task with id nope');

    expect(fn () => $this->client->updateTask('nope', ['name' => 'x']))
        ->toThrow(NotFoundException::class);

    $this->assertDatabaseHas('audit_logs', ['action' => 'update_task', 'status' => 'error']);
});

it('does not write audit rows for reads', function () {
    $this->runner->queueOk(['total' => 0, 'tasks' => []]);

    $this->client->listInbox();

    $this->assertDatabaseCount('audit_logs', 0);
});
