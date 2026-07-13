# Implementation Plan — Feature 051: Upgrade acrossai-co/main-menu 0.0.14 → 0.0.18

**Branch**: `046-absorb-core-abilities-into-manager` (shared, same PR #65)
**Date**: 2026-07-13
**Spec**: [spec.md](./spec.md)

## Summary

Pure dependency version bump. Update `composer.json` constraint,
regenerate `composer.lock`, run the quality-gate sweep, commit. No
consumer-side code changes.

## Technical Context

- **Constraint change**: `composer.json` line 25 → `"acrossai-co/main-menu": "0.0.18"`
- **Lock refresh**: `composer update acrossai-co/main-menu --no-scripts` (or `composer update` scoped to the one package)
- **Cascading changes**: None. Sibling packages (`wpboilerplate/wpb-access-control`, `berlindb/core`, `automattic/jetpack-autoloader`) are unaffected.
- **Test surface**: existing 123 PHPUnit + 8 Jest tests. No new tests required — the vendor changes are Freemius-owned UX additions that don't lend themselves to unit tests inside the manager plugin.

## Constitution Check

**Constitution version**: 1.4.8 (2026-07-01).

| Principle / Rule | Status | Notes |
|---|---|---|
| §II WordPress Standards — PHPStan / PHPCS / Plugin Check | ✔ Gated | Vendor code lives under `vendor/` and is excluded by `phpcs.xml.dist`. |
| §V Extensibility Without Core Modification | ✔ Gated | No plugin-code change to accommodate the upgrade. |
| §VII Definition of Done | ✔ Enforced | PHPStan/PHPCS/PHPUnit + npm validate must pass. |
| `DEC-STABLE-UPGRADE-WINDOW-INTERNAL-ORG` | ✔ Applies | `acrossai-co/main-menu` is an internal-org package; the "wait for v1.0.0" clause is exempted per this existing decision. |
| `DEC-EXTERNAL-PACKAGE-HOOK-CTOR` | ✔ Unchanged | `AddonsPage` constructor still self-registers hooks with `class_exists()` guard in `Main.php::define_admin_hooks()`. |
| `DEC-EXTERNAL-PACKAGE-HOOK-CTOR-SHARED-MENU` | ✔ Unchanged | `SettingsPage` bootstrap at `plugins_loaded @ P0` still uses `did_action()` guard for idempotency. |

No new deviations. No Complexity Tracking entries needed.

## Project Structure

Files touched:

```
composer.json         — 1 line change (version constraint 0.0.14 → 0.0.18)
composer.lock         — Composer-generated (new package version + tarball SHA)
```

No new files. No source-tree changes.

## Execution Order

1. Edit `composer.json` line 25.
2. Run `composer update acrossai-co/main-menu --no-scripts` to refresh `composer.lock`.
3. Run `composer phpstan` (whole plugin, level 8).
4. Run `composer phpcs -- includes/Main.php admin/Partials/` (manager-owned files).
5. Run `composer test` (full PHPUnit suite).
6. Commit both files together.
7. Push and watch CI.

## Verification

- Local: PHPStan / PHPCS / PHPUnit green.
- CI: All 8 GitHub Actions checks green on PR #65.
- Manual (deferred to user): activate the manager on a wp-env fixture and
  confirm the three new Freemius submenus (Account / Contact Us / Support
  Forum) appear under the AcrossAI top-level menu.

## Rollback

If the upgrade breaks anything, revert with:

```
git revert <the-single-commit>
composer install
```

The upgrade is a single-commit change; rollback is trivial.
