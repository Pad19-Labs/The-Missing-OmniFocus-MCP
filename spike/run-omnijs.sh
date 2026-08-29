#!/bin/zsh
# Runs an Omni Automation (omniJS) script file inside OmniFocus and prints the result.
# Usage: ./run-omnijs.sh <script.js>
set -euo pipefail

osascript -l JavaScript -e '
function run(argv) {
  const path = argv[0];
  const script = $.NSString.stringWithContentsOfFileEncodingError(path, $.NSUTF8StringEncoding, null).js;
  const app = Application("OmniFocus");
  return app.evaluateJavascript(script);
}' "$1"
