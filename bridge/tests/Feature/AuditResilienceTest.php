<?php

use App\Models\AuditLog;
use App\OmniFocus\Contracts\OmniJsRunner;
use App\OmniFocus\OmniFocusClient;
use Illuminate\Support\Facades\Schema;
use Tests\Support\FakeOmniJsRunner;

/*
 * H3: a mutation commits in OmniFocus BEFORE the audit row is written. If the
 * audit insert fails, the successful mutation must still be reported as
 * success (so the agent does not retry and duplicate the write) — with an
 * audit_status warning rather than a thrown error.
 */

beforeEach(function () {
    $this->runner = new FakeOmniJsRunner;
    $this->app->instance(OmniJsRunner::class, $this->runner);
    $this->client = $this->app->make(OmniFocusClient::class);
});

function mutationTask(array $overrides = []): array
{
    return array_merge([
        'id' => 'task1', 'name' => 'New task', 'status' => 'available', 'flagged' => false,
        'in_inbox' => true, 'defer_date' => null, 'due_date' => null, 'completion_date' => null,
        'added' => null, 'modified' => null, 'tags' => [], 'project_id' => null, 'project' => null,
        'parent_id' => null, 'estimated_minutes' => null, 'has_repetition' => false, 'note' => null,
    ], $overrides);
}

it('returns the successful mutation even when the audit write fails', function () {
    $this->runner->queueOk(['task' => mutationTask(['name' => 'Important task'])]);

    // Simulate an unwritable audit log by dropping the table after the app booted.
    Schema::drop('audit_logs');

    // The write succeeded in OmniFocus; the client must NOT throw.
    $task = $this->client->createTask(name: 'Important task');

    expect($task->name)->toBe('Important task');
});

it('records a normal audit row when the log is healthy', function () {
    $this->runner->queueOk(['task' => mutationTask()]);

    $this->client->createTask(name: 'New task');

    expect(AuditLog::where('action', 'create_task')->where('status', 'ok')->count())->toBe(1);
});
