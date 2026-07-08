# Implementation Plan: Upgrade `acrossai-co/main-menu` 0.0.11 → 0.0.12

**Branch**: `045-main-menu-0-0-12-upgrade` | **Date**: 2026-07-08 | **Spec**: [spec.md](./spec.md)

## Summary

Bump `acrossai-co/main-menu` from `0.0.11` to `0.0.12` and migrate one call site to the new instance-method API for per-tab page slug resolution. The Abilities tab at `wp-admin/admin.php?page=acrossai-settings&tab=abilities` continues to work unchanged from the admin's perspective. Four-file change — `composer.json`, `composer.lock` (auto-regenerated), `vendor/acrossai-co/main-menu/**` (auto-regenerated), and `admin/Partials/SettingsMenu.php` (2 new lines + 1 changed line + docblock note). Plus four spec-kit files.

## Technical Context

**Language/Version**: PHP 8.1+. No JS. No SCSS. No REST. No DB.
**Primary Dependencies**: `acrossai-co/main-menu@0.0.12`. Same transitive deps as 0.0.11 (`automattic/jetpack-autoloader: ^5.0`, `freemius/wordpress-sdk: ^2.0`).
**Storage**: No change. The existing `acrossai_abilities_per_page` and `acrossai_abilities_uninstall_delete_data` options continue to persist under the shared `acrossai-settings` option_group.
**Testing**: No new PHPUnit test. Manual walkthrough (T009) is the accepted verification path. PHP test count stays at 105.
**Target Platform**: WordPress 6.9+ admin, PHP 8.1+.
**Project Type**: WordPress plugin — single project. Composer dependency bump + minimal API migration.
**Performance Goals**: Zero regression. The 0.0.12 API is not measurably more expensive than the 0.0.11 static call — one accessor call + one instance-method call per `admin_init`.
**Constraints**: No changes to `acrossai-mcp-manager`. No new hooks, no new REST routes, no new DB tables. Cross-plugin coordination hazard (documented in spec Assumptions) is explicitly accepted per user decision.
**Scale/Scope**: 1 composer edit, 1 PHP file edit (~5 lines net), 1 vendor tree regeneration, 4 spec-kit files.

## Constitution Check

| Principle (CONSTITUTION.md v1.4.8) | Applies? | Status | Notes |
|---|---|---|---|
| §I Modular Architecture — Abilities module ownership | Yes | ✅ Pass | The only edited file (`admin/Partials/SettingsMenu.php`) already lives inside the admin module. No cross-module dependency introduced. |
| §I Boot Flow Rule | Yes | ✅ Pass | No hook registration or PHP loader edit. Root `Main.php` untouched. `plugins_loaded` bootstrap for `new SettingsPage()` at `acrossai-abilities-manager.php:142-154` unchanged. |
| §I Admin Partials Rule | Yes | ✅ Pass | Only edit inside `admin/Partials/` is to `SettingsMenu.php`, which already owns the settings surface. |
| §I Module Contract (singleton) | Yes | ✅ Pass | `SettingsMenu::instance()` unchanged. |
| §II WordPress Standards | Yes | ✅ Pass | Uses standard Settings API (`register_setting`, `add_settings_section`, `add_settings_field`). The new instance-method access pattern matches the 0.0.12 README's own example verbatim. |
| §II `acrossai_` prefix | Yes | ✅ Pass | No new PHP identifiers. |
| §II Multisite compatible | Yes | ✅ Pass | Pure options-API change; per-site option storage is preserved. |
| §III UI Contract (DataForm / DataViews) | Yes | ✅ Pass | Settings API sections + fields — not touched. |
| §IV Security First | Yes | ✅ Pass | No new input surface. `register_setting`'s `sanitize_callback` closures are unchanged. |
| §V Extensibility Without Core Modification | Yes | ✅ Pass | We consume the `acrossai_settings_tabs` filter (unchanged in 0.0.12) as documented. |
| §VI DRY | Yes | ✅ Pass | Reuses the existing `TAB_SLUG` constant, existing `register_tab()` callback, existing sanitizer helpers. |
| §VII Definition of Done | Yes | ✅ Verified via per-task gates in [tasks.md](./tasks.md). |

**Constitution Gate**: **PASS**. No amendment required. No accepted-deviation change.

## Memory Synthesis Findings

Synthesized in [memory-synthesis.md](./memory-synthesis.md). Highlights applied to this plan:

