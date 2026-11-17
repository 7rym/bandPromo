#!/usr/bin/env bash
# bandPromo session end / checkpoint (Linux/macOS). Mirrors scripts/session-end.ps1.
set -euo pipefail

COMMIT_MESSAGE=""
RELEASE_SUMMARY=""
PUBLISH=0
PUSH=0
SKIP_VALIDATION=0
SKIP_PACKAGE=0
PRERELEASE=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --commit-message|-CommitMessage)
      COMMIT_MESSAGE="${2:-}"; shift 2 ;;
    --release-summary|-ReleaseSummary)
      RELEASE_SUMMARY="${2:-}"; shift 2 ;;
    --publish|-Publish) PUBLISH=1; shift ;;
    --push|-Push) PUSH=1; shift ;;
    --skip-validation|-SkipValidation) SKIP_VALIDATION=1; shift ;;
    --skip-package|-SkipPackage) SKIP_PACKAGE=1; shift ;;
    --prerelease|-Prerelease) PRERELEASE=1; shift ;;
    *)
      echo "Unknown argument: $1" >&2
      echo "Usage: session-end.sh --commit-message \"...\" [--push] [--publish] [--release-summary \"...\"] [--skip-package]" >&2
      exit 1
      ;;
  esac
done

if [[ -z "$COMMIT_MESSAGE" ]]; then
  echo "Missing --commit-message" >&2
  exit 1
fi

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$REPO_ROOT"

if command -v python3 >/dev/null 2>&1; then
  PYTHON_BIN=python3
elif command -v python >/dev/null 2>&1; then
  PYTHON_BIN=python
else
  echo "python3/python not found" >&2
  exit 1
fi

is_forbidden() {
  local rel="${1//\\//}"
  rel="${rel#./}"
  case "$rel" in
    desktop.ini|Desktop.ini|web-config.json|.env|.editorconfig|.htaccess|.user.ini) return 0 ;;
    .vscode/*|data/*|media/*|log/*|backups/*|dist/*|play/.htaccess) return 0 ;;
    *) return 1 ;;
  esac
}

is_allowed_repo_path() {
  local rel="${1//\\//}"
  rel="${rel#./}"
  case "$rel" in
    scripts/*|docs/*|biblioteca/*|.github/*|.cursor/*|play/*|temp/*) return 0 ;;
    admin.php|setup.php|bootstrap.php|VERSION|README.md|LICENSE|.gitattributes|.gitignore) return 0 ;;
    *) return 1 ;;
  esac
}

echo "bandPromo session end"

mapfile -t status_lines < <(git -C "$REPO_ROOT" status --porcelain 2>/dev/null || true)
issues=()

if [[ ${#status_lines[@]} -eq 0 ]]; then
  issues+=("No changes to checkpoint. Stage your work before session end.")
else
  tracked=0
  for line in "${status_lines[@]}"; do
    [[ "$line" =~ ^\?\? ]] || tracked=1
  done
  new_allowed=0
  for line in "${status_lines[@]}"; do
    if [[ "$line" =~ ^\?\? ]]; then
      rel="${line:3}"
      rel="$(echo "$rel" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')"
      if is_allowed_repo_path "$rel"; then
        new_allowed=1
      fi
    fi
  done
  if [[ "$tracked" -eq 0 && "$new_allowed" -eq 0 ]]; then
    issues+=("No changes to checkpoint. Stage your work before session end.")
  fi

  for line in "${status_lines[@]}"; do
    if [[ "$line" =~ ^\?\? ]]; then
      rel="${line:3}"
      rel="$(echo "$rel" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')"
      if is_forbidden "$rel"; then
        continue
      fi
      case "$rel" in
        *.mov|*.mp4|*.avi|*.flac|*.mp3|*.wav) continue ;;
      esac
      if is_allowed_repo_path "$rel"; then
        continue
      fi
      issues+=("Unexpected untracked file: $rel")
    fi
  done

  changelog_changed=0
  for line in "${status_lines[@]}"; do
    if [[ "$line" == *CHANGELOG.md* ]]; then
      changelog_changed=1
    fi
  done
  if [[ "$changelog_changed" -eq 0 ]]; then
    issues+=("docs/CHANGELOG.md is not modified. Add a timestamped entry before checkpointing.")
  fi
fi

if [[ ${#issues[@]} -gt 0 && "$SKIP_VALIDATION" -eq 0 ]]; then
  echo
  echo "Validation failed"
  for i in "${issues[@]}"; do
    echo "  - $i"
  done
  echo
  echo "Fix the issues above or rerun with --skip-validation if you accept the risk."
  exit 1
fi

echo
echo "Build bump"
previous_version=""
[[ -f VERSION ]] && previous_version="$(head -n 1 VERSION | tr -d '\r')"
"$PYTHON_BIN" scripts/bump_version.py
new_version="$(head -n 1 VERSION | tr -d '\r')"
echo "  ${previous_version} -> ${new_version}"

echo
echo "Commit"
# Refresh status after bump
mapfile -t status_lines < <(git -C "$REPO_ROOT" status --porcelain 2>/dev/null || true)
for line in "${status_lines[@]}"; do
  [[ ${#line} -lt 4 ]] && continue
  rel="${line:3}"
  rel="$(echo "$rel" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')"
  if is_forbidden "$rel"; then
    continue
  fi
  git -C "$REPO_ROOT" add -- "$rel"
done
git -C "$REPO_ROOT" add -- VERSION
git -C "$REPO_ROOT" commit -m "$COMMIT_MESSAGE"

if [[ "$PUSH" -eq 1 ]]; then
  echo
  echo "Push"
  git -C "$REPO_ROOT" push origin main
  echo "Pull after push"
  git -C "$REPO_ROOT" pull --ff-only origin main
fi

if [[ "$SKIP_PACKAGE" -eq 0 ]]; then
  echo
  echo "Local release package"
  "$PYTHON_BIN" scripts/build_release_package.py --clean
fi

if [[ "$PUBLISH" -eq 1 ]]; then
  if [[ "$PUSH" -eq 0 ]]; then
    echo
    echo "Publish skipped: use --push with --publish so GitHub Actions builds from the pushed commit."
    exit 1
  fi
  tag_name="$("$PYTHON_BIN" scripts/version_format.py tag)"
  if [[ -z "$RELEASE_SUMMARY" ]]; then
    RELEASE_SUMMARY="bandPromo $new_version"
  else
    RELEASE_SUMMARY="bandPromo $new_version - $RELEASE_SUMMARY"
  fi
  prerelease_value=false
  [[ "$PRERELEASE" -eq 1 ]] && prerelease_value=true
  echo
  echo "Publish GitHub release package"
  gh workflow run "Publish release package" \
    -f "tag_name=$tag_name" \
    -f "release_name=$RELEASE_SUMMARY" \
    -f "prerelease=$prerelease_value" \
    -f "draft=false"
  echo "  Triggered tag $tag_name (prerelease=$prerelease_value)"
fi

echo
echo "Session end complete"
echo "  VERSION: $new_version"
if [[ "$SKIP_PACKAGE" -eq 0 ]]; then
  echo "  Package: dist/bandPromo.zip"
fi
