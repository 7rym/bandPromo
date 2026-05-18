
# bandPromo

**Self-hosted artist platform for music promotion, private listening, and direct fan experiences.**

Current version: `v0.7`

---

## Quick Start

Target operator workflow:

1. **Upload one bootstrap PHP file** to the target web folder.
2. **Open it in the browser** so bandPromo can validate the host, download a package ZIP, and install the tracked application files.
3. **Continue through the setup wizard** to create the first admin, confirm the operator responsibility note, and finish the site setup.
4. **Land in the admin panel** with seeded demo content and a clear next-step checklist so the operator can verify the install and start replacing example content.

Current manual/developer fallback:

1. **Upload the current repository tree** to a web server (via git, ZIP extraction, or file copy).
2. **Open the site in your browser** so the setup wizard can create the admin user and runtime configuration.
3. **Log in to the admin panel** (`/admin.php`) to upload media, edit content, and run builds.
4. **Run the build** from the admin panel (or use `python scripts/build.py` if you have shell access).

Current installation note:

- The operator-facing direction is a one-file bootstrap installer plus packaged release ZIPs; that is the workflow the product docs should optimize for.
- An initial standalone installer now exists as `bootstrap.php` in the project root. It performs environment checks, downloads a ZIP package, extracts it into place, preserves runtime state on re-entry, and then hands off to `setup.php`.
- Distributable install packages are not produced by every build. They are produced only when explicitly requested, either locally with `python scripts/build_release_package.py`, through the manual GitHub Actions workflow `Build release package artifact`, or through the manual `Publish release package` workflow when a build should become an operator-facing immutable release.
- The current repository-based install path remains a temporary manual/developer fallback and is still best suited to developer/server-admin users.
- The setup flow includes a friendly acknowledgment step covering the AGPL license plus the operator responsibilities documented for content, rights, privacy, hosting, and enabled third-party services.
- Reusable installs should seed demo content by default and confirm success primarily by opening admin with a next-step checklist, not by leaving operators at a blank public shell.
- The intended long-term update path is ZIP/package-based through the admin panel rather than requiring Git, Plesk, SSH, or other hosting-panel tooling.
- The preferred package source is immutable versioned ZIP releases hosted alongside the repository on GitHub, with the tracked `VERSION` file used for lightweight update/version checks.
- The bootstrap installer now looks for the latest published `release-manifest.json` asset first. If no published immutable release exists yet, it accepts a package ZIP URL directly and falls back to the current GitHub branch snapshot only for manual development/testing.
- A plain GitHub branch snapshot such as `archive/refs/heads/main.zip` may still be acceptable for manual developer testing, but it is not the intended operator install/update source because it is mutable and not tied to a stable packaged version.
- Package installs/updates must preserve local runtime state such as `web-config.json`, `.env`, `/data`, `/media`, and logs.

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
- To create a distributable install package intentionally, run `python scripts/build_release_package.py --clean`, trigger `Build release package artifact` for a private/manual artifact build, or trigger `Publish release package` when you want that build exposed as an immutable GitHub Release asset for bootstrap installs.
- Use tracked templates for first-time setup: `biblioteca/templates/web-config.template.json`, `biblioteca/templates/gallery.template.json`, `biblioteca/templates/bio.template.html`, `biblioteca/templates/faq.template.html`, and `.env.example`.
- For details on features, configuration, media handling, security, and roadmap: see the markdown files above.

## License

bandPromo is licensed under the GNU Affero General Public License v3 (AGPLv3). 
See the LICENSE file for details. 
Operator and deployment responsibilities are described in docs/OPERATOR-RESPONSIBILITY.md.
Third-party tools and services used by the project are documented in docs/THIRD-PARTY-NOTICES.md.
The intended first-run experience should explicitly ask operators to confirm that they understand those boundaries before completing setup.
