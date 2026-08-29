<?php

namespace App\Mcp\Tools;

use App\OmniFocus\Exceptions\OmniFocusException;
use App\OmniFocus\OmniFocusClient;
use Illuminate\Support\Str;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

abstract class OmniFocusTool extends Tool
{
    public function __construct(protected OmniFocusClient $client) {}

    public function name(): string
    {
        return Str::kebab(Str::beforeLast(class_basename($this), 'Tool'));
    }

    /**
     * Run a client call and translate bridge failures into MCP tool errors
     * the agent can read and react to, instead of protocol-level crashes.
     */
    protected function respond(callable $callback): Response
    {
        try {
            return Response::json($callback());
        } catch (OmniFocusException $e) {
            return Response::error($e->getMessage());
        }
    }
}
