<?php

namespace App\OmniFocus\Data;

final readonly class FolderData
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $parentId,
        public string $status,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            parentId: $data['parent_id'],
            status: $data['status'],
        );
    }
}
