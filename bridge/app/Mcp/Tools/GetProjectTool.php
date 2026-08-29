<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Get one OmniFocus project by id, with all of its tasks.')]
class GetProjectTool extends OmniFocusTool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate(['id' => ['required', 'string']]);

        return $this->respond(fn () => $this->client->getProject($validated['id']));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The project id.')->required(),
        ];
    }
}
