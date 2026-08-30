<?php

use App\OmniFocus\OmniFocusClient;

/*
 * Live proof that recurrence round-trips against real OmniFocus
 * (pest --group=integration): set a rule on create, read it back, change it,
 * clear it. Disposable, prefixed, cleaned up.
 */

const REC_PREFIX = 'omafocus rec — ';

function recClient(): OmniFocusClient
{
    return app(OmniFocusClient::class);
}

afterEach(function () {
    $client = recClient();
    foreach ($client->search(REC_PREFIX, limit: 50)['tasks'] as $task) {
        rescue(function () use ($client, $task) {
            $preview = $client->deleteItem('task', $task->id);
            $client->deleteItem('task', $task->id, confirmationToken: $preview['confirmation_token']);
        }, report: false);
    }
});

it('creates, reads, updates, and clears a repetition rule on a real task', function () {
    $client = recClient();

    // Create with a weekly rule.
    $task = $client->createTask(
        name: REC_PREFIX.'weekly',
        due: '2026-09-01T17:00:00Z',
        repetitionRule: 'FREQ=WEEKLY;INTERVAL=1',
        repetitionMethod: 'due_date',
    );

    expect($task->hasRepetition)->toBeTrue()
        ->and($task->repetition->rule)->toBe('FREQ=WEEKLY;INTERVAL=1')
        ->and($task->repetition->method)->toBe('due_date');

    // Read it back through getTask.
    $fetched = $client->getTask($task->id)['task'];
    expect($fetched->repetition->rule)->toBe('FREQ=WEEKLY;INTERVAL=1');

    // Change the cadence to daily.
    $updated = $client->updateTask($task->id, ['repetition_rule' => 'FREQ=DAILY']);
    expect($updated->repetition->rule)->toBe('FREQ=DAILY');

    // Clear it.
    $cleared = $client->updateTask($task->id, ['repetition_rule' => null]);
    expect($cleared->hasRepetition)->toBeFalse()
        ->and($cleared->repetition)->toBeNull();
});
