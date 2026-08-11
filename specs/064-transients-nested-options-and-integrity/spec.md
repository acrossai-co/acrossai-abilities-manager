# Feature Specification: Transient CRUD, nested option access, plugin lifecycle & checksum integrity

**Feature Branch**: `064-transients-nested-options-and-integrity`
**Created**: 2026-08-11
**Status**: Draft
**Input**: User description: "Transient CRUD (4 abilities, Cache category) + nested-key option access (2, Options) + post-meta append (1, Content) + WP.org plugin directory search + plugin uninstall (2, Plugins) + core and plugin checksum verification (2 abilities, Core + Plugins). See docs/planning/064-transients-nested-options-and-integrity.md."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Inspect and manage transient cache entries (Priority: P1)

An operator (or AI agent operating on their behalf with `manage_options`) can read a single transient by name, enumerate the transients currently stored on the site, delete a specific transient, and purge every transient whose expiry has passed — without falling back to raw SQL.

**Why this priority**: Transient state is central to WordPress performance troubleshooting (stuck update-check transients, stale API-response caches, membership-plugin cache races). Today the plugin exposes only bulk-flush and nothing else, so any agent debugging a transient-related issue has to run raw `SELECT`s. This unlocks routine debugging.

**Independent Test**: An operator can seed a transient via any writer, then read, list, delete, and expire — every one demonstrable in isolation.

**Acceptance Scenarios**:

1. **Given** a transient `foo` with value `"bar"` and an expiry in the future, **When** the operator reads the transient, **Then** the response reports `exists: true`, returns the value, and includes the expiry timestamp.
2. **Given** no transient named `foo`, **When** the operator reads it, **Then** the response reports `exists: false` (distinct from a transient that legitimately stores the value `false`).
3. **Given** any number of transients on the site, **When** the operator invokes the list ability without a search term, **Then** the response returns up to the caller's limit (default 100, cap 500), each entry including its name, expiry (if any), a boolean site-vs-blog scope flag, and a boolean is-expired flag; the response also reports the total count independent of the returned page size.
4. **Given** a transient `foo`, **When** the operator deletes it, **Then** subsequent reads report `exists: false`, and re-deleting the same transient succeeds (idempotent).
5. **Given** a mix of expired and unexpired transients, **When** the operator invokes the expire-all ability, **Then** every expired transient (both blog- and site-scope) is removed, unexpired transients are untouched, and the response reports the count removed.
6. **Given** a site-scope transient (stored via `set_site_transient`), **When** the operator reads or deletes it with the `site` flag set true, **Then** the correct site-scope entry is targeted; when the flag is false, blog-scope entries are targeted.

---

### User Story 2 - Read or mutate a single nested key inside a serialized option (Priority: P1)

The operator can read one nested key from a serialized-array option (e.g. `woocommerce_settings.general.currency_symbol`) without transferring the whole option, and can insert, update, or delete a single nested key without hand-serializing the surrounding structure.

**Why this priority**: Large plugins (WooCommerce, LearnDash, Freemius) store many settings inside single big options. Round-tripping the whole option for one key wastes bytes and creates race hazards. This ability slice is the smallest primitive that fixes both problems.

**Independent Test**: An operator can round-trip through the two nested-key abilities against any complex option and verify both read and mutation behaviour independently of every other ability in this feature.

**Acceptance Scenarios**:

