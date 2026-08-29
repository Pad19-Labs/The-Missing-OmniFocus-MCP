# Setup

[← Home](./) · [Tool reference →](tools)

## Requirements

- macOS with **OmniFocus 4 Pro** running (Pro is required for the scripting bridge)
- An MCP client: [Claude Code](https://claude.com/claude-code), Claude Desktop, or any MCP-compatible agent
- PHP is **not** required for the binary install below — only for running from source

**Before your first agent session**: OmniFocus → File → Back Up Database. The bridge is careful by design, but agents write freely — that is the point.

## Install — single binary (recommended)

Download from [Releases](https://github.com/Pad19-Labs/The-Missing-OmniFocus-MCP/releases) — `arm64` for Apple Silicon, `x86_64` for Intel. The binary bundles its own PHP runtime, provisions itself on first run (auth token, audit database — state lives in `~/Library/Application Support/MissingOmniFocusMCP/`), and speaks stdio: your MCP client launches and manages it, so there is nothing to keep running.

**Claude Desktop**: download the `.mcpb` bundle and open it — one-click install.

**Claude Code**:

```bash
chmod +x missing-omnifocus-mcp-macos-arm64
xattr -d com.apple.quarantine missing-omnifocus-mcp-macos-arm64   # Gatekeeper, until releases are notarized
claude mcp add --scope user omnifocus -- /path/to/missing-omnifocus-mcp-macos-arm64 mcp:start omnifocus
```

The first call triggers a macOS prompt — *"…wants to control OmniFocus"*. Allow it.

## Install — from source

For contributors, or if you want the shared HTTP transport:

```bash
git clone https://github.com/Pad19-Labs/The-Missing-OmniFocus-MCP.git
cd missing-omnifocus-mcp
./setup.sh   # offers to install PHP via php.new if missing
```

The script installs dependencies, creates `.env` with a fresh auth token, migrates the local audit database, and runs the test suite. It is safe to re-run.

## Transports (source install)

### stdio — simplest

Your MCP client spawns the bridge on demand and talks over stdin/stdout. Nothing to keep running.

**Claude Code:**

```bash
claude mcp add --scope user omnifocus -- php /absolute/path/to/bridge/artisan mcp:start omnifocus
```

**Claude Desktop** — fully quit the app first (⌘Q), then add to
`~/Library/Application Support/Claude/claude_desktop_config.json` and relaunch:

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

Use the full path to your PHP binary (`which php`) — GUI apps do not inherit your shell's PATH.

### HTTP — one shared server

One persistent local server that every client hits. Start it any way you like:

```bash
php bridge/artisan serve --host=127.0.0.1 --port=8321
```

- **Auto-start on login**: copy `extras/omnifocus-mcp-bridge.plist` to `~/Library/LaunchAgents/` (fill in the two placeholder paths first) and `launchctl load` it.
- **[Solo](https://soloterm.com)**: a `solo.yml` ships with the repo — trust the "MCP bridge" process once and Solo keeps it alive.

Register it with the token from `bridge/.env`:

```bash
claude mcp add --scope user --transport http omnifocus http://127.0.0.1:8321/mcp \
  --header "Authorization: Bearer YOUR_MCP_AUTH_TOKEN"
```

## Security notes

- The HTTP endpoint **fails closed** — no token configured means every request is rejected with 401.
- It binds to `127.0.0.1` only. For access from other machines (Linux, mobile), tunnel it through [Tailscale](https://tailscale.com) or similar. Never expose it bare.
- Every mutation lands in a local SQLite audit log (`audit_logs` table): action, arguments, result summary, duration.
- macOS will prompt once per launch context — *"…wants to control OmniFocus"*. Allow it; that grant is what lets the bridge talk to the app.

## Verify

Ask your agent: *"Give me an overview of my OmniFocus database."* You should get counts, your folder tree, tags, and projects. Then try: *"What's in my inbox?"*
