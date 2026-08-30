<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Update fields on an OmniFocus task: rename, edit note, dates, flag, tags, estimate, or change status (completed / dropped / active). Only the fields you pass are changed; tags replace the full tag set.')]
class UpdateTaskTool extends OmniFocusTool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'id' => ['required', 'string'],
            'name' => ['sometimes', 'string'],
            'note' => ['sometimes', 'string'],
            'due' => ['sometimes', 'nullable', 'date'],
            'defer' => ['sometimes', 'nullable', 'date'],
            'flagged' => ['sometimes', 'boolean'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string'],
            'estimated_minutes' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', 'in:active,completed,dropped'],
            'repetition_rule' => ['sometimes', 'nullable', 'string'],
            'repetition_method' => ['sometimes', 'in:fixed,due_date,defer_until_date'],
        ]);

        $fields = collect($validated)->except('id')->all();

        return $this->respond(fn () => $this->client->updateTask($validated['id'], $fields));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The task id.')->required(),
            'name' => $schema->string()->description('New task name.'),
            'note' => $schema->string()->description('New note text.'),
            'due' => $schema->string()->description('New due date (ISO 8601); pass null to clear.'),
            'defer' => $schema->string()->description('New defer date (ISO 8601); pass null to clear.'),
            'flagged' => $schema->boolean()->description('Set or clear the flag.'),
            'tags' => $schema->array()->description('Replacement tag set; missing tags are created.'),
            'estimated_minutes' => $schema->integer()->description('New duration estimate in minutes.'),
            'status' => $schema->string()->enum(['active', 'completed', 'dropped'])->description('Mark the task completed, dropped, or active again.'),
            'repetition_rule' => $schema->string()->nullable()->description('Set the repeat as an iCalendar RRULE (e.g. "FREQ=WEEKLY"); pass null to stop the task repeating.'),
            'repetition_method' => $schema->string()->enum(['fixed', 'due_date', 'defer_until_date'])->description('How the next repeat is scheduled. Defaults to due_date.'),
        ];
    }
}
