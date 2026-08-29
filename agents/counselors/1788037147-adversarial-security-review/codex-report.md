# Adversarial security & correctness review

No Critical finding was established from the code as it stands. There are multiple High-severity ways for a confused authenticated agent, a local peer, or a compromised build input to cause damage that the advertised controls do not prevent.

## High

### H1. The cascade guard is not a meaningful confirmation boundary and materially understates the deletion blast radius

**Files:** `bridge/app/Mcp/Tools/DeleteItemTool.php:16-25,37`; `bridge/resources/omnijs/delete_item.js:3-23`; `README.md:29,38`

The same untrusted agent that chooses the target also chooses `confirm_cascade=true`. Nothing requires a prior failed call, a human approval, a matching preview, or even acknowledgment of the item name and count. An agent can therefore issue this as its first call:

```json
{"type":"folder","id":"mistaken-id","confirm_cascade":true}
```

and `deleteObject(obj)` runs immediately.

The displayed count is also dangerously misleading. A task reports only `obj.children.length`, not all descendants. A folder reports only its directly contained projects and folders, not recursively contained projects or any tasks. A folder with one child folder containing thousands of tasks is described as containing `1 item(s)` and is then recursively deleted. This makes the README's “explicit confirmation” and “without accidental demolition” claims false in the threat model of a confused agent.

**Fix:** make deletion a server-mediated two-step operation. The first call should return a short-lived, single-use confirmation token bound to type, ID, current name, current `modified` value, and recursive descendant counts. Require that token on a separate destructive call, and expose MCP destructive annotations so the host can request user approval. Count every recursively affected object by type. Prefer moving to a recoverable quarantine/drop state and require a separate, explicitly user-approved purge operation for permanent deletion.

### H2. `update-project` can commit destructive changes and then report failure

**Files:** `bridge/resources/omnijs/update_project.js:1-14`; `bridge/resources/omnijs/update_folder.js:1-9`; `bridge/app/Mcp/Tools/UpdateProjectTool.php:16-29`

`update_project.js` mutates the name, note, sequencing, dates, and status before validating `folder_id`. A concrete request is:

```json
{"id":"real-project","name":"renamed","status":"done","folder_id":"stale-or-hallucinated-id"}
```

The project is renamed and assigned `Project.Status.Done`; only then does the script discover the missing folder and return `not_found`. OmniFocus has no transaction or rollback around this IIFE, so the bridge returns an error even though earlier mutations remain committed. `update_folder.js` has the same defect: it renames before validating the destination parent.

