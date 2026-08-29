# The Missing OmniFocus MCP

**Give AI agents full, safe, read/write access to your OmniFocus database.**

OmniFocus is a brilliant GTD system with no API. This project is the missing piece: a small [Laravel](https://laravel.com) bridge that exposes your entire OmniFocus database to AI agents over the [Model Context Protocol](https://modelcontextprotocol.io) — so Claude (or any MCP client) can triage your inbox, file and promote tasks, reorganize projects and folders, and coach you through your weekly review, using the same first-party automation API the OmniFocus app uses itself.

## Why not just another MCP wrapper?

Most community OmniFocus MCP servers are thin AppleScript-per-call wrappers: stringly-typed, untested, and fragile. This bridge is built differently:

- **First-party API only.** Every operation runs through Omni Automation (omniJS) via `osascript` — the same transactional, sync-safe API the app uses. It never touches the reverse-engineered `.ofocus` sync format, so your database is never at risk of corruption.
- **Injection-proof scripting.** Arguments are passed into omniJS as JSON-encoded data, never interpolated into JavaScript source. A task named `"; deleteObject(library); "` is just a task name.
- **Typed and tested.** A typed PHP client with DTOs and enums, ~45 Pest tests against a fake runner, plus an opt-in live integration suite that round-trips against a real database and cleans up after itself.
- **Audited writes.** Every mutation is recorded (action, arguments, result, duration) in a local SQLite audit log. You can always see what an agent did.
- **Guarded deletes.** Deleting anything that still contains children is refused unless the agent explicitly passes `confirm_cascade` — free manipulation without accidental demolition.
- **Serialized access.** A lock ensures one script runs inside OmniFocus at a time, with timeouts for hangs.

## The 16 tools

| Read | Write |
|---|---|
| `get-overview` — counts, folder tree, tags, all projects | `create-task` — capture to inbox, a project, or as a subtask |
| `list-inbox` — paged unprocessed captures | `update-task` — rename, notes, dates, flags, tags, complete/drop |
| `list-projects` — filter by status/folder | `move-task` — file into a project, nest, or return to inbox |
| `list-tasks` — filter by project/tag/status/flag/due | `promote-task-to-project` — turn an inbox idea into a project |
| `search` — names and notes, case-insensitive | `create-project` / `update-project` — incl. folder moves, on-hold |
| `get-task` — full detail plus children | `create-folder` / `update-folder` — incl. nesting moves |
| `get-project` — detail plus all tasks | `delete-item` — with the cascade guard |

## Requirements

- macOS with **OmniFocus 4 Pro** (Pro is required for the scripting bridge) — the app must be running
- **PHP 8.3+** and **Composer** ([Laravel Herd](https://herd.laravel.com) is the easy path)
- An MCP client — [Claude Code](https://claude.com/claude-code), Claude Desktop, or anything MCP-compatible

## Quick start

```bash
git clone https://github.com/peterramsing/missing-omnifocus-mcp.git
cd missing-omnifocus-mcp
./setup.sh
```

The script installs dependencies, creates `.env`, generates an auth token, migrates the audit database, and runs the test suite. Then pick a transport:

### Option A — stdio (simplest: nothing to keep running)

The MCP client spawns the bridge on demand and talks over stdin/stdout.

```bash
claude mcp add --scope user omnifocus -- php /absolute/path/to/bridge/artisan mcp:start omnifocus
```

**Claude Desktop** (`~/Library/Application Support/Claude/claude_desktop_config.json` — quit Desktop first; use your full PHP binary path, GUI apps don't inherit your shell PATH):

```json
{
  "mcpServers": {
    "omnifocus": {
      "command": "/full/path/to/php",
      "args": ["/absolute/path/to/bridge/artisan", "mcp:start", "omnifocus"]
    }
  }
}
```

### Option B — HTTP (one persistent server, shared by every client)

Start the server (any of these):

```bash
php bridge/artisan serve --host=127.0.0.1 --port=8321   # foreground, simplest
```

- **launchd** (auto-start on login): see `extras/omnifocus-mcp-bridge.plist`
- **[Solo](https://solo.dev)** users: a `solo.yml` is included — trust the "MCP bridge" process once and it auto-starts

Then register the URL with the bearer token from `bridge/.env`:

```bash
claude mcp add --scope user --transport http omnifocus http://127.0.0.1:8321/mcp \
  --header "Authorization: Bearer YOUR_MCP_AUTH_TOKEN"
```

The endpoint **fails closed**: no configured token means every request is rejected. It binds to localhost only; to reach it from another machine, put it behind something like [Tailscale](https://tailscale.com) — never expose it bare.

## First run

1. **Back up first**: OmniFocus → File → Back Up Database. The bridge is careful, but agents write freely by design.
2. The first call triggers a macOS prompt — *"…wants to control OmniFocus"*. Allow it. Each launch context (terminal, Desktop, launchd) needs its own one-time grant.
3. Ask your agent for an overview of your OmniFocus database and enjoy.

## How it works

```
MCP client (Claude Code / Desktop / …)
   │  stdio or streamable HTTP (bearer token)
   ▼
Laravel bridge (this repo)
   │  typed client → omniJS templates + JSON args → audit log
   ▼
osascript -l JavaScript  →  OmniFocus.evaluateJavascript(…)
   ▼
Your OmniFocus database (first-party Omni Automation API)
```

## Testing

```bash
cd bridge
./vendor/bin/pest                       # unit + feature (no OmniFocus needed)
./vendor/bin/pest --group=integration   # live round-trip against real OmniFocus
```

The integration suite creates loudly-prefixed disposable objects and removes them even when assertions fail. It never asserts on your real data.

## Roadmap

- GTD agent skills built on the tools (inbox triage, weekly-review coaching)
- Remote access recipes (Tailscale) for Linux and mobile clients
- Repeating-task (RecurrenceRule) editing
- When Omni ships official MCP support, this bridge's transport layer is designed to be swappable

## License

[MIT](LICENSE) — built with affection for OmniFocus by people who have used it for years. Not affiliated with The Omni Group.
