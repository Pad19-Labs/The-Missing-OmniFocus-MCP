<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('List tasks in the OmniFocus inbox (unprocessed captures). Returns the total count plus a page of tasks; use limit/offset to page through a large inbox.')]
class ListInboxTool extends OmniFocusTool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);

        return $this->respond(fn () => $this->client->listInbox(
            limit: $validated['limit'] ?? 50,
            offset: $validated['offset'] ?? 0,
        ));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->integer()->description('Maximum tasks to return (default 50).'),
            'offset' => $schema->integer()->description('Number of tasks to skip, for paging (default 0).'),
        ];
    }
}
