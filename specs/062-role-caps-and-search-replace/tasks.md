---
description: "Implementation tasks for feature 062 — Role & capability CRUD + site-wide DB search-replace"
---

# Tasks: Role & capability CRUD + site-wide DB search-replace

**Input**: Design documents from `specs/062-role-caps-and-search-replace/`
**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [research.md](./research.md), [data-model.md](./data-model.md), [contracts/abilities.md](./contracts/abilities.md), paired input brief at `docs/planning/062-role-caps-and-search-replace.md`
**Tests**: Included. Per constitution §VII, unit tests are part of Definition of Done.

**Organization**: Tasks are grouped by user story from spec.md. Each user story block is independently completable and PR-mergeable (though for this feature all four ship in one branch).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no ordering dependency).
- **[Story]**: `US1`, `US2`, `US3`, `US4` per spec.md, or `Setup` / `Wiring` / `QA` for cross-cutting work.

## Phase 1: Setup

- [ ] **T001** [Setup] Verify local WP install and Composer dev deps are fresh: `composer install`, then `composer run test`, `composer run phpcs`, and `composer run phpstan` all pass on the current `main` merge base — this establishes the green baseline against which every subsequent commit is measured.
- [ ] **T002** [P] [Setup] Skim `includes/Abilities/Users/Get_Role_Capabilities.php`, `includes/Abilities/Users/List_User_Roles.php`, `includes/Abilities/Users/Update_User.php`, and `includes/Abilities/Database/Update_Db_Rows.php` — these are the pattern peers every new class should mirror for docblock, namespace, formatting, and safety-envelope style.
- [ ] **T003** [P] [Setup] Skim `tests/phpunit/abilities/Test_Feature_042_Core_Update.php` as the reference for PHPUnit test file layout, `WP_UnitTestCase` fixture use, and `pre_http_request` mocking (though this feature does not need HTTP mocks).

## Phase 2: User Story 1 — Grant/revoke role capability (P1)

**Goal**: Deliver the two per-role capability writers so an operator can adjust editor / author / contributor caps end-to-end.

- [ ] **T010** [US1] Create `includes/Abilities/Users/Add_Role_Capability.php` implementing `acrossai/add-role-capability` per contracts §1. Class extends `Ability_Definition`; input schema, output schema, permission callback, and annotations verbatim from the contract. `execute()`: `sanitize_text_field()` on both inputs; look up role via `get_role($role)`; refuse with `success: false, blocked_reason: 'role_not_found'` if null; else call `$role->add_cap($capability, $grant)` and return success.
- [ ] **T011** [US1] Create `includes/Abilities/Users/Remove_Role_Capability.php` implementing `acrossai/remove-role-capability` per contracts §2. Hardcode `const CORE_ADMIN_CAPS` as the ~52-cap WordPress-core administrator baseline (from `wp-admin/includes/schema.php::populate_roles_270()`). Guard: if `$role === 'administrator' && in_array($capability, self::CORE_ADMIN_CAPS, true)`, refuse with `blocked_reason: 'core_admin_cap'`. Else call `$role->remove_cap($capability)` and return success.
- [ ] **T012** [P] [US1] Create `tests/phpunit/abilities/Test_Add_Role_Capability.php`. Golden path: seed a subscriber, add `upload_files` via ability, assert `current_user_can('upload_files')` becomes true. Guardrail: non-existent role → `blocked_reason: 'role_not_found'`.
- [ ] **T013** [P] [US1] Create `tests/phpunit/abilities/Test_Remove_Role_Capability.php`. Golden path: grant `manage_options` to editor, remove it, assert an editor user no longer passes `current_user_can('manage_options')`. Guardrail: attempt to remove `manage_options` from administrator → `blocked_reason: 'core_admin_cap'` and role's cap map is unchanged.

**Bootstrap wiring** (done once for US1 in this feature, revisited by US2/US3):

- [ ] **T014** [US1] Edit `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php::register_abilities()` — inside the existing Users block (currently lines 132–139), append `new Users\Add_Role_Capability();` and `new Users\Remove_Role_Capability();`.

**Definition of Done for US1**: `composer run test` passes 4 new tests + entire existing suite; PHPCS + PHPStan clean on the two new class files.

## Phase 3: User Story 2 — Create / delete / reset a role (P2)

**Goal**: Deliver the three role-lifecycle writers.

