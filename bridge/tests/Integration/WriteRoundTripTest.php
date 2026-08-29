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

afterEach(function () {
    $client = writeClient();

    foreach ($client->overview()['folders'] as $folder) {
        if (str_starts_with($folder['name'], TEST_PREFIX)) {
            rescue(fn () => $client->deleteItem('folder', $folder['id'], confirmCascade: true), report: false);
        }
    }

    foreach ($client->listProjects()['projects'] as $project) {
        if (str_starts_with($project->name, TEST_PREFIX)) {
            rescue(fn () => $client->deleteItem('project', $project->id, confirmCascade: true), report: false);
        }
    }

    foreach ($client->search(TEST_PREFIX, limit: 50)['tasks'] as $task) {
        rescue(fn () => $client->deleteItem('task', $task->id, confirmCascade: true), report: false);
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

    // Guarded delete: refuse without confirmation, then delete with it
    expect(fn () => $client->deleteItem('folder', $folder->id))
        ->toThrow(CascadeConfirmationRequired::class);

    $deleted = $client->deleteItem('folder', $folder->id, confirmCascade: true);
    expect($deleted['children'])->toBeGreaterThan(0);

    // Fresh call confirms the cascade took everything with it
    expect(fn () => $client->getProject($project->id))->toThrow(NotFoundException::class);
});
