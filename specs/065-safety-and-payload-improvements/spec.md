# Feature Specification: Safety envelope + payload enrichment across 9 existing abilities

**Feature Branch**: `065-safety-and-payload-improvements`
**Created**: 2026-08-12
**Status**: Draft
**Input**: User description: "Update the code of the 9 abilities where a side-by-side quality review flagged us as weaker on safety guardrails and/or payload completeness, plus add a protected-plugin guard on `acrossai/deactivate-plugin` so it refuses to deactivate the AcrossAI plugin family (mcp-manager, abilities-manager, acrossai-pro)."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Prevent accidental deactivation of the AcrossAI plugin family (Priority: P1)

An operator (or AI agent operating on their behalf with `manage_options`) cannot use `acrossai/deactivate-plugin` to deactivate a plugin that belongs to the AcrossAI plugin family — specifically `acrossai-mcp-manager`, `acrossai-abilities-manager` (this plugin), and `acrossai-pro`. Deactivating any of the three would either cut off the ability surface entirely (`acrossai-abilities-manager`) or break the very connection the AI is using to reach the site (`acrossai-mcp-manager`). The `acrossai-pro` family is protected for the same reason (it hosts the pro connectors the pro tools rely on).

**Why this priority**: A single accidental call to `acrossai/deactivate-plugin` targeting one of these three plugins is a foot-gun that will break the AI's connection to the site, or worse, silently disable every ability the AI is trying to use. Every other issue in this spec is a paper cut compared to "the AI accidentally cuts its own connection."

**Independent Test**: An operator with `manage_options` invokes `acrossai/deactivate-plugin` with any of the three protected slugs and observes the ability refuse without mutating any state.

**Acceptance Scenarios**:

1. **Given** `acrossai-mcp-manager` is currently active, **When** the operator calls `acrossai/deactivate-plugin` with input `plugin: "acrossai-mcp-manager"`, **Then** the ability refuses without mutating state and returns a message naming the protected plugin.
2. **Given** `acrossai-abilities-manager` is currently active, **When** the operator calls `acrossai/deactivate-plugin` with input `plugin: "acrossai-abilities-manager"`, **Then** the ability refuses without mutating state.
3. **Given** `acrossai-pro` is currently active, **When** the operator calls `acrossai/deactivate-plugin` with input `plugin: "acrossai-pro"`, **Then** the ability refuses without mutating state.
4. **Given** the operator passes the plugin's file path (e.g. `acrossai-mcp-manager/acrossai-mcp-manager.php`) rather than the slug, **When** the ability resolves the input to the same plugin family, **Then** the ability refuses (the guard works against every resolvable form the fuzzy resolver accepts).
5. **Given** any non-protected plugin, **When** the operator calls `acrossai/deactivate-plugin`, **Then** the ability behaves exactly as it does today (no regression on the unprotected path).

---

### User Story 2 - Require explicit confirmation before permanently deleting media or a file (Priority: P1)

Destructive filesystem and media operations require the operator to pass `confirm: true` explicitly. Passing the operation without `confirm` returns a soft refusal that names the required flag, so a misfired call from an AI agent cannot silently destroy content.

**Why this priority**: Both `acrossai/delete-media` (hard-deletes the attachment + files on disk) and `acrossai/delete-file` (removes a file inside the WordPress installation) are permanently destructive. Today neither requires acknowledgment. A single misfired call has no undo.

**Independent Test**: The operator invokes either ability without `confirm`, observes a soft refusal, then invokes it with `confirm: true` and observes the destructive operation execute.

**Acceptance Scenarios**:

