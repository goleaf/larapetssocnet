#!/usr/bin/env sh
set -eu

cd "$(git rev-parse --show-toplevel)"

git config core.hooksPath .githooks
chmod +x .githooks/pre-commit .githooks/pre-push scripts/install-git-hooks.sh scripts/controller-test-map.php

echo "Installed PetSocial git hooks from .githooks."
echo "Use SKIP_PROJECT_HOOKS=1 only for emergency local bypasses."
