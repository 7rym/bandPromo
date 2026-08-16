# Third-Party Notices

This document records the third-party software components, libraries, tools, and hosted services that bandPromo currently depends on or loads at runtime.

It exists for two reasons:

- to give clear credit to upstream projects
- to keep license and redistribution obligations visible during development and deployment

This file should be updated whenever a third-party dependency is added, removed, vendored, bundled, or loaded from a remote service.

Related: [README_LICENSE_NOTICE.md](README_LICENSE_NOTICE.md), [TRADEMARKS.md](TRADEMARKS.md), [OPERATOR-RESPONSIBILITY.md](OPERATOR-RESPONSIBILITY.md).

## Scope

This notice file distinguishes between:

- bundled or direct code dependencies used by bandPromo
- external tools required by the build pipeline
- hosted third-party services loaded at runtime
- runtime platform components required by PHP (extensions), not separate vendored packages

Planned future dependencies should not be listed here until they are actually added to the repository or required by the runtime/build workflow.

## Bundled and direct dependencies

### Pillow

- Purpose: image processing in the build pipeline
- Used by: `scripts/optimizeMedia.py`, `scripts/makeSocial.py`
- Requirement reference: `scripts/requirements.txt`
- Homepage: <https://python-pillow.org/>
- Source: <https://github.com/python-pillow/Pillow>
- License: MIT-CMU
- Notes: permissive license; safe fit for the current AGPLv3 project

### Mutagen

- Purpose: audio metadata parsing and tag handling
- Used by: `scripts/makePlaylists.py`, `scripts/optimizeMedia.py`, `scripts/debugTags.py`
- Requirement reference: `scripts/requirements.txt`
- Homepage: <https://mutagen.readthedocs.io/>
- Source: <https://github.com/quodlibet/mutagen>
- License: GPL-2.0-or-later
- Notes: copyleft build-time dependency; keep visible in future licensing decisions

### Chart.js

- Purpose: admin analytics charts
- Used by: `admin.php`
- Version: 4.4.1
- Vendored path: `vendor/chart.js`
- Upstream license file: `vendor/chart.js/LICENSE`
- Homepage: <https://www.chartjs.org/>
- Source: <https://github.com/chartjs/Chart.js>
- License: MIT
- Notes: self-hosted UMD build (`chart.umd.min.js`); admin no longer loads Chart.js from a CDN

### HTML Purifier

- Purpose: server-side sanitization of stored page HTML before persistence and public rendering
- Used by: `biblioteca/page-text-sanitize.php` (called from `page-blocks.php`, `page-renderer.php`, and the page save pipeline)
- Version: 4.19.0
- Vendored path: `vendor/htmlpurifier`
- Upstream license file: `vendor/htmlpurifier/LICENSE`
- Homepage: <http://htmlpurifier.org/>
- Source: <https://github.com/ezyang/htmlpurifier>
- License: LGPL-2.1-or-later
- Notes: this is the security boundary for stored page HTML; editor-side filtering is convenience only, not trust

## Runtime platform components

### SQLite (via PHP `pdo_sqlite`)

- Purpose: local listener activity and admin audit event store (`data/analytics/events.sqlite`)
- Used by: `biblioteca/activity-store.php`
- Requirement: PHP `pdo_sqlite` extension (checked in bootstrap/setup preflight)
- Homepage: <https://www.sqlite.org/>
- License: SQLite is public domain; the PHP PDO driver ships with PHP itself
- Notes: not a separate vendored library in this repository

## External build tools

### FFmpeg

- Purpose: audio and media processing in the build pipeline
- Used by: `scripts/build.py`, `scripts/optimizeMedia.py`
- Homepage: <https://ffmpeg.org/>
- Source: <https://ffmpeg.org/download.html>
- License: upstream FFmpeg is LGPL-2.1-or-later by default, but some builds become GPL depending on enabled components
- Notes:
  - bandPromo currently treats FFmpeg as an external tool, not a Python package
  - On Linux, `scripts/build.py` can auto-download static `ffmpeg` + `ffprobe` from the durable GitHub release tag `ffbinaries/ffbinaries-prebuilt` (currently pinned to v6.1 ZIP assets). Those Linux binaries are repackaged from johnvansickle static builds; GitHub is used as the download host for shared-host reliability.
  - if a project distribution bundles FFmpeg binaries, the exact binary build, its license terms, and corresponding notice/source obligations must be reviewed and documented at distribution time
  - do not assume every FFmpeg binary has the same license profile

## Hosted third-party services

### GitHub Releases and API

- Purpose: published install/update packages and release discovery
- Used by: `bootstrap.php`, `biblioteca/release-package.php`, `biblioteca/package-updater.php`
- Endpoints include:
  - `https://github.com/7rym/bandPromo/releases/latest/download/release-manifest.json`
  - `https://api.github.com/repos/7rym/bandPromo/releases`
- Homepage: <https://github.com/>
- Notes:
  - outbound HTTPS download support is required for bootstrap install and Site update
  - usage is governed by GitHub's service terms and the operator's hosting/network policy

### Ko-fi

- Purpose: optional operator-owned support destination linked from the player
- Used by: Settings → Support (Ko-fi handle / direct URL) and the in-flow support button on `play/index.php`
- Homepage: <https://ko-fi.com/>
- Notes:
  - bandPromo does not load Ko-fi's floating overlay widget; support is a provider-neutral link button only
  - when operators enter a Ko-fi handle without a full URL, the player links to `https://ko-fi.com/<handle>`
  - usage is governed by Ko-fi's service terms when visitors follow that outbound link

### Cloudflare speed test endpoint

- Purpose: login-page connection speed probing
- Used by: `biblioteca/login.js`
- Endpoint: `https://speed.cloudflare.com/__down?bytes=10000000`
- Homepage: <https://www.cloudflare.com/>
- Notes:
  - this is an external service endpoint, not a bundled library
  - operators who do not want external speed-test traffic should replace or disable this behavior

## Maintenance rules

- Keep this file aligned with the actual repository state.
- If a dependency is vendored or bundled, include its upstream license text or a clear reference to where that license is shipped.
- If a remote script or hosted service is added, record it here even if no source code is committed to the repository.
- If FFmpeg binaries are redistributed with bandPromo, document the exact source and build details used for that binary.
- If a vendored package remains in the tree but is no longer loaded, mark it explicitly here until it is removed.
