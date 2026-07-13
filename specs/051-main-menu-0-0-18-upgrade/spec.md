# Feature Specification: Upgrade acrossai-co/main-menu 0.0.14 → 0.0.18

**Feature Branch**: `046-absorb-core-abilities-into-manager` (shared with Feature 046 — no separate branch)
**Created**: 2026-07-13
**Status**: In progress
**Input**: User request: `acrossai-co/main-menu 0.0.18 also make sure to make the necessary changes; we are going to spec-kit so in the pr make sure we have all that files as well in the pr`

## Context

The manager plugin currently pins `acrossai-co/main-menu: 0.0.14`. Upstream
tags exist for `0.0.15`, `0.0.16`, `0.0.17`, `0.0.18` (not published as GitHub
Releases but exist as git tags). Between `0.0.14` and `0.0.18` four commits
land, all under `src/Addons/`:

1. `a6a35ffd feat(addons): expose fs_has_addons on AddonsPage $args (unblock Freemius Add-ons row)`
2. `d467f839 chore(addons): disable Add-ons submenu registration` — `?page=acrossai-addons` no longer reachable after activation.
3. `0fb50ea6 feat(addons): let consumers override the Freemius menu config via fs_menu`
4. `a58dec91 feat(addons): enable Freemius Account, Contact Us, wp.org Support Forum submenus` — visible UX addition under the shared AcrossAI menu.

Files touched upstream: `README.md`, `src/Addons/AddonsPage.php`,
`src/Addons/FreemiusInitializer.php`, `src/Addons/MenuRegistrar.php`.

Consumer-side impact assessment: **none required**. All four commits are
additive to the vendor package or opt-in for consumers. The existing
manager's `AddonsPage` call in `Main.php::define_admin_hooks()` continues
to work without argument changes.

## User Scenarios & Testing

### User Story 1 — Freemius Account / Contact / Support submenus appear (Priority: P1)

Site administrator with the manager plugin active sees three additional
Freemius-owned submenus (Account, Contact Us, wp.org Support Forum)
appear automatically under the shared AcrossAI top-level menu.

**Independent Test**: On wp-env with the manager active, open wp-admin →
AcrossAI. The three new submenus render.

**Acceptance Scenarios**:

1. **Given** the manager plugin is active on wp-env, **When** the admin
   opens wp-admin, **Then** the AcrossAI top-level menu shows Freemius
   Account, Contact Us, and wp.org Support Forum submenus in addition to
   the existing Abilities / Library / Settings entries.
2. **Given** an admin clicks the Freemius Account submenu, **When** the
   page loads, **Then** the standard Freemius account UI renders without
   PHP errors.

### User Story 2 — Existing Add-ons submenu URL is retired (Priority: P2 — expected regression)

The `?page=acrossai-addons` URL no longer resolves after the upgrade —
this is the intentional upstream retirement in commit `d467f839`. No
in-plugin remediation required; documented as expected behavior.

**Independent Test**: Visit `wp-admin/admin.php?page=acrossai-addons` — it
either redirects to the AcrossAI dashboard or shows the standard "Sorry,
you are not allowed" WP page. Either outcome is acceptable per upstream.

## Requirements

- **FR-051-01**: The manager plugin's `composer.json` MUST pin
  `acrossai-co/main-menu` to `0.0.18` (from `0.0.14`).
- **FR-051-02**: The manager plugin's `composer.lock` MUST reflect the
  updated `main-menu` package (installed tarball SHA + version metadata).
- **FR-051-03**: No consumer-side code changes to `Main.php` or any admin
  partial are required. Existing `AddonsPage` constructor call keeps
  working as-is.
- **FR-051-04**: PHPStan level 8, PHPCS, and the full PHPUnit suite must
  remain green after the composer update.
- **FR-051-05**: `?page=acrossai-addons` may become unreachable — that is
  the intentional upstream change and is not a manager-side regression.

## Success Criteria

- **SC-051-01**: `composer.json` shows `"acrossai-co/main-menu": "0.0.18"`.
- **SC-051-02**: `composer.lock` shows the package version bumped from
  `0.0.14` to `0.0.18` with a fresh tarball SHA.
- **SC-051-03**: `composer test` passes 123/123 tests unchanged.
- **SC-051-04**: `composer phpstan` exits 0.
- **SC-051-05**: `composer phpcs` on manager-owned files exits 0.
- **SC-051-06**: All 8 GitHub Actions checks (PHP Compat, PHPStan,
  PHPUnit×5, WPCS) remain green on the PR.

## Assumptions

- The upstream `main-menu` package's `0.0.18` tag is stable and won't be
  retagged. If retagged, `composer.lock` will pin the SHA at update time.
- The Freemius integration is properly configured with the rotated
  credentials from Feature 046 (`fs_product_id = 34418`,
  `fs_public_key = pk_d61a7ddb1a619f7697fbb4fc397b6`). The new Freemius
  submenus render against this account.
- No sibling AcrossAI plugin depends on `?page=acrossai-addons` being
  reachable. If any does, they need their own upgrade to accommodate the
  upstream retirement (out of scope here).