1. **Given** an existing attachment, **When** the operator calls `acrossai/delete-media` with only `id`, **Then** the ability refuses with a `confirmation_required` message.
2. **Given** the same attachment, **When** the operator calls `acrossai/delete-media` with `id` and `confirm: true`, **Then** the ability deletes the attachment.
3. **Given** a site with `MEDIA_TRASH` defined truthy, **When** the operator calls `acrossai/delete-media` with `id` + `confirm: true` and no `force`, **Then** the attachment is trashed (not permanently deleted).
4. **Given** the same site, **When** the operator additionally passes `force: true`, **Then** the attachment is permanently deleted regardless of `MEDIA_TRASH`.
5. **Given** any file inside the WordPress installation, **When** the operator calls `acrossai/delete-file` without `confirm`, **Then** the ability refuses without touching the file.
6. **Given** `acrossai/delete-file` is invoked with `confirm: true` on any file, **Then** a `.bak.<timestamp>` copy is written next to the original before the delete proceeds, and the operator's response includes the backup path.

---

### User Story 3 - Refuse to read or delete the site's secrets file (Priority: P1)

`acrossai/read-file` and `acrossai/delete-file` refuse to touch a hardcoded list of secret-holding files at the WordPress root — specifically `wp-config.php` and `.htaccess` — regardless of the caller's `manage_options` capability. This blocks the single most damaging accidental-read path (agent slurps `wp-config.php` and echoes the database password + auth salts back to the client).

**Why this priority**: `wp-config.php` contains the database password and eight authentication constants (`AUTH_KEY`, `SECURE_AUTH_KEY`, `LOGGED_IN_KEY`, `NONCE_KEY`, `AUTH_SALT`, `SECURE_AUTH_SALT`, `LOGGED_IN_SALT`, `NONCE_SALT`). A single misfired `read-file` call today discloses all nine. `manage_options` is not sufficient defense because a compromised admin session or a malicious AI client already has the capability.

**Independent Test**: The operator invokes `acrossai/read-file` with `path: "wp-config.php"`, observes a `protected_read` refusal, and does the same for `.htaccess`. Then invokes `acrossai/delete-file` on `wp-config.php` and observes a `protected_write` refusal.

**Acceptance Scenarios**:

1. **Given** any WordPress install, **When** the operator calls `acrossai/read-file` with `path: "wp-config.php"`, **Then** the ability refuses with `blocked_reason: "protected_read"` and does not return the file contents.
2. **Given** the same install, **When** the operator calls `acrossai/read-file` with `path: ".htaccess"`, **Then** the ability refuses with `blocked_reason: "protected_read"`.
3. **Given** the same install, **When** the operator calls `acrossai/delete-file` with any of the protected paths, **Then** the ability refuses with `blocked_reason: "protected_write"` without modifying the file.

---

### User Story 4 - Cap `acrossai/read-file` at a safe upload size (Priority: P2)

`acrossai/read-file` refuses to return the contents of a file that exceeds a fixed maximum byte size (default: 5 MB). Callers that legitimately need to inspect large files must issue a targeted read via an alternative surface (offset/limit — future work) rather than loading the whole file into memory.

**Why this priority**: The current implementation calls `file_get_contents()` on an arbitrary path. A 500 MB `debug.log` will exhaust PHP's memory limit, fatal the request, and reveal an OOM error to the caller. A hard cap prevents the class of failure entirely.

**Independent Test**: The operator invokes `acrossai/read-file` on a file that exceeds the cap, observes a soft refusal naming the limit and the file's actual size.

**Acceptance Scenarios**:

1. **Given** a text file smaller than the cap, **When** the operator calls `acrossai/read-file`, **Then** the ability returns the full contents as today.
2. **Given** a file larger than the cap, **When** the operator calls `acrossai/read-file`, **Then** the ability refuses with a message naming the size limit and the actual file size, without loading the file into memory.
3. **Given** a file that appears to contain binary content (non-UTF-8 bytes), **When** the operator calls `acrossai/read-file`, **Then** the ability returns a shape carrying `binary: true` + `size` + a human-readable message, but does NOT return the raw bytes as `content`.

---

### User Story 5 - Enrich `get-post` payload with derived fields (Priority: P2)

`acrossai/get-post` returns not just the raw post row but also derived fields the caller usually needs next: attached terms grouped by taxonomy, non-protected post meta, the featured image (id + URL + alt), the public permalink, the admin edit link, and a shallow author `{id, name}` shape.

