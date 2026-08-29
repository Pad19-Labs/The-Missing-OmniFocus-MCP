<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Rename an OmniFocus folder and/or move it (with all its contents) into another folder.')]
class UpdateFolderTool extends OmniFocusTool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'id' => ['required', 'string'],
            'name' => ['sometimes', 'string'],
            'parent_folder_id' => ['sometimes', 'string'],
        ]);

        $fields = collect($validated)->except('id')->all();

        return $this->respond(fn () => $this->client->updateFolder($validated['id'], $fields));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->description('The folder id.')->required(),
            'name' => $schema->string()->description('New folder name.'),
            'parent_folder_id' => $schema->string()->description('Move the folder inside this folder.'),
        ];
    }
}
