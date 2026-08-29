#!/bin/bash
# Packages the built binary as a .mcpb bundle — one-click install in Claude Desktop.
# Usage: scripts/build-mcpb.sh [arm64|x86_64]   (defaults to host arch; build the binary first)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ARCH="${1:-$(uname -m)}"
[ "$ARCH" = "aarch64" ] && ARCH="arm64"
VERSION="${VERSION:-0.1.0}"

BIN="$ROOT/dist/missing-omnifocus-mcp-macos-$ARCH"
[ -f "$BIN" ] || { echo "Binary not found: $BIN — run scripts/build-binary.sh first"; exit 1; }

STAGE="$ROOT/build/mcpb-$ARCH"
rm -rf "$STAGE"
mkdir -p "$STAGE/server"
cp "$BIN" "$STAGE/server/missing-omnifocus-mcp"

cat > "$STAGE/manifest.json" <<JSON
{
  "manifest_version": "0.3",
  "name": "missing-omnifocus-mcp",
  "display_name": "The Missing OmniFocus MCP",
  "version": "${VERSION}",
  "description": "Full read/write access to your OmniFocus database for AI agents: inbox triage, task filing, project organization, GTD coaching. Requires OmniFocus 4 Pro running on this Mac.",
  "author": { "name": "Peter Ramsing" },
  "homepage": "https://github.com/Pad19-Labs/missing-omnifocus-mcp",
  "server": {
    "type": "binary",
    "entry_point": "server/missing-omnifocus-mcp",
    "mcp_config": {
      "command": "\${__dirname}/server/missing-omnifocus-mcp",
      "args": ["mcp:start", "omnifocus"]
    }
  },
  "compatibility": { "platforms": ["darwin"] }
}
JSON

OUT="$ROOT/dist/missing-omnifocus-mcp-macos-$ARCH.mcpb"
rm -f "$OUT"
(cd "$STAGE" && zip -qr "$OUT" .)

echo "Built: $OUT ($(du -h "$OUT" | cut -f1 | tr -d ' '))"