**Why this priority**: Today `get-post` returns `get_post( $id, ARRAY_A )` — the raw WordPress row. Every downstream client that wants to render or reason about the post immediately makes 4–5 follow-up calls to hydrate terms / meta / featured image / permalink. This is wasted round-trips and wasted tokens. All the derived fields are trivially derivable in one server-side pass.

**Independent Test**: The operator invokes `acrossai/get-post` on any post; the response payload includes the derived fields alongside the raw row.

**Acceptance Scenarios**:

1. **Given** a post assigned to categories and tags, **When** the operator calls `acrossai/get-post`, **Then** the response includes a `terms` object keyed by taxonomy with per-term `{term_id, name, slug}` triples.
2. **Given** a post with non-protected meta, **When** the operator calls `acrossai/get-post`, **Then** the response includes a `meta` object of non-protected keys (keys starting with `_` or reported by `is_protected_meta()` are omitted unless allow-listed by a filter).
3. **Given** a post with a featured image, **When** the operator calls `acrossai/get-post`, **Then** the response includes `featured_image: { id, url, alt }`.
4. **Given** any post, **When** the operator calls `acrossai/get-post`, **Then** the response includes `permalink`, `edit_link`, and `author: { id, name }`.

---

### User Story 6 - Widen `list-media` search to include alt-text (Priority: P2)

`acrossai/list-media` matches the caller's `search` string against title, caption, description, AND alt-text (the postmeta field `_wp_attachment_image_alt`) — not just the three fields WP_Query's `s` param covers by default.

**Why this priority**: Alt-text is where accessibility-conscious sites store the most descriptive text about each image. Today a caller searching for "logo" against a media library where the alt-text says "Company logo, blue variant" but title/caption/description are empty will miss the match. Fixing this closes a real search-relevance gap.

**Independent Test**: The operator uploads an image with a distinctive alt-text-only string, invokes `acrossai/list-media` with that string as `search`, and observes the attachment in the results.

**Acceptance Scenarios**:

1. **Given** an attachment whose title/caption/description are empty but whose alt-text contains "brand-hero-2026", **When** the operator calls `acrossai/list-media` with `search: "brand-hero-2026"`, **Then** the attachment appears in the results.
2. **Given** an attachment whose title contains "brand-hero-2026" (and alt-text is empty), **When** the operator calls `acrossai/list-media` with the same search, **Then** the attachment still appears (current behaviour preserved).
3. **Given** two attachments — one matching only via title, one matching only via alt-text — **When** the operator calls with the shared search string, **Then** both appear in a de-duplicated result set.

---

### User Story 7 - Report which fields `update-media` actually changed (Priority: P2)

`acrossai/update-media` returns an `updated` array listing the names of the fields that were actually changed by the invocation (subset of `title`, `caption`, `description`, `alt_text`). Callers no longer need to diff before-and-after payloads to figure out what happened.

**Why this priority**: The current response returns the full media object but doesn't call out which specific fields were mutated. A caller that passes all four fields and hits a validation error on one of them cannot tell from the response which three landed successfully.

**Acceptance Scenarios**:

1. **Given** an attachment, **When** the operator calls `acrossai/update-media` with only `alt_text`, **Then** the response includes `updated: ["alt_text"]`.
2. **Given** the same attachment, **When** the operator calls with `title` + `caption` + `alt_text`, **Then** the response includes `updated: ["title", "caption", "alt_text"]` in the order the fields were processed.
3. **Given** an invocation with `id` but no update fields, **When** the operator calls the ability, **Then** the response returns `updated: []` and reports success.

---

### User Story 8 - Add correctness gates on `update-post` (Priority: P2)

`acrossai/update-post` enforces four additional gates before mutating a post:

1. The post's `post_type` must be writable (registered with `public: true` OR `show_in_rest: true` — mirrors the WP-REST behaviour). Refuses on internal-only post types like `nav_menu_item` and `revision`.
2. The `meta` object cannot include any protected meta key (a key starting with `_` or one that `is_protected_meta( $key, 'post' )` returns true for) unless allow-listed via a filter.
3. If the caller passes `status: "publish"` (or a status change into a public state), the caller must hold `publish_posts` for that post type — not just `manage_options`.
4. If the caller passes `author: <different_user_id>`, the caller must hold `edit_others_posts` for that post type.

