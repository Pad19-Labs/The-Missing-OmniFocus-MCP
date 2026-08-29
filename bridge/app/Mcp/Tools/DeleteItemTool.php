<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Permanently delete a task, project, or folder. Deleting a container removes everything inside it, so an item with children is refused unless confirm_cascade is true — check the error message for the contents count before confirming. Prefer marking things dropped over deleting when history matters.')]
class DeleteItemTool extends OmniFocusTool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'type' => ['required', 'in:task,project,folder'],
            'id' => ['required', 'string'],
            'confirm_cascade' => ['nullable', 'boolean'],
        ]);

        return $this->respond(fn () => $this->client->deleteItem(
            $validated['type'],
            $validated['id'],
            confirmCascade: $validated['confirm_cascade'] ?? false,
        ));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(['task', 'project', 'folder'])->description('What kind of item to delete.')->required(),
            'id' => $schema->string()->description('The item id.')->required(),
            'confirm_cascade' => $schema->boolean()->description('Required true to delete an item that still contains children.'),
        ];
    }
}
