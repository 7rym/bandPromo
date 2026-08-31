# bandPromo Development Guide

This guide is for developers, maintainers, and server administrators working with the repository directly.

The product's preferred install story is the bootstrap installer plus published release packages. The repository/manual path still works, but it is a developer fallback rather than the main operator workflow.

**Code layout (v0.9 candidate):** application code is split across `biblioteca/`, `scripts/`, and root `vendor/` today. A consolidated `/lib` tree and `/admin/` entry (mirroring `/play/`) is planned for v0.9 evaluation — see [CODE-LAYOUT-REFACTOR.md](CODE-LAYOUT-REFACTOR.md). Do not start during v0.8 polish.

## Manual Repository Install

Use this path when you are working from the source tree instead of the published bootstrap package flow.

1. Upload or clone the repository contents to the target web folder.
2. Ensure the server can run PHP 8+ with `pdo_sqlite` enabled (activity logs and analytics). For bootstrap/package flows and multi-file downloads, make sure `ZipArchive` is available. If you are testing the bootstrap path, also make sure outbound HTTPS download support (`curl` or `allow_url_fopen`) and a writable target folder are available.
3. Open `setup.php` in the browser.
4. Create the first admin account and finish the setup wizard.
5. Use the admin panel to upload media, edit content, and run builds.

## Runtime File Rules

These paths are runtime-managed and should not be committed back into git:

- `web-config.json`
- `.env`
- `data/`
- `media/`
- `log/`

Tracked application updates must preserve that runtime state.

## Local PHP Dev Server

`scripts/start-dev-server.ps1` runs PHP's built-in server for local admin and site testing. It spawns PHP as a detached background process and exits immediately (no long-lived terminal). Request output goes to `log/dev-server.log`; stop it with `scripts/stop-dev-server.ps1`. It does **not** read Apache `.htaccess` rules.

Player path URLs (`/play/{playlist}`, `/play/{playlist}/{track}`, `/play/{playlist}/{release}/{track}`) are parsed inside `play/index.php` (query `?playlist=` still wins). That keeps deep links working on php -S and on hosts missing `play/.htaccess`.

That is acceptable for local work:

- **Publish build** launches `scripts/build.py` through PHP (`biblioteca/build.php` → `build-runner.php`) using `proc_open` only (`php.exe` → `python.exe`). No `cmd.exe`, PowerShell, or `.bat` files.
- **Production** Apache/nginx installs deny direct HTTP access to `log/` and `data/` through setup-generated `.htaccess` stubs (from `biblioteca/templates/runtime/`). Operators can re-check and repair those managed stubs from **System → Security**.

Do not add a PHP router script to mimic `.htaccess` in dev — it duplicates production policy, triggers false-positive antivirus heuristics, and treats a symptom of web-root runner files that the launcher already prevents.

## Common Commands

- Bump session number at session start:
  - `python scripts/bump_session.py`
- Bump build number before checkpoint/push:
  - `python scripts/bump_version.py`
- Run a build from the repository:
  - `python scripts/build.py`
- Start a dev session:
  - `powershell -ExecutionPolicy Bypass -File scripts/session-start.ps1`
- End a dev session / checkpoint:
  - `powershell -ExecutionPolicy Bypass -File scripts/session-end.ps1 -CommitMessage "..." -Push -Publish -ReleaseSummary "..."`
- Check PHP syntax for a touched file:
  - `php -l path/to/file.php`
- Build a local distributable package intentionally:
  - `python scripts/build_release_package.py --clean`

## Session Start and End

Use the session scripts when opening or closing repository work.

### Session start

- CLI: `powershell -ExecutionPolicy Bypass -File scripts/session-start.ps1`
- Cursor chat slash prompt: `/bandpromo-session-start`

Session start:

1. `git pull --ff-only origin main`
2. bumps the **session** number in `VERSION` (`v0.8.4` → `v0.8.5`, build unchanged)
3. starts the PHP dev server in the background (`scripts/start-dev-server.ps1`)
4. prints environment context, git state, backlog, and a recommended next focus

Flags: `-SkipPull`, `-SkipSessionBump`, `-SkipDevServer`

### Session end

- CLI: `powershell -ExecutionPolicy Bypass -File scripts/session-end.ps1 -CommitMessage "..." [-Push] [-Publish] [-ReleaseSummary "..."]`
- Cursor chat slash prompt: `/bandpromo-session-end`

