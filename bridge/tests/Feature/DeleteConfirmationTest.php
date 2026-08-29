<?php

use App\Models\DeletionToken;
use App\OmniFocus\Contracts\OmniJsRunner;
use App\OmniFocus\Exceptions\CascadeConfirmationRequired;
use App\OmniFocus\Exceptions\NotFoundException;
use App\OmniFocus\OmniFocusClient;
use Tests\Support\FakeOmniJsRunner;

beforeEach(function () {
    $this->runner = new FakeOmniJsRunner;
    $this->app->instance(OmniJsRunner::class, $this->runner);
    $this->client = $this->app->make(OmniFocusClient::class);
});

it('previews a delete and returns a confirmation token instead of deleting', function () {
    // The preview script reports the item and its recursive descendant count.
    $this->runner->queueOk([
        'id' => 'f1', 'type' => 'folder', 'name' => 'Work', 'descendants' => 2847,
    ]);

    $preview = $this->client->deleteItem('folder', 'f1');

    expect($preview['requires_confirmation'])->toBeTrue()
        ->and($preview['descendants'])->toBe(2847)
        ->and($preview['name'])->toBe('Work')
        ->and($preview['confirmation_token'])->toBeString()
        ->and(strlen($preview['confirmation_token']))->toBeGreaterThanOrEqual(32);

    // Nothing was deleted: the preview script ran (no deleteObject, no confirmed flag).
    expect($this->runner->scripts)->toHaveCount(1)
        ->and($this->runner->lastScript())->not->toContain('deleteObject')
        ->and($this->runner->lastScript())->not->toContain('"confirmed"');

    // A token row exists, bound to this exact target.
    $token = DeletionToken::sole();
    expect($token->type)->toBe('folder')
        ->and($token->item_id)->toBe('f1')
        ->and($token->descendants)->toBe(2847);
});

it('refuses a confirm token that does not exist', function () {
    expect(fn () => $this->client->deleteItem('folder', 'f1', confirmationToken: 'made-up-token'))
        ->toThrow(CascadeConfirmationRequired::class);

    // No delete script ran.
    expect($this->runner->scripts)->toBeEmpty();
});

it('deletes when given the matching confirmation token, then consumes it', function () {
    $this->runner->queueOk(['id' => 'f1', 'type' => 'folder', 'name' => 'Work', 'descendants' => 3]);
    $preview = $this->client->deleteItem('folder', 'f1');
    $token = $preview['confirmation_token'];

    $this->runner->queueOk(['id' => 'f1', 'type' => 'folder', 'name' => 'Work', 'deleted_descendants' => 3]);
    $result = $this->client->deleteItem('folder', 'f1', confirmationToken: $token);

    expect($result['deleted'])->toBeTrue()
        ->and($result['deleted_descendants'])->toBe(3)
        ->and($this->runner->lastScript())->toContain('deleteObject')
        ->and($this->runner->lastScript())->toContain('"confirmed":true');

    // Token is single-use: a replay is rejected.
    $this->runner->queueOk(['id' => 'f1', 'type' => 'folder', 'name' => 'Work', 'deleted_descendants' => 3]);
    expect(fn () => $this->client->deleteItem('folder', 'f1', confirmationToken: $token))
        ->toThrow(CascadeConfirmationRequired::class);

    $this->assertDatabaseHas('audit_logs', ['action' => 'delete_item', 'status' => 'ok']);
});

it('rejects a token whose target does not match the requested item', function () {
    $this->runner->queueOk(['id' => 'f1', 'type' => 'folder', 'name' => 'Work', 'descendants' => 3]);
    $token = $this->client->deleteItem('folder', 'f1')['confirmation_token'];

    // Same token, different id — must not authorize deleting f2.
    expect(fn () => $this->client->deleteItem('folder', 'f2', confirmationToken: $token))
        ->toThrow(CascadeConfirmationRequired::class);
});

it('rejects an expired token', function () {
    $this->runner->queueOk(['id' => 'f1', 'type' => 'folder', 'name' => 'Work', 'descendants' => 3]);
    $token = $this->client->deleteItem('folder', 'f1')['confirmation_token'];

    DeletionToken::query()->update(['expires_at' => now()->subMinute()]);

    expect(fn () => $this->client->deleteItem('folder', 'f1', confirmationToken: $token))
        ->toThrow(CascadeConfirmationRequired::class);
});

it('previews a not-found item as an error, without a token', function () {
    $this->runner->queueError('not_found', 'No folder with id ghost');

    expect(fn () => $this->client->deleteItem('folder', 'ghost'))
        ->toThrow(NotFoundException::class);

    expect(DeletionToken::count())->toBe(0);
});

it('still deletes an empty item on preview+confirm (descendants zero)', function () {
    $this->runner->queueOk(['id' => 't1', 'type' => 'task', 'name' => 'Lone task', 'descendants' => 0]);
    $token = $this->client->deleteItem('task', 't1')['confirmation_token'];

    $this->runner->queueOk(['id' => 't1', 'type' => 'task', 'name' => 'Lone task', 'deleted_descendants' => 0]);
    $result = $this->client->deleteItem('task', 't1', confirmationToken: $token);

    expect($result['deleted'])->toBeTrue();
});