The `done` assignment is especially destructive. [OmniFocus documents completing a project as automatically marking unfinished actions complete](https://omni-automation.com/omnifocus/project.html). Thus a stale destination ID can cause an error response after bulk completion of a large project. The caller will reasonably retry or assume nothing changed. This directly contradicts the README's “transactional” implication. The live integration test checks only a successful `on_hold` update and never exercises mixed valid/invalid fields or `done` descendants.

**Fix:** resolve and validate every referenced object, destination, cycle constraint, date, and enum before the first mutation. Treat `done` as a bulk destructive operation: preview affected incomplete descendants and require a bound confirmation. If OmniFocus cannot provide a real transaction, snapshot all changed fields and roll back on every failure, or use a tested single undo group. Add live tests proving that an invalid final field leaves every earlier field and descendant unchanged.

### H3. Audit failure turns a successful mutation into an error, encouraging duplicate or contradictory writes

**Files:** `bridge/app/OmniFocus/OmniFocusClient.php:243-270`; `bridge/app/Mcp/Tools/OmniFocusTool.php:20-29`; `README.md:29,37`

The OmniFocus mutation completes first, and only afterward does `AuditLog::create()` run. A full disk, read-only database, SQLite lock, missing `audit_logs` table, or schema mismatch makes the audit insert throw after the real write has committed. That exception is not an `OmniFocusException`, so neither `mutate()` nor `OmniFocusTool::respond()` converts it into an accurate tool result.

Concrete outcome: `create-task` creates the task, the SQLite insert fails, MCP reports a server/protocol error, and an agent retries the apparently failed call, creating a duplicate. The same pattern makes a successful delete look unsuccessful and leaves it unlogged. The claims that every change is audit-logged and that “everything Claude does is recorded” are false. The audit row also contains only requested arguments and a tiny result summary, not before/after state or cascaded descendants, so it is not a recovery log.

**Fix:** decide and document whether audit availability is a precondition. If it is, perform a writeability/transaction check before contacting OmniFocus, while recognizing that SQLite and OmniFocus still cannot form one atomic transaction. If it is not, return the committed OmniFocus result with an explicit `audit_status: failed` warning and write a durable fallback record. Add an idempotency key to every mutation and persist/reconcile it so retries return the original result. Record before/after state and recursive effects for destructive operations.

### H4. Release artifacts trust and execute unverified network downloads, then ask users to disable Gatekeeper

**Files:** `scripts/build-binary.sh:29-46`; `.github/workflows/release.yml:7-29`; `README.md:56-61`

The release job downloads a Box PHAR and executes it without checking a digest or signature. It also downloads `micro.sfx` without verification and concatenates it into the shipped executable. The job has `contents: write`; compromise of the Box release asset, `static-php.dev`, DNS/TLS termination, or either mutable third-party GitHub Action tag can run code in the release job and publish an OmniFocus-controlling backdoor. `SHA256SUMS.txt` is generated by that same potentially compromised job, so it proves only that the attacker-generated files match attacker-generated checksums.

The distributed binary and `.mcpb` are neither signed nor notarized, and the installation instructions explicitly tell users to remove quarantine. That removes the only strong platform warning before granting the process Automation access to OmniFocus.

**Fix:** pin every Action to a full commit SHA; vendor or fetch Box and static PHP by immutable digest; verify hard-coded SHA-256 values and, where available, upstream signatures before execution; split build from release so the build job has no release token; produce signed provenance/SBOM; sign with Developer ID, notarize, and staple both binary and bundle. Publish checksums through a separately authenticated signing key (for example Sigstore/keyless provenance), not beside the artifacts in the same job.

### H5. Tokens and audit data are created group/world-readable

**Files:** `bridge/app/Support/PortableRuntime.php:41-58`; `setup.sh:24-42`; `bridge/app/OmniFocus/OmniFocusClient.php:262-270`

Portable setup creates directories with `0755`; `file_put_contents()` and `touch()` inherit the ordinary umask. The built smoke artifacts demonstrate the result: the data directory is `0755`, while `.env` and `database.sqlite` are `0644`. Source setup copies a `0644` `.env.example` and never tightens it; the current source `.env` and SQLite database are likewise `0644`.

Any local account able to traverse the parent directory can read the bearer token and the audit database, which stores full mutation arguments including task/project names and notes. On a machine where the HTTP service is running, that token lets the local peer call the full read/write API against the victim's logged-in OmniFocus session. A shared or permissive `OMNIFOCUS_MCP_DATA_DIR` makes this worse.

**Fix:** set a restrictive umask during preparation, create the data root and all storage directories as `0700`, create `.env`, SQLite files, logs, and caches as `0600`, reject unsafe ownership/symlinks, and repair permissions on every startup. Make `setup.sh` run `chmod 600 .env database/database.sqlite` and validate parent traversal permissions. Do not store full note bodies in the audit log by default; support redaction and retention limits.

## Medium

### M1. Portable “self-migration” is neither a migration system nor recoverable after partial failure

**Files:** `bridge/app/Providers/AppServiceProvider.php:38-64`; `bridge/database/migrations/0001_01_01_000001_create_cache_table.php:14-24`; `bridge/app/Support/PortableRuntime.php:84-90`; `bridge/app/OmniFocus/OsascriptRunner.php:25-30`

If `audit_logs` exists, startup returns without considering any other current or future migration. Otherwise it invokes every migration's `up()` directly, records no migration history, catches every exception as though it meant “already applied,” and catches the outer failure too. A partial cache migration can create `cache` and fail before `cache_locks`; a later audit migration can still create `audit_logs`. Every future boot then short-circuits, but `CACHE_STORE=database` makes `Cache::lock()` depend on the missing lock table, disabling all OmniFocus calls. Any schema migration added in a later binary release will never run for an existing installation because `audit_logs` already exists.

**Fix:** use Laravel's migrator with a real migrations repository and an explicit PHAR-compatible migration file list. Run migrations in deterministic order with transactions where SQLite permits them, lock first-run setup, fail startup loudly on migration errors, and never use one unrelated table as the schema-version sentinel. Add upgrade and injected-partial-failure tests against the actual packaged binary.

### M2. The lock does not prevent stale-user overwrites and is scoped only to one cache database

**Files:** `bridge/app/OmniFocus/OsascriptRunner.php:25-44`; `bridge/app/OmniFocus/OmniFocusClient.php:144-164,204-228`; `bridge/resources/omnijs/_lib.js:50-60`; `bridge/app/Mcp/Servers/OmniFocusServer.php:27-34`

`Cache::lock('omnifocus-runner')` serializes only bridge instances sharing the same cache store. A source checkout, a binary data directory, and another custom data directory use separate SQLite lock tables and can all enter concurrently. More importantly, the lock cannot cover edits made in OmniFocus's UI or on another synced device.

No mutation accepts an expected `modified` timestamp, expected parent, expected name, or expected tag set. The server instruction merely tells the model to re-fetch if data is “old.” Example: the agent reads tags `[work]`; the user adds `urgent`; the agent later sends its stale replacement tag set, and `clearTags()` silently deletes the user's new tag. Re-fetching is advisory and has a race between read and write.

**Fix:** add optimistic concurrency fields and compare them inside the same OmniJS invocation before mutation. Return a conflict containing current state. Use add/remove tag operations instead of replacing the entire set. If process serialization is still wanted, use a user-global lock location independent of installation/data directory and explicitly handle lock timeout as an MCP tool error.

### M3. Several read paths and one advertised paginated path permit unbounded output and memory/token blow-up

**Files:** `bridge/resources/omnijs/overview.js:1-18`; `bridge/resources/omnijs/list_projects.js:1-7`; `bridge/resources/omnijs/get_project.js:1-6`; `bridge/app/Mcp/Tools/ListInboxTool.php:14-29`; `bridge/app/OmniFocus/OsascriptRunner.php:29-43`

`get-overview` returns every folder, tag, and project; `list-projects` returns every matching project including up to 500 note characters each; `get-project` returns every flattened task; and `list-inbox` casts caller input without validation or a maximum. An authenticated caller can request an enormous inbox page, while normal calls against a large real database can already produce multi-megabyte JSON. Symfony Process buffers stdout in memory, the bridge decodes and materializes it again, and the MCP response can then exhaust the model's context or server memory. The 30-second timeout does not impose an output cap.

**Fix:** add cursor pagination and hard maximums to every collection endpoint, including overview and project detail; validate `list-inbox` limit/offset as is done for other list tools; cap note/name sizes and response bytes; stream or reject oversized process output; and return compact counts separately from listings.

### M4. Destination selection is documented as exclusive but implemented with silent precedence

**Files:** `bridge/app/Mcp/Tools/MoveTaskTool.php:11-28`; `bridge/resources/omnijs/move_task.js:3-14`; `bridge/app/Mcp/Tools/CreateTaskTool.php:16-38`; `bridge/resources/omnijs/create_task.js:1-13`

`move-task` says “Provide exactly one destination,” but validation allows any combination. The script silently chooses inbox over project over parent. `create-task` similarly accepts both `project_id` and `parent_task_id` and silently chooses the project. A model that emits two fields because of stale context gets a successful response for a different move than it intended.

`promote-task-to-project` also accepts any task, not just a top-level inbox capture. A mistaken ID can convert a nested task and its descendants into a root/folder project, and this toolset offers no inverse operation.

**Fix:** enforce exactly one move destination and mutual exclusion for create destinations at request validation and again in OmniJS. Restrict promotion to top-level inbox tasks by default; require a preview/bound confirmation to promote an already filed or nested task, and return its old location for recovery.

### M5. Tag names are ambiguous identifiers and can silently attach or filter the wrong tag

**Files:** `bridge/resources/omnijs/_lib.js:46-59`; `bridge/resources/omnijs/list_tasks.js:3-6`; `bridge/app/Mcp/Tools/CreateTaskTool.php:24-25,55`

[OmniFocus explicitly permits multiple tags with the same name](https://www.omni-automation.com/omnifocus/tag.html), including within its tag hierarchy. `findOrCreateTag()` chooses the first flattened tag with an equal name; list filtering also compares only names. A database containing `Work/Urgent` and `Home/Urgent` makes `tags:["Urgent"]` nondeterministic from the caller's perspective, and a filter for `Urgent` merges both categories. Although overview exposes tag IDs and parent IDs, write tools refuse to use them.

**Fix:** address existing tags by stable ID. If name convenience remains, reject ambiguous matches and accept an explicit parent/path for creation. Return tag IDs in task serialization and filter by ID.

### M6. Date validation accepts syntax that the JavaScript runtime is not required to parse consistently

**Files:** `bridge/app/Mcp/Tools/CreateTaskTool.php:21-22`; `bridge/app/Mcp/Tools/UpdateProjectTool.php:23-24`; `bridge/resources/omnijs/_lib.js:53-54`; `bridge/resources/omnijs/update_project.js:6-7`

The schemas promise ISO 8601, but Laravel's broad `date` rule accepts many `strtotime()` formats. Those strings are passed to JavaScriptCore's `new Date(...)`, whose accepted non-ISO formats and timezone behavior differ. A value accepted by PHP can fail during or after earlier OmniFocus field assignments, feeding the partial-write problem above.

**Fix:** validate one explicit RFC 3339/ISO 8601 format, normalize it server-side, pass an epoch or canonical UTC string, and check `isNaN(date.getTime())` before any mutation.

## Low

### L1. Output “sanitization” breaks on the escape sequence it claims to tolerate

**Files:** `bridge/app/OmniFocus/OmniFocusClient.php:284-316`; `bridge/tests/Unit/OmniFocusClientTest.php:62-73`

The sanitizer starts at whichever comes first, `{` or `[`. A normal CSI color prefix begins with `ESC [`; therefore `"\x1b[31m...{\"ok\":true...}"` is sliced at `[31m` and JSON decoding fails. The test covers an OSC prefix (`ESC ]`) but not CSI. Searching for the first delimiter also accepts arbitrary junk rather than verifying one complete envelope.

**Fix:** do not expect shell-profile output from a direct Symfony Process invocation. Require stdout to be exactly one JSON response after whitespace trimming. If escape removal is genuinely needed, strip well-defined ANSI sequences first and then parse the entire remaining string; add CSI and trailing-junk tests.

### L2. Runner failures escape the tool's error contract and are not audited

**Files:** `bridge/app/OmniFocus/OsascriptRunner.php:27-43`; `bridge/app/Mcp/Tools/OmniFocusTool.php:20-29`; `bridge/app/OmniFocus/OmniFocusClient.php:250-259`

Only unsuccessful process exit is wrapped in `ScriptException`. `ProcessTimedOutException`, process-start failure (including argv-size failure from an unbounded note/tags payload), cache lock timeout, and database cache errors escape as unrelated exceptions. `mutate()` records only `OmniFocusException`, so these cases have neither a consistent MCP error nor an audit row. If OmniFocus committed a change before the local osascript timed out or lost its response, the commit state is unknown.

**Fix:** catch and classify lock, process-start, and timeout exceptions; bound argument sizes before composition; give callers an `outcome_unknown` result that forbids blind retry; reconcile by idempotency key or re-fetch; and audit through an independent best-effort path.

## Claims checked without a finding

No exploitable argument-to-JavaScript injection path was found in the exposed tools: PHP's default `json_encode` escapes quotes, slashes, control characters, U+2028, and U+2029, `</script>` has no HTML context, and Symfony passes the composed source as an argv element without a shell (`bridge/app/OmniFocus/ScriptRepository.php:15-31`, `bridge/app/OmniFocus/OsascriptRunner.php:16-32`). Keep the existing adversarial tests and add U+2029, NUL, invalid UTF-8, and end-to-end JXA cases.

No HTTP authentication bypass was found. The bearer check rejects missing configuration and uses `hash_equals`, and route inspection shows the middleware on GET, POST, and DELETE `/mcp` (`bridge/app/Http/Middleware/EnsureMcpAuthToken.php:15-24`, `bridge/routes/ai.php:9-10`). `/` and `/up` are unauthenticated but do not expose OmniFocus data. Stdio is intentionally unauthenticated and should be documented as relying entirely on local process/TCC boundaries.

## Test coverage that gives false confidence

The 47-test suite passes, but it mocks the OmniJS runner for almost every mutation. It does not test audit-storage failure after a successful write, partial writes, `Project.Status.Done` descendants, recursive delete counts, confirmation provenance, optimistic conflicts, cross-install locking, lock/process timeouts, output limits, permissions, symlinks, packaged upgrades, or downloaded-artifact verification. The live suite exercises only a happy-path lifecycle and then performs real cascade deletion as cleanup.

## Most dangerous issue

The most dangerous issue is **H1: an AI agent can self-authorize a permanent recursive deletion on its first call, using a count that can understate thousands of descendants as one item**. The advertised cascade guard does not separate the actor requesting destruction from the actor confirming it, so it does not protect against the exact “confused agent” failure mode this project claims to handle.
