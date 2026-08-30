<?php

namespace App\OmniFocus;

use App\Models\AuditLog;
use App\Models\DeletionToken;
use App\OmniFocus\Contracts\OmniJsRunner;
use App\OmniFocus\Data\FolderData;
use App\OmniFocus\Data\ProjectData;
use App\OmniFocus\Data\TaskData;
use App\OmniFocus\Enums\ProjectStatus;
use App\OmniFocus\Enums\RepetitionMethod;
use App\OmniFocus\Enums\TaskStatus;
use App\OmniFocus\Exceptions\CascadeConfirmationRequired;
use App\OmniFocus\Exceptions\NotFoundException;
use App\OmniFocus\Exceptions\OmniFocusException;
use App\OmniFocus\Exceptions\ScriptException;
use Illuminate\Support\Facades\Log;
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

    /**
     * @param  list<string>|null  $tags
     */
    public function createTask(
        string $name,
        ?string $note = null,
        ?string $projectId = null,
        ?string $parentTaskId = null,
        ?string $due = null,
        ?string $defer = null,
        ?bool $flagged = null,
        ?array $tags = null,
        ?int $estimatedMinutes = null,
        ?string $repetitionRule = null,
        ?string $repetitionMethod = null,
    ): TaskData {
        if ($projectId !== null && $parentTaskId !== null) {
            throw new ScriptException('create_task accepts project_id or parent_task_id, not both.');
        }

        $this->assertRepetition($repetitionRule, $repetitionMethod, onCreate: true);

        $data = $this->mutate('create_task', [
            'name' => $name,
            'note' => $note,
            'project_id' => $projectId,
            'parent_task_id' => $parentTaskId,
            'due' => $due,
            'defer' => $defer,
            'flagged' => $flagged,
            'tags' => $tags,
            'estimated_minutes' => $estimatedMinutes,
            'repetition_rule' => $repetitionRule,
            'repetition_method' => $repetitionMethod,
        ]);

        return TaskData::fromArray($data['task']);
    }

    /**
     * @param  array{name?: string, note?: string, due?: ?string, defer?: ?string, flagged?: bool, tags?: list<string>, estimated_minutes?: int, status?: string}  $fields
     */
    public function updateTask(string $id, array $fields): TaskData
    {
        $this->assertRepetition(
            $fields['repetition_rule'] ?? null,
            $fields['repetition_method'] ?? null,
            onCreate: false,
        );

        $data = $this->mutate('update_task', ['id' => $id] + $fields);

        return TaskData::fromArray($data['task']);
    }

    private function assertRepetition(?string $rule, ?string $method, bool $onCreate): void
    {
        if ($method !== null && RepetitionMethod::tryFrom($method) === null) {
            throw new ScriptException("Unknown repetition_method: {$method}. Use fixed, due_date, or defer_until_date.");
        }

        if ($rule !== null) {
            $rule = trim($rule);
            if ($rule === '' || strlen($rule) > 1024) {
                throw new ScriptException('repetition_rule must be a non-empty iCalendar RRULE under 1024 characters.');
            }
            if (! str_contains($rule, 'FREQ=')) {
                throw new ScriptException('repetition_rule must be an iCalendar RRULE containing FREQ= (e.g. FREQ=WEEKLY;INTERVAL=1).');
            }
        }

        // On create, a method without a rule silently produced a non-repeating
        // task; require the rule so intent is explicit.
        if ($onCreate && $method !== null && $rule === null) {
            throw new ScriptException('repetition_method requires repetition_rule when creating a task.');
        }
    }

    public function moveTask(
        string $id,
        ?string $projectId = null,
        ?string $parentTaskId = null,
        bool $toInbox = false,
    ): TaskData {
        $destinations = array_filter([$projectId, $parentTaskId, $toInbox ?: null]);
        if (count($destinations) !== 1) {
            throw new ScriptException('move_task requires exactly one destination: project_id, parent_task_id, or to_inbox.');
        }

        $data = $this->mutate('move_task', [
            'id' => $id,
            'project_id' => $projectId,
            'parent_task_id' => $parentTaskId,
            'to_inbox' => $toInbox,
        ]);

        return TaskData::fromArray($data['task']);
    }

    public function promoteTaskToProject(
        string $taskId,
        ?string $folderId = null,
        ?ProjectStatus $status = null,
    ): ProjectData {
        $data = $this->mutate('promote_task_to_project', [
            'task_id' => $taskId,
            'folder_id' => $folderId,
            'status' => $status?->value,
        ]);

        return ProjectData::fromArray($data['project']);
    }

    public function createProject(
        string $name,
        ?string $folderId = null,
        ?bool $sequential = null,
        bool $singleton = false,
        ?ProjectStatus $status = null,
        ?string $note = null,
    ): ProjectData {
        $data = $this->mutate('create_project', [
            'name' => $name,
            'folder_id' => $folderId,
            'sequential' => $sequential,
            'singleton' => $singleton,
            'status' => $status?->value,
            'note' => $note,
        ]);

        return ProjectData::fromArray($data['project']);
    }

    /**
     * @param  array{name?: string, note?: string, folder_id?: string, status?: string, sequential?: bool, due?: ?string, defer?: ?string}  $fields
     */
    public function updateProject(string $id, array $fields): ProjectData
    {
        $data = $this->mutate('update_project', ['id' => $id] + $fields);

        return ProjectData::fromArray($data['project']);
    }

    public function createFolder(string $name, ?string $parentFolderId = null): FolderData
    {
        $data = $this->mutate('create_folder', [
            'name' => $name,
            'parent_folder_id' => $parentFolderId,
        ]);

        return FolderData::fromArray($data['folder']);
    }

    /**
     * @param  array{name?: string, parent_folder_id?: string}  $fields
     */
    public function updateFolder(string $id, array $fields): FolderData
    {
        $data = $this->mutate('update_folder', ['id' => $id] + $fields);

        return FolderData::fromArray($data['folder']);
    }

    /**
     * Two-step delete. Without a valid confirmation token, previews the target
     * and returns a single-use token bound to (type, id) — the caller must send
     * it back on a separate call to actually delete. This separates requesting
     * a deletion from confirming it, and surfaces the true recursive blast
     * radius before anything is destroyed.
     *
     * @return array<string, mixed>
     */
    public function deleteItem(string $type, string $id, ?string $confirmationToken = null): array
    {
        if ($confirmationToken === null) {
            return $this->previewDelete($type, $id);
        }

        $token = DeletionToken::where('token', $confirmationToken)->first();

        if (! $token || ! $token->isValidFor($type, $id)) {
            throw new CascadeConfirmationRequired(
                'Invalid, expired, or already-used confirmation token. Call delete_item without a token first to preview and obtain a fresh one.'
            );
        }

        $result = $this->mutate('delete_item', [
            'type' => $type,
            'id' => $id,
            'confirmed' => true,
        ]);

        $token->update(['used_at' => now()]);

        return $result + ['deleted' => true];
    }

    /**
     * @return array<string, mixed>
     */
    private function previewDelete(string $type, string $id): array
    {
        $data = $this->mutate('preview_delete', ['type' => $type, 'id' => $id], status: 'preview');

        $token = DeletionToken::create([
            'token' => bin2hex(random_bytes(24)),
            'type' => $type,
            'item_id' => $id,
            'item_name' => $data['name'],
            'descendants' => $data['descendants'],
            'expires_at' => now()->addMinutes(10),
        ]);

        return [
            'requires_confirmation' => true,
            'type' => $type,
            'id' => $id,
            'name' => $data['name'],
            'descendants' => $data['descendants'],
            'confirmation_token' => $token->token,
            'message' => $data['descendants'] > 0
                ? "Deleting this {$type} will permanently remove it and {$data['descendants']} descendant item(s). Call delete_item again with confirmation_token to proceed."
                : "This {$type} has no descendants. Call delete_item again with confirmation_token to permanently delete it.",
        ];
    }

    /**
     * Run a write template and record it in the audit log, success or failure.
     */
    private function mutate(string $template, array $args, string $status = 'ok'): array
    {
        $startedAt = hrtime(true);

        try {
            $data = $this->call($template, $args);
        } catch (OmniFocusException $e) {
            // The mutation did not commit, so an audit failure here is harmless.
            $this->recordAudit($this->auditAction($template), $args, 'error', ['message' => $e->getMessage()], $startedAt);

            throw $e;
        }

        // The mutation HAS committed in OmniFocus. An audit-log failure must
        // never turn this into a reported error, or the agent will retry and
        // duplicate the write. Record best-effort; surface a warning instead.
        $audited = $this->recordAudit($this->auditAction($template), $args, $status, $this->summarize($data), $startedAt);

        if (! $audited) {
            $data['audit_status'] = 'failed';
        }

        return $data;
    }

    /**
     * The preview and confirmed-delete templates are both facets of the
     * delete_item tool, so they share its audit action name.
     */
    private function auditAction(string $template): string
    {
        return $template === 'preview_delete' ? 'delete_item' : $template;
    }

    /**
     * Best-effort audit write. Returns false (never throws) if the log is
     * unavailable, so a committed mutation is still reported as success.
     */
    private function recordAudit(string $action, array $args, string $status, array $summary, int $startedAtNs): bool
    {
        try {
            AuditLog::create([
                'action' => $action,
                'arguments' => array_filter($args, fn ($v) => $v !== null),
                'result_summary' => $summary,
                'status' => $status,
                'duration_ms' => intdiv(hrtime(true) - $startedAtNs, 1_000_000),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('omafocus: audit log write failed', [
                'action' => $action,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function summarize(array $data): array
    {
        foreach (['task', 'project', 'folder'] as $key) {
            if (isset($data[$key]['id'])) {
                return [$key => ['id' => $data[$key]['id'], 'name' => $data[$key]['name'] ?? null]];
            }
        }

        return $data;
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
                'cascade_confirmation_required' => new CascadeConfirmationRequired($error['message']),
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