- [ ] **T020** [US2] Create `includes/Abilities/Users/Create_Role.php` implementing `acrossai/create-role` per contracts §3. Guard: refuse with `blocked_reason: 'role_exists'` if `get_role($role)` returns non-null. When `clone_from` supplied, resolve source via `get_role($clone_from)` and seed `add_role($role, $display_name, $source->capabilities)`; otherwise seed with empty caps array. Return the new role's capability map in the output.
- [ ] **T021** [US2] Create `includes/Abilities/Users/Delete_Role.php` implementing `acrossai/delete-role` per contracts §4. `const DEFAULT_ROLES = ['administrator','editor','author','contributor','subscriber']`. Guard 1: if `in_array($role, self::DEFAULT_ROLES, true)`, refuse with `blocked_reason: 'default_role'`. Guard 2: probe `get_users(['role' => $role, 'number' => 1, 'fields' => 'ID'])`; if non-empty, refuse with `blocked_reason: 'role_has_users'` and echo `user_count` in the output. Else `remove_role($role)`.
- [ ] **T022** [US2] Create `includes/Abilities/Users/Reset_Role.php` implementing `acrossai/reset-role` per contracts §5. `const RESETTABLE_ROLES = ['administrator','editor','author','contributor','subscriber']`. Guard: refuse with `blocked_reason: 'not_default_role'` if not in the enum. Implementation: `remove_role($role); require_once ABSPATH . 'wp-admin/includes/schema.php'; populate_roles();` — this is deliberately coarse (re-seeds all five defaults), but it is idempotent and correct. Include the restored capability map in the output.
- [ ] **T023** [P] [US2] Create `tests/phpunit/abilities/Test_Create_Role.php`. Golden path: create `support_agent` cloning from editor, assert role exists with correct caps. Guardrails: (a) re-creating same slug → `role_exists`; (b) unknown `clone_from` → `blocked_reason: 'clone_source_not_found'` (add to contracts alignment if not yet documented — but the schema in contracts §3 already accepts `clone_from` as optional; the `execute()` should reject non-existent sources).
- [ ] **T024** [P] [US2] Create `tests/phpunit/abilities/Test_Delete_Role.php`. Golden path: create a fresh role held by zero users, delete it, assert `get_role()` returns null. Guardrails: (a) attempting to delete `administrator` → `default_role`; (b) role with a user → `role_has_users`, user_count reported correctly.
- [ ] **T025** [P] [US2] Create `tests/phpunit/abilities/Test_Reset_Role.php`. Golden path: strip `edit_posts` from editor, reset, assert `edit_posts` is back. Guardrail: attempting to reset `support_agent` (non-default) → `blocked_reason: 'not_default_role'`.
- [ ] **T026** [US2] Extend `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php::register_abilities()` — append `new Users\Create_Role();`, `new Users\Delete_Role();`, `new Users\Reset_Role();` after the two US1 entries added in T014.

## Phase 4: User Story 3 — Grant / revoke user capability (P2)

**Goal**: Per-user capability granularity that WP core's `WP_User::add_cap` / `remove_cap` provides.

- [ ] **T030** [US3] Create `includes/Abilities/Users/Add_User_Capability.php` implementing `acrossai/add-user-capability` per contracts §6. Resolve `get_userdata($user_id)`; refuse with `blocked_reason: 'user_not_found'` if false. Else call `$user->add_cap($capability, $grant)`.
- [ ] **T031** [US3] Create `includes/Abilities/Users/Remove_User_Capability.php` implementing `acrossai/remove-user-capability` per contracts §7. Reuse `CORE_ADMIN_CAPS` — either duplicate the constant on this class (matches Decision 3 from research.md) or reference the one on `Remove_Role_Capability`. Guard: count admins via `get_users(['role' => 'administrator', 'fields' => 'ID'])`; if `count() === 1 && (int) $result[0] === (int) $user_id && in_array($capability, CORE_ADMIN_CAPS, true)`, refuse with `blocked_reason: 'last_admin_core_cap'`. Else `$user->remove_cap($capability)`.
- [ ] **T032** [P] [US3] Create `tests/phpunit/abilities/Test_Add_User_Capability.php`. Golden path: subscriber user, grant `upload_files` directly, assert `current_user_can` change on that user only. Guardrail: non-existent user id → `blocked_reason: 'user_not_found'`.
- [ ] **T033** [P] [US3] Create `tests/phpunit/abilities/Test_Remove_User_Capability.php`. Golden path: revoke `upload_files` from a subscriber who had it via `add-user-capability`. Guardrail: create a scenario where user #1 is the last admin, attempt to remove `manage_options` from user #1 → `blocked_reason: 'last_admin_core_cap'` and admins-count guard is provably tested.
- [ ] **T034** [US3] Extend `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php::register_abilities()` — append `new Users\Add_User_Capability();` and `new Users\Remove_User_Capability();` after the US2 entries.

## Phase 5: User Story 4 — Safe site-wide DB search-replace (P1)

**Goal**: One ability that walks the whole database safely with dry-run default.

