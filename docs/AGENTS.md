# AI Coding Agent Instructions for bandPromo

Welcome to the bandPromo codebase! This file provides essential guidance for AI coding agents to be productive and safe when working in this repository. It summarizes key conventions, build/test commands, and project-specific practices. For details, always refer to the linked documentation files.


## Project Overview

- bandPromo is a PHP-based music site with an admin panel, setup flow, media build pipeline, and analytics.
- Runtime/user-managed content lives outside tracked templates and is created during setup.
- **Campaign content** is portable via **PCF** (Portable Campaign File, `.pcf`) — see [PORTABILITY.md](PORTABILITY.md). Setup imports `bandPromo-demo.pcf` (legacy `bandPromo-demo.prp` still accepted until the next demo-content publish). Do not invent parallel default-theme/demo content packages for new work.
- **No special demo content handling:** setup imports the Demo PCF as a normal campaign file, then locks it. Operators hide / duplicate; localhost may unlock + re-export. Hide is preference-level (`demo_campaign_id` / `demo_release_hidden` in `data/install-preferences.json`), not filename-prefix policy; demo containers (including Pages) and unused demo workspace media leave admin lists / Files / pickers; demo Brands leave Branding/PBF export unless they are Base; shell media stays only while Base / non-demo brands reference it. Do not add heal/sync/force/`bandPromo_*`→demo forks or a `system_managed` freeze beyond `locked`. See [PLATFORM-MODEL.md](PLATFORM-MODEL.md) and [PORTABILITY.md](PORTABILITY.md).
- The entire `/media`, `/data`, `/log`, and `/backups` trees are git-ignored. Demo masters travel in the Demo PCF / published artifacts, not as tracked git binaries.
- Apache/PHP protection stubs (root `.htaccess`, `.user.ini`, `play/.htaccess`, deny-all stubs under data/log/backups/media) are generated from `biblioteca/templates/runtime/` by setup when missing — not tracked at install paths.
- Operators can verify and repair those managed stubs from **System → Security** (`security-sanity-check.php` / `security-sanity-repair.php`). Repair overwrites drifted managed stubs only; it never rewrites `web-config.json`.
- IDE preferences (`.vscode/`, `.cursor/`, `.editorconfig`) are local-only and not tracked.

## Key Conventions

