#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

files=$(rg --files resources/views -g '*.blade.php')

for f in $files; do
  perl -0pi -e 's/text-gray-900/text-gray-700/g; s/text-gray-800/text-gray-700/g; s/text-gray-700/text-gray-600/g; s/text-gray-600/text-gray-500/g; s/text-gray-500/text-gray-400/g; s/text-slate-900/text-slate-700/g; s/text-slate-800/text-slate-700/g; s/text-slate-700/text-slate-600/g; s/bg-gray-900/bg-gray-100/g; s/bg-gray-800/bg-gray-100/g; s/bg-slate-900/bg-slate-100/g; s/bg-slate-800/bg-slate-100/g; s/border-gray-900/border-gray-300/g; s/border-gray-800/border-gray-300/g; s/border-slate-900/border-slate-300/g; s/hover:text-gray-900/hover:text-gray-700/g; s/hover:bg-gray-900/hover:bg-gray-200/g; s/focus:text-gray-900/focus:text-gray-700/g; s/focus:bg-gray-900/focus:bg-gray-200/g;' "$f"
done

echo "Normalized Blade colors to light palette"
