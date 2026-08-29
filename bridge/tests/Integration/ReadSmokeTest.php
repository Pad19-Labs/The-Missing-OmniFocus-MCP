<?php

use App\OmniFocus\Data\ProjectData;
use App\OmniFocus\Data\TaskData;
use App\OmniFocus\Exceptions\ScriptException;
use App\OmniFocus\OmniFocusClient;

/*
 * Runs against the real OmniFocus on this Mac (pest --group=integration).
 * Reads only; never asserts on the contents of real data, only on shape.
 * If these fail with an AppleEvent/authorization error, grant this process
 * "Automation → OmniFocus" permission in System Settings → Privacy & Security.
 */

function client(): OmniFocusClient
{
    return app(OmniFocusClient::class);
}

it('reads a live overview with sane counts', function () {
    try {
        $overview = client()->overview();
    } catch (ScriptException $e) {
        if (str_contains($e->getMessage(), 'Not authorized')) {
            $this->fail("macOS blocked automation of OmniFocus (TCC). Grant this terminal Automation permission.\n".$e->getMessage());
        }
        throw $e;
    }

    expect($overview['counts'])->toHaveKeys(['inbox', 'projects', 'tasks', 'tags', 'folders'])
        ->and($overview['counts']['tasks'])->toBeGreaterThanOrEqual(0)
        ->and($overview['folders'])->toBeArray()
        ->and($overview['projects'])->toBeArray();
});

it('lists live inbox tasks as valid DTOs', function () {
    $result = client()->listInbox(limit: 5);

    expect($result['total'])->toBeGreaterThanOrEqual(0)
        ->and($result['tasks'])->each->toBeInstanceOf(TaskData::class);
});

it('lists live projects as valid DTOs', function () {
    $result = client()->listProjects();

    expect($result['projects'])->each->toBeInstanceOf(ProjectData::class);
});
