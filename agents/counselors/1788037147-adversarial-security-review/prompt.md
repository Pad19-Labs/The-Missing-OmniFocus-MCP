# Adversarial security & correctness review — The Missing OmniFocus MCP

You are a hostile, skeptical security reviewer. Your job is to BREAK this project, not praise it. Assume the author is over-confident. Find real defects with concrete exploit or failure scenarios. Praise nothing.

## What this is

A Laravel bridge (in `bridge/`) that gives AI agents read/write access to a user's OmniFocus database. It runs omniJS scripts inside OmniFocus via `osascript`, exposes them as MCP tools, logs mutations to a SQLite audit table, and also ships as a self-contained single binary (static PHP + phar) and a `.mcpb` Claude Desktop bundle. An HTTP transport is guarded by a bearer token.

Working directory is the repo root. Read the actual code — do not assume. Key places to attack:

- `bridge/app/OmniFocus/` — ScriptRepository (script composition), OsascriptRunner (Symfony Process + cache lock), OmniFocusClient (the typed facade + audit logging + response parsing/sanitizing), Data/*, Enums/*, Exceptions/*
- `bridge/resources/omnijs/` — the omniJS templates (_lib.js and per-operation scripts) and how PHP arguments reach them
- `bridge/app/Mcp/` — the 16 MCP tools and the server
- `bridge/app/Http/Middleware/EnsureMcpAuthToken.php` and `bridge/routes/ai.php` — HTTP auth
- `bridge/app/Support/PortableRuntime.php` and `bridge/bootstrap/app.php` — the binary/phar bootstrap, external data dir, self-migration
- `scripts/build-binary.sh`, `scripts/build-mcpb.sh`, `.github/workflows/` — the build/release pipeline
- `bridge/tests/` — see what is and ISN'T tested

## Specifically hunt for

1. **Injection**: The author claims JSON-encoding arguments into `const ARGS = {...}` makes omniJS injection impossible. Is that actually true for EVERY path? Consider U+2028/U+2029, `</script>`-style breaks, the JXA wrapper, script composition, template edge cases, and any place a value reaches osascript or JS as code rather than data. Try to construct a task name / note / id / tag that executes.
2. **Auth**: Is `EnsureMcpAuthToken` actually safe? Timing, fail-open vs fail-closed, missing token config, header parsing, methods that bypass it, the local/stdio path, CORS, the `/up` health route, anything reachable without the token.
3. **Data loss**: The cascade-delete guard, the self-migration that runs `up()` on every migration file, the "capture fields before deleteObject" pattern, concurrent edits, the `move`/`promote` operations. Can an agent (or a confused one) irreversibly destroy data it shouldn't?
4. **Concurrency**: The `Cache::lock('omnifocus-runner')` — with which cache store? Does it actually serialize in the binary (CACHE_STORE)? Lock timeouts, deadlocks, the live-database staleness the author waves away.
5. **The binary/phar bootstrap**: The config-loading and provider-merging hacks, `APP_RUNNING_IN_CONSOLE`, self-migration idempotency and partial-failure, the external data dir (`~/Library/Application Support/...`, `OMNIFOCUS_MCP_DATA_DIR`) — path handling, permissions, symlink/traversal, a hostile `OMNIFOCUS_MCP_DATA_DIR`, first-run token generation quality.
6. **Secrets & supply chain**: The auth token in `.env` / data dir (permissions, world-readability), the `.mcpb`/binary distribution (unsigned, un-notarized — the docs tell users to strip quarantine), the build pipeline downloading a prebuilt static-php micro.sfx over the network (integrity? pinned? checksummed?), box config leaking dev files.
7. **Error handling & DoS**: osascript timeouts, a hung OmniFocus modal, huge outputs, the escape-code "sanitize" (stripping to first `{`/`[`), malformed omniJS responses, unbounded list results, token-blowup.
8. **Anything else that bites.** Logic bugs in the enum mapping, date handling, tag creation, the status-change logic (markComplete/drop/markIncomplete), pagination, the `search` being name+note substring only, etc.

## Output

Rank findings by severity (Critical / High / Medium / Low). For each: file:line, the concrete failure or exploit scenario (inputs → bad outcome), and a specific fix. Call out the author's stated security claims that are FALSE or overstated. If a whole class of attack is genuinely well-defended, say so in one line and move on — spend your words on what's broken. End with the single most dangerous issue you found.
