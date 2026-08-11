# Feature Specification: Site introspection read endpoints

**Feature Branch**: `063-site-introspection-reads`
**Created**: 2026-08-11
**Status**: Draft
**Input**: User description: "Site introspection read-only endpoints (11 abilities): get-wp-version, get-db-prefix, get-wp-config-constant, list-theme-mods, list-rewrite-rules, list-widgets, list-sidebars, list-image-sizes, get-comment-count, get-maintenance-mode-status, test-wp-cron. Introduces a new Widgets category. See docs/planning/063-site-introspection-reads.md."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Retrieve small single-purpose site facts (Priority: P1)

An operator (or AI agent operating on their behalf with `manage_options`) can obtain individual site facts that WordPress does not expose through a public REST endpoint today — the WordPress core version, the database table prefix, the value of a single `wp-config.php` constant, the active theme's stored theme modifications, the currently-generated rewrite rules, the registered image sizes, an aggregated comment count, whether maintenance mode is active, and whether the WordPress cron endpoint is reachable — one at a time, each through its own dedicated ability.

**Why this priority**: These small facts are what an agent needs first before it can reason about any other operation on the site (e.g. "what version am I on?" before deciding whether an update ability is safe to call). None of them are individually expensive but their collective absence today forces every agent to guess or fall back to raw SQL. Ships as P1 because it unblocks every downstream automation.

**Independent Test**: An operator can call any one of the nine single-purpose reads (version, prefix, wp-config constant, theme mods, rewrite rules, image sizes, comment count, maintenance status, cron test) and receive a correctly-shaped success response, without any other ability in this feature needing to exist.

**Acceptance Scenarios**:

1. **Given** a WordPress site running any supported version, **When** the operator invokes the version read ability, **Then** the response includes the exact WordPress core version string and a boolean indicating whether the install is multisite.
2. **Given** a WordPress site with any table prefix, **When** the operator invokes the database-prefix read ability, **Then** the response includes both the current-blog prefix and the base (multisite root) prefix.
3. **Given** a `wp-config.php` that defines a non-sensitive constant such as `WP_DEBUG`, **When** the operator invokes the config-constant read ability with the constant name, **Then** the response returns the defined value.
4. **Given** any `wp-config.php`, **When** the operator invokes the config-constant read ability with a constant that is not defined, **Then** the response indicates the constant is not defined without returning a value.
5. **Given** a `wp-config.php` that defines an authentication key or salt (`AUTH_KEY`, `SECURE_AUTH_KEY`, `LOGGED_IN_KEY`, `NONCE_KEY`, `AUTH_SALT`, `SECURE_AUTH_SALT`, `LOGGED_IN_SALT`, `NONCE_SALT`) or the database password (`DB_PASSWORD`), **When** the operator invokes the config-constant read ability for any of those names, **Then** the ability refuses and returns a message noting the constant is blocked from disclosure.
6. **Given** a site with theme modifications customised via the Customizer, **When** the operator invokes the theme-mods read ability, **Then** the response includes the active theme's stylesheet name and the full map of stored modifications.
7. **Given** a site where WordPress has generated rewrite rules, **When** the operator invokes the rewrite-rules read ability, **Then** the response includes the current rules object and a count of entries.
8. **Given** a site with additional image sizes registered by a theme or plugin, **When** the operator invokes the image-sizes read ability, **Then** the response enumerates each registered size with its declared width, height, and crop mode.
9. **Given** a site with approved, spam, moderated, and trashed comments, **When** the operator invokes the comment-count read ability, **Then** the response returns per-status counts and a total.
10. **Given** a site that is not currently upgrading, **When** the operator invokes the maintenance-mode status ability, **Then** the response reports `active: false`.
11. **Given** a site that is currently upgrading (the `.maintenance` marker file exists), **When** the operator invokes the maintenance-mode status ability, **Then** the response reports `active: true` and includes the timestamp the marker was created; if the timestamp is older than the WordPress-core-defined 10-minute threshold, the response also flags the marker as stale.
12. **Given** a site whose WordPress cron endpoint is reachable over HTTP, **When** the operator invokes the cron-test ability, **Then** the response reports `reachable: true` and includes whether `DISABLE_WP_CRON` is defined.

---

### User Story 2 - Inspect legacy widget and sidebar assignments (Priority: P2)

The operator can enumerate every registered sidebar on the site and every widget assigned to each sidebar, without touching WordPress admin UI.

**Why this priority**: Widgets are the legacy WordPress content-injection surface (predating blocks) and remain widely used in classic themes and headless setups. There is no current AcrossAI ability that exposes them. Ships as P2 because it introduces a whole new ability category (Widgets) — a slightly larger surface than the P1 one-liner reads.

