# Trademarks

Source of truth for how bandPromo names itself and how third-party product names appear in the project.

Related: [README_LICENSE_NOTICE.md](README_LICENSE_NOTICE.md), [THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md), [OPERATOR-RESPONSIBILITY.md](OPERATOR-RESPONSIBILITY.md).

## bandPromo product naming

- **bandPromo** is the product name for this self-hosted publishing platform.
- Preferred styling in English project text: `bandPromo` (camel case **P**).
- Copyright for the bandPromo software is held by **7rym.net** (see [README_LICENSE_NOTICE.md](README_LICENSE_NOTICE.md) and `/LICENSE`).
- bandPromo is software, not a hosted service. Each operator runs their own installation under their own site name, domain, and branding.

Nothing in this file grants permission to use third-party marks, and nothing here restricts legitimate fair use of the bandPromo name when referring to the software itself under the project's license terms.

## Operator-owned branding

Operators remain responsible for the names, logos, artwork, and promotional copy on the sites they publish with bandPromo.

That includes:

- artist, band, label, or project names
- release titles and cover art
- custom domains and social links
- support/membership destinations they configure

bandPromo provides structure and tooling; it does not claim ownership of operator content or operator brand identity.

## Third-party marks referenced by bandPromo

The software and documentation may reference third-party product or service names when describing integrations, presets, examples, or optional hosted features.

Common examples today:

| Mark | How bandPromo uses it |
|------|------------------------|
| **Ko-fi** | optional support widget or link destination configured by the operator |
| **Spotify** | release streaming-link preset label in the release editor |
| **Apple Music** | release streaming-link preset label in the release editor |
| **GitHub** | published release packages and Site update source |
| **Cloudflare** | optional login-page speed-test endpoint |
| **Chart.js** | admin analytics chart library (self-hosted under `vendor/chart.js`) |
| **FFmpeg** | external media tool used by the build pipeline |

Future integrations named in roadmap/docs (for example Chromecast, Patreon, Stripe, PayPal, Vipps) are planning references only until actually implemented.

## Non-endorsement

All third-party trademarks, service marks, logos, and trade names mentioned in bandPromo code or documentation are the property of their respective owners.

Reference to a third-party name in bandPromo does **not** imply:

- sponsorship or endorsement by that third party
- an official partnership unless the operator has arranged one separately
- that bandPromo is affiliated with that company's products or services

Operators enabling third-party integrations are responsible for complying with those providers' terms, branding rules, and disclosure requirements on the sites they run.

## Maintenance rules

- Update this file when a new third-party brand becomes user-visible in product UI, setup copy, or operator docs.
- Keep example/preset labels aligned with actual UI text in admin and player surfaces.
- Do not use third-party logos in repository assets unless license and trademark permission are explicit.
