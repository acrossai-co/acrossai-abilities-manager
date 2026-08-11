---
description: "Implementation tasks for feature 064 — Transient CRUD, nested option access, plugin lifecycle & checksum integrity"
---

# Tasks: Transient CRUD, nested option access, plugin lifecycle & checksum integrity

**Input**: Design documents from `specs/064-transients-nested-options-and-integrity/`
**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md), [data-model.md](./data-model.md), [contracts/abilities.md](./contracts/abilities.md), paired input brief at `docs/planning/064-transients-nested-options-and-integrity.md`
**Tests**: Included per constitution §VII.

**Organization**: Tasks grouped by user story (US1 transient CRUD, US2 nested options, US3 post-meta append, US4 plugin lifecycle & checksums) plus Setup, Wiring, QA phases.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no ordering dependency).
- **[Story]**: `US1`, `US2`, `US3`, `US4`, or `Setup` / `Wiring` / `QA`.

## Phase 1: Setup

- [ ] **T001** [Setup] Baseline gates: `composer install`, then `composer run test`, `composer run phpcs`, `composer run phpstan` — all clean on the `main` merge base.
- [ ] **T002** [P] [Setup] Skim reference peers: `includes/Abilities/Cache/Flush_Transients.php` (transient patterns), `includes/Abilities/Options/Update_Option.php` (option write pattern + block-list), `includes/Abilities/Content/Update_Post_Meta.php` (input schema aliases template for `add-post-meta`), `includes/Abilities/Plugins/Install_Plugin.php` (`plugins_api`, `File_Mods_Guard` reuse), `includes/Abilities/Recovery/Unpause_Plugin.php` (`Plugin_Helpers::resolve_plugin` reuse), `tests/phpunit/abilities/Test_Feature_042_Core_Update.php` (`pre_http_request` mocking pattern).
- [ ] **T003** [P] [Setup] Check whether `Update_Option::BLOCKED_OPTIONS` already exists as a `const` on the class. If not, extract the block-list into a `public const BLOCKED_OPTIONS` on `Update_Option.php` in this branch so `Patch_Option_Value.php` can reference it via `Update_Option::BLOCKED_OPTIONS`. Do not duplicate the array.

## Phase 2: User Story 1 — Transient CRUD (P1)

- [ ] **T010** [US1] Create `includes/Abilities/Cache/Get_Transient.php` implementing `acrossai/get-transient` per contracts §1. `$value = $site ? get_site_transient($key) : get_transient($key)`. Distinguish "unset" from `value === false` by consulting the underlying option: `$exists = null !== get_option($site ? "_site_transient_{$key}" : "_transient_{$key}", null);` — return `{ exists, value, expires_at }` where `expires_at` is read from the companion `_transient_timeout_<key>` option (or `_site_transient_timeout_<key>`).
- [ ] **T011** [US1] Create `includes/Abilities/Cache/List_Transients.php` implementing `acrossai/list-transients` per contracts §2. Query: `SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s ORDER BY option_name LIMIT %d OFFSET %d`, prepared with `$wpdb->esc_like('_transient_') . '%'` and `$wpdb->esc_like('_site_transient_') . '%'`. Filter timeout companion rows in PHP (do not return `_transient_timeout_*`). Join expiry from companion rows. Compute `is_expired: $timeout > 0 && $timeout < time()`. Honour `search` (`AND option_name LIKE %s`), `site_only`, `include_expired`, `limit`, `offset`, and populate `total` via `SELECT COUNT(*)`.
- [ ] **T012** [US1] Create `includes/Abilities/Cache/Delete_Transient.php` implementing `acrossai/delete-transient` per contracts §3. `$deleted = $site ? delete_site_transient($key) : delete_transient($key);`.
- [ ] **T013** [US1] Create `includes/Abilities/Cache/Delete_Expired_Transients.php` implementing `acrossai/delete-expired-transients` per contracts §4. Before call: count expired transients (blog + site) via prepared query. Then call WP core `delete_expired_transients();`. Return the pre-call count as `deleted` — WP core's return signature is `void`, so the count must be captured before the call.
- [ ] **T014** [P] [US1] `tests/phpunit/abilities/Test_Get_Transient.php` — golden path (seed + read + assert), unset path (assert `exists: false`), site-scope path (seed via `set_site_transient` + read with `site: true`).
- [ ] **T015** [P] [US1] `tests/phpunit/abilities/Test_List_Transients.php` — seed 5 transients (mix of expired / unexpired, blog / site); assert filtering by `search`, `include_expired: false`, `site_only: true` each work; assert `total` is stable regardless of `limit`.
- [ ] **T016** [P] [US1] `tests/phpunit/abilities/Test_Delete_Transient.php` — golden (seed → delete → assert gone), idempotent (delete twice → both succeed), site path.
- [ ] **T017** [P] [US1] `tests/phpunit/abilities/Test_Delete_Expired_Transients.php` — seed 3 expired + 2 unexpired transients; assert `deleted: 3` and the 2 unexpired remain.