**Independent Test**: An operator can invoke the sidebars read ability and receive the full sidebar registry, and separately invoke the widgets read ability and receive per-sidebar widget assignments — either one on its own delivers value.

**Acceptance Scenarios**:

1. **Given** a site with one or more registered sidebars, **When** the operator invokes the sidebars read ability, **Then** the response enumerates each sidebar with its identifier, display name, description, and its widget-wrapper HTML fragments (`before_widget`, `after_widget`, `before_title`, `after_title`).
2. **Given** a site whose sidebars have widgets assigned, **When** the operator invokes the widgets read ability, **Then** the response includes the per-sidebar widget-identifier list and the registered-widgets metadata map so the caller can resolve identifiers to widget classes.
3. **Given** a site with no widgets assigned to any sidebar, **When** the operator invokes the widgets read ability, **Then** the response returns an empty per-sidebar map and the registered-widgets map, both marked with `success: true`.

---

### Edge Cases

- **`wp-config.php` constant lookup on a blocked key.** Rejected without disclosure — the response signals blocked, does not include the value, and does not include an error stack.
- **Rewrite-rules read on a fresh site that has never triggered rewrite-rule generation.** Returns success with an empty rules map and count of zero (matches `get_option('rewrite_rules')` returning `false` or empty array).
- **Theme-mods read on a site whose active theme has been deleted from disk.** Returns the mods stored for the stylesheet name that WordPress considers active; does not fail, since theme-mods are stored as an option regardless of file presence.
- **Image-sizes read where a plugin has registered a size with dimensions of zero or negative values.** The response includes the entry as-registered without normalising; downstream callers decide whether to filter.
- **Comment-count read for a specific post-id that does not exist.** WordPress core `wp_count_comments()` returns all-zeros counters in this case; the response mirrors that behaviour with `success: true`.
- **Maintenance-mode status read where `.maintenance` exists but is unreadable due to filesystem permissions.** Returns `active: true` because the file exists, and includes a note that the timestamp could not be read.
- **Cron-test where the site is behind HTTP basic auth or a reverse proxy that rejects the wp-cron.php request.** Returns `reachable: false` with the observed HTTP status or error in the message, and still surfaces the `DISABLE_WP_CRON` state so the operator can distinguish network-blocked from configuration-blocked.
- **Widgets read on a fresh install with no theme registering sidebars.** Empty registered-widgets and empty per-sidebar maps; `success: true`.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The plugin MUST expose a read-only ability that returns the currently-installed WordPress core version string and a boolean indicating multisite mode.
- **FR-002**: The plugin MUST expose a read-only ability that returns both the current-blog database table prefix and the multisite base prefix.
- **FR-003**: The plugin MUST expose a read-only ability that returns the defined value of a caller-named PHP constant (typically defined in `wp-config.php`), together with a boolean indicating whether the constant is defined at all.
- **FR-004**: The ability described in FR-003 MUST refuse to return the value of any constant whose name appears in a hardcoded block-list consisting of at minimum: `AUTH_KEY`, `SECURE_AUTH_KEY`, `LOGGED_IN_KEY`, `NONCE_KEY`, `AUTH_SALT`, `SECURE_AUTH_SALT`, `LOGGED_IN_SALT`, `NONCE_SALT`, `DB_PASSWORD`.
- **FR-005**: The plugin MUST expose a read-only ability that returns the active theme's stylesheet identifier and the full map of stored theme modifications.
- **FR-006**: The plugin MUST expose a read-only ability that returns the current rewrite-rules array (or an empty structure if rewrite rules have never been generated) plus a count of entries.
- **FR-007**: The plugin MUST expose a read-only ability that enumerates every registered image size, including WordPress core defaults and any additional sizes registered by themes or plugins, with each entry's declared width, height, and crop mode.
- **FR-008**: The plugin MUST expose a read-only ability that returns the site-wide (or optionally per-post) comment counts grouped by moderation status (approved, moderated, spam, trash, post-trashed) plus a total.
- **FR-009**: The plugin MUST expose a read-only ability that reports whether the site is currently in maintenance mode, and when active includes the marker file's timestamp and a flag indicating whether the timestamp exceeds WordPress core's 10-minute stale threshold.
- **FR-010**: The plugin MUST expose a read-only ability that probes the site's WordPress cron endpoint and reports (a) whether the endpoint is reachable and (b) whether the `DISABLE_WP_CRON` PHP constant is defined and truthy.
- **FR-011**: The plugin MUST expose a read-only ability that enumerates every registered sidebar with its identifier, display name, description, and widget-wrapper HTML fragments.
- **FR-012**: The plugin MUST expose a read-only ability that returns the per-sidebar widget-identifier list AND the registered-widgets metadata map, sufficient for the caller to resolve identifiers back to widget classes.
- **FR-013**: Every ability listed in FR-001 through FR-012 MUST gate execution on the requester holding the `manage_options` WordPress capability, using the identical permission-callback pattern already used by every existing ability in the plugin.
- **FR-014**: Every ability listed in FR-001 through FR-012 MUST be annotated as read-only, idempotent, and non-destructive so that clients (and the plugin's own admin UI) render them correctly.
- **FR-015**: The plugin MUST group the two widget-related abilities (FR-011, FR-012) under a new ability category exposed to the plugin's admin UI as "Widgets", distinct from the existing Themes, Menus, and Block categories.
- **FR-016**: Every ability response MUST include at minimum a boolean success flag and — for the read abilities that return no other payload on success — a message field with a human-readable summary.

### Key Entities *(include if feature involves data)*

- **WordPress core fact**: A single small piece of information about the installed WordPress environment (version string, table prefix, defined constant value, etc.). Read from PHP globals, `wp-config.php`-defined constants, or WordPress option storage. Never mutated by this feature.
- **Theme modification**: A key/value pair stored per active theme in the site's option table (`theme_mods_<stylesheet>`). Includes Customizer-set values (site title colour, header image, etc.).
- **Rewrite rule**: A mapping from a URL pattern (regex) to a WordPress query. Generated by WordPress core in response to permalink structure and taxonomy/CPT registrations. Persisted in the `rewrite_rules` option.
- **Registered image size**: A named `{ width, height, crop }` tuple registered by WordPress core defaults (thumbnail/medium/large/full) plus any theme- or plugin-registered additional sizes.
- **Comment count summary**: Per-status counts of comments (approved, moderated / pending, spam, trash, post-trashed) plus a total. Computed on demand by WordPress core.
- **Maintenance marker**: The `.maintenance` file at the WordPress root, present only while an upgrade is in progress. Contains a timestamp WordPress core reads to decide whether the marker is stale (>10 minutes old).
- **Sidebar**: A named region in a widget-enabled theme into which widgets may be placed. Identified by a slug (`sidebar-1`, `footer-1`, etc.).
- **Widget**: A discrete UI component (recent posts, tag cloud, arbitrary text) registered with WordPress core and assignable to a sidebar. Identified by an instance identifier (widget-class-name-N).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A caller with `manage_options` obtaining any of the 11 facts via one round-trip per ability receives a `success: true` payload in 100% of golden-path invocations.
- **SC-002**: A caller with `edit_posts` but not `manage_options` receives a permission-denied response on 100% of invocations of every ability in this feature.
- **SC-003**: 100% of `wp-config.php`-constant read invocations for any of the nine blocked constants return the blocked signal without disclosing the value.
- **SC-004**: The 11 abilities together add zero rows, zero option keys, and zero database tables to the site — this feature is strictly read-only and MUST NOT persist state.
- **SC-005**: An operator inspecting the plugin's admin ability table sees the new Widgets category rendered as a distinct category, populated by exactly the two widget-related abilities (FR-011, FR-012).
- **SC-006**: 90% of the individual read abilities in this feature respond in under 100 ms on a WordPress site with a fully-warmed object cache (excludes the cron-test ability, which issues one HTTP request and is bounded by network latency; and excludes checksum-fetching abilities from sibling features).
- **SC-007**: The image-sizes ability returns all four WordPress core default sizes (`thumbnail`, `medium`, `medium_large`, `large`, plus `full`) with their configured dimensions on 100% of vanilla WordPress installs.

## Assumptions

- The AcrossAI Abilities Manager plugin is installed and active on the target site.
- The invoking user (or credentials the AI agent is authenticated as) already holds `manage_options`.
- The plugin's convention that every ability's permission callback is exactly `static function (): bool { return current_user_can( 'manage_options' ); }` remains in force; no other capability gate applies to any of the new abilities.
- Multisite: the reads operate on the currently-active site; network-wide reads (across all subsites in one call) are out of scope.
- The cron-test ability may issue a very short-timeout, non-blocking HTTP request to the site's own `wp-cron.php` endpoint. Sites that block their own outbound HTTP calls will observe `reachable: false` — that is the correct behaviour and matches how WordPress-facing monitoring tools test WP-Cron reachability.
- Widget-related abilities target the legacy widget system (classic themes, `wp_get_sidebars_widgets`, `$wp_registered_widgets`). Block-based widget areas (registered via `register_block_type`) are covered by existing block-related abilities and are not duplicated here.
- No changelog / version bump inside this spec's branch — this feature ships bundled with two sibling specs (062, 064) in the unified 0.0.23 plugin release.
