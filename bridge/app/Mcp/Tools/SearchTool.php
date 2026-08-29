<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Search all OmniFocus tasks (including the inbox) by name and note text, case-insensitive.')]
class SearchTool extends OmniFocusTool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'query' => ['required', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return $this->respond(fn () => $this->client->search(
            $validated['query'],
            limit: $validated['limit'] ?? 20,
        ));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Text to find in task names and notes.')->required(),
            'limit' => $schema->integer()->description('Maximum tasks to return (default 20, max 100).'),
        ];
    }
}