## Phase 3: User Story 2 — Nested option access (P1)

- [ ] **T020** [US2] Create `includes/Abilities/Options/Get_Nested_Option_Value.php` implementing `acrossai/get-nested-option-value` per contracts §5. `$option_value = get_option($option, null); if (null === $option_value) { return exists: false }`. Walk `$path`: for each step, if the current node is an array or ArrayAccess and the key exists, descend; else return `exists: false`. On success return `exists: true, value: <leaf>`.
- [ ] **T021** [US2] Create `includes/Abilities/Options/Patch_Option_Value.php` implementing `acrossai/patch-option-value` per contracts §6. Guards:
  - Reject empty path → `blocked_reason: 'empty_path'`.
  - Reject `in_array($option, Update_Option::BLOCKED_OPTIONS, true)` → `blocked_reason: 'blocked_option'`.
  - Fetch current value. Walk to `path[0..len-2]`. If any intermediate is not array-like, refuse with `blocked_reason: 'non_traversable_intermediate'`.
  - Apply `operation`: `insert` (fail if leaf exists → `key_exists`), `update` (fail if leaf missing? — the contract accepts either; per spec, update creates if missing), `delete` (fail if leaf missing → `not_found` OR just return success — decision: return success with `deleted: false`).
  - Persist with `update_option($option, $mutated_root)`.
