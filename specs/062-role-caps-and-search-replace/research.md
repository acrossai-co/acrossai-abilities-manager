# Phase 0 Research: Role & capability CRUD + site-wide DB search-replace

**Status**: No open NEEDS CLARIFICATION markers. Every technical decision is inherited from the existing plugin's established patterns; this document records the decisions and why the alternatives were rejected.

## Decision 1: Category placement — reuse existing `Users/` and `Database/`

**Decision**: 7 role/cap abilities land under `includes/Abilities/Users/`; the DB search-replace ability lands under `includes/Abilities/Database/`.

**Rationale**: Existing role/cap-adjacent classes (`Get_Role_Capabilities.php`, `List_User_Roles.php`, `Update_User.php`) already live under `Users/` and touch the same WP core functions (`get_role()`, `wp_roles()`, `WP_User::add_cap/remove_cap`). Creating a new `Roles/` category would fragment a semantically identical cluster and force a new Category_Registrar for zero end-user benefit. Search-replace is a database-wide write operation, so it belongs next to `Update_Db_Rows.php` and `Delete_Db_Rows.php`.

**Alternatives considered**:
- New `Roles/` category dedicated to role/cap CRUD. Rejected — it would double the category count without changing the user-visible grouping (the admin UI's sub-group label is already `Users`).
- `Utilities/Search_Replace.php` (utility rather than an ability). Rejected — the spec's user story #4 requires this to be a first-class ability invocable through the WP Abilities API REST surface; a utility class would not be discoverable through the ability library.

## Decision 2: `dry_run: true` as the default for `search-replace`

**Decision**: The `search-replace` ability defaults to dry-run mode; callers must explicitly pass `dry_run: false` to execute a mutating replacement.

**Rationale**: This ability is the highest-blast-radius single operation in the plugin — one misfired call can rewrite every string in every table. WordPress core's `wp search-replace` CLI defaults to *executing* (dry-run is opt-in via `--dry-run`), which is safer for interactive CLI use but dangerous for automated agents that may hallucinate arguments. Reversing the default forces the agent to prove intent to mutate.

**Alternatives considered**:
- Default to executing (WP-CLI parity). Rejected — the plugin's threat model is different from WP-CLI's; abilities are invoked by AI agents that may retry or misinterpret. Safe-by-default matches the plugin's existing conservative posture (e.g. `list-cron-jobs` defaults to summarizing, not running).
- Require a two-call handshake (preview → confirm). Rejected — the plugin has no approval-flow framework; adding one for one ability would be premature abstraction. `dry_run: true` default achieves the same safety with one call.

## Decision 3: `CORE_ADMIN_CAPS` hardcoded per-class rather than shared utility

**Decision**: The ~52-capability WordPress-core-administrator baseline (from `wp-admin/includes/schema.php::populate_roles_270()`) is hardcoded as a `const CORE_ADMIN_CAPS` on the three classes that need it (`Remove_Role_Capability`, `Remove_User_Capability`, and the internal guard used by `Reset_Role`).

**Rationale**: Only three classes reference this list, and they reference it identically. Extracting a `AcrossAI_Abilities_Manager\Includes\Abilities\Utilities\Admin_Cap_Registry` would introduce a shared surface for zero flexibility gain. The plugin's stated preference (CLAUDE.md: "Three similar lines is better than a premature abstraction") applies.

**Alternatives considered**:
- Extract to `includes/Abilities/Utilities/Admin_Cap_Registry.php`. Rejected — premature abstraction; no third caller foreseen.
- Derive dynamically from WP core at runtime via `get_role('administrator')->capabilities`. Rejected — that would treat the *current* admin caps as canonical, including any modifications a prior plugin or misfired ability may have made. The whole point of `CORE_ADMIN_CAPS` is to name the *WP-core-shipped* baseline as an immovable safety anchor, independent of live state.

## Decision 4: Serialized-data walk for `search-replace` uses `maybe_unserialize()` + recursive walk + `maybe_serialize()`

**Decision**: For every row in every affected column, if `maybe_unserialize()` returns a value distinct from the raw string, walk it recursively (arrays and objects), apply the search-replace to every string leaf, then re-serialize with `maybe_serialize()`. Non-serialized rows use plain `str_replace()`.

**Rationale**: This is the proven WP-CLI approach (`Search_Replace::recursive_unserialize_replace`). It handles the common cases (`s:N:"..."` strings inside arrays / objects) correctly, preserves numeric widths (`s:11:"example.com"` → `s:9:"other.tld"`), and gracefully falls through on binary or intentionally-corrupt serialization.

**Alternatives considered**:
- Regex-based length-fixing on raw serialized bytes. Rejected — fragile against nested serialization and doesn't handle serialized objects at all.
- Skip serialized columns entirely. Rejected — that would make the ability useless for `wp_postmeta.meta_value` and `wp_options.option_value`, which are the two columns most callers actually care about.

## Decision 5: `search-replace` defaults `include_guids: false` (stricter than WP-CLI)

**Decision**: `wp_posts.guid` is skipped by default; the caller must opt in with `include_guids: true`.

**Rationale**: WordPress documentation explicitly warns against rewriting `guid` because it is a canonical identifier for syndication and feed subscriptions. Any workflow that legitimately needs to rewrite `guid` (e.g. rare cases in the first hour after a fresh install) can opt in; the vastly more common case (domain migration on a live site) is safer with the default off. Matches the WP-CLI documented warning at https://developer.wordpress.org/cli/commands/search-replace/#guid.

**Alternatives considered**:
- Match WP-CLI (defaults to including `guid` unless `--skip-columns=guid` is passed). Rejected — misaligned with the plugin's safe-by-default posture; the WP-CLI CLI-user is assumed to have read the warning, an AI agent is not.

## Decision 6: PHPUnit fixtures via `WP_UnitTestCase` factories, no mocking

**Decision**: Tests extend `WP_UnitTestCase` and use `$this->factory->user->create()` for users, direct `add_role()` for role fixtures, and a real `$wpdb` for the search-replace tests. HTTP mocking not needed (no external calls in this feature).

**Rationale**: Real fixtures give correct end-to-end coverage of WP core role/cap behaviour, which is the whole point of these abilities. Mocking `WP_Role` or `$wpdb` would test our wrapper, not the observable behaviour the spec's Success Criteria require.

**Alternatives considered**:
- Mock `WP_Role` / `$wpdb`. Rejected — would test the wrapper, not the WP core interaction; guardrails like last-admin protection can only be validated end-to-end.

## Open items

None. All Technical Context inputs are resolved; the Constitution Check passes on every principle; no complexity-tracking entries needed.
