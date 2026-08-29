<?php

namespace App\Mcp\Tools;

use App\OmniFocus\Enums\ProjectStatus;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Create an OmniFocus project, optionally inside a folder, sequential or parallel, or as a single-action list.')]
class CreateProjectTool extends OmniFocusTool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'name' => ['required', 'string'],
            'folder_id' => ['nullable', 'string'],
            'sequential' => ['nullable', 'boolean'],
            'singleton' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'note' => ['nullable', 'string'],
        ]);

        return $this->respond(fn () => $this->client->createProject(
            $validated['name'],
            folderId: $validated['folder_id'] ?? null,
            sequential: $validated['sequential'] ?? null,
            singleton: $validated['singleton'] ?? false,
            status: isset($validated['status']) ? ProjectStatus::from($validated['status']) : null,
            note: $validated['note'] ?? null,
        ));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('The project name.')->required(),
            'folder_id' => $schema->string()->description('Folder to create the project in; omit for the library root.'),
            'sequential' => $schema->boolean()->description('Tasks must be done in order (true) or in any order (false, default).'),
            'singleton' => $schema->boolean()->description('Create as a single-action list.'),
            'status' => $schema->string()->enum(['active', 'on_hold', 'done', 'dropped'])->description('Initial status (default active).'),
            'note' => $schema->string()->description('Note text for the project.'),
        ];
    }
}
