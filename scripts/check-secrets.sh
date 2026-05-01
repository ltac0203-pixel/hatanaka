#!/usr/bin/env bash
# Detects accidentally committed secret files and hardcoded API key patterns.
# Usage:
#   scripts/check-secrets.sh --staged   # check staged changes (used in pre-commit)
#   scripts/check-secrets.sh --repo     # check the whole tracked tree (used in CI)
set -euo pipefail

MODE="${1:---repo}"

SECRET_FILES=(
  ".env"
  ".env.local"
  ".env.production"
  ".env.staging"
  ".env.testing"
  ".env.backup"
  "credentials.json"
  "service-account.json"
  "id_rsa"
  "id_ed25519"
  "auth.json"
)

# Patterns of obvious provider API keys. Add to this list as needed.
SECRET_PATTERNS=(
  'AKIA[0-9A-Z]{16}'           # AWS access key
  'sk-[a-zA-Z0-9]{20,}'         # OpenAI / generic
  'sk_live_[a-zA-Z0-9]+'        # Stripe live secret
  'ghp_[a-zA-Z0-9]{36}'         # GitHub personal access token
  'glpat-[a-zA-Z0-9\-]{20}'     # GitLab personal access token
  'xoxb-[0-9]+-[0-9]+-[a-zA-Z0-9]+' # Slack bot token
)

EXCLUDE_PATHS='vendor/|node_modules/|\.git/|public/build/|storage/'

errors=0

list_files() {
  if [ "$MODE" = "--staged" ]; then
    git diff --cached --name-only --diff-filter=ACM
  else
    git ls-files
  fi
}

target_files=$(list_files || true)

# 1. Detect secret-named files.
for pattern in "${SECRET_FILES[@]}"; do
  if [ "$MODE" = "--staged" ]; then
    matches=$(printf "%s\n" "$target_files" | grep -E "(^|/)${pattern}$" || true)
  else
    matches=$(git ls-files "$pattern" 2>/dev/null || true)
  fi
  if [ -n "$matches" ]; then
    echo "ERROR: secret-named file detected:" >&2
    echo "$matches" >&2
    errors=$((errors + 1))
  fi
done

# 2. Detect hardcoded secret patterns.
if [ "$MODE" = "--staged" ]; then
  staged=$(printf "%s\n" "$target_files" | grep -vE "$EXCLUDE_PATHS" || true)
  if [ -n "$staged" ]; then
    while IFS= read -r file; do
      [ -z "$file" ] && continue
      [ -f "$file" ] || continue
      for pat in "${SECRET_PATTERNS[@]}"; do
        if grep -qP "$pat" "$file" 2>/dev/null; then
          echo "ERROR: secret pattern matched in $file (pattern: $pat)" >&2
          errors=$((errors + 1))
        fi
      done
    done <<< "$staged"
  fi
else
  for pat in "${SECRET_PATTERNS[@]}"; do
    matches=$(git grep -lP "$pat" -- . 2>/dev/null | grep -vE "$EXCLUDE_PATHS" || true)
    if [ -n "$matches" ]; then
      echo "ERROR: secret pattern '$pat' matched in:" >&2
      echo "$matches" >&2
      errors=$((errors + 1))
    fi
  done
fi

if [ "$errors" -gt 0 ]; then
  echo "" >&2
  echo "$errors secret issue(s) detected. Aborting." >&2
  exit 1
fi

echo "No secrets detected."
