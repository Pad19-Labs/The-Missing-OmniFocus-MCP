<?php

namespace App\Mcp\Tools;

use App\OmniFocus\Enums\ProjectStatus;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Convert a task (typically an inbox idea) into a full project — the GTD move for captures that are really multi-step outcomes. Optionally place it in a folder and set its status (e.g. on_hold for someday/maybe).')]
class PromoteTaskToProjectTool extends OmniFocusTool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'task_id' => ['required', 'string'],
            'folder_id' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
        ]);

        return $this->respond(fn () => $this->client->promoteTaskToProject(
            $validated['task_id'],
            folderId: $validated['folder_id'] ?? null,
            status: isset($validated['status']) ? ProjectStatus::from($validated['status']) : null,
        ));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->string()->description('The task to convert into a project.')->required(),
            'folder_id' => $schema->string()->description('Folder to place the new project in; omit for the library root.'),
            'status' => $schema->string()->enum(['active', 'on_hold', 'done', 'dropped'])->description('Initial project status.'),
        ];
    }
}
