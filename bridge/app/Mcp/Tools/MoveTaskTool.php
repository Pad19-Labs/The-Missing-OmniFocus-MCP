<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Move a task into a project (filing an inbox capture), under a parent task (making it a subtask), or back to the inbox. Provide exactly one destination.')]
class MoveTaskTool extends OmniFocusTool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'id' => ['required', 'string'],
            'project_id' => ['nullable', 'string'],
            'parent_task_id' => ['nullable', 'string'],
            'to_inbox' => ['nullable', 'boolean'],
        ]);

        return $this->respond(fn () => $this->client->moveTask(
            $validated['id'],
            projectId: $validated['project_id'] ?? null,
            parentTaskId: $validated['parent_task_id'] ?? null,
            toInbox: $validated['to_inbox'] ?? false,
        ));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The task id to move.')->required(),
            'project_id' => $schema->string()->description('Destination project.'),
            'parent_task_id' => $schema->string()->description('Destination parent task.'),
            'to_inbox' => $schema->boolean()->description('Move the task back to the inbox.'),
        ];
    }
}
