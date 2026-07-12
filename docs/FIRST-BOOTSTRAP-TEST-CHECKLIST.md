# First Bootstrap Test Checklist

Use this checklist for the first real hosted test of the operator bootstrap installer.

This checklist is intentionally narrow. It is for proving that the first install flow works on a real host, not for full beta coverage.

## Goal

Confirm that a non-technical operator can upload `bootstrap.php`, open it in the browser, install the latest published release package, finish setup, and land in admin without using Git, SSH, shell access, or hosting-panel repository tools.

## Preflight: release prerequisites

- [ ] A GitHub release package has been published with the `Publish release package` workflow.
- [ ] `releases/latest/download/release-manifest.json` is reachable.
- [ ] The published manifest contains at least: `version`, `package_url`, and `sha256`.
- [ ] The ZIP URL in the manifest is reachable.

## Preflight: host prerequisites

- [ ] A disposable test folder or test site exists.
- [ ] The target folder is writable by PHP.
- [ ] The host runs PHP 8+.
- [ ] `pdo_sqlite` is available.
- [ ] `ZipArchive` is available.
- [ ] Outbound HTTPS requests work from PHP.
- [ ] The folder does not already contain another application that could be overwritten.

## Deployment step

- [ ] Upload only `bootstrap.php` into the target web folder.
- [ ] Open `https://your-test-host.example/bootstrap.php`.

## Expected bootstrap page behavior

- [ ] The page loads successfully.
- [ ] Environment checks are shown.
- [ ] All required checks are marked OK.
- [ ] Release discovery shows the published version.
- [ ] Release discovery shows the immutable package source.
- [ ] The install button is enabled.

## Install action

- [ ] Click `Download and install latest release`.
- [ ] The installer downloads the package without a fatal PHP error.
- [ ] The installer extracts the ZIP without a package-structure error.
- [ ] The installer finishes with a success message.
- [ ] `setup.php` is available afterward.

## Setup handoff

- [ ] Open `setup.php`.
- [ ] The setup wizard loads.
- [ ] The license/operator-responsibility acknowledgment is shown.
- [ ] The first admin account can be created.
- [ ] Setup completes without missing-runtime-file errors.

## First-run verification

- [ ] Admin opens after setup.
- [ ] Seeded demo content is present.
- [ ] The next-step checklist is visible/readable.
- [ ] The public site opens.
- [ ] Basic playback/build/theming surfaces look intact enough for a first-run verification pass.

## Failure notes to capture

Record the exact failing stage if the test stops:

- bootstrap page missing/unreachable
- manifest lookup failed
- package download failed
- checksum verification failed
- ZIP extract failed
- package root validation failed
- file copy/apply failed
- setup handoff failed
- setup runtime seeding failed
- admin landing failed

## Current known blockers before the first real test

At the time this checklist was added, the first full hosted bootstrap test was still blocked by deployment/publishing prerequisites:

- the published `release-manifest.json` URL was not yet reachable
- the live `bootstrap.php` URL on `bandpromo.site` was not yet deployed/reachable