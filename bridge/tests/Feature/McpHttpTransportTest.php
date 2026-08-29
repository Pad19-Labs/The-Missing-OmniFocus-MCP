<?php

use App\OmniFocus\Contracts\OmniJsRunner;
use Tests\Support\FakeOmniJsRunner;

beforeEach(function () {
    config()->set('services.omnifocus_bridge.token', 'test-token-123');
    $this->app->instance(OmniJsRunner::class, new FakeOmniJsRunner);
});

function initializePayload(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-03-26',
            'capabilities' => (object) [],
            'clientInfo' => ['name' => 'test', 'version' => '1'],
        ],
    ];
}

it('rejects /mcp without a bearer token', function () {
    $this->postJson('/mcp', initializePayload())
        ->assertUnauthorized();
});

it('rejects /mcp with a wrong bearer token', function () {
    $this->postJson('/mcp', initializePayload(), ['Authorization' => 'Bearer wrong'])
        ->assertUnauthorized();
});

it('rejects /mcp when no token is configured at all (fail closed)', function () {
    config()->set('services.omnifocus_bridge.token', null);

    $this->postJson('/mcp', initializePayload(), ['Authorization' => 'Bearer anything'])
        ->assertUnauthorized();
});

it('serves the MCP initialize handshake with a valid bearer token', function () {
    $response = $this->postJson('/mcp', initializePayload(), ['Authorization' => 'Bearer test-token-123']);

    $response->assertOk();
    expect($response->getContent())->toContain('"name":"OmniFocus"');
});
