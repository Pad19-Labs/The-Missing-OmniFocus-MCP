<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Update an OmniFocus project: rename, edit note, change status (active / on_hold / done / dropped), toggle sequential, set dates, or move it to another folder. Only the fields you pass are changed.')]
class UpdateProjectTool extends OmniFocusTool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'id' => ['required', 'string'],
            'name' => ['sometimes', 'string'],
            'note' => ['sometimes', 'string'],
            'folder_id' => ['sometimes', 'string'],
            'status' => ['sometimes', 'in:active,on_hold,done,dropped'],
            'sequential' => ['sometimes', 'boolean'],
            'due' => ['sometimes', 'nullable', 'date'],
            'defer' => ['sometimes', 'nullable', 'date'],
        ]);

        $fields = collect($validated)->except('id')->all();

        return $this->respond(fn () => $this->client->updateProject($validated['id'], $fields));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The project id.')->required(),
            'name' => $schema->string()->description('New project name.'),
            'note' => $schema->string()->description('New note text.'),
            'folder_id' => $schema->string()->description('Move the project into this folder.'),
            'status' => $schema->string()->enum(['active', 'on_hold', 'done', 'dropped'])->description('New status.'),
            'sequential' => $schema->boolean()->description('Tasks must be done in order.'),
            'due' => $schema->string()->description('New due date (ISO 8601); pass null to clear.'),
            'defer' => $schema->string()->description('New defer date (ISO 8601); pass null to clear.'),
        ];
    }
}
