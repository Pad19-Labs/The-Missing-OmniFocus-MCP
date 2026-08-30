<?php

use App\OmniFocus\Enums\ProjectStatus;
use App\OmniFocus\Enums\TaskStatus;
use App\OmniFocus\Exceptions\CascadeConfirmationRequired;
use App\OmniFocus\Exceptions\NotFoundException;
use App\OmniFocus\OmniFocusClient;

/*
 * Live write round-trip against the real OmniFocus (pest --group=integration).
 * Every object it creates is loudly prefixed and removed again; afterEach
 * sweeps for leftovers even when an assertion fails mid-flight.
 */

const TEST_PREFIX = 'omafocus test — ';

function writeClient(): OmniFocusClient
{
    return app(OmniFocusClient::class);
}

// Two-step delete: preview to obtain a token, then confirm.
function forceDelete(OmniFocusClient $client, string $type, string $id): void
{
    rescue(function () use ($client, $type, $id) {
        $preview = $client->deleteItem($type, $id);
        $client->deleteItem($type, $id, confirmationToken: $preview['confirmation_token']);
    }, report: false);
}

afterEach(function () {
    $client = writeClient();

    foreach ($client->overview()['folders'] as $folder) {
        if (str_starts_with($folder['name'], TEST_PREFIX)) {
            forceDelete($client, 'folder', $folder['id']);
        }
    }

    foreach ($client->listProjects()['projects'] as $project) {
        if (str_starts_with($project->name, TEST_PREFIX)) {
            forceDelete($client, 'project', $project->id);
        }
    }

    foreach ($client->search(TEST_PREFIX, limit: 50)['tasks'] as $task) {
        forceDelete($client, 'task', $task->id);
    }
});

it('runs the full organize lifecycle: create, file, promote, reorganize, guarded delete', function () {
    $client = writeClient();

    $folder = $client->createFolder(TEST_PREFIX.'folder');
    $project = $client->createProject(TEST_PREFIX.'project', folderId: $folder->id, sequential: true);
    expect($project->folderId)->toBe($folder->id)
        ->and($project->sequential)->toBeTrue();

    // Capture straight to a project
    $task = $client->createTask(TEST_PREFIX.'task in project', projectId: $project->id, flagged: true);
    expect($task->projectId)->toBe($project->id)
        ->and($task->inInbox)->toBeFalse()
        ->and($task->flagged)->toBeTrue();

    // Capture to inbox, then file it (the triage move)
    $inboxTask = $client->createTask(TEST_PREFIX.'inbox task');
    expect($inboxTask->inInbox)->toBeTrue();
    $filed = $client->moveTask($inboxTask->id, projectId: $project->id);
    expect($filed->inInbox)->toBeFalse()
        ->and($filed->projectId)->toBe($project->id);

    // Complete it
    $completed = $client->updateTask($filed->id, ['status' => 'completed']);
    expect($completed->status)->toBe(TaskStatus::Completed)
        ->and($completed->completionDate)->not->toBeNull();

    // Promote an inbox idea to a project (the stacked-ideas move)
    $idea = $client->createTask(TEST_PREFIX.'idea');
    $promoted = $client->promoteTaskToProject($idea->id, folderId: $folder->id, status: ProjectStatus::OnHold);
    expect($promoted->name)->toBe(TEST_PREFIX.'idea')
        ->and($promoted->folderId)->toBe($folder->id)
        ->and($promoted->status)->toBe(ProjectStatus::OnHold);

    // Reorganize: rename the folder, move the project's status around
    $renamed = $client->updateFolder($folder->id, ['name' => TEST_PREFIX.'folder (renamed)']);
    expect($renamed->name)->toBe(TEST_PREFIX.'folder (renamed)');
    $onHold = $client->updateProject($project->id, ['status' => 'on_hold']);
    expect($onHold->status)->toBe(ProjectStatus::OnHold);

    // Two-step delete: preview reports the true recursive count and a token;
    // a first call never deletes.
    $preview = $client->deleteItem('folder', $folder->id);
    expect($preview['requires_confirmation'])->toBeTrue()
        ->and($preview['descendants'])->toBeGreaterThan(0)
        ->and($client->getProject($project->id)['project']->id)->toBe($project->id);

    // A bad token is refused.
    expect(fn () => $client->deleteItem('folder', $folder->id, confirmationToken: 'wrong'))
        ->toThrow(CascadeConfirmationRequired::class);

    // The real token deletes, and reports what it removed.
    $deleted = $client->deleteItem('folder', $folder->id, confirmationToken: $preview['confirmation_token']);
    expect($deleted['deleted'])->toBeTrue()
        ->and($deleted['deleted_descendants'])->toBeGreaterThan(0);

    // Fresh call confirms the cascade took everything with it
    expect(fn () => $client->getProject($project->id))->toThrow(NotFoundException::class);
});
