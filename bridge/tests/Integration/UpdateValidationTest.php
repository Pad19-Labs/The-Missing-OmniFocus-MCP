<?php

use App\OmniFocus\Enums\ProjectStatus;
use App\OmniFocus\Exceptions\NotFoundException;
use App\OmniFocus\OmniFocusClient;

/*
 * Live tests for H2: update_project / update_folder must validate every
 * referenced object BEFORE mutating any field, so an invalid destination
 * leaves the item completely unchanged (no rename, no status change).
 * Runs against real OmniFocus (pest --group=integration).
 */

const H2_PREFIX = 'omafocus h2 — ';

function h2Client(): OmniFocusClient
{
    return app(OmniFocusClient::class);
}

afterEach(function () {
    $client = h2Client();
    foreach ($client->overview()['folders'] as $folder) {
        if (str_starts_with($folder['name'], H2_PREFIX)) {
            $t = $client->deleteItem('folder', $folder['id']);
            rescue(fn () => $client->deleteItem('folder', $folder['id'], confirmationToken: $t['confirmation_token']), report: false);
        }
    }
    foreach ($client->listProjects()['projects'] as $project) {
        if (str_starts_with($project->name, H2_PREFIX)) {
            $t = $client->deleteItem('project', $project->id);
            rescue(fn () => $client->deleteItem('project', $project->id, confirmationToken: $t['confirmation_token']), report: false);
        }
    }
});

it('leaves a project fully unchanged when the destination folder is invalid', function () {
    $client = h2Client();
    $project = $client->createProject(H2_PREFIX.'keep me active', status: ProjectStatus::Active);
    $task = $client->createTask(H2_PREFIX.'incomplete task', projectId: $project->id);

    // Attempt an update that renames + marks done + moves to a hallucinated folder.
    expect(fn () => $client->updateProject($project->id, [
        'name' => H2_PREFIX.'RENAMED',
        'status' => 'done',
        'folder_id' => 'this-folder-does-not-exist',
    ]))->toThrow(NotFoundException::class);

    // Nothing changed: name, status, and the task's completion are all intact.
    $after = $client->getProject($project->id);
    expect($after['project']->name)->toBe(H2_PREFIX.'keep me active')
        ->and($after['project']->status)->toBe(ProjectStatus::Active)
        ->and($after['tasks'][0]->status->value)->not->toBe('completed');
});

it('leaves a folder unchanged when the destination parent is invalid', function () {
    $client = h2Client();
    $folder = $client->createFolder(H2_PREFIX.'keep name');

    expect(fn () => $client->updateFolder($folder->id, [
        'name' => H2_PREFIX.'RENAMED',
        'parent_folder_id' => 'nonexistent-parent',
    ]))->toThrow(NotFoundException::class);

    $names = array_map(fn ($f) => $f['name'], $client->overview()['folders']);
    expect($names)->toContain(H2_PREFIX.'keep name')
        ->and($names)->not->toContain(H2_PREFIX.'RENAMED');
});