- Treat documentation as source-of-truth. If code and docs disagree, update docs in the same change.
- When updating planning docs or TODO lists, order work from policy/cases/definitions first and implementation second. Keep sections conceptually coherent; do not let headings become mixed bags of unrelated tasks.
- At the start of every session, check the active environment context first: OS, current shell, workspace root, available tasks, and language runtimes relevant to the task.
- Default to the fast startup path first: run `scripts/session-start.ps1` to sync the repo, bump the session number, start the dev server, and gather the standard environment/worktree/backlog summary. If [SESSION-HANDOFF.md](SESSION-HANDOFF.md) exists, that file is the exact resume point — read it before older TODO items. Use `scripts/session-end.ps1` for publishable checkpoints.
- Choose commands and tooling that match the active session environment. On Windows + PowerShell sessions, prefer PowerShell-native commands and repo tasks/scripts; do not probe Bash/Linux command variants first unless the environment explicitly provides them or the task requires them.
- Treat an unqualified "checkpoint" request as a publishable checkpoint unless the user explicitly asks for status-only: summarize progress against the current milestone/checkpoint docs, run focused validation for the touched work, bump `VERSION`, commit the checkpoint, push it, **publish the GitHub Release package** (see below), and then verify local/remote sync with the repository's pull-after-push workflow.
- Do not add runtime fallbacks that silently use example/template files in production paths.
- Runtime files are required and should fail loudly with actionable messages when missing.
- Keep local-only files out of git (for example web-config.json, data files, .env, icons, manifests).
- **Never destroy this working copy’s runtime.** `data/` (including `data/analytics/`), `media/`, `log/` (analytics and admin-audit test data), `backups/`, `web-config.json`, and `data/terces` are operator data. “Try a fresh install”, SESSION-HANDOFF, or “clean host” do **not** authorize deleting them here. Remote test fleet: **bandpromo.site** (Vanilla — always the fresh-install host), **Spandexual Tension** (spandexualtension.com), **HITZ** (hitz.no). **Twisted Chronicles** is paused until v0.9 reinstall. The user must name those exact paths in the same message before any rmtree/delete. Same bar as secrets: stop and ask.
- **desktop.ini files:** Windows + Google Drive creates these metadata files in every folder locally. They are **not** tracked by git (see `.gitignore`) and will be recreated on every local sync. Never try to add them to git; they cause corruption in `.git/refs/` and should always be ignored. If you accidentally commit one, remove it immediately.
- This repository lives inside Google Drive, so `.gitignore` alone is not enough. Run `powershell -ExecutionPolicy Bypass -File scripts/protect-google-drive-git.ps1` once per clone to move `.git` outside the synced folder. That is the durable fix; `.gitignore` only protects the working tree.
- Use UTF-8 encoding for all tracked repository files and generated logs/artifacts committed to git.
- Keep repository-authored text in **UK English** only (not US English). See **Language** below.
- Exception: content inside `biblioteca/templates/` and runtime user data (for example `data/`) may contain any language.
- Always add a timestamped note to `docs/CHANGELOG.md` whenever repository files are changed.
- **Python 3.6.9 hard floor:** every file under `scripts/` must parse and run on CPython **3.6.9+** (shared-host baseline, e.g. bandpromo.site). Do not use `from __future__ import annotations`, PEP 604 `X | Y`, PEP 585 `list[str]`/`dict[...]`, `subprocess` `text=`/`capture_output=`, or `shutil.rmtree(..., onexc=)`. Prefer `typing.List`/`Dict`/`Optional`/`Union`, `universal_newlines=True`, and `stdout=`/`stderr=` pipes. Run `python scripts/check_python36_compat.py` before committing script changes.

### Language

House style is **UK English** for every repository-authored string: admin and player copy, docs, comments, logs, notifications, and system messages. Prefer **catalogue**, **colour**, **organise**, **favourite**, **-ise** / **-our** spellings over US **catalog**, **color**, **organize**, **favorite**.

Do not rename existing code identifiers, CSS properties, JSON keys, file names, or APIs to match UK spelling (`catalog_id`, `demo-catalog-state.php`, `buildCatalog.py`, CSS `color`, HTML/JS ids). New identifiers may keep the established `catalog` / `color` technical names so they stay consistent with those APIs. Operator-facing labels next to those identifiers still use UK English (Content → **Catalogue**, **Repair catalogue**).

**Portable Campaign File (PCF):** operator-facing name is always **PCF** or **`.pcf`**. Never tell operators it is a ZIP, that they may rename it to `.zip`, or mention ZipArchive for campaign files. Full site backup remains a ZIP; the application package remains `bandPromo.zip`. Import still accepts legacy `.prp` without advertising it. Internally the file is zip-backed (`ZipArchive`). Prefer `.pcf` on export and on the durable `demo-content` tag; fall back to `.prp` when that is what GitHub still serves.

**Portable Brand File (PBF):** operator-facing name is always **PBF** or **`.pbf`**. Same rules as PCF (never call it a ZIP). Unit is one brand + curated library masters — not a campaign. See [PORTABILITY.md](PORTABILITY.md).

**Transfer integrity:** admin downloads and large uploads go through shared helpers (`http-stream.php`, `chunked-upload.php`, `bandpromoDownloadVerified` / `bandpromoUploadChunked`). Jobs store archive SHA-256; verified download refuses truncated files; PCF/PBF manifests include per-path digests.

`biblioteca/templates/` and runtime user data may use any language.

### VERSION + push/pull workflow

