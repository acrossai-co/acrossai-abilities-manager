# Security Constraints — Feature 054

**Status**: Audit-only feature — no new security surface introduced in this PR.

## Runtime surface delta (this PR)

- **New REST routes**: none.
- **New capability boundaries**: none.
- **New nonces**: none.
- **New user input surfaces**: none.
- **New external HTTP calls**: none.
- **New database reads/writes**: none.
- **New filesystem reads/writes**: none.

The only code edits are two single-line version-string flips (`acrossai-abilities-manager.php:26` header + `includes/Main.php:194` constant). Neither touches any input, output, or capability check.

## Documentation-only additions

Files added under `specs/054-ability-gap-audit/` are markdown; not loaded at runtime; no security implications.

`.wordpress-org/` PNGs are static assets served only by the WordPress.org plugin directory; not loaded by the plugin at runtime.

## Constraints on future per-domain implementation waves

Every future ability implementation seeded by Phase 3 of `tasks.md` MUST satisfy the plugin's baseline security constraints (Constitution §IV):

- **`permission_callback`**: minimum `manage_options`. Mutating abilities also require the operation-specific capability (`edit_posts`, `moderate_comments`, `manage_categories`, `upload_files`, `update_plugins`, `update_themes`, `update_core`, etc.).
- **`DISALLOW_FILE_MODS`**: honored by any ability that writes files (via `File_Mods_Guard`).
- **Multisite**: any ability touching network-level state must add a multisite guard following the pattern in `Core\Wp_Core_Update`.
- **Input schema**: every ability MUST declare `input_schema` with typed properties + `additionalProperties: false`.
- **Path traversal**: any ability accepting a file path MUST resolve via `realpath()` and enforce the resolved path stays under a known base directory (see `Media\Upload_Media::do_execute` line 232–240 for the reference pattern).
- **SSRF**: any ability accepting a URL input MUST validate via `wp_http_validate_url()` and use a hardcoded or tightly-scoped host allowlist (see `Core\Wp_Core_Rollback` for the WP.org-only reference pattern).

## Specific security notes for high-risk future abilities (from `tasks.md`)

- **T801 `media-rename-file`**: rename on disk. Must reject any `new_filename` containing directory separators, null bytes, or a leading `.`. Must confirm the resolved target stays inside the attachment's original upload subdirectory. Must NOT allow renaming into a path that would shadow an existing file. Reference: `Media\Upload_Media::do_execute` line 232–240 for realpath-based containment.
- **T507/T508/T509/T510 `content-internal-link-suggestion-*`**: suggestion queue lives in a custom table. Enforce `manage_options` for policy reads/writes and `edit_others_posts` for apply. Apply must re-parse blocks server-side and reject any suggestion whose `target_url` no longer resolves within-site.
- **T1001 `comments-bulk-update`**: cap `comment_ids` length (e.g. ≤100 per call) to bound execution time. Enforce `moderate_comments`; do not accept `moderate_comments` on individual comment authors' own comments if the current user is not admin.
- **T201–T205 Admin menu abilities**: reading `$menu`/`$submenu` globals leaks the current user's capability profile. Must gate to `manage_options` and NOT accept a `user_id` override in input schemas.

## Sign-off

No security review required for this PR beyond the standard PR review (no runtime code changed). Every future spec seeded from Phase 3 requires its own `security-constraints.md` covering its specific attack surface.