Session end:

1. validates tracked changes and `docs/CHANGELOG.md`
2. bumps the **build** number in `VERSION`
3. commits non-forbidden tracked changes
4. optionally pushes to `main`
5. builds `dist/bandpromo-*.zip`
6. optionally triggers **Publish release package** when `-Publish` and `-Push` are set

Use `-SkipValidation` only when you intentionally accept a risky checkpoint.

## Release Package Notes

Distributable install packages are intentional release artifacts, not something every build should emit automatically.

**Pushing to `main` does not update Site update.** The admin package updater checks the newest published GitHub Release manifest, not the branch tip.

### Tester / operator publish checklist

When shipping a checkpoint to hosted testers:

1. Bump `VERSION`, commit, push to `main`.
2. Publish the immutable GitHub Release package (workflow below).
3. Verify the release tag, assets, and `release-manifest.json` on GitHub.
4. Open **Dashboard → Site update** on a test install and confirm the new build is offered.

Tag naming: `v0.8.4 build 303` in `VERSION` → release tag `v0.8.4-build-303`.

Available paths:

- Run `python scripts/build_release_package.py --clean --skip-demo-manifest` locally for a quick app package/manifest sanity check (use `--require-demo-manifest` when the durable `demo-content` release exists).
- Trigger **Build release package artifact** for a private/manual artifact build (no public release).
- Trigger **Publish release package** when a build should become the latest operator-facing immutable GitHub Release package (`bandPromo.zip` + `release-manifest.json` only).
- Update Demo PCF only when campaign content changes: `python scripts/prepare_demo_content_package.py --pcf path\to\export.pcf --clean --publish`.
- Or use `scripts/session-end.ps1 -Push -Publish` after a validated checkpoint commit.

Example publish command (GitHub CLI):

```powershell
gh workflow run "Publish release package" `
  -f tag_name=v0.8.15-build-377 `
  -f release_name="bandPromo v0.8.15 build 377" `
  -f prerelease=false `
  -f draft=false
```

Use `prerelease=false` for closed-beta tester packages unless you intentionally keep a build off `/releases/latest`.

The bootstrap installer and admin **Site update** both rely on the published `release-manifest.json` asset and the immutable package URLs declared there. Mutable branch snapshots are acceptable for ad-hoc developer testing, but they are no longer part of the normal operator install path.

**Fresh-install tests** always run on **https://bandpromo.site** (Vanilla: empty install + setup / Site update). The other active remote test sites are **Spandexual Tension** and **HITZ**. **Twisted Chronicles** rejoins via reinstall at v0.9. Never delete local `data/`, `media/`, `log/` (analytics/audit test data), or `backups/` in this Google Drive working copy.

Local PHP built-in server must allow large PCF uploads: `scripts/start-dev-server.ps1` sets `upload_max_filesize=512M` / `post_max_size=520M`. Hosted installs use `biblioteca/templates/runtime/user.ini` (same ceilings). Backup/PCF background jobs must still run when `fastcgi_finish_request()` is missing.

Platform install icons are tracked at `biblioteca/templates/icons/bP-icons.zip`. Build/setup/packaging copy and expand them into gitignored `media/icons/` when required favicon/PWA files are missing; they are never committed under `/media`.

## First-Time Runtime Seeding

First-time setup depends on tracked templates and examples being copied into runtime-managed locations. For the current seeded setup flow, the important tracked sources include:

- `biblioteca/templates/web-config.template.json`
- `biblioteca/templates/default.release.template.json`
- `biblioteca/templates/bio.template.json`
- `biblioteca/templates/faq.template.json`
- `biblioteca/templates/icons/bP-icons.zip` (platform favicon/PWA seed → `media/icons/` at build/setup)
- `.env.example`

Legacy import shapes (not seeded on fresh installs) live under `biblioteca/templates/legacy/`.

## Related Docs

- [BUILD-PIPELINE-AUDIT.md](BUILD-PIPELINE-AUDIT.md) — publish stage order, gaps, refactor plan
- [INSTALL-UPDATE.md](INSTALL-UPDATE.md)
- [MEDIA-HANDLING.md](MEDIA-HANDLING.md)
- [ROADMAP.md](ROADMAP.md)
- [TODO.md](TODO.md)
- [AGENTS.md](AGENTS.md)
