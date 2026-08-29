---
layout: default
layout_hero: true
---

<section class="hero">
  <h1>Claude, meet your OmniFocus.</h1>
  <p class="tagline">For OmniFocus users who also use Claude Desktop: download one file, open it, and Claude can read and organize your entire system.</p>
  <div class="download">
    <a class="button-primary" href="https://github.com/Pad19-Labs/The-Missing-OmniFocus-MCP/releases/latest/download/missing-omnifocus-mcp-macos-arm64.mcpb">Download for Claude Desktop</a>
    <p class="download-meta">Free and open source. Requires OmniFocus 4 Pro on your Mac.<br>Apple Silicon — <a href="https://github.com/Pad19-Labs/The-Missing-OmniFocus-MCP/releases/latest">Intel and all other downloads</a></p>
  </div>
  <hr class="hero-divider">
</section>

<div class="prose" markdown="1">

## Download, open, done

OmniFocus is a brilliant GTD system with no API — so Claude can't see your inbox, your projects, or your someday/maybe list. This is the missing piece. Open the downloaded file and Claude Desktop installs it as an extension; allow the one macOS permission prompt, and then just talk:

> "What's in my OmniFocus inbox?"
>
> "File these captures into the right projects and flag anything urgent."
>
> "That 'learn woodworking' idea — make it an on-hold project in my Someday folder."
>
> "Walk me through a weekly review."

No PHP, no terminal, no configuration. [The setup guide](setup) covers Claude Code and running from source, too.

## What Claude can do with it

- **Tame the inbox** — read every unprocessed capture, clarify it, file it into the right project, or promote a big idea straight into a new project in the right folder
- **Organize freely** — create, rename, nest, and move projects and folders; flip projects between active, on hold, done, and dropped
- **Work your lists** — search everything; filter by project, tag, status, flag, or due date; complete, defer, tag, and flag tasks
- **Coach your GTD practice** — with real visibility into your system, Claude can run a weekly review with you instead of guessing

The full surface is [16 tools](tools) covering reads, writes, and organizing.

## Why trust it with years of your data?

Most OmniFocus AI integrations are fragile scripts. This one is engineered:

- **First-party API only.** Every operation runs through Omni Automation — the same transactional, sync-safe API the OmniFocus app uses itself. It never touches the reverse-engineered sync format.
- **Injection-proof.** Task data is passed as data, never spliced into code.
- **Audited writes.** Action, arguments, result, and duration of every change, logged locally.
- **Guarded deletes.** Anything that still contains items requires an explicit confirm flag.
- **Local and private.** Everything runs on your Mac. Your tasks go to Claude only when you ask Claude about them.
- **Tested.** ~50 fast tests plus a live integration suite that round-trips a real database and cleans up after itself.

</div>