1. **Given** an option `settings` whose value is `{ "a": { "b": "c" } }`, **When** the operator reads with path `["a", "b"]`, **Then** the response reports `exists: true` and returns the string `"c"`.
2. **Given** the same option, **When** the operator reads with a path whose leaf key does not exist (e.g. `["a", "z"]`), **Then** the response reports `exists: false` without returning a value.
3. **Given** the same option, **When** the operator applies an update operation with path `["a", "b"]` and value `"d"`, **Then** the option becomes `{ "a": { "b": "d" } }` — no other keys mutated.
4. **Given** an option `settings` whose value is `{ "a": { "b": "c" } }`, **When** the operator applies an insert operation with path `["a", "e"]` and value `"f"`, **Then** the option becomes `{ "a": { "b": "c", "e": "f" } }`.
5. **Given** the same option, **When** the operator applies a delete operation with path `["a", "b"]`, **Then** the option becomes `{ "a": {} }` — the parent key is preserved even when empty.
6. **Given** an option whose name appears in the block-list of protected core options (mirrors the block-list used by the existing option-update ability), **When** the operator attempts any nested mutation, **Then** the ability refuses without mutating state.
7. **Given** a path that traverses into a non-array intermediate value (e.g. path `["a", "b", "x"]` when `a.b` is a string), **When** the operator invokes any nested-mutation operation, **Then** the ability refuses and returns a message explaining that the parent is not traversable.

---

### User Story 3 - Append an additional post-meta row without replacing existing rows (Priority: P2)

The operator can add a new post-meta row for a given `(post_id, key)` pair without replacing any existing rows for the same key, complementing the existing "replace" (`update-post-meta`) and "delete" (`delete-post-meta`) writers.

**Why this priority**: WordPress post-meta is a one-to-many store — a single post can legitimately have multiple rows under the same key (e.g. per-attachment references, multi-value custom fields). The existing update ability replaces all rows for a key; there is no way today to append. Ships as P2 because it fills a specific gap in an existing surface rather than opening a new surface.

**Independent Test**: An operator can seed a post with one meta row via the existing update-post-meta ability, then append two more via this new ability, and verify all three rows persist independently.

**Acceptance Scenarios**:

1. **Given** a post with zero existing rows for meta key `attachments`, **When** the operator appends a new row with a value, **Then** the ability returns a numeric meta identifier and the post now has one row for that key.
2. **Given** a post with three existing rows for meta key `attachments`, **When** the operator appends another row, **Then** the post now has four rows and the response returns the newly-created row's identifier.
3. **Given** the same post, **When** the operator appends with the "unique" flag set true and a row for that key already exists, **Then** the ability returns a false / null identifier and does not create a row (matches WordPress core `add_post_meta( ..., true )` behaviour).
4. **Given** a post identifier that does not exist, **When** the operator appends, **Then** the ability refuses without mutating state and names the missing post.

---

### User Story 4 - Discover, uninstall, and verify plugin file integrity (Priority: P2)