**Why this priority**: Today `manage_options` is the only gate; the ability blindly passes every input through `wp_update_post`. That means a caller with `manage_options` but restricted lower-tier caps (unusual but possible on custom role setups) can bypass WordPress-core's own permission model. It also means a caller can silently write protected meta that WordPress core would normally block via the REST API.

**Acceptance Scenarios**:

1. **Given** a `nav_menu_item` post (internal), **When** the operator calls `acrossai/update-post` on it, **Then** the ability refuses with a message naming the non-writable post type.
2. **Given** a caller passing `meta: { "_edit_lock": "...", "custom_field": "..." }`, **When** the ability executes, **Then** `_edit_lock` is stripped from the meta_input and the response reports which meta keys were dropped; `custom_field` is written as usual.
3. **Given** a caller passing `status: "publish"` on a post type where the caller lacks `publish_posts`, **When** the ability executes, **Then** it refuses with a permission-denied message.
4. **Given** a caller passing `author: <someone_else>` on a post where the caller lacks `edit_others_posts`, **When** the ability executes, **Then** it refuses with a permission-denied message.

---

### User Story 9 - Suggest a redirect target when `delete-post` removes a public URL (Priority: P3)

`acrossai/delete-post`'s response includes a `suggested_redirect` field naming the permalink that just went dead + a suggested replacement URL (either the parent, the archive, or the site root — computed heuristically). The caller can use this to feed a redirect-manager plugin or a redirect ability without having to reconstruct the URL after the delete.

**Why this priority**: Deleting a published post kills a URL that may have inbound links. Surfacing the dead URL in the response is a low-cost quality-of-life improvement. Not urgent because deletion works today; the caller can compute the URL themselves — this just saves a step.

**Acceptance Scenarios**:

1. **Given** a published post, **When** the operator calls `acrossai/delete-post` with `id` and `force: true`, **Then** the response includes `suggested_redirect: { from: <permalink>, to: <parent_or_archive_or_root_url> }`.
2. **Given** a draft post, **When** the operator deletes it, **Then** no `suggested_redirect` is included (drafts have no public URL that needs redirecting).
3. **Given** a trashed post (soft delete), **When** the ability trashes it, **Then** no `suggested_redirect` is included (URL might come back if the post is restored).

---

### Edge Cases

