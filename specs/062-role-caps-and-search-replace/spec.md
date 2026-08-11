# Feature Specification: Role & capability CRUD + site-wide DB search-replace

**Feature Branch**: `062-role-caps-and-search-replace`
**Created**: 2026-08-11
**Status**: Draft
**Input**: User description: "Role & capability CRUD (7 abilities under Users) plus site-wide DB search-replace ability under Database. Fills two gaps that WordPress core REST does not cover. See docs/planning/062-role-caps-and-search-replace.md for the full input brief."

## User Scenarios & Testing *(mandatory)*

<!--
  User stories are ordered by importance. Each story is INDEPENDENTLY TESTABLE —
  implementing just the P1 story delivers a viable minimum improvement to the ability surface.
-->

### User Story 1 - Grant or revoke a capability on a role (Priority: P1)

A site administrator (or an AI agent operating on their behalf with `manage_options`) can adjust which WordPress capabilities are granted to any existing role, without installing a role-editor plugin and without hand-editing `wp_options`.

**Why this priority**: The single most common role/cap administrative task in day-to-day WordPress operations. Every "let editors publish custom-post-type X" or "let contributors upload media" request maps directly to add-cap or remove-cap on a role. Also the smallest slice — one add and one remove ability gives the operator immediate value.

**Independent Test**: An operator with `manage_options` can pick a role, grant it a capability, then revoke that same capability, and every subsequent authorization check on users holding that role reflects the change. Delivers value the moment it ships; no other new ability needs to exist for this to be useful.

**Acceptance Scenarios**:

1. **Given** an existing role `editor` that does not currently grant `manage_options`, **When** the operator grants `manage_options` to `editor`, **Then** every existing editor user immediately passes `current_user_can( 'manage_options' )`.
2. **Given** an existing role `editor` that currently grants `edit_theme_options`, **When** the operator revokes `edit_theme_options` from `editor`, **Then** every existing editor user immediately fails `current_user_can( 'edit_theme_options' )`.
3. **Given** a non-existent role slug, **When** the operator attempts to grant a capability, **Then** the ability returns a failure result that names the missing role, without mutating any state.
4. **Given** the administrator role, **When** the operator attempts to revoke a WordPress-core administrator capability (e.g. `manage_options`, `activate_plugins`, `delete_users`), **Then** the ability refuses the change and returns a message explaining why (removing this capability from the administrator role would lock the site owner out).

---

### User Story 2 - Create, delete, or reset a role (Priority: P2)

The operator can add a new custom role (optionally cloning capabilities from an existing role), remove a role they no longer need, or reset a WordPress-core role back to the capabilities WordPress ships with.

**Why this priority**: Less frequent than per-capability adjustments but still a common admin need — creating "Shop Manager" or "Support Rep" style custom roles, cleaning up abandoned roles left behind by uninstalled plugins, and recovering from accidental cap changes on WordPress-core roles. Requires the per-capability abilities from P1 to be truly useful, so ships second.

**Independent Test**: An operator can create a new role, then delete it, then reset a WordPress-core role — each in isolation — and observe the site's role registry change accordingly.

**Acceptance Scenarios**:

1. **Given** a role slug that does not exist, **When** the operator creates it with a display name and clones capabilities from an existing role, **Then** the new role appears in the site's role registry with exactly the cloned capabilities.
2. **Given** a role slug that already exists, **When** the operator attempts to create it, **Then** the ability refuses without mutating state and returns a message naming the collision.
3. **Given** a custom role held by zero users, **When** the operator deletes it, **Then** the role is removed from the site's role registry.
4. **Given** a custom role held by one or more users, **When** the operator attempts to delete it, **Then** the ability refuses without mutating state and returns a message explaining how many users still hold the role.
5. **Given** any of WordPress's five built-in roles (`administrator`, `editor`, `author`, `contributor`, `subscriber`), **When** the operator attempts to delete it, **Then** the ability refuses regardless of user count.
6. **Given** a WordPress-core role with modified capabilities, **When** the operator resets it, **Then** the role's capabilities match the WordPress-core defaults for the currently installed version.
7. **Given** a non-built-in role slug, **When** the operator attempts to reset it, **Then** the ability refuses and returns a message noting reset applies only to WordPress-core roles.

---

### User Story 3 - Grant or revoke a capability directly on a specific user (Priority: P2)

The operator can grant or revoke a single capability on an individual user, overriding the user's role-derived permissions for that capability only. This mirrors WordPress core's `WP_User::add_cap()` / `remove_cap()`.

**Why this priority**: Ships alongside P2 because it complements the role-level work with the per-user granularity that WordPress core supports natively. Common for one-off cases ("give this specific contributor upload access without changing every contributor's permissions").

**Independent Test**: An operator can grant a capability to a specific user, verify that user passes the capability check while other users of the same role do not, and then revoke the capability.

**Acceptance Scenarios**:

