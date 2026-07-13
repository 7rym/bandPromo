# Install and Update Guide

This guide is for operators who want to run bandPromo without relying on Git, SSH, shell access, Plesk repository features, or other developer-only tooling.

## What this guide assumes

- You can upload files into the folder where your site should live.
- Your host can run PHP 8+ with `pdo_sqlite` enabled and SQLite **3.8.0+** bundled with that PHP build.
- Your host allows outbound HTTPS requests so the bootstrap installer can download the published release package and query the GitHub Releases API for beta prereleases.
- You can open a URL in the browser after uploading the installer file.

This guide does not assume:

- Git access
- SSH or shell access
- root or server-admin access
- Cloudflare or other edge/CDN services
- Plesk-specific workflows

## First install: operator path

The preferred operator install flow is a one-file bootstrap installer.

### 1. Prepare the target folder

- Choose the web folder where bandPromo should be installed.
- Make sure the folder is writable by PHP.
- Start with an empty folder or a folder that does not already contain another application.

### 2. Upload `bootstrap.php`

- Upload the standalone `bootstrap.php` file into the target web folder.
- Keep the filename as `bootstrap.php`.

### 3. Open the installer in the browser

- Visit `https://your-site.example/bootstrap.php`.
- The installer should run environment checks before it downloads anything.

The current bootstrap installer checks at least:

- PHP 8+
- `pdo_sqlite` (listener activity logs and analytics)
- SQLite **3.8.0+** bundled with PHP (bootstrap runs `SELECT sqlite_version()`)
- `ZipArchive`
- HTTPS-capable download support
- writable target folder access

If one of these checks fails, stop and fix that problem first. The installer is designed to stop safely rather than continue with a partial install.

### 4. Let the bootstrap discover the published release package

- The operator installer now uses the published `release-manifest.json` as the authoritative source.
- During v0.8 beta, it resolves the newest published release tag through the GitHub Releases API (including prereleases). GitHub `releases/latest` alone points only at the newest stable release.
- It should show the release version before install.

Normal operator installs should not use mutable branch snapshots such as `main.zip`.

### 5. Start the install

When you choose `Download and install latest release`, the bootstrap should:

- download the release ZIP into a temporary work area
- verify package integrity when the published manifest provides a checksum
- extract the package into staging
- confirm that the extracted package looks like a valid bandPromo application
- copy tracked application files into place
- preserve local runtime paths if they already exist

Preserved runtime paths include at least:

- `web-config.json`
- `.env`
- `data/`
- `media/`
- `log/`

Bundled demo content (locked release `bandpromo-demo`) is installed from the setup starter pack before the first publish build on the host. That package includes demo audio (`media/audio/original/bandPromo_*.flac`), visuals, and icons. Demo media is not tracked as general operator uploads in git.

### 6. Continue into setup

After package install, open `setup.php`.

Setup should then:

- verify required PHP extensions (including `pdo_sqlite` for activity logging)
- create the first admin account
- seed the required runtime files from tracked templates
- download the required default theme package if its starter assets are not already present on the server
- ask for the license/operator-responsibility acknowledgment
- land you in admin with seeded demo content and a next-step checklist

The seeded demo content is intentional. It is part of first-run verification and helps confirm that playback, theming, and the site shell are working on the real host. Demo audio/media are delivered by the setup starter pack and publish build — not copied from git-tracked files in the repository.

## If the bootstrap stops

The installer is expected to stop safely when something is wrong.

Common causes:

- no published release manifest is available yet
- the target folder is not writable
- `pdo_sqlite` is missing
- `ZipArchive` is missing
- the host cannot make outbound HTTPS requests
- the package download fails

In those cases:

- do not keep refreshing and hoping the state will repair itself
- fix the reported host problem first
- rerun `bootstrap.php`

The bootstrap flow is intended to be safe to rerun in the same folder.

## Current update status

