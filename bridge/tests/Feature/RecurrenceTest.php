<?php

use App\OmniFocus\Contracts\OmniJsRunner;
use App\OmniFocus\Data\TaskData;
use App\OmniFocus\Exceptions\ScriptException;
use App\OmniFocus\OmniFocusClient;
use Tests\Support\FakeOmniJsRunner;

beforeEach(function () {
    $this->runner = new FakeOmniJsRunner;
    $this->app->instance(OmniJsRunner::class, $this->runner);
    $this->client = $this->app->make(OmniFocusClient::class);
});

function recTask(array $overrides = []): array
{
    return array_merge([
        'id' => 't1', 'name' => 'Water the plants', 'status' => 'available', 'flagged' => false,
        'in_inbox' => false, 'defer_date' => null, 'due_date' => null, 'completion_date' => null,
        'added' => null, 'modified' => null, 'tags' => [], 'project_id' => null, 'project' => null,
        'parent_id' => null, 'estimated_minutes' => null, 'has_repetition' => true,
        'repetition' => ['rule' => 'FREQ=WEEKLY;INTERVAL=1', 'method' => 'due_date'],
        'note' => null,
    ], $overrides);
}

it('parses a task repetition into a typed value on the DTO', function () {
    $this->runner->queueOk(['task' => recTask(), 'children' => []]);

    $result = $this->client->getTask('t1');
    $task = $result['task'];

    expect($task)->toBeInstanceOf(TaskData::class)
        ->and($task->hasRepetition)->toBeTrue()
        ->and($task->repetition)->not->toBeNull()
        ->and($task->repetition->rule)->toBe('FREQ=WEEKLY;INTERVAL=1')
        ->and($task->repetition->method)->toBe('due_date');
});

it('exposes null repetition on a non-repeating task', function () {
    $this->runner->queueOk(['task' => recTask([
        'has_repetition' => false, 'repetition' => null,
    ]), 'children' => []]);

    $task = $this->client->getTask('t1')['task'];

    expect($task->hasRepetition)->toBeFalse()
        ->and($task->repetition)->toBeNull();
});

it('sets a repetition rule when creating a task', function () {
    $this->runner->queueOk(['task' => recTask(['name' => 'Weekly review'])]);

    $this->client->createTask(
        name: 'Weekly review',
        repetitionRule: 'FREQ=WEEKLY;BYDAY=FR',
        repetitionMethod: 'due_date',
    );

    expect($this->runner->lastScript())
        ->toContain('"repetition_rule":"FREQ=WEEKLY;BYDAY=FR"')
        ->toContain('"repetition_method":"due_date"');
});

it('sets a repetition rule when updating a task', function () {
    $this->runner->queueOk(['task' => recTask()]);

    $this->client->updateTask('t1', [
        'repetition_rule' => 'FREQ=DAILY',
        'repetition_method' => 'defer_until_date',
    ]);

    expect($this->runner->lastScript())
        ->toContain('"repetition_rule":"FREQ=DAILY"')
        ->toContain('"repetition_method":"defer_until_date"');
});

it('clears a repetition rule when passed null', function () {
    $this->runner->queueOk(['task' => recTask(['has_repetition' => false, 'repetition' => null])]);

    $this->client->updateTask('t1', ['repetition_rule' => null]);

    expect($this->runner->lastScript())->toContain('"repetition_rule":null');
});

it('rejects an unknown repetition method before hitting OmniFocus', function () {
    expect(fn () => $this->client->createTask(
        name: 'x',
        repetitionRule: 'FREQ=WEEKLY',
        repetitionMethod: 'nonsense',
    ))->toThrow(ScriptException::class);

    expect($this->runner->scripts)->toBeEmpty();
});
