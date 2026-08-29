<?php

namespace App\OmniFocus\Data;

use App\OmniFocus\Enums\ProjectStatus;

final readonly class ProjectData
{
    public function __construct(
        public string $id,
        public string $name,
        public ProjectStatus $status,
        public ?string $folderId,
        public ?string $folderName,
        public bool $sequential,
        public bool $containsSingletonActions,
        public ?string $deferDate,
        public ?string $dueDate,
        public int $taskCount,
        public ?string $note,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            status: ProjectStatus::from($data['status']),
            folderId: $data['folder_id'],
            folderName: $data['folder'],
            sequential: $data['sequential'],
            containsSingletonActions: $data['contains_singleton_actions'],
            deferDate: $data['defer_date'],
            dueDate: $data['due_date'],
            taskCount: $data['task_count'],
            note: $data['note'],
        );
    }
}