- **Protected-plugin guard on `deactivate-plugin`.** The fuzzy resolver accepts multiple forms of input for the same plugin (slug, file, partial). The guard MUST match against the resolved plugin file path, not the raw input, so `deactivate-plugin` can't be tricked by passing a partial name that fuzzy-resolves to a protected plugin.
- **`delete-media` with `MEDIA_TRASH` defined but `force: true`.** `force` takes precedence; the ability permanently deletes and reports `deleted: "deleted"` rather than `deleted: "trashed"`.
- **`read-file` on a file exactly at the size cap.** Returns success (cap is `>`, not `>=`).
- **`get-post` on a post with a broken featured-image reference** (attachment deleted, meta lingers). `featured_image` is present in the response as `null`, not omitted.
- **`update-post` protected-meta filter with a filter callback that removes every default protected key.** Behaves as if the caller had specified every key — no defensive guard against a totally-open filter, since that's an intentional site-owner choice.
- **`update-media` idempotent invocation.** Calling twice with the same fields returns `updated: [...]` both times — the ability does not compare against the current stored value; it re-writes and reports what was written.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: `acrossai/deactivate-plugin` MUST maintain a hardcoded protected-plugin list containing at minimum: `acrossai-mcp-manager/acrossai-mcp-manager.php`, `acrossai-abilities-manager/acrossai-abilities-manager.php`, and `acrossai-pro/acrossai-pro.php`. Match is against the resolved plugin file path so the guard cannot be bypassed via fuzzy input.
- **FR-002**: `acrossai/deactivate-plugin` MUST return `success: false` with `blocked_reason: "protected_plugin"` when the resolved target matches an entry in the protected list, without invoking `deactivate_plugins()`.
- **FR-003**: `acrossai/delete-media` MUST require an explicit `confirm: true` input; refuse with `blocked_reason: "confirmation_required"` when the input is absent or falsy.
- **FR-004**: `acrossai/delete-media` MUST honour the `MEDIA_TRASH` PHP constant — when defined truthy AND the caller omits `force: true`, the attachment is trashed (`wp_delete_attachment( $id, false )`); otherwise the delete is permanent.
- **FR-005**: `acrossai/delete-media` response MUST include a `deleted` field with values `"deleted"` or `"trashed"` reflecting the observed outcome.
- **FR-006**: `acrossai/list-media` MUST expand its `search` behaviour to match against `_wp_attachment_image_alt` postmeta in addition to the WP_Query `s` fields (title, caption, description). The union is de-duplicated by attachment ID.
- **FR-007**: `acrossai/update-media` response MUST include an `updated` array naming each field the ability actually wrote (subset of `title`, `caption`, `description`, `alt_text`). Empty array when no update fields were passed.
- **FR-008**: `acrossai/update-post` MUST refuse invocations on post types that are neither `public: true` NOR `show_in_rest: true` (matches the WP REST writability convention).
- **FR-009**: `acrossai/update-post` MUST filter the caller-supplied `meta` object to remove any key that begins with `_` OR that `is_protected_meta( $key, 'post' )` returns true for, unless the filter `acrossai_allowed_protected_meta` returns an array containing that key. Stripped keys MUST be reported in the response payload as `dropped_meta_keys`.
- **FR-010**: `acrossai/update-post` MUST refuse when the caller passes `status: "publish"` (or any status that maps to a public status) AND lacks `publish_posts` for the target post type.
- **FR-011**: `acrossai/update-post` MUST refuse when the caller passes `author` set to a user ID other than the current user AND lacks `edit_others_posts` for the target post type.
- **FR-012**: `acrossai/get-post` MUST decorate its response with the following derived fields: `terms` (object keyed by taxonomy of `[{ term_id, name, slug }]`), `meta` (object of non-protected meta), `featured_image` (`{ id, url, alt }` or `null`), `permalink` (string), `edit_link` (string), `author` (`{ id, name }`).
- **FR-013**: `acrossai/get-post` `meta` field MUST use the same protected-key filter as FR-009 (respecting the `acrossai_allowed_protected_meta` filter).
- **FR-014**: `acrossai/read-file` MUST refuse to return content for any file whose absolute path matches a hardcoded read-protection list containing at minimum: `wp-config.php` at ABSPATH root and `.htaccess` at ABSPATH root. Refusal MUST use `blocked_reason: "protected_read"`.
- **FR-015**: `acrossai/read-file` MUST refuse to return content for any file whose byte size exceeds `MAX_READ_BYTES` (default 5 MB). Refusal MUST use `blocked_reason: "file_too_large"` and MUST report both the observed size and the cap.
- **FR-016**: `acrossai/read-file` MUST detect non-UTF-8 (binary) content and return a distinct shape carrying `binary: true` + `size` + `path` + `message` rather than the raw bytes.
- **FR-017**: `acrossai/delete-file` MUST require an explicit `confirm: true` input; refuse with `blocked_reason: "confirmation_required"` when absent or falsy.
- **FR-018**: `acrossai/delete-file` MUST refuse to delete any file matching the read-protection list from FR-014 (`wp-config.php`, `.htaccess`). Refusal MUST use `blocked_reason: "protected_write"`.
- **FR-019**: `acrossai/delete-file` MUST write a `.bak.<timestamp>` copy next to the target file before invoking the delete; the backup path MUST be returned in the response as `backup`.
- **FR-020**: `acrossai/delete-file` MUST call `opcache_invalidate()` on the deleted path (guarded by `function_exists()` — OPcache may be absent).
- **FR-021**: `acrossai/delete-post` response MUST include a `suggested_redirect` field of shape `{ from: string, to: string }` when the post being deleted is currently published AND `force: true`. Omitted otherwise.
- **FR-022**: Every response payload from a refused (guardrail-triggered) invocation MUST include `success: false`, a human-readable `message`, and a machine-readable `blocked_reason` string; no state mutation MUST occur on the refusal path.
- **FR-023**: Every ability listed above MUST continue to gate on `current_user_can( 'manage_options' )` via the identical permission callback the rest of the ability surface uses. No changes to the permission-callback shape.

