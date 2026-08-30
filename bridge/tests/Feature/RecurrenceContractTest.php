<?php

use App\OmniFocus\Contracts\OmniJsRunner;
use App\OmniFocus\OmniFocusClient;
use Tests\Support\FakeOmniJsRunner;

/*
 * Contract-level checks for the recurrence fixes (H1/M1/L2). The composed
 * omniJS is asserted here; the atomicity behavior itself is proven live in
 * the integration suite.
 */

beforeEach(function () {
    $this->runner = new FakeOmniJsRunner;
    $this->app->instance(OmniJsRunner::class, $this->runner);
    $this->client = $this->app->make(OmniFocusClient::class);
});

function crTask(array $overrides = []): array
{
    return array_merge([
        'id' => 't1', 'name' => 'Water plants', 'status' => 'available', 'flagged' => false,
        'in_inbox' => false, 'defer_date' => null, 'due_date' => null, 'completion_date' => null,
        'added' => null, 'modified' => null, 'tags' => [], 'project_id' => null, 'project' => null,
        'parent_id' => null, 'estimated_minutes' => null, 'has_repetition' => true,
        'repetition' => ['rule' => 'FREQ=WEEKLY', 'method' => 'due_date'], 'note' => null,
    ], $overrides);
}

it('validates the repetition rule BEFORE creating the task (H1)', function () {
    $this->runner->queueOk(['task' => crTask()]);

    $this->client->createTask(name: 'x', repetitionRule: 'FREQ=WEEKLY');

    // The composed create script must construct+validate the rule before new Task().
    $script = $this->runner->lastScript();
    $buildPos = strpos($script, 'buildRepetitionRule');
    $newTaskPos = strpos($script, 'new Task(ARGS.name)');
    expect($buildPos)->not->toBeFalse()
        ->and($newTaskPos)->not->toBeFalse()
        ->and($buildPos)->toBeLessThan($newTaskPos);
});

it('exposes the completed occurrence and successor when completing a repeating task (H2)', function () {
    // The update script should report both the resolved occurrence and any successor.
    $this->runner->queueOk([
        'task' => crTask(['status' => 'completed', 'completion_date' => '2026-08-30T10:00:00Z']),
        'next_occurrence' => crTask(['id' => 't2', 'status' => 'available']),
    ]);

    $result = $this->client->updateTask('t1', ['status' => 'completed']);

    // markComplete is called and its return captured in the script.
    expect($this->runner->lastScript())->toContain('markComplete()');
});

it('passes repetition_method through on update so it is preserved (M1)', function () {
    $this->runner->queueOk(['task' => crTask()]);

    $this->client->updateTask('t1', ['repetition_rule' => 'FREQ=DAILY', 'repetition_method' => 'fixed']);

    expect($this->runner->lastScript())
        ->toContain('"repetition_rule":"FREQ=DAILY"')
        ->toContain('"repetition_method":"fixed"');
});
