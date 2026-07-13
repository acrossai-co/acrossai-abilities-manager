# Tasks — Feature 051: Upgrade acrossai-co/main-menu 0.0.14 → 0.0.18

**Input**: Design documents from `/specs/051-main-menu-0-0-18-upgrade/`
**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md)

Small dependency-bump feature — 5 tasks, all in one commit.

## Phase 1 — Setup + Bump

- [ ] T001 Edit `composer.json` line 25 — change `"acrossai-co/main-menu": "0.0.14"` to `"acrossai-co/main-menu": "0.0.18"`.
- [ ] T002 Run `composer update acrossai-co/main-menu --no-scripts` to refresh `composer.lock` with the new tarball SHA + version metadata.

## Phase 2 — Quality Gates

- [ ] T003 Run `composer phpstan` (whole plugin, level 8) — assert zero errors.
- [ ] T004 Run `composer phpcs` (scoped by phpcs.xml.dist to manager-owned files) — assert zero errors.
- [ ] T005 Run `composer test` (full PHPUnit suite) — assert 123/123 tests pass, 307 assertions, 0 failures.

## Phase 3 — Ship

- [ ] T006 Commit `composer.json` + `composer.lock` + the three `specs/051-…/*.md` files together with a descriptive message.
- [ ] T007 Push. Watch the 8 GitHub Actions checks on PR #65; assert all green.
- [ ] T008 (Manual, deferred to user) Activate the manager on wp-env; verify Freemius Account / Contact Us / Support Forum submenus render under the AcrossAI top-level menu; verify `?page=acrossai-addons` no longer resolves (expected upstream retirement).

## Notes

- No new source files under `includes/`, `admin/`, or `src/`.
- No new tests. The vendor changes are Freemius-UX additions; there is no manager-side surface to unit-test.
- Constitution deviations: none.
- Follow-up: none tracked for this feature. If a sibling AcrossAI plugin depended on `?page=acrossai-addons`, that's their upgrade to schedule.
