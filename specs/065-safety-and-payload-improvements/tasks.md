---
description: "Implementation tasks for feature 065 — safety envelope + payload enrichment across 9 abilities"
---

# Tasks: Safety envelope + payload enrichment across 9 abilities

**Input**: Design docs from `specs/065-safety-and-payload-improvements/`
**Prerequisites**: [spec.md](./spec.md), [plan.md](./plan.md), [contracts/abilities.md](./contracts/abilities.md)
**Tests**: Included per constitution §VII.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no ordering dependency).
- **[Story]**: `US1..US9` per spec.md, or `QA`.

## Phase 1: Protected-plugin guard (US1, P1)

- [ ] **T010** [US1] Edit `includes/Abilities/Plugins/Deactivate_Plugin.php`. Add `private const PROTECTED_PLUGINS = ['acrossai-mcp-manager/acrossai-mcp-manager.php', 'acrossai-abilities-manager/acrossai-abilities-manager.php', 'acrossai-pro/acrossai-pro.php']`. In `execute()`, after `Plugin_Helpers::resolve_plugin()` returns the resolved file path, check `in_array($plugin_file, self::PROTECTED_PLUGINS, true)` and refuse with `blocked_reason: 'protected_plugin'` + message naming the plugin. Order: resolve first, then check protection, then check active state.

## Phase 2: Filesystem safety (US2 + US3 + US4)

- [ ] **T020** [US3] Edit `includes/Abilities/FileManager/Read_File.php`. Add `private const PROTECTED_FILES = ['wp-config.php', '.htaccess']` (paths relative to ABSPATH). Add `private const MAX_READ_BYTES = 5 * 1024 * 1024`. In `execute()`, after realpath resolution: (a) if the resolved path matches ABSPATH + protected filename, refuse with `blocked_reason: 'protected_read'`; (b) `filesize()` check against MAX_READ_BYTES, refuse with `blocked_reason: 'file_too_large'` + reported size/cap in message; (c) check `mb_check_encoding( $content, 'UTF-8' )` — if false, return `{ success: true, path, size, binary: true, message: '...' }` without `content`.
- [ ] **T021** [US2 + US3] Edit `includes/Abilities/FileManager/Delete_File.php`. Add same `PROTECTED_FILES` constant. In `execute()`, after realpath resolution but before delete: (a) require `confirm: true` in input, refuse with `blocked_reason: 'confirmation_required'` if absent/falsy; (b) if resolved path matches protected file, refuse with `blocked_reason: 'protected_write'`; (c) copy source to `$abs_path . '.bak.' . time()` before deleting — capture the backup path for the response; (d) after successful delete, call `opcache_invalidate($abs_path, true)` guarded by `function_exists('opcache_invalidate')`. Response includes `backup: <path>` field.

## Phase 3: Media enhancements (US2 + US6 + US7)

- [ ] **T030** [US2] Edit `includes/Abilities/Media/Delete_Media.php`. Input schema: add `confirm: boolean` (required) and `force: boolean` (optional, default false). Refuse without `confirm: true` (`blocked_reason: 'confirmation_required'`). In `execute()`: compute `$trashed = ! $force && defined('MEDIA_TRASH') && MEDIA_TRASH`; call `wp_delete_attachment($id, ! $trashed)`. Response `deleted` field: `"trashed"` or `"deleted"` string (change output_schema type from boolean to string).
- [ ] **T031** [US7] Edit `includes/Abilities/Media/Update_Media.php`. In `execute()`, track `$updated = []` as each field is written. Append `'title'`, `'caption'`, `'description'`, `'alt_text'` to `$updated` as they are processed. Include `updated: array<string>` in the response payload. Also add `updated` to `output_schema.properties`.
- [ ] **T032** [US6] Edit `includes/Abilities/Media/List_Media.php`. When `search` is non-empty: run TWO id-only queries — the current `s`-based query (title/caption/description) AND a `meta_query` id-only lookup against `_wp_attachment_image_alt` using `LIKE`. Union and de-duplicate the IDs. If both are empty, return the current no-results shape. Otherwise pass `post__in => $ids` into the main paginated `WP_Query` (drop the `s`). Keep the existing `mime_type`/`parent`/`page`/`per_page` args.

## Phase 4: Content-side enrichments and gates (US5 + US8 + US9)

