#!/bin/bash
# The Missing OmniFocus MCP — bootstrap: prepares the Laravel bridge for first use.
# Safe to re-run; it never overwrites an existing .env or token.
set -euo pipefail

cd "$(dirname "$0")/bridge"

if ! command -v php >/dev/null; then
    echo "PHP not found."
    read -r -p "Install PHP + Composer now via php.new (Laravel's official installer)? [y/N] " reply
    if [[ "$reply" =~ ^[Yy] ]]; then
        /bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.4)"
        export PATH="$HOME/.config/herd-lite/bin:$PATH"
    else
        echo "Install PHP 8.3+ yourself (Laravel Herd, Homebrew, or php.new) and re-run."
        exit 1
    fi
fi
command -v composer >/dev/null || { echo "composer not found — see https://getcomposer.org"; exit 1; }

echo "==> Installing dependencies"
composer install --no-interaction --quiet

if [ ! -f .env ]; then
    echo "==> Creating .env"
    cp .env.example .env
    php artisan key:generate --no-interaction
fi

if ! grep -q '^MCP_AUTH_TOKEN=.\+' .env; then
    echo "==> Generating MCP auth token"
    TOKEN=$(openssl rand -hex 32)
    if grep -q '^MCP_AUTH_TOKEN=' .env; then
        sed -i '' "s/^MCP_AUTH_TOKEN=.*/MCP_AUTH_TOKEN=${TOKEN}/" .env
    else
        printf '\nMCP_AUTH_TOKEN=%s\n' "$TOKEN" >> .env
    fi
fi

echo "==> Preparing database (audit log)"
touch database/database.sqlite
php artisan migrate --force --no-interaction

echo "==> Securing secrets (owner-only)"
chmod 600 .env database/database.sqlite

echo "==> Running test suite"
./vendor/bin/pest

BRIDGE_DIR=$(pwd)
TOKEN=$(grep '^MCP_AUTH_TOKEN=' .env | cut -d= -f2)

cat <<NEXT

Setup complete. Make sure OmniFocus is running, then connect a client:

  Claude Code (stdio — nothing to keep running):
    claude mcp add --scope user omnifocus -- php ${BRIDGE_DIR}/artisan mcp:start omnifocus

  Claude Code (HTTP — start the server first, see README):
    claude mcp add --scope user --transport http omnifocus http://127.0.0.1:8321/mcp \\
      --header "Authorization: Bearer ${TOKEN}"

  Claude Desktop: see the README's "Claude Desktop" section.

The first call may trigger a macOS prompt to allow controlling OmniFocus — allow it.
Recommended before your first agent session: OmniFocus > File > Back Up Database.
NEXT
