# Implementation Plan: Safety envelope + payload enrichment across 9 existing abilities

**Branch**: `065-safety-and-payload-improvements` | **Date**: 2026-08-12 | **Spec**: [spec.md](./spec.md)

## Summary

Modify **9 existing ability classes** to add safety guardrails, payload enrichment, and one protected-plugin guard. No new abilities. No new categories. No new modules. No bootstrap changes. No version bump.

- **`Plugins/Deactivate_Plugin.php`** — hardcoded protected-plugin list (3 entries).
- **`Media/Delete_Media.php`** — `confirm: true` required + `MEDIA_TRASH` respect + `deleted: "deleted"|"trashed"` in response.
- **`Media/Update_Media.php`** — `updated[]` field in response.
- **`Media/List_Media.php`** — expand `s` search to include alt-text via union id-lookup.
- **`Content/Get_Post.php`** — enrich response with terms/meta/featured_image/permalink/edit_link/author.
- **`Content/Update_Post.php`** — writable-post-type gate + protected-meta filter + `publish_posts` cap on status change + `edit_others_posts` on author change.
- **`Content/Delete_Post.php`** — `suggested_redirect` field on published-post force delete.
- **`FileManager/Read_File.php`** — 5 MB size cap + binary detection + read-protection list.
- **`FileManager/Delete_File.php`** — `confirm: true` required + backup + protected-file list + OPcache invalidation.

## Technical Context

**Language/Version**: PHP 8.1+ (unchanged).
**Primary Dependencies**: WordPress 6.9+. No new Composer or npm packages.
**Storage**: None new. All operations continue against existing WordPress-managed tables and the filesystem.
**Testing**: PHPUnit 10.5. Follow the plugin's established source-inspection test pattern (`Test_Feature_<NNN>_*.php` under `tests/phpunit/abilities/`).
**Target Platform**: WordPress 6.9+ on PHP 8.1–8.5 (existing CI matrix).
**Project Type**: WordPress plugin — single project.
**Performance Goals**: No regression on golden-path invocations. Safety-refusal paths return in <10 ms (no I/O).
**Constraints**: Every ability continues to use the LITERAL `permission_callback` verbatim; NO changes to permission callbacks. Every modification is additive to `execute()` — no removal of existing behaviour on the accept path.
**Scale/Scope**: 9 files modified in `includes/Abilities/`. 1 new tests file (`Test_Feature_065_Safety_And_Payload.php`) covering every new guardrail and every new payload field. Zero bootstrap changes.

## Constitution Check

*GATE: Must pass before implementation.*

### I. Modular Architecture — PASS

Each ability is a self-contained class. No cross-module dependencies added. Any hardcoded lists (protected-plugin, protected-file, MAX_READ_BYTES) are per-class `const` fields, not extracted to shared utilities — matches the plugin's stated "three similar lines is better than a premature abstraction" preference.

### II. WordPress Standards Compliance — PASS

- PHPCS: no new files, only additive edits to existing WPCS-compliant files.
- PHPStan level 8: every WP core function used is stubbed (`get_plugins`, `is_plugin_active`, `is_protected_meta`, `wp_get_attachment_metadata`, `get_object_taxonomies`, `get_the_terms`, `get_permalink`, `get_edit_post_link`, `get_userdata`, `wp_delete_attachment`, `opcache_invalidate`, `wp_check_filetype`, etc.).
- Plugin Check: no `eval`/`extract`/shell exec. `opcache_invalidate` is a PHP core function, `function_exists()`-guarded.
- Multisite: reads/writes target the currently-active site (unchanged behaviour).

### III. User-Centric Design — PASS (N/A, no admin UI)

No admin UI changes. Every improvement is at the ability execute-callback layer.

### IV. Security First — PASS

- Sanitization: all new string inputs (`confirm`, `force`, etc.) processed via `(bool)` / `absint()` cast or `sanitize_text_field()` where string.
- Capability: `manage_options` remains the gate. NEW: `update-post` adds per-post-type `publish_posts` and `edit_others_posts` checks; both are `current_user_can()` calls — never a `WP_REST_Response`.
- Prepared queries: `list-media` alt-text union query uses `$wpdb->prepare()` with `%s` for the search token.
- Blocked lists: protected-plugin, protected-file (`wp-config.php`, `.htaccess`), protected-meta filter all hardcoded per FRs.

### V. Extensibility Without Core Modification — PASS

Modifications are inside the existing ability classes. The `acrossai_allowed_protected_meta` filter is added as the extensibility surface for protected-meta writing. No changes to any other class.

### VI. Reusability & DRY Principle — PASS

Reuses:
- `Media_Formatter::to_array()` for the enriched `get-post` featured_image lookup.
- `Plugin_Helpers::resolve_plugin()` for the protected-plugin guard.
- `File_Mods_Guard::blocked_response()` for the existing filesystem gate (unchanged).
- `wp_get_attachment_image_src()` for featured-image URL resolution.

The protected-file list is duplicated across `Read_File` and `Delete_File` as a `const` on each class rather than extracted to a shared utility — same three-line-preference reasoning as elsewhere.

### VII. Definition of Done — PLANNED

Every gate: PHPCS zero, PHPStan level 8 zero, PHPUnit passes. ESLint N/A (no JS).

**Overall gate: PASS. No complexity-tracking entries required.**

## Project Structure

### Documentation

```text
specs/065-safety-and-payload-improvements/
├── plan.md              # This file
├── spec.md              # Written by /speckit-specify equivalent
├── tasks.md             # Below
├── contracts/
│   └── abilities.md     # Contracts for the modified abilities (below)
└── checklists/
    └── requirements.md  # Below
```

### Source Code (modified files)

```text
includes/Abilities/
├── Plugins/
│   └── Deactivate_Plugin.php         # MODIFIED — protected-plugin list
├── Media/
│   ├── Delete_Media.php              # MODIFIED — confirm + MEDIA_TRASH
│   ├── Update_Media.php              # MODIFIED — updated[] field
│   └── List_Media.php                # MODIFIED — alt-text search
├── Content/
│   ├── Get_Post.php                  # MODIFIED — enriched payload
│   ├── Update_Post.php               # MODIFIED — writable-type + meta filter + caps
│   └── Delete_Post.php               # MODIFIED — suggested_redirect
├── FileManager/
│   ├── Read_File.php                 # MODIFIED — size cap + binary + protection list
│   └── Delete_File.php               # MODIFIED — confirm + backup + protection + OPcache

tests/phpunit/abilities/
└── Test_Feature_065_Safety_And_Payload.php    # NEW — one test class per FR

specs/065-safety-and-payload-improvements/
└── (docs above)
```

**Structure Decision**: Purely additive edits to 9 existing files. No new directories, no new abilities, no bootstrap changes.

## Complexity Tracking

*No entries — Constitution Check passes with no justifications required.*
