<?php

use App\OmniFocus\Contracts\OmniJsRunner;
use App\OmniFocus\Exceptions\ScriptException;
use App\OmniFocus\OmniFocusClient;
use Tests\Support\FakeOmniJsRunner;

/*
 * M4: destinations documented as exclusive must be enforced, not silently
 * resolved by precedence. A caller that (from stale context) sends two
 * destinations should get an error, not a silent move to the wrong place.
 */

beforeEach(function () {
    $this->runner = new FakeOmniJsRunner;
    $this->app->instance(OmniJsRunner::class, $this->runner);
    $this->client = $this->app->make(OmniFocusClient::class);
});

it('rejects move-task with more than one destination', function () {
    expect(fn () => $this->client->moveTask('t1', projectId: 'p1', toInbox: true))
        ->toThrow(ScriptException::class);

    // No script ran — rejected before reaching OmniFocus.
    expect($this->runner->scripts)->toBeEmpty();
});

it('rejects move-task with no destination', function () {
    expect(fn () => $this->client->moveTask('t1'))
        ->toThrow(ScriptException::class);
});

it('rejects create-task with both a project and a parent task', function () {
    expect(fn () => $this->client->createTask(name: 'x', projectId: 'p1', parentTaskId: 't1'))
        ->toThrow(ScriptException::class);

    expect($this->runner->scripts)->toBeEmpty();
});

it('accepts move-task with exactly one destination', function () {
    $this->runner->queueOk(['task' => [
        'id' => 't1', 'name' => 'x', 'status' => 'available', 'flagged' => false, 'in_inbox' => false,
        'defer_date' => null, 'due_date' => null, 'completion_date' => null, 'added' => null,
        'modified' => null, 'tags' => [], 'project_id' => 'p1', 'project' => null, 'parent_id' => null,
        'estimated_minutes' => null, 'has_repetition' => false, 'note' => null,
    ]]);

    $task = $this->client->moveTask('t1', projectId: 'p1');
    expect($task->projectId)->toBe('p1');
});
