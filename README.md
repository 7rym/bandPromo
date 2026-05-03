
# bandPromo

**Self-hosted artist platform for music promotion, private listening, and direct fan experiences.**

Current version: `v0.7`

---

## Quick Start

1. **Upload the repo** to a web server (via git, zip, or copy).
2. **Open in your browser** – the setup wizard will start automatically and create the admin user.
3. **Log in to the admin panel** (`/admin.php`) to upload media, edit content, and run builds.
4. **Run the build** from the admin panel (or use `python scripts/build.py` if you have shell access).

---

## Requirements

- Web server: Apache/Nginx with PHP 8+
- PHP extensions: `json`, `session`, `openssl`
- HTTPS hosting required, HTTP support for localhost supported
- For build: Python 3.8+, `Pillow`, `mutagen`, `ffmpeg`

---

## Documentation

- [Features](docs/FEATURES.md) — Current features overview
- [Roadmap](docs/ROADMAP.md) — Long-term goals and milestones
- [Media Handling](docs/MEDIA-HANDLING.md) — Source media policy, metadata, masters, and delivery strategy
- [Third-Party Notices](docs/THIRD-PARTY-NOTICES.md) — Third-party libraries, tools, hosted services, and license notes
- [Operator Responsibility](docs/OPERATOR-RESPONSIBILITY.md) — Operator boundaries
- [Support](docs/SUPPORT.md) — Support and maintenance
- [Trademarks](docs/TRADEMARKS.md) — Naming and branding

---

**Tips:**
- Source/demo audio does not have to be perfect, but reliable builds still depend on enough usable media information ([docs/MEDIA-HANDLING.md](docs/MEDIA-HANDLING.md)).
- Local runtime files (`web-config.json`, `.env`, media, data, logs) are not tracked by git and should never be committed.
- For repository pushes to `main`, bump the tracked build number locally first with `python scripts/bump_version.py` so the commit already contains the new `VERSION`.
- Use tracked templates for first-time setup: `biblioteca/templates/web-config.template.json`, `biblioteca/templates/gallery.template.json`, `biblioteca/templates/bio.template.html`, `biblioteca/templates/faq.template.html`, and `.env.example`.
- For details on features, configuration, media handling, security, and roadmap: see the markdown files above.

## License

bandPromo is licensed under the GNU Affero General Public License v3 (AGPLv3). 
See the LICENSE file for details. 
Operator and deployment responsibilities are described in docs/OPERATOR-RESPONSIBILITY.md.
Third-party tools and services used by the project are documented in docs/THIRD-PARTY-NOTICES.md.
