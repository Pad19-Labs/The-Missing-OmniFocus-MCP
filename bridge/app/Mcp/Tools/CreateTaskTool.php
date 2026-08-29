<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Create a task in OmniFocus. With no project_id or parent_task_id it lands in the inbox — the standard GTD capture. Tags that do not exist yet are created.')]
class CreateTaskTool extends OmniFocusTool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'name' => ['required', 'string'],
            'note' => ['nullable', 'string'],
            'project_id' => ['nullable', 'string'],
            'parent_task_id' => ['nullable', 'string'],
            'due' => ['nullable', 'date'],
            'defer' => ['nullable', 'date'],
            'flagged' => ['nullable', 'boolean'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1'],
        ]);

        return $this->respond(fn () => $this->client->createTask(
            name: $validated['name'],
            note: $validated['note'] ?? null,
            projectId: $validated['project_id'] ?? null,
            parentTaskId: $validated['parent_task_id'] ?? null,
            due: $validated['due'] ?? null,
            defer: $validated['defer'] ?? null,
            flagged: $validated['flagged'] ?? null,
            tags: $validated['tags'] ?? null,
            estimatedMinutes: $validated['estimated_minutes'] ?? null,
        ));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('The task name.')->required(),
            'note' => $schema->string()->description('Note text for the task.'),
            'project_id' => $schema->string()->description('Project to create the task in; omit for the inbox.'),
            'parent_task_id' => $schema->string()->description('Create as a subtask of this task instead.'),
            'due' => $schema->string()->description('Due date, ISO 8601.'),
            'defer' => $schema->string()->description('Defer date, ISO 8601.'),
            'flagged' => $schema->boolean()->description('Flag the task.'),
            'tags' => $schema->array()->description('Tag names to apply; missing tags are created.'),
            'estimated_minutes' => $schema->integer()->description('Estimated duration in minutes.'),
        ];
    }
}
