# The Missing OmniFocus MCP

**Let Claude read and organize your OmniFocus — download one file, open it, done.**

This is for **OmniFocus users who also use Claude Desktop**. OmniFocus is a brilliant GTD system with no API — so Claude can't see your inbox, your projects, or your someday/maybe list. This project fixes that with a single downloadable file. No PHP, no terminal, no configuration.

## Get started in about a minute

1. **Download** the `.mcpb` file from [Releases](https://github.com/Pad19-Labs/The-Missing-OmniFocus-MCP/releases) (`arm64` for Apple Silicon, `x86_64` for Intel Macs).
2. **Open it.** Claude Desktop installs it as an extension — that's the whole setup.
3. **Allow the permission** when macOS asks whether Claude may control OmniFocus (first use only).

Then just talk:

> *"What's in my OmniFocus inbox?"*
> *"File these captures into the right projects and flag anything urgent."*
> *"That 'learn woodworking' idea — make it an on-hold project in my Someday folder."*
> *"Walk me through a weekly review."*

You need a Mac with **OmniFocus 4 Pro** running (Pro powers the automation bridge; the standard edition won't work). One sensible precaution before your first session: **OmniFocus → File → Back Up Database** — Claude edits your real system, that's the point.

## What Claude can do with it

- **Tame your inbox** — read every capture, clarify it, file it into the right project, or promote a big idea into a brand-new project in the right folder.
- **Organize freely** — create, rename, nest, and move projects and folders; set things active, on hold, done, or dropped.
- **Work your lists** — search everything; filter by project, tag, flag, or due date; complete, defer, tag, and flag tasks.
- **Coach your GTD practice** — with real visibility into your system, Claude can actually run a weekly review with you instead of guessing.

Everything Claude does is recorded in a local audit log, and deleting anything that still contains items requires explicit confirmation — free manipulation without accidental demolition.

## Why trust it with years of your data?

Most OmniFocus AI integrations are fragile scripts. This one is engineered:

- **First-party API only.** Every operation runs through Omni Automation — the same transactional, sync-safe API the OmniFocus app uses itself. It never touches the reverse-engineered sync format, so your database is never at risk of corruption.
- **Injection-proof.** Task data is passed as data, never spliced into code. A task named `"; deleteObject(library); "` is just a task name.
- **Audited writes.** Action, arguments, result, and duration of every change, logged locally.
- **Guarded deletes.** Anything with children requires an explicit confirm flag.
- **Tested.** ~50 tests plus a live integration suite that round-trips a real database and cleans up after itself.
- **Local and private.** Everything runs on your Mac. Your tasks go to Claude only when you ask Claude about them.

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

## Other ways to run it

**Claude Code (or any MCP client)** — use the bare binary from [Releases](https://github.com/Pad19-Labs/The-Missing-OmniFocus-MCP/releases) instead of the `.mcpb`:

```bash
chmod +x missing-omnifocus-mcp-macos-arm64
xattr -d com.apple.quarantine missing-omnifocus-mcp-macos-arm64   # Gatekeeper, until releases are notarized
claude mcp add --scope user omnifocus -- /path/to/missing-omnifocus-mcp-macos-arm64 mcp:start omnifocus
```

The binary is fully self-contained (a static PHP runtime and the app in one file), speaks stdio so your client launches and manages it, and keeps its state — auth token, audit log — in `~/Library/Application Support/MissingOmniFocusMCP/`.

**From source** — for contributors, or for the persistent HTTP transport (one shared server, bearer-token guarded, Tailscale-able for remote access). Requires PHP 8.3+ (the setup script offers to install it):

```bash
git clone https://github.com/Pad19-Labs/The-Missing-OmniFocus-MCP.git
cd missing-omnifocus-mcp
./setup.sh
```

Then either register stdio (`claude mcp add --scope user omnifocus -- php /absolute/path/to/bridge/artisan mcp:start omnifocus`) or start the HTTP server:

```bash
php bridge/artisan serve --host=127.0.0.1 --port=8321
```

and register it with the token from `bridge/.env`:

```bash
claude mcp add --scope user --transport http omnifocus http://127.0.0.1:8321/mcp \
  --header "Authorization: Bearer YOUR_MCP_AUTH_TOKEN"
```

The HTTP endpoint fails closed (no token configured → every request rejected), binds to localhost only, and can auto-start via launchd (`extras/omnifocus-mcp-bridge.plist`) or [Solo](https://soloterm.com) (`solo.yml` included). To reach it from another machine, put it behind [Tailscale](https://tailscale.com) — never expose it bare.

## How it works

```
Claude Desktop / Claude Code / any MCP client
   │  stdio (or HTTP with bearer token)
   ▼
Self-contained bridge (Laravel inside a single binary)
   │  typed client → omniJS templates + JSON args → audit log
   ▼
osascript  →  OmniFocus.evaluateJavascript(…)
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
- Notarized releases (no Gatekeeper incantations)
- Remote access recipes (Tailscale) for Linux and mobile clients
- Repeating-task (RecurrenceRule) editing
- When Omni ships official MCP support, this bridge's transport layer is designed to be swappable

## License

[MIT](LICENSE) — built with affection for OmniFocus by people who have used it for years. Not affiliated with The Omni Group. Prebuilt binaries embed PHP and permissively-licensed libraries; see [THIRD-PARTY-NOTICES](THIRD-PARTY-NOTICES.md).