- [ ] **T040** [US5] Edit `includes/Abilities/Content/Get_Post.php`. Preserve current inputs and refusal shape. In `execute()`, after fetching `$post`, build the enriched payload:
  - `terms`: iterate `get_object_taxonomies($post->post_type)`; for each taxonomy, `get_the_terms($post, $tax)` → map to `[{term_id, name, slug}]`.
  - `meta`: iterate `get_post_meta($id)`; skip keys starting with `_` OR where `is_protected_meta($key, 'post')` is true, UNLESS the key is in `apply_filters('acrossai_allowed_protected_meta', [])`. For each retained key, if the value array has one element unserialize it, else map `maybe_unserialize` over the array.
  - `featured_image`: `$thumb_id = get_post_thumbnail_id($post)`; if `>0`, `{id, url: wp_get_attachment_image_url($thumb_id, 'full'), alt: get_post_meta($thumb_id, '_wp_attachment_image_alt', true)}`; else `null`.
  - `permalink`: `get_permalink($post)`.
  - `edit_link`: `get_edit_post_link($post->ID, 'raw')`.
  - `author`: `$author_obj = get_userdata((int) $post->post_author)`; if valid, `{id, name: display_name}`; else `{id: 0, name: ""}`.
  Response payload adds these six fields alongside `post` (the raw `get_post(ARRAY_A)` — keep for backward compatibility). Update `output_schema` accordingly.
- [ ] **T041** [US8] Edit `includes/Abilities/Content/Update_Post.php`. In `execute()`, after resolving `$post`:
  - **Writable-post-type check.** `$pt_obj = get_post_type_object($post->post_type)`; refuse if `null` OR `! ($pt_obj->public || $pt_obj->show_in_rest)` with `blocked_reason: 'non_writable_post_type'`.
  - **Protected-meta filter.** If `$input['meta']` is a non-empty array, build `$allowed = (array) apply_filters('acrossai_allowed_protected_meta', [])`. For each key, if `str_starts_with($key, '_') || is_protected_meta($key, 'post')` AND NOT `in_array($key, $allowed, true)`, drop it. Track dropped keys as `$dropped_meta_keys` array for the response. Pass the filtered meta into `$args['meta_input']`.
  - **`publish_posts` cap check on status change.** If `$input['status']` is `publish` OR a status that `get_post_status_object($input['status'])?->public` is true, AND `! current_user_can($pt_obj->cap->publish_posts)`, refuse with `blocked_reason: 'publish_cap_required'`.
  - **`edit_others_posts` cap on author change.** If `$input['author']` is set AND `(int) $input['author'] !== (int) get_current_user_id()` AND `! current_user_can($pt_obj->cap->edit_others_posts)`, refuse with `blocked_reason: 'edit_others_posts_required'`.
  Response payload adds `dropped_meta_keys: array<string>` when non-empty; add to `output_schema`.
- [ ] **T042** [US9] Edit `includes/Abilities/Content/Delete_Post.php`. When the invocation succeeds AND `$force === true` AND the post's status before delete was `publish` (probe via snapshot before delete): compute `$permalink = get_permalink($post)`; compute a suggested target — if `$post->post_parent > 0` and the parent is published, use its permalink; else if the post type has an archive, `get_post_type_archive_link($post->post_type)`; else `home_url('/')`. Include `suggested_redirect: { from: $permalink, to: $target }` in the response. Add to `output_schema`.

## Phase 5: Tests

- [ ] **T050** [P] [QA] Create `tests/phpunit/abilities/Test_Feature_065_Safety_And_Payload.php` extending `WP_UnitTestCase`. Follow the plugin's established source-inspection pattern (matches `Test_Feature_042/043/057/059/062/063/064`). One test method per FR (23 methods). Each test asserts (a) the source contains the expected constant / method call / branch (source-inspection assertion), and (b) where possible without a real WP DB, calls the ability's `execute()` and asserts the response shape.
- [ ] **T051** [P] [QA] Add the new test file to `phpunit.xml.dist` under a new `<testsuite name="feature-065-unit">` entry.

## Phase 6: QA gates

- [ ] **T060** [QA] `composer run test` — every existing test still passes; every new test passes.
- [ ] **T061** [QA] `composer run phpcs` — zero errors, zero warnings.
- [ ] **T062** [QA] `composer run phpstan` at level 8 — zero errors.

## Non-goals

- No changes to `edit-file` naming/semantics — deferred to a follow-up spec (breaking change deserves its own decision).
- No version bump.
- No changes to admin UI.
- No new abilities.
- No changes to any of the other 240+ existing abilities.
- Extensibility of the protected-plugin list (via filter) is out of scope; hardcoded in this feature.