1. **Given** a user who does not have `upload_files`, **When** the operator grants it directly to that user, **Then** that user passes `current_user_can( 'upload_files' )` while other users of the same role remain unaffected.
2. **Given** a target user, **When** the operator revokes a WordPress-core administrator capability from that user, **AND** the target is the last remaining administrator on the site, **Then** the ability refuses and returns a message explaining the site would be left without an administrator.
3. **Given** a non-existent user identifier, **When** the operator attempts to grant a capability, **Then** the ability returns a failure result naming the missing user.

---

### User Story 4 - Perform a safe site-wide database search-replace (Priority: P1)

The operator can replace every occurrence of a source string with a target string across every table in the WordPress database, safely handling serialized data. By default the operation runs in preview mode (dry-run), reporting what would change without mutating any row; the operator must explicitly opt in to execute the actual replacement.

**Why this priority**: The single ability that most site migrations, domain changes, and bulk metadata rewrites depend on. WordPress core has no REST endpoint for this and no other ability in the plugin walks the whole database. Ships as P1 alongside the P1 capability grants because both fill core-REST gaps that no other ability replicates.

**Independent Test**: An operator can preview a replacement across the whole database, then apply it, and verify that every occurrence of the source string in every applicable column now reads as the target string, including inside serialized post-meta arrays. Deliverable and demonstrable independently of any role/cap ability.

**Acceptance Scenarios**:

1. **Given** the source string `example.com` appears in `wp_posts.post_content` and inside a serialized array in `wp_postmeta.meta_value`, **When** the operator runs the replacement in dry-run mode, **Then** the ability returns a per-table / per-column tally of matches and replacements-that-would-happen without mutating any row.
2. **Given** the same starting state, **When** the operator runs the replacement with dry-run explicitly disabled, **Then** every occurrence of the source string in every applicable column is replaced with the target string, and the ability's response reports the per-table / per-column tally of actually-replaced rows.
3. **Given** a request that omits the dry-run field, **When** the ability executes, **Then** it defaults to dry-run behaviour and returns preview data (safe-by-default).
4. **Given** an operator-supplied `tables` list containing a table name that does not exist in the database, **When** the ability executes, **Then** it refuses without mutating state and returns a message naming the missing table.
5. **Given** an operator-supplied `skip_columns` list, **When** the ability executes, **Then** it does not touch any listed column even if it contains matches.
6. **Given** a serialized array stored in a meta column, **When** the ability replaces a substring inside a string value inside that serialized array, **Then** the serialized data remains structurally valid and re-reads correctly through WordPress core's option / meta APIs.
7. **Given** the `guid` column on `wp_posts` (which WordPress documentation warns against rewriting), **When** the operator does not explicitly set `include_guids: true`, **Then** the ability does not modify `guid` values.

---

### Edge Cases

- **Deleting a role currently held by many users.** Refused (see US2 scenario 4); operator must reassign users first via the existing user-update ability, then retry.
- **Granting a capability that WordPress core does not know about.** Allowed — WordPress core `WP_Role::add_cap()` freely accepts any string; custom capability names are how plugin ecosystems (WooCommerce, LearnDash, etc.) extend the permissions model.
- **Reset invoked mid-request-cycle after a plugin has registered new caps on a WordPress-core role via `add_cap`.** Reset restores the WordPress-core baseline for that role only; caps other plugins added are lost. The ability response includes a summary of the diff so the operator sees what was removed.
- **Search-replace against a database with mixed-charset tables.** The replacement respects each column's declared charset; behaviour is undefined only for byte sequences that are not valid in the target column's charset (matches the well-established WP-CLI behaviour).
- **Search-replace with an empty target string.** Allowed — this is the canonical way to strip a substring from the database. The source string must still be non-empty.
- **Search-replace where source and target are identical.** Ability returns success with a summary showing zero replacements and does not mutate anything.
- **A `tables` list that includes only tables outside the WordPress prefix while `all_tables` is false.** Refused — the caller should either set `all_tables: true` explicitly or restrict to prefixed tables.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The plugin MUST expose an ability that grants a single capability to an existing role.
- **FR-002**: The plugin MUST expose an ability that revokes a single capability from an existing role.
- **FR-003**: The plugin MUST expose an ability that creates a new role with a caller-supplied slug and display name, with an optional starting capability set cloned from an existing role.
- **FR-004**: The plugin MUST expose an ability that deletes an existing role.
- **FR-005**: The plugin MUST expose an ability that resets any of the five WordPress built-in roles back to their WordPress-core default capabilities.
- **FR-006**: The plugin MUST expose an ability that grants a single capability directly to a specific user.
- **FR-007**: The plugin MUST expose an ability that revokes a single capability directly from a specific user.
- **FR-008**: The plugin MUST expose an ability that performs a site-wide, serialized-data-safe, string-to-string search-replace across the WordPress database.
- **FR-009**: Every ability listed in FR-001 through FR-008 MUST gate execution on the requester holding the `manage_options` WordPress capability, using the identical permission-callback pattern already used by every existing ability in the plugin.
- **FR-010**: The role-delete ability MUST refuse to delete any role that is currently held by one or more users, and MUST return a response naming the number of holders.
- **FR-011**: The role-delete ability MUST refuse to delete any of the five WordPress built-in roles (`administrator`, `editor`, `author`, `contributor`, `subscriber`) regardless of user count.
- **FR-012**: The role-reset ability MUST refuse to operate on any role slug that is not one of the five WordPress built-in roles.
- **FR-013**: The role-revoke-capability ability MUST refuse when the target role is `administrator` AND the capability being revoked is one WordPress core grants to the administrator role by default.
- **FR-014**: The user-revoke-capability ability MUST refuse when the target user is the last remaining site administrator AND the capability being revoked is one WordPress core grants to the administrator role by default.
- **FR-015**: The search-replace ability MUST default to dry-run (preview-only) behaviour when the caller does not explicitly set the dry-run field to `false`.
- **FR-016**: The search-replace ability MUST refuse without mutating any state when the caller supplies a `tables` list containing one or more table names that do not exist in the database.
- **FR-017**: The search-replace ability MUST correctly handle strings stored inside PHP-serialized values (arrays, objects) — a replacement inside a string element of a serialized array MUST leave the surrounding structure intact and re-readable through WordPress core option / meta APIs.
- **FR-018**: The search-replace ability MUST NOT modify the `guid` column on `wp_posts` unless the caller explicitly opts in via the `include_guids` field.
- **FR-019**: Every write ability listed in FR-001 through FR-008 MUST return an outcome payload that includes at minimum a boolean success flag, a human-readable message, and (for search-replace) a per-table / per-column tally of matches and replacements.
- **FR-020**: The create-role ability MUST refuse to create a role whose slug already exists in the site's role registry.

