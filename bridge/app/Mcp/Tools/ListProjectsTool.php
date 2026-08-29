<?php

namespace App\Mcp\Tools;

use App\OmniFocus\Enums\ProjectStatus;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('List OmniFocus projects with full detail, optionally filtered by status and/or containing folder.')]
class ListProjectsTool extends OmniFocusTool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'folder_id' => ['nullable', 'string'],
        ]);

        return $this->respond(fn () => $this->client->listProjects(
            status: isset($validated['status']) ? ProjectStatus::from($validated['status']) : null,
            folderId: $validated['folder_id'] ?? null,
        ));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()->enum(['active', 'on_hold', 'done', 'dropped'])->description('Only projects with this status.'),
            'folder_id' => $schema->string()->description('Only projects directly inside this folder.'),
        ];
    }
}
