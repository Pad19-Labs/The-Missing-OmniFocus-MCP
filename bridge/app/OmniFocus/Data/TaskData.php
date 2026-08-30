<?php

namespace App\OmniFocus\Data;

use App\OmniFocus\Enums\TaskStatus;

final readonly class TaskData
{
    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        public string $id,
        public string $name,
        public TaskStatus $status,
        public bool $flagged,
        public bool $inInbox,
        public ?string $deferDate,
        public ?string $dueDate,
        public ?string $completionDate,
        public ?string $added,
        public ?string $modified,
        public array $tags,
        public ?string $projectId,
        public ?string $projectName,
        public ?string $parentId,
        public ?int $estimatedMinutes,
        public bool $hasRepetition,
        public ?RepetitionData $repetition,
        public ?string $note,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            name: $data['name'],
            status: TaskStatus::from($data['status']),
            flagged: $data['flagged'],
            inInbox: $data['in_inbox'],
            deferDate: $data['defer_date'],
            dueDate: $data['due_date'],
            completionDate: $data['completion_date'],
            added: $data['added'] ?? null,
            modified: $data['modified'] ?? null,
            tags: $data['tags'],
            projectId: $data['project_id'],
            projectName: $data['project'],
            parentId: $data['parent_id'],
            estimatedMinutes: $data['estimated_minutes'],
            hasRepetition: $data['has_repetition'],
            repetition: RepetitionData::fromArray($data['repetition'] ?? null),
            note: $data['note'],
        );
    }
}
