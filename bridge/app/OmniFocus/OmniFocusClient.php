<?php

namespace App\OmniFocus;

use App\OmniFocus\Contracts\OmniJsRunner;
use App\OmniFocus\Data\ProjectData;
use App\OmniFocus\Data\TaskData;
use App\OmniFocus\Enums\ProjectStatus;
use App\OmniFocus\Enums\TaskStatus;
use App\OmniFocus\Exceptions\NotFoundException;
use App\OmniFocus\Exceptions\ScriptException;
use JsonException;

class OmniFocusClient
{
    public function __construct(
        private readonly OmniJsRunner $runner,
        private readonly ScriptRepository $scripts,
    ) {}

    public function overview(): array
    {
        return $this->call('overview');
    }

    /**
     * @return array{total: int, tasks: list<TaskData>}
     */
    public function listInbox(int $limit = 50, int $offset = 0): array
    {
        $data = $this->call('list_inbox', ['limit' => $limit, 'offset' => $offset]);

        return ['total' => $data['total'], 'tasks' => $this->tasks($data['tasks'])];
    }

    /**
     * @return array{total: int, projects: list<ProjectData>}
     */
    public function listProjects(?ProjectStatus $status = null, ?string $folderId = null): array
    {
        $data = $this->call('list_projects', ['status' => $status?->value, 'folder_id' => $folderId]);

        return [
            'total' => $data['total'],
            'projects' => array_map(ProjectData::fromArray(...), $data['projects']),
        ];
    }

    /**
     * @return array{total: int, tasks: list<TaskData>}
     */
    public function listTasks(
        ?string $projectId = null,
        ?string $tag = null,
        ?TaskStatus $status = null,
        ?bool $flagged = null,
        ?string $dueBefore = null,
        int $limit = 50,
    ): array {
        $data = $this->call('list_tasks', [
            'project_id' => $projectId,
            'tag' => $tag,
            'status' => $status?->value,
            'flagged' => $flagged,
            'due_before' => $dueBefore,
            'limit' => $limit,
        ]);

        return ['total' => $data['total'], 'tasks' => $this->tasks($data['tasks'])];
    }

    /**
     * @return array{total: int, tasks: list<TaskData>}
     */
    public function search(string $query, int $limit = 20): array
    {
        $data = $this->call('search', ['query' => $query, 'limit' => $limit]);

        return ['total' => $data['total'], 'tasks' => $this->tasks($data['tasks'])];
    }

    /**
     * @return array{task: TaskData, children: list<TaskData>}
     */
    public function getTask(string $id): array
    {
        $data = $this->call('get_task', ['id' => $id]);

        return [
            'task' => TaskData::fromArray($data['task']),
            'children' => $this->tasks($data['children']),
        ];
    }

    /**
     * @return array{project: ProjectData, tasks: list<TaskData>}
     */
    public function getProject(string $id): array
    {
        $data = $this->call('get_project', ['id' => $id]);

        return [
            'project' => ProjectData::fromArray($data['project']),
            'tasks' => $this->tasks($data['tasks']),
        ];
    }

    private function call(string $template, array $args = []): array
    {
        $script = $this->scripts->compose($template, $args);
        $raw = $this->runner->run($script);

        try {
            $decoded = json_decode($this->sanitize($raw), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new ScriptException("OmniFocus returned a non-JSON response for [{$template}]: ".substr($raw, 0, 200), previous: $e);
        }

        if (! is_array($decoded) || ! ($decoded['ok'] ?? false)) {
            $error = $decoded['error'] ?? ['code' => 'unknown', 'message' => 'Malformed omniJS response'];

            throw match ($error['code'] ?? 'unknown') {
                'not_found' => new NotFoundException($error['message']),
                default => new ScriptException($error['message'] ?? 'omniJS error'),
            };
        }

        return $decoded['data'];
    }

    /**
     * Shell profiles and terminal integrations can prepend escape noise to
     * captured output; the payload always starts at the first JSON delimiter.
     */
    private function sanitize(string $raw): string
    {
        $positions = array_filter([strpos($raw, '{'), strpos($raw, '[')], fn ($p) => $p !== false);

        return $positions === [] ? $raw : substr($raw, min($positions));
    }

    /**
     * @return list<TaskData>
     */
    private function tasks(array $rows): array
    {
        return array_map(TaskData::fromArray(...), $rows);
    }
}
