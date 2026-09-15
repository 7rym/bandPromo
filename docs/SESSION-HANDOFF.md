# Session Handoff

## Resume point

Published hotfix for HITZ stdout closed-file crash (**build 498** pending). After Site update: Refresh should get past Python startup.

### Fixed

- Double UTF-8 wrap in `build.py` after `stdio_utf8.configure()` (Py 3.6 closed file)
- Notifications no longer paint “steps waiting” into under-the-hood `buildStatus`

### Encoding / line endings

Always UTF-8; preflight must warn and reconfigure ASCII-locale hosts. Repo text LF via `.gitattributes`. Rule: `.cursor/rules/utf8-line-endings.mdc`.

### Follow-on

- Native Python Repair Apply body
- Remaining publish stages that shell PHP CLI

### Active fleet

| Host | Persona |
|------|---------|
| bandpromo.site | Vanilla |
| hitz.no | HITZ |
| spandexualtension.com | Band / release sequence |

### Local workspace

Checkout is **`C:\dev\bandpromo`**. Never wipe `data/` / `media/` / `log/` / `backups/` here.
