<?php

namespace App\Mcp\Tools;

use App\OmniFocus\Enums\TaskStatus;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('List OmniFocus tasks across the whole database, filtered by project, tag, status, flag, or due date. Prefer filters over large unfiltered pulls.')]
class ListTasksTool extends OmniFocusTool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'project_id' => ['nullable', 'string'],
            'tag' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'flagged' => ['nullable', 'boolean'],
            'due_before' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        return $this->respond(fn () => $this->client->listTasks(
            projectId: $validated['project_id'] ?? null,
            tag: $validated['tag'] ?? null,
            status: isset($validated['status']) ? TaskStatus::from($validated['status']) : null,
            flagged: $validated['flagged'] ?? null,
            dueBefore: $validated['due_before'] ?? null,
            limit: $validated['limit'] ?? 50,
        ));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->string()->description('Only tasks in this project.'),
            'tag' => $schema->string()->description('Only tasks carrying this tag name.'),
            'status' => $schema->string()->enum(['available', 'next', 'blocked', 'due_soon', 'overdue', 'completed', 'dropped'])->description('Only tasks with this status.'),
            'flagged' => $schema->boolean()->description('Only flagged (true) or unflagged (false) tasks.'),
            'due_before' => $schema->string()->description('Only tasks due on/before this ISO 8601 date.'),
            'limit' => $schema->integer()->description('Maximum tasks to return (default 50, max 200).'),
        ];
    }
}
