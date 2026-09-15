# Session Handoff

## Resume point

Published **v0.8.59 build 496** (pending) HITZ trust hotfix after 495 failed prep on ASCII locale.

### Fixed in this hotfix

- Publish prep UTF-8 stream (no more `ascii codec` crash on HITZ)
- Help default closed; Status help not a scare banner
- Site update prompts for Refresh — does not auto-start or lie about rebuilding
- Under-the-hood “steps waiting” nudge removed

### Encoding / line endings

Always UTF-8; preflight must warn and reconfigure ASCII-locale hosts. Repo text LF via `.gitattributes`. Rule: `.cursor/rules/utf8-line-endings.mdc`.

### Active fleet

| Host | Persona |
|------|---------|
| bandpromo.site | Vanilla |
| hitz.no | HITZ |
| spandexualtension.com | Band / release sequence |

### Local workspace

Checkout is **`C:\dev\bandpromo`**. Never wipe `data/` / `media/` / `log/` / `backups/` here.