### Key Entities *(include if feature involves data)*

- **Protected plugin list**: hardcoded array of WordPress plugin file paths (relative to `WP_PLUGIN_DIR`) that `acrossai/deactivate-plugin` refuses to deactivate. Not filterable in this feature — extensibility deferred.
- **Read-protection list / write-protection list**: hardcoded array of absolute file paths (all at `ABSPATH` root) that filesystem abilities refuse to read or modify. Same list is used for both refusal categories in this feature.
- **Protected-meta filter (`acrossai_allowed_protected_meta`)**: a WordPress filter returning an array of protected meta keys the ability layer should treat as writable. Default: empty. Applied at both `update-post` write time (FR-009) and `get-post` read time (FR-013).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: 100% of attempts to deactivate any of the three named AcrossAI plugins via `acrossai/deactivate-plugin` are refused without state mutation, regardless of input format (slug, file path, partial name).
- **SC-002**: 100% of attempts to hard-delete media or a file without `confirm: true` are refused.
- **SC-003**: 100% of attempts to read or delete `wp-config.php` and `.htaccess` are refused with the correct `blocked_reason`.
- **SC-004**: 100% of `acrossai/read-file` invocations on files larger than 5 MB refuse without loading the file into memory (verified by comparing peak-memory before and during the call).
- **SC-005**: 100% of `acrossai/update-post` invocations against `nav_menu_item` and `revision` post types are refused.
- **SC-006**: 100% of `acrossai/get-post` invocations return non-empty `terms` for posts that have taxonomies assigned, non-`null` `featured_image` for posts with a featured image, and a valid `permalink` string.
- **SC-007**: A `list-media` search that matches only alt-text returns the correct attachment on 100% of invocations against a controlled fixture.
- **SC-008**: A `update-media` invocation with three fields returns `updated: [three_field_names]` in insertion order on 100% of invocations.
- **SC-009**: All existing tests in the `composer run test` suite continue to pass; new tests added for every guardrail and every enrichment.
- **SC-010**: PHPCS + PHPStan level 8 stay clean on the branch.

## Assumptions

- The plugin's convention that every ability's permission callback is the literal `static function (): bool { return current_user_can( 'manage_options' ); }` remains in force. No changes to any permission callback in this feature.
- No changes to the plugin's overall admin UI. All improvements are behind-the-scenes at the ability layer.
- The `acrossai-pro` slug refers to the paid family plugin (`acrossai-pro/acrossai-pro.php`). If the actual file name differs on a given install, the protected-list guard falls through as if the plugin were not installed (which is correct behaviour — nothing to protect).
- `confirm: true` becoming a required input on `delete-media` and `delete-file` is a **breaking change** to any existing programmatic caller that omits the flag. Bounded blast radius (both are `manage_options`-gated); documented in the PR body and in the ability's `description` string.
- `acrossai/update-post` protected-meta filtering is a **soft-breaking change** for any caller that was writing `_`-prefixed meta through the ability. Documented; keys are named in the response's `dropped_meta_keys` field so the caller can adapt.
- `MAX_READ_BYTES = 5 * 1024 * 1024` (5 MB) is a reasonable default. Not configurable in this feature; a later feature could add a `acrossai_read_file_max_bytes` filter.
- No version bump in this PR. This feature ships in whatever the next release is (0.0.24 or later) — the release cut is out of scope for this spec.
