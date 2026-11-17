#!/usr/bin/env bash
# bandPromo session start (Linux/macOS). Mirrors scripts/session-start.ps1.
set -euo pipefail

BUMP_SESSION=0
SKIP_DEV_SERVER=0
SKIP_PULL=0
TODO_LIMIT=4
CHANGELOG_LIMIT=3

while [[ $# -gt 0 ]]; do
  case "$1" in
    --bump-session|-BumpSession) BUMP_SESSION=1; shift ;;
    --skip-dev-server|-SkipDevServer) SKIP_DEV_SERVER=1; shift ;;
    --skip-pull|-SkipPull) SKIP_PULL=1; shift ;;
    *)
      echo "Unknown argument: $1" >&2
      echo "Usage: session-start.sh [--bump-session] [--skip-dev-server] [--skip-pull]" >&2
      exit 1
      ;;
  esac
done

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$REPO_ROOT"

cmd_line() {
  local cmd="$1"; shift
  if ! command -v "$cmd" >/dev/null 2>&1; then
    echo "${cmd}: not found"
    return
  fi
  local out
  out="$("$cmd" "$@" 2>/dev/null | head -n 1 || true)"
  if [[ -z "$out" ]]; then
    echo "${cmd}: available"
  else
    echo "$out"
  fi
}

echo "bandPromo session start"
echo "Repo: $REPO_ROOT"
echo "OS: $(uname -s) $(uname -r)"
echo "Shell: bash"
echo "PHP: $(cmd_line php -v)"
# Prefer python3 on Linux; fall back to python
if command -v python3 >/dev/null 2>&1; then
  echo "Python: $(cmd_line python3 --version)"
  PYTHON_BIN=python3
elif command -v python >/dev/null 2>&1; then
  echo "Python: $(cmd_line python --version)"
  PYTHON_BIN=python
else
  echo "Python: not found"
  PYTHON_BIN=python3
fi
echo

if [[ "$SKIP_PULL" -eq 0 ]]; then
  echo "Repository sync"
  set +e
  pull_out="$(git -C "$REPO_ROOT" pull --ff-only origin main 2>&1)"
  pull_rc=$?
  set -e
  while IFS= read -r line; do
    [[ -n "$line" ]] && echo "  $line"
  done <<<"$pull_out"
  if [[ "$pull_rc" -ne 0 ]]; then
    echo "  git pull returned a non-zero exit code."
    echo "  Continue locally, but resolve sync before checkpointing."
  fi
  echo
fi

previous_version=""
if [[ -f VERSION ]]; then
  previous_version="$(head -n 1 VERSION | tr -d '\r')"
fi

if [[ "$BUMP_SESSION" -eq 1 ]]; then
  echo "Session version"
  [[ -n "$previous_version" ]] && echo "  Previous: $previous_version"
  set +e
  bump_out="$("$PYTHON_BIN" scripts/bump_session.py 2>&1)"
  bump_rc=$?
  set -e
  if [[ "$bump_rc" -ne 0 ]]; then
    echo "  Session bump failed: $bump_out"
  else
    echo "  Current:  $(echo "$bump_out" | tail -n 1)"
    echo "  Build number is unchanged until session end checkpoint."
  fi
  echo
elif [[ -n "$previous_version" ]]; then
  echo "Session version"
  echo "  Current:  $previous_version"
  echo "  Session number unchanged (pass --bump-session only when starting a real work session)."
  echo
fi

if [[ "$SKIP_DEV_SERVER" -eq 0 ]]; then
  echo "Dev server"
  set +e
  server_out="$(bash scripts/start-dev-server.sh 2>&1)"
  server_rc=$?
  set -e
  while IFS= read -r line; do
    [[ -n "$line" ]] && echo "  $line"
  done <<<"$server_out"
  if [[ "$server_rc" -ne 0 ]]; then
    echo "  Dev server start failed."
  fi
  echo
fi

current_version=""
if [[ -f VERSION ]]; then
  current_version="$(head -n 1 VERSION | tr -d '\r')"
fi

echo "VERSION: ${current_version:-missing}"
echo

if [[ "$BUMP_SESSION" -eq 1 && "$current_version" =~ ^(v[0-9]+\.[0-9]+\.[0-9]+) ]]; then
  chat_title="Session ${BASH_REMATCH[1]}"
  echo "Chat title"
  echo "  $chat_title"
  echo "  AGENT: call cursor-app-control rename_chat with title \"$chat_title\""
  echo
fi

echo "Git"
git -C "$REPO_ROOT" status --short --branch 2>/dev/null | while IFS= read -r line; do
  echo "  $line"
done || echo "  git status unavailable"
echo

echo "Session scripts"
echo "  - scripts/session-start.sh  (Linux; Windows: session-start.ps1)"
echo "  - scripts/session-end.sh"
echo "  - scripts/start-dev-server.sh / scripts/stop-dev-server.sh"
echo

echo "Recent changes"
if [[ -f docs/CHANGELOG.md ]]; then
  grep -E '^[0-9]{4}-[0-9]{2}-[0-9]{2}' docs/CHANGELOG.md | head -n "$CHANGELOG_LIMIT" | while IFS= read -r line; do
    echo "  - $line"
  done
else
  echo "  - none found"
fi
echo

if [[ -f docs/SESSION-HANDOFF.md ]]; then
  echo "Session handoff (resume here — read this before older TODO items)"
  cat docs/SESSION-HANDOFF.md
  echo
  echo "Recommended focus"
  echo "  Follow docs/SESSION-HANDOFF.md above. Do not skip to an older open TODO item."
else
  echo "Recommended focus"
  echo "  No SESSION-HANDOFF.md found; check docs/TODO.md."
fi

echo
echo "Session end"
echo "  When ready to checkpoint: bash scripts/session-end.sh --commit-message \"...\""
echo
echo "Linux note"
echo "  scripts/vendor/ from Windows is not reusable — rebuild via Site health or vendor wheels."
echo "  Prefer python3. Do not call PowerShell scripts on this host."
