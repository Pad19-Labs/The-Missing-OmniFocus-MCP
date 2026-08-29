<?php

use App\OmniFocus\Data\ProjectData;
use App\OmniFocus\Data\TaskData;
use App\OmniFocus\Enums\ProjectStatus;
use App\OmniFocus\Enums\TaskStatus;
use App\OmniFocus\Exceptions\NotFoundException;
use App\OmniFocus\Exceptions\ScriptException;
use App\OmniFocus\OmniFocusClient;
use App\OmniFocus\ScriptRepository;
use Tests\Support\FakeOmniJsRunner;

function makeClient(FakeOmniJsRunner $runner): OmniFocusClient
{
    return new OmniFocusClient(
        $runner,
        new ScriptRepository(dirname(__DIR__, 2).'/resources/omnijs'),
    );
}

function sampleTask(array $overrides = []): array
{
    return array_merge([
        'id' => 'abc123',
        'name' => 'Buy compressor oil',
        'status' => 'available',
        'flagged' => true,
        'in_inbox' => true,
        'defer_date' => null,
        'due_date' => '2026-09-01T07:00:00.000Z',
        'completion_date' => null,
        'added' => '2026-08-01T12:00:00.000Z',
        'modified' => '2026-08-02T12:00:00.000Z',
        'tags' => ['errands'],
        'project_id' => null,
        'project' => null,
        'parent_id' => null,
        'estimated_minutes' => 15,
        'has_repetition' => false,
        'note' => 'ISO 100 grade',
    ], $overrides);
}

it('lists inbox tasks as TaskData with parsed status enum', function () {
    $runner = new FakeOmniJsRunner;
    $runner->queueOk(['total' => 1, 'tasks' => [sampleTask()]]);

    $result = makeClient($runner)->listInbox(limit: 25, offset: 5);

    expect($result['total'])->toBe(1)
        ->and($result['tasks'])->toHaveCount(1)
        ->and($result['tasks'][0])->toBeInstanceOf(TaskData::class)
        ->and($result['tasks'][0]->status)->toBe(TaskStatus::Available)
        ->and($result['tasks'][0]->dueDate)->toBe('2026-09-01T07:00:00.000Z')
        ->and($runner->lastScript())->toStartWith('const ARGS = {"limit":25,"offset":5};');
});

it('tolerates terminal noise before the JSON payload', function () {
    $runner = new FakeOmniJsRunner;
    $runner->queue("\x1b]2;junk\x07 garbage ".json_encode([
        'ok' => true,
        'data' => ['total' => 0, 'tasks' => []],
    ]));

    $result = makeClient($runner)->listInbox();

    expect($result['total'])->toBe(0)->and($result['tasks'])->toBe([]);
});

it('maps a not_found error to NotFoundException', function () {
    $runner = new FakeOmniJsRunner;
    $runner->queueError('not_found', 'No task with id nope');

    makeClient($runner)->getTask('nope');
})->throws(NotFoundException::class, 'No task with id nope');

it('maps other script errors to ScriptException', function () {
    $runner = new FakeOmniJsRunner;
    $runner->queueError('script_error', 'boom');

    makeClient($runner)->listInbox();
})->throws(ScriptException::class, 'boom');

it('throws ScriptException when the response is not JSON at all', function () {
    $runner = new FakeOmniJsRunner;
    $runner->queue('total nonsense, no JSON here');

    makeClient($runner)->listInbox();
})->throws(ScriptException::class);

it('fetches a task with its children', function () {
    $runner = new FakeOmniJsRunner;
    $runner->queueOk([
        'task' => sampleTask(['id' => 'parent1']),
        'children' => [sampleTask(['id' => 'child1', 'name' => 'Subtask', 'parent_id' => 'parent1'])],
    ]);

    $result = makeClient($runner)->getTask('parent1');

    expect($result['task']->id)->toBe('parent1')
        ->and($result['children'][0]->id)->toBe('child1')
        ->and($runner->lastScript())->toStartWith('const ARGS = {"id":"parent1"};');
});

it('lists projects as ProjectData with parsed status enum', function () {
    $runner = new FakeOmniJsRunner;
    $runner->queueOk(['total' => 1, 'projects' => [[
        'id' => 'proj1',
        'name' => 'NORDVEST launch',
        'status' => 'on_hold',
        'folder_id' => 'f1',
        'folder' => 'Work',
        'sequential' => true,
        'contains_singleton_actions' => false,
        'defer_date' => null,
        'due_date' => null,
        'task_count' => 4,
        'note' => null,
    ]]]);

    $result = makeClient($runner)->listProjects(status: ProjectStatus::OnHold);

    expect($result['projects'][0])->toBeInstanceOf(ProjectData::class)
        ->and($result['projects'][0]->status)->toBe(ProjectStatus::OnHold)
        ->and($runner->lastScript())->toStartWith('const ARGS = {"status":"on_hold","folder_id":null};');
});

it('searches tasks by query', function () {
    $runner = new FakeOmniJsRunner;
    $runner->queueOk(['total' => 1, 'tasks' => [sampleTask()]]);

    $result = makeClient($runner)->search('compressor', limit: 10);

    expect($result['tasks'][0]->name)->toBe('Buy compressor oil')
        ->and($runner->lastScript())->toStartWith('const ARGS = {"query":"compressor","limit":10};');
});

it('returns the overview payload', function () {
    $runner = new FakeOmniJsRunner;
    $runner->queueOk([
        'counts' => ['inbox' => 486, 'projects' => 130, 'tasks' => 1464, 'tags' => 20, 'folders' => 18],
        'folders' => [],
        'tags' => [],
        'projects' => [],
    ]);

    $overview = makeClient($runner)->overview();

    expect($overview['counts']['inbox'])->toBe(486);
});
