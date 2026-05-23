
# bandPromo

**Self-hosted artist platform for music promotion, private listening, and direct fan experiences.**

Current version: `v0.7`

---

bandPromo is intended for artists and small operators who want to run a branded music site on their own hosting without depending on a centralized platform. The normal install path is now a browser-based bootstrap flow aimed at non-technical operators.

## For Operators

Most installs should use the bootstrap method.

1. **Upload `bootstrap.php`** to the target web folder.
2. **Open it in the browser** so bandPromo can check the host, download the latest published release package, and install the tracked application files.
3. **Continue through the setup wizard** to create the first admin account, confirm the operator responsibility note, and finish setup.
4. **Open the admin panel** to verify the seeded demo content and start replacing the example material with your own site details, media, and pages.

bandPromo is designed so the first-run experience can work with ordinary PHP hosting. The product direction is one uploaded bootstrap file, one browser-based install flow, and package-based updates rather than Git, SSH, Plesk, or other developer-oriented server tools.

### Manual Alternative

bandPromo can also work by uploading or cloning the repository and then opening `setup.php`, but that path is mainly for developers or server administrators. It is supported as a fallback, not as the primary operator story.

---

## Hosting Requirements

- Web server: Apache/Nginx with PHP 8+
- `ZipArchive` for bootstrap package install, package-based updates, and multi-file downloads
- For bootstrap install: outbound HTTPS download support (`curl` or `allow_url_fopen`) and a writable target folder
- HTTPS hosting required, HTTP support for localhost supported
- For the build step: Python 3.8+, `Pillow`, `mutagen`, and `ffmpeg`

If your hosting provider does not already support the build requirements, the bootstrap and setup flow now try to explain what needs to be enabled before continuing.

---

## Operator Documentation

- [Features](docs/FEATURES.md) — Current features overview
- [First Bootstrap Test Checklist](docs/FIRST-BOOTSTRAP-TEST-CHECKLIST.md) — Real-host smoke test checklist for the operator installer
- [Install and Update Guide](docs/INSTALL-UPDATE.md) — Operator-facing install and planned package-update guidance
- [Operator Responsibility](docs/OPERATOR-RESPONSIBILITY.md) — Content, rights, privacy, hosting, and integration boundaries
- [Support](docs/SUPPORT.md) — Support and maintenance
- [Third-Party Notices](docs/THIRD-PARTY-NOTICES.md) — Third-party libraries, tools, hosted services, and license notes
- [Trademarks](docs/TRADEMARKS.md) — Naming and branding

## For Developers

If you are evaluating bandPromo as a codebase rather than as an operator, start here instead:

- [Development Guide](docs/DEVELOPMENT.md) — Repository workflow, manual install path, build/package commands, and release notes for developers
- [Roadmap](docs/ROADMAP.md) — Long-term goals and milestones
- [Media Handling](docs/MEDIA-HANDLING.md) — Source media policy, metadata, masters, and delivery strategy

The preferred packaged operator flow and the repository/manual setup flow are both valid, but they serve different audiences. The README stays focused on operators; deeper repository workflow details now live in the development guide.

---

## License

bandPromo is licensed under the GNU Affero General Public License v3 (AGPLv3). 
See the LICENSE file for details. 
Operator and deployment responsibilities are described in docs/OPERATOR-RESPONSIBILITY.md.
Third-party tools and services used by the project are documented in docs/THIRD-PARTY-NOTICES.md.
The intended first-run experience should explicitly ask operators to confirm that they understand those boundaries before completing setup.