- **AC-ENQUEUE-ADMIN** — not applicable. No JS bundle change.
- **DEC-MENU-HOOK-SUFFIX** — not applicable. Hook suffixes for the Settings page are owned by the `acrossai-co/main-menu` package; we don't cache one on our side.
- **Feature 026** — the Add-ons submenu (`acrossai-addons`) is also owned by `main-menu`. No change to that surface in 0.0.12 (per 0.0.12 README's own note that the Add-ons page ships from `main-menu` itself). Not affected by this bump.
- **Feature 027** — the Library submenu bootstrap. Not affected — that page belongs to this plugin, not to `main-menu`.
- **Feature 038** — established the shared `acrossai` parent menu slug ownership by `main-menu`. This feature reinforces that pattern: `main-menu` owns the Settings-page rendering surface; consumer plugins own their tabs and sections.
- **No new memory pattern warranted at n=1**. First `main-menu` bump. If a second breaking bump ships later, capture as `PATTERN-MAIN-MENU-BUMP-CHECKLIST`.

## Project Structure

### Documentation (this feature)

```text
specs/045-main-menu-0-0-12-upgrade/
├── spec.md              # 10 FRs, 8 SCs, 2 user stories, 6 edge cases
├── plan.md              # This file
├── tasks.md             # 11 tasks across 5 phases
└── memory-synthesis.md  # Memory synthesis
```

### Source Code (repository root)

**Files EDITED** (2 tracked + auto-generated vendor/lock):

```text
composer.json                                            # 1 line: 0.0.11 → 0.0.12
composer.lock                                            # auto-regenerated
vendor/acrossai-co/main-menu/**                          # auto-regenerated
admin/Partials/SettingsMenu.php                          # 2 new lines + 1 changed line + docblock update
```

**Files NOT touched**:

```text
acrossai-mcp-manager/                                    # Sibling — mandatory follow-up (out of scope)
src/js/                                                  # No JS change
src/scss/                                                # No SCSS change
build/                                                   # No rebuild required
includes/Main.php                                        # Filter wiring unchanged
admin/Main.php                                           # Enqueue guards unchanged
admin/Partials/Menu.php                                  # Untouched
admin/Partials/LibraryMenu.php                           # Untouched
tests/phpunit/                                           # No test added or modified
docs/memory/                                             # No memory entry added
```

**Structure Decision**: Single-project WordPress plugin. Composer dependency bump + minimal API migration inside one class. No new directories except the new `specs/045-*/` folder.

## Phase 0 — Research Findings

| Question | Decision | Rationale |
|---|---|---|
| Bump strategy — `composer update main-menu` or `--with-dependencies`? | **`--with-dependencies`**. | Safest against transitive dep drift. Verified from 0.0.12's composer.json: no new hard deps beyond what 0.0.11 already had, but `--with-dependencies` ensures any sub-dep resolution consistent with 0.0.12's requirements. |
| Do we consume `TabbedPageRenderer` (new abstract) directly? | **No**. | The existing Abilities tab lives on the shared Settings page, which is already owned by `SettingsPageRenderer` (a subclass of `TabbedPageRenderer`). We extend it via the `acrossai_settings_tabs` filter — same as 0.0.11. Subclassing `TabbedPageRenderer` would be relevant only if we wanted a NEW tabbed admin page. |
| Null-guard `get_settings_renderer()` return? | **Yes**. | 0.0.12 README example (README.md:172-175) shows the guard. Our bootstrap guarantees the renderer is populated by `admin_init` (we `new SettingsPage()` on `plugins_loaded` priority 0), but the guard is cheap and future-proofs against boot-ordering edits. |
| Update the docblock reference to the removed static? | **Yes**. | The docblock at `admin/Partials/SettingsMenu.php:97-107` explicitly names the removed helper and pins "acrossai-co/main-menu v0.0.4+". Both bits are now stale. Refreshing both is a 10-second win that helps every future reader. |
| Touch `acrossai-mcp-manager` in this feature? | **No** (user decision). | Confirmed via AskUserQuestion. Sibling upgrade is a mandatory follow-up documented in the PR body — but the sibling's code lives in a different git repo/plugin and is out of scope for this branch. |
| Add PHPUnit coverage for the new call path? | **No**. | The refactor is a one-line static-to-instance API swap. Adding a Settings-API test now would require mocking the entire `main-menu` package initialization sequence — high setup cost, low value at n=1. Manual walkthrough is the accepted verification path (matches Feature 043's precedent). |
| Register a memory pattern for the bump procedure? | **No**. | n=1 first main-menu bump. Capture on recurrence — if a second bump ships, register `PATTERN-MAIN-MENU-BUMP-CHECKLIST` then. |

## Phase 1 — Design

### Data Model

No change. `acrossai_abilities_per_page` and `acrossai_abilities_uninstall_delete_data` continue to persist as options under the shared `acrossai-settings` option_group. Sanitizers unchanged.

### Contracts

**Consumed** (external, `acrossai-co/main-menu@0.0.12`):

- `\AcrossAI_Main_Menu\SettingsPage::get_settings_renderer(): ?SettingsPageRenderer` — static accessor. NEW in 0.0.12.
- `\AcrossAI_Main_Menu\SettingsPageRenderer::tab_page_slug( string $tab_slug ): string` — inherited from `TabbedPageRenderer`. Returns `'acrossai-settings-<slug>'` (same string the removed static returned).
- `acrossai_settings_tabs` filter — unchanged extension point. Continues to accept the same `[ 'slug', 'label', 'priority' ]` entries.

**Emitted** (unchanged):

- `acrossai_abilities_per_page` (int, 1–200) — the "Abilities per page" option.
- `acrossai_abilities_uninstall_delete_data` (0/1) — the uninstall wipe flag.

### Quickstart

Per-task verification recipes in [tasks.md](./tasks.md). A separate quickstart.md is not needed — the manual walkthrough is a 4-step click-through on any WP install with this plugin active.

## Complexity Tracking

One accepted deviation from ideal: the sibling `acrossai-mcp-manager` will fatal on its own Settings tab until it is upgraded to 0.0.12 with the identical refactor. This is a known-and-flagged cross-plugin coordination hazard, explicitly accepted per user decision, and documented in spec.md's Assumptions and Edge Cases sections plus the PR body's "Blocking release note" callout.