- [ ] **T022** [P] [US2] `tests/phpunit/abilities/Test_Get_Nested_Option_Value.php` — golden (seed `{"a":{"b":"c"}}` → path `["a","b"]` returns `"c"`), missing path (returns `exists: false`), option-does-not-exist (returns `exists: false`).
- [ ] **T023** [P] [US2] `tests/phpunit/abilities/Test_Patch_Option_Value.php` — full CRUD via nested path on a seeded option; blocked-option refusal; non-traversable-intermediate refusal (path traverses into a string); byte-identical round-trip (option's other keys unchanged after patch).

## Phase 4: User Story 3 — Post-meta append (P2)

- [ ] **T030** [US3] Create `includes/Abilities/Content/Add_Post_Meta.php` implementing `acrossai/add-post-meta` per contracts §7. Mirror the input-schema structure of `Update_Post_Meta.php` (accept `key`/`meta_key` and `value`/`meta_value` aliases). Add `unique: bool = false`. `execute()`:
  - Post existence check (as in `Update_Post_Meta.php:109`).
  - `$meta_id = add_post_meta($post_id, $key, $value, $unique);` — WP core returns integer meta_id on success or `false` on duplicate-blocked.
  - Return `{ success: true, meta_id: (int|bool) $meta_id, message }`.
- [ ] **T031** [P] [US3] `tests/phpunit/abilities/Test_Add_Post_Meta.php`. Golden: two appends → both `meta_id` are distinct positive integers; `get_post_meta($post_id, $key, false)` returns 2 rows in correct order. Guardrail: `unique: true` on a key with an existing row → `meta_id: false`; existing row unchanged. Guardrail: non-existent post → refused.

## Phase 5: User Story 4 — Plugin lifecycle & checksum integrity (P2)

- [ ] **T040** [US4] Create `includes/Abilities/Plugins/Search_Wp_Plugin_Directory.php` implementing `acrossai/search-wp-plugin-directory` per contracts §8. `require_once ABSPATH . 'wp-admin/includes/plugin-install.php';` (REST context does not preload). `$result = plugins_api('query_plugins', ['search' => $query, 'per_page' => $per_page, 'page' => $page, 'fields' => ['slug','name','short_description','rating','active_installs','homepage','download_link']]);`. If `is_wp_error($result)`, return `{ success: true, plugins: [], info: {...}, message: <error> }` — success stays true because the ability itself did not fail; the caller sees the empty result and error message. Sanitize `short_description` via `wp_kses_post()`.
- [ ] **T041** [US4] Create `includes/Abilities/Plugins/Uninstall_Plugin.php` implementing `acrossai/uninstall-plugin` per contracts §9. Order of checks (each refuses immediately with `success: false, blocked_reason: X`):
  1. `File_Mods_Guard::blocked_response()` → `file_mods_disallowed`.
  2. `$plugin_file = \AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Plugin_Helpers::resolve_plugin($input['plugin']);` (fuzzy); if null → `plugin_not_found`.
  3. `is_plugin_active($plugin_file)` → `plugin_active`.
  Then `require_once ABSPATH . 'wp-admin/includes/plugin.php'; require_once ABSPATH . 'wp-admin/includes/file.php';` and call `uninstall_plugin($plugin_file)`. Return `uninstalled: true`.
- [ ] **T042** [US4] Create `includes/Abilities/Plugins/Verify_Plugin_Checksums.php` implementing `acrossai/verify-plugin-checksums` per contracts §10. Resolve plugin via `Plugin_Helpers::resolve_plugin`. Fetch expected manifest: `wp_remote_get('https://api.wordpress.org/plugins/checksums/1.0/?plugin=' . rawurlencode($slug) . '&version=' . rawurlencode($version));`. If HTTP fail or manifest empty: return `success: true, results: [], summary: { total: 0, ... }, message: 'no_manifest'`. Else walk the manifest: for each file, `md5_file(WP_PLUGIN_DIR . '/' . $slug . '/' . $file)` and compare against `expected`. Emit per-file `status: 'ok'|'modified'|'missing'`. When `strict: true`, walk the on-disk plugin dir and flag any file not in the manifest as `status: 'added'`.
- [ ] **T043** [US4] Create `includes/Abilities/Core/Verify_Core_Checksums.php` implementing `acrossai/verify-core-checksums` per contracts §11. `require_once ABSPATH . 'wp-admin/includes/update.php';`. `$manifest = get_core_checksums($version ?? get_bloginfo('version'), $locale ?? 'en_US');`. Same diff algorithm as T042 but paths are relative to `ABSPATH`. Honour `include_root: bool` (defaults `false` — root-level files like `wp-config.php`, `.htaccess` are skipped by default) and `exclude: string[]`.
- [ ] **T044** [P] [US4] `tests/phpunit/abilities/Test_Search_Wp_Plugin_Directory.php`. Mock `pre_http_request` to return a canned JSON response with 3 plugins; assert response has 3 items with correct field shape. Second test: `WP_Error` return → assert `plugins: [], message` includes the error code.
- [ ] **T045** [P] [US4] `tests/phpunit/abilities/Test_Uninstall_Plugin.php`. Set up a fake plugin fixture in `WP_PLUGIN_DIR/test-plugin/`. Golden: uninstall while inactive → success + files gone. Guardrails: (a) active plugin → `plugin_active`; (b) `DISALLOW_FILE_MODS` defined true → `file_mods_disallowed`; (c) unknown plugin → `plugin_not_found`.
- [ ] **T046** [P] [US4] `tests/phpunit/abilities/Test_Verify_Plugin_Checksums.php`. Mock `pre_http_request` to return a canned manifest. Golden: unmodified fixture returns all-ok; modified fixture returns one modified entry.
- [ ] **T047** [P] [US4] `tests/phpunit/abilities/Test_Verify_Core_Checksums.php`. Mock `pre_http_request` to return a canned manifest; assert diff structure and `strict: true` `added` flagging.

## Phase 6: Bootstrap wiring

- [ ] **T050** [Wiring] Edit `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php::register_abilities()` — 11 new instantiation lines inside their existing category blocks:
  - Cache (4): `new Cache\Get_Transient(); new Cache\List_Transients(); new Cache\Delete_Transient(); new Cache\Delete_Expired_Transients();`.
  - Options (2): `new Options\Get_Nested_Option_Value(); new Options\Patch_Option_Value();`.
  - Content (1): `new Content\Add_Post_Meta();`.
  - Plugins (3): `new Plugins\Search_Wp_Plugin_Directory(); new Plugins\Uninstall_Plugin(); new Plugins\Verify_Plugin_Checksums();`.
  - Core (1): `new Core\Verify_Core_Checksums();`.

## Phase 7: Cross-cutting quality gates

- [ ] **T060** [QA] `composer run phpcs` — zero errors, zero warnings across all 11 new class files + 11 new test files. (If Setup task T003 modified `Update_Option.php` to extract `BLOCKED_OPTIONS`, that file must also PHPCS clean.)
- [ ] **T061** [QA] `composer run phpstan` at level 8 — zero errors.
- [ ] **T062** [QA] `composer run test` — every new PHPUnit method passes + no regressions.
- [ ] **T063** [P] [QA] Load `http://wordpress-7-0.local/wp-admin/admin.php?page=acrossai-abilities-library`; verify the 11 new abilities appear under Cache (4), Options (2), Content (1), Plugins (3), Core (1) with correct annotations.
- [ ] **T064** [P] [QA] Run through `quickstart.md` sections 1 through 6. Every expected result MUST match.

## Independent-completion checkpoint

- US1 (transients) is fully self-contained — it can ship as a standalone slice.
- US2 (nested options) is self-contained but depends on Setup task T003 (BLOCKED_OPTIONS extraction).
- US3 (post-meta append) is self-contained.
- US4 (plugin lifecycle + checksums) is self-contained — the four abilities are independent of one another (uninstall doesn't need verify to exist, etc.).

Any one story can ship in isolation; per the plan, all four ship together on branch 064 and roll into `release-0.0.23` alongside features 062 and 063.

## Not in scope for this feature

- No version bump / changelog entry — reserved for the unified `release-0.0.23` cut.
- No cache-plugin-specific purge adapters (deferred per unified plan).
- No admin JS/CSS changes.
- No modifications to any of the 219 existing ability classes (except the possible `BLOCKED_OPTIONS` extraction on `Update_Option.php` in T003, which is a pure move of an inline literal into a `const` and does not change behaviour).
