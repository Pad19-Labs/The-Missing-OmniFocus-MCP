<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Permanently delete a task, project, or folder. This is a TWO-STEP operation: call it first WITHOUT confirmation_token to preview the target and its true recursive descendant count — you get back a single-use confirmation_token. Then call again WITH that token to actually delete. Deleting a container removes everything inside it, so always read the descendant count in the preview before confirming. Prefer marking things dropped over deleting when history matters.')]
class DeleteItemTool extends OmniFocusTool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'type' => ['required', 'in:task,project,folder'],
            'id' => ['required', 'string'],
            'confirmation_token' => ['nullable', 'string'],
        ]);

        return $this->respond(fn () => $this->client->deleteItem(
            $validated['type'],
            $validated['id'],
            confirmationToken: $validated['confirmation_token'] ?? null,
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
            'confirmation_token' => $schema->string()->description('The single-use token from a prior preview call. Omit on the first call to preview; supply it on the second call to delete.'),
        ];
    }
}
