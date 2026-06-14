# bandPromo Development Guide

This guide is for developers, maintainers, and server administrators working with the repository directly.

The product's preferred install story is the bootstrap installer plus published release packages. The repository/manual path still works, but it is a developer fallback rather than the main operator workflow.

## Manual Repository Install

Use this path when you are working from the source tree instead of the published bootstrap package flow.

1. Upload or clone the repository contents to the target web folder.
2. Ensure the server can run PHP 8+. For bootstrap/package flows and multi-file downloads, make sure `ZipArchive` is available. If you are testing the bootstrap path, also make sure outbound HTTPS download support (`curl` or `allow_url_fopen`) and a writable target folder are available.
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

## Common Commands

- Bump version before pushing to `main`:
  - `python scripts/bump_version.py`
- Run a build from the repository:
  - `python scripts/build.py`
- Run the fast session-start summary:
  - `powershell -ExecutionPolicy Bypass -File scripts/session-start.ps1`
- Check PHP syntax for a touched file:
  - `php -l path/to/file.php`
- Build a local distributable package intentionally:
  - `python scripts/build_release_package.py --clean`

## Fast Session Start

Use the fast startup path when opening the repository after a break or starting a new chat session.

- CLI: `powershell -ExecutionPolicy Bypass -File scripts/session-start.ps1`
- VS Code task: `bandPromo: Fast session startup`
- VS Code chat slash prompt: `/bandpromo-session-start`

The fast path is meant to replace repeated manual startup checks. It prints the active shell/runtime context, current git state, available workspace tasks, the current milestone target, the first unresolved v0.8 tasks from `docs/TODO.md`, recent changelog entries, and one recommended next focus.

## Release Package Notes

Distributable install packages are intentional release artifacts, not something every build should emit automatically.

Available paths:

- Run `python scripts/build_release_package.py --clean` locally.
- Trigger `Build release package artifact` for a private/manual artifact build.
- Trigger `Publish release package` when a build should become the latest operator-facing immutable GitHub Release package.

The bootstrap installer now relies on the published `release-manifest.json` asset and the immutable package URL declared there. Mutable branch snapshots are acceptable for ad-hoc developer testing, but they are no longer part of the normal operator install path.

## First-Time Runtime Seeding

First-time setup depends on tracked templates and examples being copied into runtime-managed locations. For the current seeded setup flow, the important tracked sources include:

- `biblioteca/templates/web-config.template.json`
- `biblioteca/templates/gallery.template.json`
- `biblioteca/templates/bio.template.json`
- `biblioteca/templates/faq.template.json`
- `.env.example`

## Related Docs

- [INSTALL-UPDATE.md](INSTALL-UPDATE.md)
- [MEDIA-HANDLING.md](MEDIA-HANDLING.md)
- [ROADMAP.md](ROADMAP.md)
- [TODO.md](TODO.md)
- [AGENTS.md](AGENTS.md)
