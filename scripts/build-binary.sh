#!/bin/bash
# Builds the self-contained macOS binary: static PHP (micro SAPI) + app phar.
# Usage: scripts/build-binary.sh [arm64|x86_64]   (defaults to host arch)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ARCH="${1:-$(uname -m)}"
case "$ARCH" in
    arm64|aarch64) SPC_ARCH="aarch64"; OUT_ARCH="arm64" ;;
    x86_64)        SPC_ARCH="x86_64";  OUT_ARCH="x86_64" ;;
    *) echo "Unsupported arch: $ARCH"; exit 1 ;;
esac

PHP_VERSION="${PHP_VERSION:-8.5.8}"
BOX_VERSION="${BOX_VERSION:-4.6.6}"
BUILD="$ROOT/build"
DIST="$ROOT/dist"
mkdir -p "$BUILD/tools" "$DIST"

echo "==> Copying app (fresh, no dev files)"
rsync -a --delete \
    --exclude .env --exclude storage --exclude tests --exclude node_modules \
    --exclude vendor --exclude 'database/*.sqlite*' --exclude .mcp.json \
    --exclude .claude --exclude app.phar --exclude .phpunit.result.cache \
    "$ROOT/bridge/" "$BUILD/app/"
mkdir -p "$BUILD/app/storage/framework/cache" "$BUILD/app/storage/framework/views" \
    "$BUILD/app/storage/framework/sessions" "$BUILD/app/storage/logs"

echo "==> Installing production dependencies"
(cd "$BUILD/app" && composer install --no-dev --optimize-autoloader --no-interaction --quiet)

echo "==> Building phar (box $BOX_VERSION)"
BOX="$BUILD/tools/box-$BOX_VERSION.phar"
[ -f "$BOX" ] || curl -fsSL -o "$BOX" \
    "https://github.com/box-project/box/releases/download/$BOX_VERSION/box.phar"
(cd "$BUILD/app" && php -d phar.readonly=0 "$BOX" compile --no-interaction)

echo "==> Fetching static PHP $PHP_VERSION micro ($SPC_ARCH)"
MICRO_TGZ="$BUILD/tools/php-$PHP_VERSION-micro-macos-$SPC_ARCH.tar.gz"
[ -f "$MICRO_TGZ" ] || curl -fsSL -o "$MICRO_TGZ" \
    "https://dl.static-php.dev/static-php-cli/common/php-$PHP_VERSION-micro-macos-$SPC_ARCH.tar.gz"
tar -xzf "$MICRO_TGZ" -C "$BUILD/tools"

echo "==> Combining"
OUT="$DIST/missing-omnifocus-mcp-macos-$OUT_ARCH"
cat "$BUILD/tools/micro.sfx" "$BUILD/app/app.phar" > "$OUT"
chmod +x "$OUT"

echo "==> Smoke test"
rm -rf "$BUILD/smoke-data"
OMNIFOCUS_MCP_DATA_DIR="$BUILD/smoke-data" "$OUT" --version

echo
echo "Built: $OUT ($(du -h "$OUT" | cut -f1 | tr -d ' '))"
