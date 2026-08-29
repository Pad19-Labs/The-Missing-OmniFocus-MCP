# The Missing OmniFocus MCP

**OmniFocus is a brilliant GTD system with no API. This is the missing piece.**

A small, well-tested bridge that exposes your entire OmniFocus database to AI agents over the [Model Context Protocol](https://modelcontextprotocol.io). Claude — or any MCP client — can triage your inbox, file and promote tasks, reorganize your projects and folders, and coach you through a weekly review. All of it through the same first-party automation API the OmniFocus app uses itself, so your years of data are never at risk from reverse-engineered file formats.

[Get started →](setup) · [Tool reference →](tools) · [Source on GitHub →](https://github.com/Pad19-Labs/missing-omnifocus-mcp)

---

## What your agents can do

- **Tame the inbox** — read every unprocessed capture, clarify it, file it into the right project, or promote a big idea straight into a new project in the right folder, set on hold for someday/maybe.
- **Organize freely** — create, rename, nest, and move projects and folders; flip projects between active, on hold, done, and dropped; sequential or parallel.
- **Work your lists** — search everything, filter by project, tag, status, flag, or due date; complete and drop tasks; manage flags, defer and due dates, estimates, and tags.
- **Coach your GTD practice** — with full visibility into your system, an agent can actually run a weekly review with you instead of guessing.

## Why this one, and not another wrapper

Most OmniFocus MCP servers are thin AppleScript wrappers — stringly-typed, untested, fragile. This bridge is engineered:

| | |
|---|---|
| **First-party API only** | Every operation runs through Omni Automation (omniJS), the app's own transactional, sync-safe API. |
| **Injection-proof** | Arguments enter scripts as JSON data, never as concatenated code. |
| **Audited** | Every write is logged locally: action, arguments, result, duration. |
| **Guarded deletes** | Deleting anything with children requires explicit confirmation from the agent. |
| **Tested** | ~45 fast tests plus a live integration suite that cleans up after itself. |
| **Fails closed** | The HTTP transport rejects everything if no auth token is configured. |

## Installs in one step — no PHP required

It ships as a **single self-contained binary** with its own runtime baked in. Claude Desktop users download a `.mcpb` bundle and double-click it — that's the entire install. Claude Code users register the binary with one command. It provisions itself on first run: auth token, audit database, everything.

Prefer running from source? The Laravel app is right there in the repo, with a persistent bearer-token-guarded **HTTP transport** ready to sit behind Tailscale for remote access.

Requires only macOS and OmniFocus 4 Pro. [Setup takes about two minutes →](setup)

---

MIT licensed. Built with affection for OmniFocus by people who have used it for years. Not affiliated with The Omni Group.
