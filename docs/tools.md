# Tool reference

[← Home](./) · [Setup →](setup)

All 16 tools return JSON. Ids are stable OmniFocus identifiers — agents should address items by id, not name. Errors (not found, validation, the cascade guard) come back as readable MCP tool errors the agent can react to.

## Read tools

### `get-overview`
Counts, the full folder tree, all tags, and a light list of every project (id, name, status, folder, task count). No parameters. Doubles as a health check — call it first.

### `list-inbox`
Unprocessed captures. `limit` (default 50), `offset` for paging. Returns the total count alongside the page.

### `list-projects`
Full project detail. Filters: `status` (`active` / `on_hold` / `done` / `dropped`), `folder_id`.

### `list-tasks`
Tasks across the whole database. Filters: `project_id`, `tag`, `status` (`available` / `next` / `blocked` / `due_soon` / `overdue` / `completed` / `dropped`), `flagged`, `due_before` (ISO 8601), `limit` (max 200).

### `search`
Case-insensitive search over task names and notes. `query` (required), `limit` (max 100).

### `get-task`
One task by `id`, with its full note (up to 4000 chars) and child tasks.

### `get-project`
One project by `id`, with all of its tasks.

## Write tools

Every write is recorded in the local audit log.

### `create-task`
`name` (required), `note`, `due`, `defer` (ISO 8601), `flagged`, `tags` (created if missing), `estimated_minutes`. Destination: `project_id`, `parent_task_id`, or neither — which means the inbox, the standard GTD capture.

### `update-task`
`id` (required) plus any of the fields above, or `status` (`completed` / `dropped` / `active`). Only passed fields change; `tags` replaces the whole tag set; passing `null` for a date clears it.

### `move-task`
`id` plus exactly one destination: `project_id` (file it), `parent_task_id` (nest it), or `to_inbox: true`.

### `promote-task-to-project`
Convert a task — typically an inbox idea — into a full project. `task_id` (required), `folder_id`, `status` (e.g. `on_hold` for someday/maybe).

### `create-project`
`name` (required), `folder_id`, `sequential`, `singleton` (single-action list), `status`, `note`.

### `update-project`
`id` plus any of: `name`, `note`, `folder_id` (moves it), `status`, `sequential`, `due`, `defer`.

### `create-folder` / `update-folder`
`name` and optional `parent_folder_id`; updating with `parent_folder_id` moves the folder with all its contents.

### `delete-item`
`type` (`task` / `project` / `folder`), `id`, `confirm_cascade`. **The guard:** an item that still contains children is refused with a contents count unless `confirm_cascade` is true. Deletion is permanent — prefer `status: dropped` when history matters.