The operator-facing admin/package updater is now available from **Dashboard → Site update**.

That means:

- bandPromo can check the published `release-manifest.json` from admin
- during v0.8 beta, prerelease packages are included: the updater resolves the newest published release tag (including prereleases) because GitHub `releases/latest` points only at the newest stable release
- operators can download and apply immutable release packages in the browser
- normal operators should not be expected to use `git pull`, SSH, or hosting-panel repository tools as the long-term update path
- failed update attempts are logged locally and are safe to retry

### If admin is unavailable after an update

If `/admin.php` returns HTTP 500 after a package update, use a one-time repository pull from the site root (developer or hosting-panel git tool), then reload admin:

```text
git pull origin main
```

Your content under `data/`, `media/`, `web-config.json`, and `log/` is preserved. After admin loads again, use **Dashboard → Site update** for future releases.

## Operator update flow

The intended operator update flow is:

### 1. Check for an available update in admin

Open **Dashboard → Site update**. The updater will:

- compare the installed version with the published release metadata
- validate `release-manifest.json` requirements (PHP version, `pdo_sqlite`, `ZipArchive`, and future declared deps) against this server **before** download/apply
- show the current version and available version
- explain whether the update is recommended or optional
- show a short change summary when available

### 2. Download the selected release package

The updater will:

- download the immutable release package into a temporary runtime-safe location
- verify package integrity before applying it
- extract to staging and validate the package structure before replacing files

### 3. Apply the update safely

The updater replaces tracked application files while preserving runtime/operator-managed state.

Preserved runtime/operator-managed state includes at least:

- `web-config.json`
- `.env`
- `data/`
- `media/`
- `log/`

Operators should be warned clearly that:

- application files are being replaced
- runtime content is being preserved
- a failed update should not wipe local media or configuration

### 4. Run post-update tasks

Where practical, the updater runs required post-update tasks automatically, such as:

- cache refresh
- manifest refresh
- build-required recalculation
- required migrations for the shipped version

### 5. Report the outcome clearly

The updater reports whether:

- the update completed successfully
- the site is ready immediately
- a follow-up admin action is needed
- a retry is safe after a failed extraction or apply step

Normal operators should not need manual cleanup just because a package apply failed.

## After a successful Site update

Package update **preserves** your content. It replaces application PHP/JS only. These paths stay on disk:

- `web-config.json`, `.env`, `data/`, `media/`, `log/`

**What to do next (normal workflow):**

1. Open **Notifications** or **System → Deliverables**.
2. Run **Rebuild all deliverables** once. After every package update, bandPromo marks delivery work as pending — this refreshes listener-ready files and the site manifest. It is expected, not an error.
3. Smoke-test admin and playback.

**What Site update does not do:**

- It does **not** wipe or replace your pages, playlists, media, or config.

**After Site update (v0.8.3+):**

1. Open **Notifications** or **System → Deliverables**.
2. Run **Rebuild all deliverables** once. bandPromo prepares your content links automatically during that step — there is no separate content-model upgrade card.
3. Config structure updates happen silently in the background when you open admin.

**Before updating:** use **Admin → System → Backup & export** to **create** a full site backup, wait until it shows **Ready**, then download it. On hosts without ZipArchive, use your hosting panel to ZIP `data/`, `media/`, and `web-config.json` instead.

## Manual operator fallback

- Prefer published immutable release packages over repository snapshots.
- Treat local runtime data as something that must survive updates.
- Test the site after install and after any manual update.
- If you rely on a developer/manual update path, do not overwrite `web-config.json`, `.env`, `data/`, `media/`, or `log/`.

## Related documents

- `README.md` for the quick-start overview
- `docs/ROADMAP.md` for the install/update product contract
- `docs/PORTABILITY.md` for full backup vs data export/import and moved-site recovery
- `docs/OPERATOR-RESPONSIBILITY.md` for operator boundaries
- `docs/SUPPORT.md` for support expectations