- Format: `v<major>.<minor>.<session> build <number>` (for example `v0.8.4 build 303`).
- **Session start** bumps the session number with `python scripts/bump_session.py` (build number unchanged).
- **Session end / checkpoint** bumps the build number with `python scripts/bump_version.py` before pushing to `main`.
- Commit the VERSION change together with the work being pushed so local and remote stay aligned.
- CI validates the VERSION format on push, but it does not create a follow-up bot commit anymore.
- Do not batch unrelated manual VERSION edits into feature commits unless explicitly requested.

### Tester-facing checkpoint (push is not enough)

Hosted operators and closed-beta testers use **Dashboard → Site update**, which reads the published GitHub Release (`release-manifest.json` + ZIP assets), **not** `main` alone.

After pushing a checkpoint meant for testers, also publish the release package:

1. Read `VERSION` (for example `v0.8.15 build 377`) and derive the release tag: `v0.8.15-build-377` (lowercase, spaces → hyphens).
2. Trigger the GitHub Actions workflow **Publish release package** (`.github/workflows/publish-release-package.yml`).
3. Confirm the new tag appears on GitHub Releases with **`bandPromo.zip`** and **`release-manifest.json` only** (clean title like `bandPromo v0.8.15 build 377`).
4. Sanity-check that **Site update** on a test install offers the new build.

**Demo content is separate:** durable GitHub release tag `demo-content` holds `bandPromo-demo.pcf` + `demo-manifest.json` (legacy `bandPromo-demo.prp` until the next demo publish). Update it only when demo campaign content changes. Setup imports it once; **Site update** re-imports when the published SHA differs from the install marker (locked demo; skip unlocked localhost). Admin Publish does not re-download the Demo PCF.

```powershell
python scripts/prepare_demo_content_package.py --pcf path\to\export.pcf --clean --publish
```

App release manifests embed a pointer to that durable Demo PCF; they do not re-upload the ~145MB PCF on every app build.

Example (GitHub CLI):

```powershell
gh workflow run "Publish release package" `
  -f tag_name=v0.8.15-build-377 `
  -f release_name="bandPromo v0.8.15 build 377" `
  -f prerelease=false `
  -f draft=false
```

Use `prerelease=false` for closed-beta tester packages so hosts that cannot call `api.github.com` still resolve the build via GitHub `/releases/latest`. Site update also falls back to the public Releases Atom feed (includes prereleases) when the API is blocked. Reserve `prerelease=true` for internal experiments that should stay off `/releases/latest`. Local-only validation can use `python scripts/build_release_package.py --clean --skip-demo-manifest`, but testers still need the published GitHub Release.

## Build/Test Commands

- Preferred build path: Admin panel -> System -> Status → Refresh site files.
- Successful rebuilds end with a scoped summary (media / playlists / share images / manifest) and elapsed time — not a legacy path dump.
- CLI build: `python scripts/build.py`
- PHP syntax check: `php -l <file>`
- Before committing, validate touched PHP and JSON/template files.

## Documentation Links

- [README.md](README.md)
- [docs/TODO.md](TODO.md)
- [docs/SESSION-HANDOFF.md](SESSION-HANDOFF.md) (when present — live pause/resume pointer)
- [docs/ROADMAP.md](ROADMAP.md)
- [docs/USE-CASES.md](USE-CASES.md)
- [docs/PLATFORM-MODEL.md](PLATFORM-MODEL.md)
- [docs/ACCESS-MODEL.md](ACCESS-MODEL.md)
- [docs/DELIVERY-ARCHITECTURE.md](DELIVERY-ARCHITECTURE.md)
- [docs/PORTABILITY.md](PORTABILITY.md)
- [docs/MEDIA-HANDLING.md](MEDIA-HANDLING.md)
- [docs/MASTER-TIER-AUDIT.md](MASTER-TIER-AUDIT.md)
- [docs/FEATURES.md](FEATURES.md)
- [docs/ADMIN-UI.md](ADMIN-UI.md)
- [docs/OPERATOR-MESSAGING.md](OPERATOR-MESSAGING.md) (v0.9 messaging plan; policy locked v0.8)
- [docs/MARKETING-STRATEGY.md](MARKETING-STRATEGY.md)