The operator can search the WordPress.org plugin directory for installable candidates, uninstall a plugin that is already deactivated (firing the plugin's own cleanup hook and deleting its files), and verify the on-disk file integrity of a plugin or of WordPress core itself against the official WordPress.org checksums.

**Why this priority**: These four abilities together close the plugin lifecycle: discovery → install (already exists) → activate (already exists) → deactivate (already exists) → uninstall (new) → verify (new). Also enables lightweight integrity auditing without a full site-scan plugin. Ships as P2 because each individual ability is independently deployable and none are load-bearing for daily operations.

**Independent Test**: An operator can search for a plugin, uninstall an inactive plugin, and verify the checksums of another plugin or of WordPress core — each in isolation.

**Acceptance Scenarios**:

1. **Given** a plugin term (e.g. `"jetpack"`), **When** the operator invokes the plugin-directory search ability, **Then** the response includes a list of matching plugins from wordpress.org with at minimum: slug, human name, short description, rating, active-install count, homepage, and download link — plus pagination metadata (page, total pages, total results).
2. **Given** a plugin that is currently active, **When** the operator invokes the uninstall ability against it, **Then** the ability refuses without mutating state and returns a message pointing the caller to the deactivate ability.
3. **Given** a plugin that is inactive and installed on disk, **When** the operator invokes the uninstall ability, **Then** the plugin's registered uninstall hook fires and its files are removed from disk, and the response reports success.
4. **Given** a site where `DISALLOW_FILE_MODS` is defined, **When** the operator invokes the uninstall ability, **Then** the ability refuses regardless of any other input state.
5. **Given** an installed plugin whose files are unmodified, **When** the operator invokes the plugin-checksums verify ability against it, **Then** the response reports an "ok" status for every checked file and a summary of totals (total, ok, modified, missing, added).
6. **Given** an installed plugin one of whose files has been edited, **When** the operator invokes the plugin-checksums verify ability, **Then** the response includes an entry for the modified file with status "modified" and includes the expected vs actual checksum values.
7. **Given** the currently-installed WordPress core version, **When** the operator invokes the core-checksums verify ability without arguments, **Then** the response reports a per-file status and a totals summary equivalent to the plugin verify shape.
8. **Given** the core-checksums verify ability invoked with the "strict" flag set true, **When** it encounters files present on disk that are not in the expected manifest (e.g. added by a custom deploy), **Then** those files are flagged with status "added" in the response.

---

### Edge Cases

- **Transient list with `search` and pagination together.** Returned entries are the intersection of the search filter and the requested page — the total count reflects the search filter, not the whole site.
- **Nested-option update with a value that changes the option's byte size significantly.** Written atomically via WordPress core option APIs; caller does not need to worry about the underlying serialization.
- **Nested-option delete on the root path (empty path array).** Refused — the ability is not a full option deleter; use the existing delete-option ability instead.
- **Post-meta append against a value that is an array.** WordPress core `add_post_meta()` serializes automatically; response returns the new meta identifier as usual.
- **WordPress.org search where the wordpress.org API is unreachable.** The ability returns success with an empty results list and an error field naming the transport failure (does not raise a fatal), so the caller can distinguish "no results" from "unable to reach directory".
- **Plugin uninstall for a plugin whose uninstall hook throws.** WordPress core catches and logs the exception; the ability reports partial success (files deleted, hook failure) with a message describing what happened.
- **Plugin-checksum verify for a plugin not hosted on wordpress.org (e.g. custom/paid plugin without a checksum manifest).** The ability returns a "no manifest available" response rather than a failure; the operator learns that checksum verification cannot be performed for this plugin.
- **Core-checksum verify against a non-`en_US` locale.** The ability accepts a locale input; if omitted, defaults to `en_US`. Locale-specific file lists are honored so translated core installs are correctly verified.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The plugin MUST expose an ability that reads one transient by name and reports both whether the transient exists and (when it exists) its value and remaining expiry timestamp.
- **FR-002**: The plugin MUST expose an ability that enumerates transients with configurable filters: name substring search, blog-vs-site scope, include-expired toggle, page size (default 100, max 500), and offset.
- **FR-003**: The plugin MUST expose an ability that deletes one transient by name, correctly targeting either blog-scope or site-scope storage based on a caller-supplied flag.
- **FR-004**: The plugin MUST expose an ability that purges every expired transient in one operation and returns the count purged.
- **FR-005**: The plugin MUST expose a read-only ability that walks a serialized option's nested structure along a caller-supplied key path and returns the value at that path together with an `exists` flag.
- **FR-006**: The plugin MUST expose a write ability that inserts, updates, or deletes a single value inside a serialized option at a caller-supplied key path, without touching any other key.
- **FR-007**: The ability described in FR-006 MUST refuse to operate on any option whose name appears in the same block-list used by the existing option-update ability, and MUST refuse when the caller passes an empty key path.
- **FR-008**: The plugin MUST expose a write ability that appends a new post-meta row for a caller-supplied `(post_id, key, value)` triple without replacing existing rows, mirroring WordPress core `add_post_meta()` semantics, including the "unique" flag that blocks the append if any row already exists for the key.
- **FR-009**: The plugin MUST expose a read-only ability that searches the WordPress.org plugin directory and returns at minimum slug, name, short description, rating, active-install count, homepage URL, download link, and pagination metadata (page, total pages, total results).
- **FR-010**: The plugin MUST expose a write ability that uninstalls a caller-named plugin, invoking WordPress core's uninstall workflow (fires the plugin's registered uninstall hook and deletes its files).
- **FR-011**: The ability described in FR-010 MUST refuse to operate on any currently-active plugin and MUST refuse on any site where `DISALLOW_FILE_MODS` is defined truthy.
- **FR-012**: The plugin MUST expose a read-only ability that verifies a plugin's on-disk files against the official WordPress.org checksums manifest for the installed version, returning per-file status (ok / modified / missing / added) and a totals summary.
- **FR-013**: The plugin MUST expose a read-only ability that performs the equivalent verification against WordPress core files, accepting optional `version`, `locale`, `include_root`, and `exclude` inputs.
- **FR-014**: Every ability listed in FR-001 through FR-013 MUST gate execution on the requester holding the `manage_options` WordPress capability, using the identical permission-callback pattern already used by every existing ability in the plugin.
- **FR-015**: The transient-delete, expire-all, nested-option-mutate, and plugin-uninstall abilities MUST carry a `destructive: true` annotation; every read ability in this feature MUST carry `readonly: true`.
- **FR-016**: Every ability response MUST include at minimum a boolean success flag and a human-readable message field.

