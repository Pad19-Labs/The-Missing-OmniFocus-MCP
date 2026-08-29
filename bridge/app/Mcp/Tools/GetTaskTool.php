<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Get one OmniFocus task by id, with its full note and any child tasks.')]
class GetTaskTool extends OmniFocusTool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(['id' => ['required', 'string']]);

        return $this->respond(fn () => $this->client->getTask($validated['id']));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The task id.')->required(),
        ];
    }
}