## Project Structure

Current layout (v0.9 refactor **planned**, not started — see [CODE-LAYOUT-REFACTOR.md](CODE-LAYOUT-REFACTOR.md)):

- `admin.php`: admin panel entrypoint (target: `admin/index.php` mirroring `/play/`)
- `setup.php`: first-run/bootstrap flow
- `play/index.php`: player UI
- `biblioteca/`: PHP helpers, HTTP APIs, admin/player JS & CSS, seed templates (target: `lib/`)
- `biblioteca/templates/`: tracked seed templates (including `runtime/` Apache/PHP stubs)
- `data/`, `log/`, `backups/`, `media/`: runtime roots — **fully git-ignored**
- `scripts/`: Python build pipeline + dev/session PowerShell (target: `lib/build/`)
- `vendor/`: committed third-party PHP/JS (target: `lib/vendor/`)
- `.github/workflows/`: policy and CI workflows

## Common Pitfalls

- **Wiping local `data/`, `media/`, or `log/`:** this Google Drive tree is the live operator catalogue (`log/` includes analytics test history). Fresh-install smokes always run on **bandpromo.site**; the other active remote tests are Spandexual Tension and HITZ (Twisted Chronicles paused until v0.9). Do not rmtree runtime roots unless the operator named those paths in the same message.
- Accidentally tracking local files from `data/`, `log/`, `backups/`, `media/`, root config, generated assets, `.vscode/`, `.cursor/`, or `.editorconfig`.
- Breaking strict setup-seeding by reintroducing example fallbacks in runtime code.
- Forgetting to bump `VERSION` before pushing changes to `main`.
- Reaching for Bash/Linux commands in a Windows PowerShell session before checking the active environment and available repo tasks.
- Assuming ripgrep is available on every Windows environment.
- Introducing non-UTF-8 encoded files that later cause garbled output in tools/logs.
- Mixing US English or non-English operational text into code comments, docs, logs, or admin/system messaging (house style is UK English).
- **Letting Google Drive manage `.git`:** `.gitignore` cannot stop Google Drive from writing inside `.git`. If `.git` stays under the synced folder, `desktop.ini` will eventually reappear in `.git/refs/`, `.git/logs/`, or `.git/objects/` and break fetch/push operations. The required protection is to relocate `.git` outside Google Drive with `scripts/protect-google-drive-git.ps1`.
- **Committing desktop.ini files by accident:** They corrupt `.git/refs/` and break fetch/push operations. Always ensure they stay ignored in the worktree, and clean `.git` metadata if Google Drive has already recreated them.
- **Committing `/media` or install-path `.htaccess`:** Ignore rules must keep runtime trees untracked. Platform favicon seed is tracked at `biblioteca/templates/icons/bP-icons.zip`; packaging/setup extract it into gitignored `media/icons/` for the app ZIP and installs. Never re-add demo binaries or host Apache stubs to git.

## When in Doubt

- Choose safer behavior: explicit validation, explicit errors, no silent fallback.
- Keep changes minimal and scoped.
- Ask for confirmation before destructive or wide-reaching repository operations. Deleting gitignored runtime trees (`data/`, `media/`, `log/`, `backups/`) is the same class of action as entering a password: **forbidden** unless the operator named those exact paths in the same message. Docs and “fresh install” are not permission.


_Last updated: 2026-08-18_

- **Python requirements:** host CPython **3.6.9+**; build deps `Pillow`, `mutagen`, `xxhash` (site-local `scripts/vendor/` + offline `scripts/vendor-wheels/`); `ffmpeg` (see [README.md](README.md)). Operators never run `pip`.
- **Campaign portability:** [PORTABILITY.md](PORTABILITY.md) PCF / `.pcf` contract is source of truth for demo and operator campaign handoff.
