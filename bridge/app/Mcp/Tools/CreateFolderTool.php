<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;

#[Description('Create an OmniFocus folder, optionally nested inside another folder.')]
class CreateFolderTool extends OmniFocusTool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'name' => ['required', 'string'],
            'parent_folder_id' => ['nullable', 'string'],
        ]);

        return $this->respond(fn () => $this->client->createFolder(
            $validated['name'],
            parentFolderId: $validated['parent_folder_id'] ?? null,
        ));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('The folder name.')->required(),
            'parent_folder_id' => $schema->string()->description('Parent folder; omit for the library root.'),
        ];
    }
}
