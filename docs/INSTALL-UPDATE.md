# Install and Update Guide

This guide is for operators who want to run bandPromo without relying on Git, SSH, shell access, Plesk repository features, or other developer-only tooling.

## What this guide assumes

- You can upload files into the folder where your site should live.
- Your host can run PHP 8+.
- Your host allows outbound HTTPS requests so the bootstrap installer can download the published release package.
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
- `ZipArchive`
- HTTPS-capable download support
- writable target folder access

If one of these checks fails, stop and fix that problem first. The installer is designed to stop safely rather than continue with a partial install.

### 4. Let the bootstrap discover the published release package

- The operator installer now uses the published `release-manifest.json` as the authoritative source.
- It should automatically discover the latest published immutable release package.
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

Bundled install-seed assets shipped inside the package, such as the default icon bundle and tracked `bandPromo_*` demo media, should still be copied into a fresh install even though the broader `media/` tree is treated as preserved runtime state on updates.

### 6. Continue into setup

After package install, open `setup.php`.

Setup should then:

- create the first admin account
- seed the required runtime files from tracked templates
- ask for the license/operator-responsibility acknowledgment
- land you in admin with seeded demo content and a next-step checklist

The seeded demo content is intentional. It is part of first-run verification and helps confirm that playback, theming, and the site shell are working on the real host.

## If the bootstrap stops

The installer is expected to stop safely when something is wrong.

Common causes:

- no published release manifest is available yet
- the target folder is not writable
- `ZipArchive` is missing
- the host cannot make outbound HTTPS requests
- the package download fails

In those cases:

- do not keep refreshing and hoping the state will repair itself
- fix the reported host problem first
- rerun `bootstrap.php`

The bootstrap flow is intended to be safe to rerun in the same folder.

## Current update status

The operator-facing admin/package updater is the intended direction, but it is not the normal shipped path yet.

That means:

- bandPromo should move toward browser-based package updates from admin
- normal operators should not be expected to use `git pull`, SSH, or hosting-panel repository tools as the long-term update path
- until the admin updater is shipped, updates may still require a developer/manual workflow

## Future operator update flow

The intended operator update flow is:

### 1. Check for an available update in admin

The future updater should:

- compare the installed version with the published release metadata
- show the current version and available version
- explain whether the update is recommended or optional
- show a short change summary when available

### 2. Download the selected release package

The future updater should:

- download the immutable release package into a temporary runtime-safe location
- verify package integrity before applying it
- extract to staging and validate the package structure before replacing files

### 3. Apply the update safely

The future updater should replace tracked application files while preserving runtime/operator-managed state.

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

Where practical, the updater should run required post-update tasks automatically, such as:

- cache refresh
- manifest refresh
- build-required recalculation
- required migrations for the shipped version

### 5. Report the outcome clearly

The updater should report whether:

- the update completed successfully
- the site is ready immediately
- a follow-up admin action is needed
- a retry is safe after a failed extraction or apply step

Normal operators should not need manual cleanup just because a package apply failed.

## Current operator advice until the admin updater ships

- Prefer published immutable release packages over repository snapshots.
- Treat local runtime data as something that must survive updates.
- Test the site after install and after any manual update.
- If you rely on a developer/manual update path today, do not overwrite `web-config.json`, `.env`, `data/`, `media/`, or `log/`.

## Related documents

- `README.md` for the quick-start overview
- `docs/ROADMAP.md` for the install/update product contract
- `docs/OPERATOR-RESPONSIBILITY.md` for operator boundaries
- `docs/SUPPORT.md` for support expectations