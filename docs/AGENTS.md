# AI Coding Agent Instructions for bandPromo

Welcome to the bandPromo codebase! This file provides essential guidance for AI coding agents to be productive and safe when working in this repository. It summarizes key conventions, build/test commands, and project-specific practices. For details, always refer to the linked documentation files.


## Project Overview

- bandPromo is a PHP-based music site with an admin panel, setup flow, media build pipeline, and analytics.
- Runtime/user-managed content lives outside tracked templates and is created during setup.

## Key Conventions

- Treat documentation as source-of-truth. If code and docs disagree, update docs in the same change.
- When updating planning docs or TODO lists, order work from policy/cases/definitions first and implementation second. Keep sections conceptually coherent; do not let headings become mixed bags of unrelated tasks.
- At the start of every session, check the active environment context first: OS, current shell, workspace root, available tasks, and language runtimes relevant to the task.
- Default to the fast startup path first: run `scripts/session-start.ps1` or the workspace task `bandPromo: Fast session startup` to gather the standard environment/worktree/backlog summary, then widen into deeper docs only when the task needs more context.
- Choose commands and tooling that match the active session environment. On Windows + PowerShell sessions, prefer PowerShell-native commands and repo tasks/scripts; do not probe Bash/Linux command variants first unless the environment explicitly provides them or the task requires them.
- Treat an unqualified "checkpoint" request as a publishable checkpoint unless the user explicitly asks for status-only: summarize progress against the current milestone/checkpoint docs, run focused validation for the touched work, bump `VERSION`, commit the checkpoint, push it, and then verify local/remote sync with the repository's pull-after-push workflow.
- Do not add runtime fallbacks that silently use example/template files in production paths.
- Runtime files are required and should fail loudly with actionable messages when missing.
- Keep local-only files out of git (for example web-config.json, data files, .env, icons, manifests).
- **desktop.ini files:** Windows + Google Drive creates these metadata files in every folder locally. They are **not** tracked by git (see `.gitignore`) and will be recreated on every local sync. Never try to add them to git; they cause corruption in `.git/refs/` and should always be ignored. If you accidentally commit one, remove it immediately.
- This repository lives inside Google Drive, so `.gitignore` alone is not enough. Run `powershell -ExecutionPolicy Bypass -File scripts/protect-google-drive-git.ps1` once per clone to move `.git` outside the synced folder. That is the durable fix; `.gitignore` only protects the working tree.
- Use UTF-8 encoding for all tracked repository files and generated logs/artifacts committed to git.
- Keep repository-authored text in English only.
- Exception: content inside `biblioteca/templates/` and runtime user data (for example `data/`) may contain any language.
- Always add a timestamped note to `docs/CHANGELOG.md` whenever repository files are changed.

### VERSION + push/pull workflow

- Bump `VERSION` locally before pushing to `main` with `python scripts/bump_version.py`.
- Commit the VERSION change together with the work being pushed so local and remote stay aligned.
- CI validates the VERSION format on push, but it does not create a follow-up bot commit anymore.
- Do not batch unrelated manual VERSION edits into feature commits unless explicitly requested.

## Build/Test Commands

- Preferred build path: Admin panel -> Build tab.
- CLI build: `python scripts/build.py`
- PHP syntax check: `php -l <file>`
- Before committing, validate touched PHP and JSON/template files.

## Documentation Links

- [README.md](README.md)
- [docs/TODO.md](TODO.md)
- [docs/ROADMAP.md](ROADMAP.md)
- [docs/FEATURES.md](FEATURES.md)

## Project Structure

- `admin.php`: admin panel entrypoint
- `setup.php`: first-run/bootstrap flow
- `play/index.php`: player UI
- `biblioteca/templates/`: tracked seed templates
- `data/`: runtime user-managed content (ignored except .htaccess)
- `scripts/build.py`: media/build pipeline
- `.github/workflows/`: policy and CI workflows

## Common Pitfalls

- Accidentally tracking local files from `data/`, root config, or generated assets.
- Breaking strict setup-seeding by reintroducing example fallbacks in runtime code.
- Forgetting to bump `VERSION` before pushing changes to `main`.
- Reaching for Bash/Linux commands in a Windows PowerShell session before checking the active environment and available repo tasks.
- Assuming ripgrep is available on every Windows environment.
- Introducing non-UTF-8 encoded files that later cause garbled output in tools/logs.
- Mixing non-English operational text into code comments, docs, logs, or admin/system messaging.
- **Letting Google Drive manage `.git`:** `.gitignore` cannot stop Google Drive from writing inside `.git`. If `.git` stays under the synced folder, `desktop.ini` will eventually reappear in `.git/refs/`, `.git/logs/`, or `.git/objects/` and break fetch/push operations. The required protection is to relocate `.git` outside Google Drive with `scripts/protect-google-drive-git.ps1`.
- **Committing desktop.ini files by accident:** They corrupt `.git/refs/` and break fetch/push operations. Always ensure they stay ignored in the worktree, and clean `.git` metadata if Google Drive has already recreated them.

## When in Doubt

- Choose safer behavior: explicit validation, explicit errors, no silent fallback.
- Keep changes minimal and scoped.
- Ask for confirmation before destructive or wide-reaching repository operations.


_Last updated: 2026-05-03_

- **Python requirements:** `Pillow`, `mutagen`, `ffmpeg` (see [README.md](README.md))
