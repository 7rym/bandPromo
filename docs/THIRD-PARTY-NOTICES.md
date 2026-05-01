# Third-Party Notices

This document records the third-party software components, libraries, tools, and hosted services that bandPromo currently depends on or loads at runtime.

It exists for two reasons:

- to give clear credit to upstream projects
- to keep license and redistribution obligations visible during development and deployment

This file should be updated whenever a third-party dependency is added, removed, vendored, bundled, or loaded from a remote service.

## Scope

This notice file distinguishes between:

- bundled or direct code dependencies used by bandPromo
- external tools required by the build pipeline
- hosted third-party services loaded at runtime

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
- Notes: this is already a copyleft build dependency in the current project; keep that visible in future licensing decisions

### Chart.js

- Purpose: admin analytics charts
- Used by: `admin.php` via jsDelivr CDN (`chart.js@4.4.1`)
- Homepage: <https://www.chartjs.org/>
- Source: <https://github.com/chartjs/Chart.js>
- License: MIT
- Notes: currently loaded from CDN, not vendored in the repository

### TinyMCE Community

- Purpose: rich text editing for admin-managed static page content
- Used by: `admin.php`, `biblioteca/admin.js`
- Version: 8.5.0
- Vendored path: `vendor/tinymce`
- Homepage: <https://www.tiny.cloud/>
- Source: <https://github.com/tinymce/tinymce>
- License: GPL-2.0-or-later
- Notes: self-hosted community build; chosen because it fits the current server-rendered PHP admin model and supports a practical source-mode fallback

### HTML Purifier

- Purpose: server-side sanitization of admin-managed HTML content before storage and rendering
- Used by: `biblioteca/save-bio.php`
- Version: 4.19.0
- Vendored path: `vendor/htmlpurifier`
- Homepage: <http://htmlpurifier.org/>
- Source: <https://github.com/ezyang/htmlpurifier>
- License: LGPL-2.1-or-later
- Notes: used as the actual security boundary for rich text content; editor-side filtering is treated only as convenience, not trust

## External build tools

### FFmpeg

- Purpose: audio and media processing in the build pipeline
- Used by: `scripts/build.py`, `scripts/optimizeMedia.py`
- Homepage: <https://ffmpeg.org/>
- Source: <https://ffmpeg.org/download.html>
- License: upstream FFmpeg is LGPL-2.1-or-later by default, but some builds become GPL depending on enabled components
- Notes:
  - bandPromo currently treats FFmpeg as an external tool, not a Python package
  - `scripts/build.py` can attempt to auto-download a static FFmpeg build from `johnvansickle.com/ffmpeg`
  - if a project distribution bundles FFmpeg binaries, the exact binary build, its license terms, and corresponding notice/source obligations must be reviewed and documented at distribution time
  - do not assume every FFmpeg binary has the same license profile

## Hosted third-party services

### Ko-fi overlay widget

- Purpose: floating support widget on the player page
- Used by: `play/index.php`
- Script source: `https://storage.ko-fi.com/cdn/scripts/overlay-widget.js`
- Homepage: <https://ko-fi.com/>
- Notes:
  - this is a hosted remote script, not a vendored library in the repository
  - usage is governed by Ko-fi's service terms and hosted script behavior
  - deployments with strict privacy, CSP, or third-party-script restrictions may choose to remove or disable this integration

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