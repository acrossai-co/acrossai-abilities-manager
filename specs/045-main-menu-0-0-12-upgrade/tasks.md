---
description: "Task list for Feature 045 — Upgrade acrossai-co/main-menu 0.0.11 → 0.0.12"
---

# Tasks: Upgrade `acrossai-co/main-menu` 0.0.11 → 0.0.12 (Preserve Abilities Settings Tab)

**Input**: Design documents from `/specs/045-main-menu-0-0-12-upgrade/`
**Prerequisites**: [spec.md](./spec.md), [plan.md](./plan.md), [memory-synthesis.md](./memory-synthesis.md)

**Tests**: No new PHPUnit tests. Test count unchanged (holds at 105). Manual walkthrough (T009) is the accepted verification path.

**Organization**: Tasks grouped by phase; each task has an exact target file + verification step.

## Format: `[ID] [P?] Description`

- **[P]**: Can run in parallel (different files, no dependencies).

## Phase 1: Setup

- [ ] **T001** Confirm branch `045-main-menu-0-0-12-upgrade` created from `main` post-Feature-043 (Feature 044 is on a separate branch and its PR is independent). Working tree clean apart from unrelated `.claude/settings.local.json` + `.phpunit.cache/test-results` local dev state.

## Phase 2: Foundational (Blocking Prerequisites — read-only audits)

- [ ] **T002** Grep audit — confirm the only call site of the removed static in this plugin's application code is at `admin/Partials/SettingsMenu.php:109`:
  ```
  grep -rn "SettingsPage::tab_page_slug" --include="*.php" \
    admin/ includes/ public/ tests/
  ```
  Expected: exactly one hit in `admin/Partials/SettingsMenu.php`. If additional hits surface, add them to T005's refactor scope.

- [ ] **T003** Read the 0.0.12 README's "Tabs" section and confirm the new consumer API for adding sections to a specific tab. Verified during Phase 1 exploration:
  - `\AcrossAI_Main_Menu\SettingsPage::get_settings_renderer()` — nullable static accessor
  - `\AcrossAI_Main_Menu\SettingsPageRenderer::tab_page_slug( string $tab_slug )` — inherited instance method
  - `acrossai_settings_tabs` filter — unchanged extension point

- [ ] **T004** Confirm the sibling plugin `acrossai-mcp-manager` also calls the removed static (documented for the PR's "Blocking release note"):
  ```
  grep -n "SettingsPage::tab_page_slug" \
    /Users/raftaar1191/local-sites/wordpress-7-0/app/public/wp-content/plugins/acrossai-mcp-manager/admin/Partials/SettingsMenu.php
  ```
  Expected: 1 hit at line 113. This is the sibling's migration point — out of scope for this feature but MUST be noted in the PR body.

**Checkpoint**: Scope confirmed as 1 composer bump + 1 refactored PHP method + vendor tree regeneration. No JS, no build.

## Phase 3: Composer bump

- [ ] **T005** Edit `composer.json` — change the `acrossai-co/main-menu` version pin from `"0.0.11"` to `"0.0.12"`. Exact-version pin style is preserved.

- [ ] **T006** Run `composer update acrossai-co/main-menu --with-dependencies` in the plugin root. Verify:
  - `composer.lock` shows `"version": "0.0.12"` and the source `reference` matches the 0.0.12 tag SHA (`12c05c9d...`).
  - `vendor/acrossai-co/main-menu/src/SettingsPageRenderer.php` and `vendor/acrossai-co/main-menu/src/TabbedPageRenderer.php` exist.
  - `vendor/acrossai-co/main-menu/src/PageRenderer.php` does NOT exist (removed in 0.0.12).
  - No platform-check or autoload errors.

## Phase 4: PHP refactor

- [ ] **T007** Edit `admin/Partials/SettingsMenu.php::register_settings()` (currently around lines 108-109). Replace the removed static call with the new instance-method access, guarded against a null renderer:
  ```php
  public function register_settings(): void {
      $renderer = \AcrossAI_Main_Menu\SettingsPage::get_settings_renderer();
      if ( ! $renderer ) {
          return; // main-menu package not booted in this request
      }
      $page_slug = $renderer->tab_page_slug( self::TAB_SLUG );

      // ... rest of the method (register_setting/add_settings_section/add_settings_field) unchanged ...
  }
  ```
  The rest of the method body — `register_setting` calls, `add_settings_section` calls, `add_settings_field` calls — is unchanged. `option_group` stays `'acrossai-settings'` per FR-007.

- [ ] **T008** Update the docblock on `register_settings()` (currently lines 97-107):
  - Replace `"the host package's SettingsPage::tab_page_slug() helper"` with `"the host package's SettingsPage::get_settings_renderer()->tab_page_slug() helper"`.
  - Bump the "acrossai-co/main-menu v0.0.4+" note to `v0.0.12+`.

## Phase 5: Verification + PR

- [ ] **T009** Manual walkthrough on the local WordPress 7.0 install with this plugin active:
  1. Navigate to `http://wordpress-7-0.local/wp-admin/admin.php?page=acrossai-settings&tab=abilities`.
  2. Expect an "Abilities" tab in the `nav-tab-wrapper`, marked active. Below: "Display Settings" section with "Abilities per page" field; "Uninstall Settings" section with the "Delete all data on uninstall" checkbox; one Save button.
  3. Change "Abilities per page" from `20` → `50`. Click **Save Changes**. Expect success notice; URL still carries `tab=abilities`; field re-renders as `50` on reload.
  4. Check the "Delete all data on uninstall" checkbox. Click **Save Changes**. Expect the option to persist (verify via `wp option get acrossai_abilities_uninstall_delete_data` → `1`).
  5. Tail `wp-content/debug.log` (`WP_DEBUG_LOG` must be on). Expect zero `Uncaught Error: Call to undefined method` entries and zero deprecation notices from `acrossai-co/main-menu`.
  6. If `acrossai-mcp-manager` is also active — expect `?page=acrossai-settings&tab=mcp` to FATAL (sibling not yet migrated). This is EXPECTED and documented in the PR body. Deactivate the sibling before demo-ing the passing scenario.

- [ ] **T010** Static-analysis pass — run:
  ```
  composer lint       # PHPCS
  composer phpstan    # PHPStan
  composer test       # PHPUnit
  ```
  Expected: PHPCS clean on `admin/Partials/SettingsMenu.php`; PHPStan clean; PHPUnit at 105 tests, all green.

- [ ] **T011** Commit changes on `045-main-menu-0-0-12-upgrade` (spec-kit + composer + vendor + refactor). Push to origin. Open PR against `main` titled `Feature 045 — Upgrade acrossai-co/main-menu to 0.0.12`. Body MUST include a **⚠ Blocking release note** paragraph explaining the sibling plugin coordination requirement (see spec.md Assumptions).

**Final Checkpoint**: T001–T004 grep audits, T005–T008 composer + refactor, T009 manual walkthrough, T010 lint/phpstan/test green, T011 commit + PR with blocking release note.