### Key Entities *(include if feature involves data)*

- **Transient**: A short-lived value cached in the site's option table (blog-scope) or network option table (site-scope) with an associated expiry timestamp. Reads, writes, and deletes are keyed by name.
- **Serialized option**: An option whose stored value is a PHP-serialized array or object structure. Nested-key access refers to walking that structure by a caller-supplied path.
- **Post-meta row**: A single row in `wp_postmeta` with a `(post_id, key, value)` triple. Multiple rows may exist for the same `(post_id, key)`.
- **Plugin directory listing**: A wordpress.org-hosted plugin's public metadata (slug, human name, description, rating, active-install count, homepage URL, download link) as returned by the wordpress.org plugins API.
- **Checksum entry**: A per-file record within the WordPress.org checksums manifest, mapping a file path to its expected content hash. Verification produces a per-file status (ok, modified, missing, added).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: An operator can seed a transient via the read ability's setUp, list it, delete it, and confirm exists-false — every step delivered by an ability in this feature, no admin UI or raw SQL needed.
- **SC-002**: A nested-option update of a single string value inside a 100-key serialized option produces a byte-for-byte identical option except at the targeted key on 100% of golden-path invocations.
- **SC-003**: Appending a post-meta row does not touch any pre-existing row for the same `(post_id, key)` on 100% of golden-path invocations.
- **SC-004**: 100% of attempts to uninstall an active plugin are refused without mutating state.
- **SC-005**: 100% of attempts to invoke any write ability in this feature by a user holding `edit_posts` but not `manage_options` are refused with a permission-denied response.
- **SC-006**: The plugin-directory search ability returns a non-empty result list within 3 seconds on 90% of invocations against the wordpress.org public API (excludes cold-start timing; excludes cases where the API is unreachable).
- **SC-007**: The plugin- and core-checksum verify abilities correctly flag every modified file when a controlled test edits one file — no false positives on unmodified files, no false negatives on modified files, on 100% of test invocations.
- **SC-008**: The transient-list ability's `include_expired: false` mode returns zero expired entries on 100% of invocations, verified by cross-checking the entries' expiry timestamps.

## Assumptions

- The AcrossAI Abilities Manager plugin is installed and active on the target site.
- The invoking user (or credentials the AI agent is authenticated as) already holds `manage_options`.
- The plugin's convention that every ability's permission callback is exactly `static function (): bool { return current_user_can( 'manage_options' ); }` remains in force; no other capability gate applies to any of the new abilities.
- The site can reach `api.wordpress.org` over HTTPS. Sites in air-gapped environments will observe expected failures on the search and checksum-verify abilities; those are correct behaviours, not bugs.
- Multisite: the transient abilities target the currently-active site by default; the `site` flag switches to the network-level store where appropriate.
- Plugin uninstall respects WordPress core's own `DISALLOW_FILE_MODS` constant.
- The nested-option abilities operate only on options stored via WordPress core option APIs; options stored directly in custom database tables are out of scope.
- No changelog / version bump inside this spec's branch — this feature ships bundled with two sibling specs (062, 063) in the unified 0.0.23 plugin release.