- [ ] **T040** [US4] Create `includes/Abilities/Database/Search_Replace.php` implementing `acrossai/search-replace` per contracts §8. Structure:
  1. `sanitize_text_field()` on `old` and `new`.
  2. Fetch full table list: `$tables_available = $wpdb->get_col('SHOW TABLES');` (mirror `Update_Db_Rows.php:156–166`).
  3. Determine target tables: intersect input `tables[]` with `$tables_available`; if any input table is missing, refuse with `blocked_reason: 'unknown_table'` and no writes. If `tables` omitted, use every table that starts with `$wpdb->prefix` (unless `all_tables: true`, then use every table).
  4. For each target table, `DESCRIBE {table}` to get column list. Skip columns in `skip_columns`. Skip `wp_posts.guid` unless `include_guids: true`.
  5. Per column, `SELECT primary_key, {column}` and iterate rows. For each row: try `maybe_unserialize($value)` — if the unserialized value != raw value, walk it (recursive helper defined in the class) and apply `str_replace` to every string leaf, then `maybe_serialize` back. Else `str_replace` directly.
  6. Count matches. If `dry_run: true`, do not write. Else `$wpdb->update($table, [$column => $new_value], [$primary_key => $pk])`.
  7. Return `results[]` per (table, column) and `summary`.
- [ ] **T041** [P] [US4] Create `tests/phpunit/abilities/Test_Search_Replace.php`. Tests:
  - Golden dry-run: seed a post with `old-domain.com` in `post_content`, run dry-run, assert `results[0].matches === 1, results[0].replaced === 0`, and the row still contains `old-domain.com` afterward (byte-identity check).
  - Golden apply: same setup, `dry_run: false`, assert row now contains `new-domain.com` and no trace of the old string.
  - Serialized-safe: seed a post-meta row whose value is a PHP-serialized array containing `old-domain.com` inside a nested string. Apply the replacement and confirm (a) the string is replaced, (b) the serialization is still structurally valid (`get_post_meta` returns the correct nested array with the new string).
  - Blocked table: pass `tables: ['wp_nonexistent']`, assert `blocked_reason: 'unknown_table'` and no writes.
  - Empty `old`: pass `old: ''`, assert `blocked_reason: 'empty_old'`.
  - `guid` protection: seed `wp_posts.guid` with the old string, apply without `include_guids`, assert `guid` is unchanged; then apply with `include_guids: true`, assert `guid` is replaced.
  - `skip_columns`: pass `skip_columns: ['post_content']` and confirm `post_content` is not modified even though it contains matches.
- [ ] **T042** [US4] Extend `includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php::register_abilities()` — inside the existing Database block (currently lines 143–151), append `new Database\Search_Replace();`.

## Phase 6: Cross-cutting quality gates

- [ ] **T050** [QA] Run `composer run phpcs` on the branch — assert zero errors, zero warnings across all 8 new class files + 8 new test files.
- [ ] **T051** [QA] Run `composer run phpstan` at level 8 on the branch — assert zero errors.
- [ ] **T052** [QA] Run `composer run test` — assert every new PHPUnit method passes AND every existing test in the suite still passes (no regressions).
- [ ] **T053** [P] [QA] Load `http://wordpress-7-0.local/wp-admin/admin.php?page=acrossai-abilities-library` in a browser; verify all 8 new abilities render with the correct sub-groups (7 under Users, 1 under Database), correct destructive/idempotent annotations, and `manage_options` as the permission.
- [ ] **T054** [P] [QA] Run through `quickstart.md` sections 1 through 5 (curl-based end-to-end tests against the local site). Every expected result MUST match.

## Phase 7: Wiring & delivery

- [ ] **T060** [Wiring] Confirm the 8 bootstrap-instantiation lines added across T014, T026, T034, T042 all land inside their category's existing block, in a stable order (matches file names alphabetically). Bootstrap already registered the Users and Database categories in `register_category_callbacks()` — no changes needed there for this feature.
- [ ] **T061** [Wiring] Rebuild `composer.lock` on this branch only if any new autoload paths need registering (they do not — every new class is under an already-autoloaded PSR-4 namespace). This task is a no-op verification.

## Independent-completion checkpoint

Each user-story block above can be fully implemented, tested, PHPCS-clean, PHPStan-clean, and merged in isolation. Per the plan, however, all four ship together on `062-role-caps-and-search-replace` and roll into the unified `release-0.0.23` alongside features 063 and 064. Task ordering therefore assumes a single-branch, sequential completion; T053 and T054 can only run once every prior task in the branch has landed.

## Not in scope for this feature

- No version bump / changelog entry inside this branch — the plan explicitly reserves the release-branch cut for the unified 0.0.23 delivery.
- No changes to admin UI JavaScript or CSS.
- No modifications to any of the existing 219 ability classes.
