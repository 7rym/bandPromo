# Operator Responsibility

bandPromo is self-hosted publishing software. It provides tools for running a music site, but it does not take over the responsibilities that belong to the site operator.

This document exists to separate product responsibility from operator responsibility clearly.

## What bandPromo is intended to provide

bandPromo, as software, is intended to provide:

- the application code, setup flow, and documented runtime requirements
- the admin tools, media/build pipeline, playback flow, and analytics features described in the project docs
- technical controls and workflows the product explicitly claims to support
- documentation about how the software is intended to be used

bandPromo is not a hosted service, moderation service, legal service, or operational staff.

## What the operator is responsible for

The operator of each installation is responsible for the actual site they run.

That includes:

- the content they publish
- the rights they rely on for audio, images, video, lyrics, text, branding, and other uploaded material
- the privacy, consent, and legal/compliance obligations that apply in their jurisdiction
- the way accounts, passwords, and access rules are managed on their installation
- the hosting environment, domain, TLS/HTTPS, backups, uptime, and server operations
- the review and safe use of third-party integrations they enable
- the real-world consequences of publishing broken, misleading, infringing, or unlawful material

## Content and rights

bandPromo does not verify ownership, licensing, or clearance of uploaded material.

The operator is responsible for ensuring they have the right to publish and distribute:

- audio and lyrics
- artwork, cover images, gallery media, and page illustrations
- logos, brand assets, and promotional copy
- any third-party material embedded, quoted, linked, or reused on the site

If rights are unclear, that is an operator problem to resolve before publication.

## Hosting, security, and operations

bandPromo can help structure a site, but it does not operate the deployment.

The operator is responsible for:

- choosing and maintaining the hosting environment
- securing admin access and server credentials
- keeping the deployment updated and tested before/after upgrades
- handling backups and recovery planning
- monitoring storage, logs, certificates, and server health
- deciding how private or public access is configured

If a server is misconfigured, unavailable, compromised, or loses data, that is part of operator responsibility.

## Privacy and data handling

bandPromo may log playback, admin actions, and other operational data, but the operator is responsible for how their installation uses that data.

The operator is responsible for:

- deciding what data is collected and retained
- publishing any privacy information their jurisdiction requires
- configuring access rules appropriately for private or public use
- using analytics and logs in a lawful and proportionate way
- handling requests, disclosures, or retention rules that apply to their installation

bandPromo is not a substitute for privacy review or compliance advice.

## Third-party services and integrations

If the operator enables third-party services such as analytics providers, email services, OAuth providers, payment tools, or other external platforms, the operator is responsible for:

- deciding whether those integrations are appropriate
- accepting their terms and privacy implications
- handling the data-sharing and compliance consequences
- maintaining the credentials and configuration they require

bandPromo may support integrations technically, but it does not assume responsibility for the services behind them.

## Media quality and publication decisions

bandPromo can improve packaging quality, validation, metadata guidance, and delivery generation, but it cannot decide what the operator should publish.

The operator remains responsible for:

- deciding whether weak source media is good enough to publish
- reviewing validation warnings and publish blockers
- confirming metadata, artwork, lyrics, and release presentation before release
- choosing whether a site should remain private, restricted, or public

bandPromo can help diagnose and repair packaging problems, but it does not replace operator review.

## Moderation and audience-facing decisions

If an installation exposes comments, community tools, fan interactions, or public-facing content areas, the operator is responsible for moderation and policy decisions on that installation.

That includes:

- what user-generated content is allowed
- how abusive or unlawful content is handled
- how accounts are approved, suspended, or removed
- how disputes or takedown issues are handled

bandPromo may provide moderation tools, but responsibility for using them remains local to the operator.

## Support boundaries

bandPromo does not include guaranteed support, uptime, legal review, moderation review, or operational assistance.

Bug reports, suggestions, and contributions may be considered, but the project does not promise:

- response times
- custom deployment help
- guaranteed fixes
- compatibility guarantees across every hosting setup
- operator-specific legal or compliance guidance

## Practical rule of thumb

Use this distinction:

- If the question is about what the software claims to do, that belongs to bandPromo documentation and product scope.
- If the question is about what a specific site publishes, stores, exposes, permits, or integrates, that belongs to the operator.

bandPromo provides the toolset. The operator is responsible for the installation they run with it.