### Key Entities *(include if feature involves data)*

- **Role**: A named collection of WordPress capabilities. Identified by a slug (`administrator`, `editor`, custom names). Holds a set of capability-to-boolean mappings. Persisted by WordPress core in the `wp_user_roles` option.
- **Capability**: A string permission token (`edit_posts`, `manage_options`, `upload_files`, and any plugin-defined string). Can be attached to a role (affecting every user of that role) or directly to a user (overriding role-derived permissions for that user only).
- **User**: A WordPress user account. Identified by numeric user ID, login name, or email address. Holds one or more roles plus any per-user capabilities that have been directly assigned.
- **Search-replace operation**: A one-shot command specifying a source string, target string, optional table / column allowlist and skip-list, a dry-run mode flag, and an include-guids flag. Produces a report of matches, would-be-replacements (in dry-run), or actual replacements (when applied).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An operator can create a custom role, grant it three capabilities, assign it to a test user, then delete the role after reassigning the user — all through the ability surface, without touching WordPress admin UI, in under 60 seconds of API interaction time.
- **SC-002**: A search-replace across a 1000-post site (approximately 5000 rows across `wp_posts`, `wp_postmeta`, `wp_options`) completes in under 30 seconds in dry-run mode and returns an accurate match tally.
- **SC-003**: 100% of dry-run search-replace invocations leave the database byte-identical to the pre-invocation state, verified by a row-hash comparison on every affected table.
- **SC-004**: 100% of attempts to delete a role currently held by any user are refused without mutating state.
- **SC-005**: 100% of attempts to revoke a WordPress-core administrator capability from the administrator role are refused without mutating state.
- **SC-006**: 100% of attempts to revoke a WordPress-core administrator capability from the last remaining site administrator are refused without mutating state.
- **SC-007**: An operator with `edit_posts` but not `manage_options` receives a permission-denied response on 100% of invocations of every ability in this feature.
- **SC-008**: Serialized data inside `wp_postmeta.meta_value` remains re-readable through WordPress core's `get_post_meta()` on 100% of rows the search-replace ability modifies.

## Assumptions

- The AcrossAI Abilities Manager plugin is installed and active, exposing the shared ability-registration runtime (via `wp_register_ability()`).
- The invoking user (or the credentials the AI agent is authenticated as) already holds `manage_options` on the target site. Elevation of privilege is not part of this feature.
- The plugin's existing convention that every ability's permission callback is exactly `static function (): bool { return current_user_can( 'manage_options' ); }` remains in force; no other capability gate applies to any of the new abilities.
- The site's WordPress core version is one currently supported by the plugin's PHPUnit matrix (PHP 8.1 through 8.5); older PHP behaviour is out of scope.
- Multisite behaviour: the abilities operate on the currently-active site's role table. Network-wide role changes on a multisite install are out of scope for this feature.
- The search-replace ability operates against the WordPress database configured in `wp-config.php` — external databases or non-WordPress tables are out of scope even when `all_tables: true`.
- No approval / two-step confirmation workflow: the destructive-annotation and per-ability guardrails (default dry-run for search-replace, role-holder count checks, last-admin protection) are the only safety envelope. Fine-grained approval flow is deferred to a future feature.
- No changelog / version bump inside this spec's branch — this feature ships bundled with two sibling specs (063, 064) in the unified 0.0.23 plugin release. The release-branch cut is out of scope for this spec.
