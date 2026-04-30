# Copilot Instructions for bandPromo

This file provides additional guidance for GitHub Copilot and other AI coding agents working in the bandPromo repository. For full project context, see [AGENTS.md](../AGENTS.md).

## Key Points
- **bandPromo** is a self-hosted artist platform (see [README.md](../README.md)).
- **First-run setup** is browser-based; shell access is optional.
- **Build pipeline**: Use the admin panel or `python scripts/build.py`.
- **Media input**: Follow the current media policy and source-handling rules (see [MEDIA-HANDLING.md](../MEDIA-HANDLING.md)).
- **Security**: Session-based auth, CSRF, rate limiting, HTTPS (see [SECURITY-AUDIT.md](../SECURITY-AUDIT.md)).
- **Never overwrite** local runtime files (`web-config.json`, uploaded media, logs, data/) during updates.

## Documentation
- [AGENTS.md](../AGENTS.md): Main agent instructions
- [README.md](../README.md): Setup, requirements
- [FEATURES.md](../FEATURES.md): Features, architecture
- [MEDIA-HANDLING.md](../MEDIA-HANDLING.md): Media policy and current handling contract
- [ROADMAP.md](../ROADMAP.md): Roadmap
- [TODO.md](../TODO.md): Current tasks
- [SECURITY-AUDIT.md](../SECURITY-AUDIT.md): Security

## Common Pitfalls
- Source audio may be weak, but the current build still depends on enough usable metadata and media structure to produce a trustworthy result.
- Local runtime files are not tracked by git—never overwrite these on update.
- Some security features depend on correct server configuration—verify after deployment.

---

_Last updated: 2026-04-28_
