# bandPromo Security Audit Report

Current review target: `v0.8`  
Review date: 2026-07-12  
Previous review: 2026-06-12

## Summary

bandPromo is a closed, session-authenticated PHP application with file/JSON storage for site content and a **local SQLite activity store** (`data/analytics/events.sqlite`) for listener/audit analytics. Path handling, upload routing, HTTPS enforcement, and page HTML sanitization are generally sound. The **2026-06-12 release checkpoint** adds a shared **admin-role guard** on `admin.php` and admin biblioteca APIs so listener accounts cannot reach operator surfaces.

Remaining release risks are mostly **session abuse hardening** (CSRF, login rate limits, password hashing, explicit server-side session lifetime policy) rather than missing authorization on admin endpoints.

**2026-06-13 UX note:** Admin and player now include a lightweight client-side session watchdog (`biblioteca/session-auth.js` + `biblioteca/session-check.php`) that redirects expired sessions to login. This improves operator clarity but is not a substitute for explicit server-side timeout configuration.

**2026-07-12 storage note:** Analytics and audit events now use PDO SQLite through `biblioteca/activity-store.php`. This is a local append-only store, not a shared multi-tenant database. Queries use prepared statements; there is no operator-facing SQL surface.

## Fixed in v0.7 build 276 checkpoint

| Item | Mitigation |
| --- | --- |
| Listener sessions could open admin panel and admin APIs | `bandpromo_require_admin_session()` in `biblioteca/auth.php`, `biblioteca/admin-api-guard.php`, `admin.php` access-denied page, and admin biblioteca endpoints |
| Log files potentially web-readable | `log/.htaccess` denies direct HTTP access |

## Critical — fix before wider public beta

_None open after the admin-role guard. Re-verify on each release._

## High — should fix soon

| Risk | Location | Notes |
| --- | --- | --- |
| MD5 password hashing | `biblioteca/auth.php` | Replace with `password_hash()` / `password_verify()` and migrate `data/terces` |
| CSRF missing on most admin mutations | Admin save/upload/delete/build endpoints | Only quiz scores and audio metadata save validate CSRF today |
| No login rate limiting | `admin.php`, `index.php`, `setup-init.php` | `rate-limit.php` is quiz-only |
| Setup window account creation | `setup-init.php` | No setup token, CSRF, or rate limit on first-admin POST |
| Migration mode treats all users as admin | `biblioteca/auth.php` `isAdminUser()` | When no `admin`/`developer` roles exist, every account is admin-capable |

## Medium — hardening

| Risk | Location | Notes |
| --- | --- | --- |
| Session fixation | Login handlers | No `session_regenerate_id()` on successful login |
| Gallery stored XSS | `biblioteca/gallery.js` | `src` / `alt` rendered without escaping |
| Public debug endpoints | `pwa-debug.php`, `speed-test.php` | Unauthenticated diagnostics / bandwidth use |
| Sensitive build debug in API | `build.php`, `get-build-log.php` | Paths and environment hints in JSON responses |
| Quiz answer leakage | `quiz.php` | Correct answers sent to client |
| IP header trust | `rate-limit.php`, `admin-audit.php` | `X-Forwarded-For` accepted without trusted-proxy boundary |
| SQLite file permissions | `data/analytics/events.sqlite` | Relies on `data/.htaccess` and host filesystem permissions; verify on each deployment |

## Low / informational

| Area | Status |
| --- | --- |
| SQL injection | Low risk for current surface: activity-store uses PDO prepared statements only; no ad-hoc SQL from request input |
| Path traversal on upload/delete/download | `basename()`, extension routing, fixed directories |
| Page HTML XSS | HTMLPurifier in `biblioteca/page-text-sanitize.php` (page save/render pipeline) |
| `data/` HTTP exposure | Denied via `data/.htaccess` (includes analytics SQLite under `data/analytics/`) |
| HTTPS | Enforced except localhost (`https.php`) |
| Direct JSON access | Root `.htaccess` rewrites for config/highscores/quiz |
| Quiz score integrity | Server-side validation + rate limits |
| Command injection in build | `proc_open` with fixed script paths |
| Third-party admin scripts | Chart.js is self-hosted under `vendor/chart.js`; Ko-fi widget remains optional operator-controlled remote script on the player |

## Listener vs admin surfaces

**Authenticated listener endpoints** (player/quiz/logging) intentionally remain available to `role=user` accounts:

- `audio.php`, `gallery.php`, `get-config.php`, `quiz.php`, `save-score.php`, `get-highscores.php`, `log.php`

**Admin-only endpoints** now require an admin-capable role via `admin-api-guard.php`.

## Recommended next security work (v0.8 track)

1. Password hashing migration
2. CSRF on all state-changing admin endpoints and admin form POSTs
3. Server-side login/setup rate limits
4. `session_regenerate_id()` on login
5. Validate gallery `src` / `alt` on save; escape on render
6. Gate or remove debug endpoints in production builds
7. Re-verify SQLite directory permissions and backup scope after analytics migration on beta hosts

## Review method

Static code review of auth/session flows, biblioteca API guards, upload/delete/download paths, activity-store SQL usage, and operator documentation. No external penetration test was performed for this checkpoint.
