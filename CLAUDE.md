# The Missing OmniFocus MCP

(Working directory is still named `omafocus` locally; the public name is "The Missing OmniFocus MCP", repo slug `missing-omnifocus-mcp`. Open source under MIT; docs site lives in `docs/` for GitHub Pages.)

Give AI agents full, reliable read/write access to OmniFocus data. OmniFocus stays the UI (capture, mobile, sync); we build the agent-facing layer. Long-term: a bridge service on the always-on Mac with a typed API, an MCP veneer on top, reachable from Linux over the network. Writes go through Omni Automation only — never hand-write the `.ofocus` sync format.

## Status

- **2026-08-29 — Spike complete.** Full round-trip proven via `osascript -l JavaScript` → `Application("OmniFocus").evaluateJavascript(...)`: full DB dump to JSON (a ~1,500-item real database), task created in inbox, task deleted. Scripts in `spike/`.
- **2026-08-29 — Organizing spike complete** (`spike/06`–`08`): folders/projects can be freely created, renamed, nested, moved, re-statused, and deleted. Inbox tasks can be filed into projects (`moveTasks`) and promoted to projects (`convertTasksToProjects`) — the core verbs for agent-driven GTD organizing all work.
- **2026-08-29 — Bridge v0.1 shipped** (`bridge/`, Laravel 13): typed read/write client over omniJS, audit-logged mutations, guarded cascade deletes, and an MCP server (`laravel/mcp`, stdio) exposing 16 tools. Run tests: `cd bridge && ./vendor/bin/pest` (unit/feature) or `./vendor/bin/pest --group=integration` (live OmniFocus).
- **2026-08-29 — HTTP transport shipped.** `POST /mcp` (streamable HTTP) guarded by a bearer token (`MCP_AUTH_TOKEN` in `bridge/.env`, fails closed). Served by `php artisan serve` on `127.0.0.1:8321`, auto-started by Solo (`solo.yml`, process "MCP bridge" — must be trusted once in the Solo UI). Registered machine-wide in Claude Code at user scope (`claude mcp list` → omnifocus). The stdio transport (`php bridge/artisan mcp:start omnifocus`) still exists for Claude Desktop or fallback. Next: GTD agent layer (inbox-triage skill, weekly-review coach); Tailscale exposure of :8321 for Linux/mobile.
- **2026-08-29 — Self-contained binary shipped.** `scripts/build-binary.sh` (box phar + static-php micro.sfx → 81MB, no PHP needed; stdio transport only — `artisan serve` can't work inside micro) and `scripts/build-mcpb.sh` (.mcpb for one-click Claude Desktop install). Phar gotchas solved in `bootstrap/app.php` + `PortableRuntime` (realpath/glob fail on phar streams; PHP_SAPI is "micro"; state lives in `~/Library/Application Support/MissingOmniFocusMCP`, override with `OMNIFOCUS_MCP_DATA_DIR`). Release CI builds arm64+x86_64 on v* tags. Homebrew formula template in `extras/` (needs public repo + release + tap). Binaries not yet notarized — docs tell users to strip quarantine; consider Apple Developer ID later.
- Omni Group is building official MCP support (announced July 2026, beta: test-mcp@omnigroup.com). Keep the OmniFocus transport swappable.

## How to talk to OmniFocus

```sh
./spike/run-omnijs.sh <script.js>   # runs an omniJS file inside OmniFocus, prints the result
```

The omniJS script must return a **string** (use `JSON.stringify`). Useful globals in the omniJS context: `inbox`, `flattenedTasks`, `flattenedProjects`, `flattenedTags`, `flattenedFolders`, `Task.byIdentifier(id)`, `new Task(name)` (no parent → inbox), `deleteObject(obj)`.

Organizing verbs (all proven in spike 06):
- `new Folder(name, position)` / `new Project(name, position)` — position like `library.ending`, `folder.beginning`
- `moveSections([projectsOrFolders], position)` — move/nest projects and folders anywhere
- `moveTasks([tasks], project.beginning)` — file inbox tasks into a project (clears `inInbox`)
- `convertTasksToProjects([tasks], position)` — promote an inbox idea to a real project
- Plain property writes: `p.name`, `p.status = Project.Status.OnHold`, `p.sequential`, `folder.status`
- `deleteObject(folder)` deletes the folder **and everything inside it** — bridge must treat this as highly destructive

## Gotchas learned in the spike

- After `deleteObject(t)`, touching the object (even `Task.byIdentifier`) **throws** "Object is scheduled for deletion" — verify deletions in a *separate* `evaluateJavascript` call.
- `String(task.taskStatus)` yields `[object Task.Status: Completed]` — map enum values to clean strings in the bridge.
- The user's zsh profile emits terminal-title escape codes into captured stdout; when capturing osascript output, strip everything before the first `{` (won't matter once the bridge calls osascript directly, not via zsh).
- Requires OmniFocus Pro (confirmed) and the app running (always-on Mac, confirmed).

## Conventions

- TDD; bug fixes start with a failing test.
- Keep it simple — no services for their own sake; user owns the infrastructure.
- Privacy: real OmniFocus data dumps go to the scratchpad, never into the repo.
