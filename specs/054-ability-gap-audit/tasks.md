---

description: "Task list for Feature 054 — Ability gap audit + 0.0.13 release housekeeping"
---

# Tasks: Feature 054

**Input**: Design documents from `/specs/054-ability-gap-audit/`
**Prerequisites**: [plan.md](./plan.md), [spec.md](./spec.md), [memory-synthesis.md](./memory-synthesis.md), [security-constraints.md](./security-constraints.md), [architecture-review.md](./architecture-review.md)
**Scope**: Audit-only PR. Phase 1 (branch + spec-kit artifacts) and Phase 2 (release housekeeping) complete in this PR. Phase 3 tasks (per-ability backlog) are seed rows for future one-spec-per-domain follow-up work — **do not implement in this PR**.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Parallelizable (different files, no dependencies)
- **[Story]**: US1 (maintainer sees gap) / US2 (contributor picks ability) / US3 (release housekeeping)
- Tasks marked ⏳ are backlog seeds for follow-up specs — one spec per domain group

---

## Phase 1 — Setup + spec-kit artifacts (this PR)

- [x] T001 [US1] Cut branch `054-ability-gap-audit` from `main`.
- [x] T002 [US1] Author `specs/054-ability-gap-audit/spec.md` with the frozen gap table (31 rows across 10 domains) + user stories + success criteria.
- [x] T003 [US1] Author `specs/054-ability-gap-audit/plan.md` — audit-only approach + verification steps + naming convention for future implementations.
- [x] T004 [US2] Author `specs/054-ability-gap-audit/tasks.md` — this file.
- [x] T005 [US1] [P] Author ancillary stubs: `memory-synthesis.md`, `security-constraints.md`, `architecture-review.md`, `checklists/.gitkeep`.

---

## Phase 2 — Release housekeeping (this PR)

- [x] T010 [US3] `README.txt:8` — bump `Stable tag: 0.0.12` → `Stable tag: 0.0.13`.
- [x] T011 [US3] `README.txt` Screenshots section — add entry `6. Settings — Display (abilities-per-page) and Upload Media Abilities (allowed-MIME list + Add file types).`
- [x] T012 [US3] `README.txt` Changelog — insert `= 0.0.13 =` block above `= 0.0.12 =` describing the audit + wp.org assets.
- [x] T013 [US3] `README.txt` Upgrade Notice — insert `= 0.0.13 =` block above `= 0.0.12 =` describing the doc + assets release as safe.
- [x] T014 [US3] `acrossai-abilities-manager.php:26` — bump `* Version: 0.0.12` → `* Version: 0.0.13`.
- [x] T015 [US3] `includes/Main.php:194` — bump `ACROSSAI_ABILITIES_MANAGER_VERSION` constant `'0.0.12'` → `'0.0.13'`.
- [x] T016 [US3] Stage 8 wp.org assets: `git add .wordpress-org/banner1544x500.png .wordpress-org/banner772x250.png .wordpress-org/screenshot-{1,2,3,4,5,6}.png`.
- [ ] T017 [US3] Verification — run all 8 Success Criteria checks from `spec.md` (SC-001 through SC-008); expect all green before push.
- [ ] T018 [US3] Commit + push branch; open PR against `main` with title `Ability gap audit — track missing abilities vs. external tool inventory (0.0.13)`.

---

## Phase 3 — Per-ability backlog seeds ⏳ (future one-spec-per-domain follow-ups; NOT this PR)

Each row below is one future task. Body includes: proposed slug, target file, closest existing class to model after, and input-schema sketch. Add a new `Category_Registrar.php` + entry in `AcrossAI_Core_Abilities_Bootstrap.php::register_category_callbacks()` for entire-domain-absent groups.

### Domain: Site editor / structure (4 items) — new future spec `NNN-site-editor-abilities`

- [ ] T101 ⏳ [US2] `acrossai-abilities-manager/site-editor-get-context` — target `includes/Abilities/Block/Site_Editor_Get_Context.php`; closest existing: `Block\Template_List`; input `{ scope?: 'templates'|'parts'|'styles' }`; output `{ active_template: string, active_parts: string[], active_style_variation: string, editor_url: string }`.
- [ ] T102 ⏳ [US2] `acrossai-abilities-manager/site-editor-refresh-context` — target `includes/Abilities/Block/Site_Editor_Refresh_Context.php`; closest existing: `Block\Template_List`; input `{}`; output `{ refreshed_at: int, invalidated: string[] }` — invalidates any editor-context transients.
- [ ] T103 ⏳ [US2] `acrossai-abilities-manager/site-structure-list-reusable-blocks` — target `includes/Abilities/Block/Reusable_Blocks_List.php`; closest existing: `Block\Pattern_List`; input `{ per_page?: int, page?: int }`; output `{ items: array<{ id, title, slug, updated_at }>, total }`. Query `post_type=wp_block`.
- [ ] T104 ⏳ [US2] `acrossai-abilities-manager/site-structure-list-block-areas` — target `includes/Abilities/Block/Block_Areas_List.php`; closest existing: `Block\Template_Part_List`; input `{}`; output `{ areas: array<{ area, label, template_parts: string[] }> }`. Reads block-template-part areas registered via theme.json.

### Domain: Admin menu (5 items — entire domain absent) — new future spec `NNN-admin-menu-abilities`

**Prerequisite**: add new `includes/Abilities/AdminMenu/Category_Registrar.php` and register in `AcrossAI_Core_Abilities_Bootstrap.php::register_category_callbacks()`. Category slug: `acrossai-abilities-manager-admin-menu`.

- [ ] T201 ⏳ [US2] `acrossai-abilities-manager/admin-menu-get-context` — target `includes/Abilities/AdminMenu/Admin_Menu_Get_Context.php`; closest existing: `none` (new domain); input `{}`; output `{ current_screen: string, current_page: string, current_submenu: string, breadcrumbs: string[], user_capabilities: string[] }`. Reads `get_current_screen()` + `$_GET['page']`.
- [ ] T202 ⏳ [US2] `acrossai-abilities-manager/admin-menu-refresh-context` — target `includes/Abilities/AdminMenu/Admin_Menu_Refresh_Context.php`; closest existing: `none`; input `{}`; output `{ refreshed_at: int, menu_cache_flushed: bool }`. Clears menu-related transients if any.
- [ ] T203 ⏳ [US2] `acrossai-abilities-manager/admin-menu-list-pages` — target `includes/Abilities/AdminMenu/Admin_Menu_List_Pages.php`; closest existing: `none`; input `{ include_hidden?: bool }`; output `{ items: array<{ slug, title, capability, url, parent?, submenu?: array<...> }> }`. Reads globals `$menu` / `$submenu`.
- [ ] T204 ⏳ [US2] `acrossai-abilities-manager/admin-menu-get-navigation-target` — target `includes/Abilities/AdminMenu/Admin_Menu_Get_Navigation_Target.php`; closest existing: `none`; input `{ intent: string }` (natural-language routing hint, e.g. `"settings > reading"`); output `{ resolved_slug: string, resolved_url: string, confidence: float }`.
- [ ] T205 ⏳ [US2] `acrossai-abilities-manager/admin-menu-list-settings` — target `includes/Abilities/AdminMenu/Admin_Menu_List_Settings.php`; closest existing: `Options\List_Options` (option-level, not settings-page-level); input `{ page?: string }`; output `{ items: array<{ page, section, field, option_name, label }> }`. Reads `$wp_settings_sections` + `$wp_settings_fields` globals.

### Domain: Navigation (2 items) — new future spec `NNN-navigation-abilities`

- [ ] T301 ⏳ [US2] `acrossai-abilities-manager/navigation-get-context` — target `includes/Abilities/Menus/Navigation_Get_Context.php`; closest existing: `Menus\List_Menus`; input `{}`; output `{ menus: array<{ id, name, location, item_count }>, active_locations: array<{ location, menu_id }> }`. Combines `wp_get_nav_menus()` + `get_nav_menu_locations()`.
- [ ] T302 ⏳ [US2] `acrossai-abilities-manager/navigation-list-locations` — target `includes/Abilities/Menus/Navigation_List_Locations.php`; closest existing: `none`; input `{}`; output `{ locations: array<{ slug, label, assigned_menu_id?: int, assigned_menu_name?: string }> }`. Reads `get_registered_nav_menus()` + `get_nav_menu_locations()`.

### Domain: Users (1 item) — future spec `NNN-users-current-access` (or fold into next Users bump)

- [ ] T401 ⏳ [US2] `acrossai-abilities-manager/users-current-access` — target `includes/Abilities/Users/Current_Access.php`; closest existing: `Users\Role_Capabilities` (role-level, not current-user-level); input `{}`; output `{ user_id: int, roles: string[], capabilities: array<string, bool>, is_super_admin: bool, is_network_admin: bool }`. Reads `wp_get_current_user()` + `WP_User::get_role_caps()`.

### Domain: Content index / search / linking (11 items — entire domain absent) — new future spec `NNN-content-search-abilities`

**Prerequisite**: add new `includes/Abilities/ContentSearch/Category_Registrar.php` + Bootstrap wiring. Category slug: `acrossai-abilities-manager-content-search`. Consider a supporting index table for search — this is a larger multi-part spec (11 abilities).

- [ ] T501 ⏳ [US2] `acrossai-abilities-manager/content-index-refresh-batch` — target `includes/Abilities/ContentSearch/Content_Index_Refresh_Batch.php`; closest existing: `none`; input `{ post_ids?: int[], since?: string }`; output `{ indexed: int, skipped: int, failed: int }`. Rebuilds a content-search index (implementation choice: WP native `s=` fallback vs. dedicated FT table — decide in follow-up spec).
- [ ] T502 ⏳ [US2] `acrossai-abilities-manager/content-search-items` — target `includes/Abilities/ContentSearch/Content_Search_Items.php`; closest existing: `Content\Get_Posts` (WP_Query fallback); input `{ query: string, per_page?: int, post_types?: string[] }`; output `{ items: array<{ id, title, url, excerpt, score }> }`.
- [ ] T503 ⏳ [US2] `acrossai-abilities-manager/content-search-chunks` — target `includes/Abilities/ContentSearch/Content_Search_Chunks.php`; closest existing: `none`; input `{ query: string, per_page?: int }`; output `{ chunks: array<{ post_id, chunk_index, text, offset, score }> }`. Requires chunking strategy.
- [ ] T504 ⏳ [US2] `acrossai-abilities-manager/content-find-related` — target `includes/Abilities/ContentSearch/Content_Find_Related.php`; closest existing: `none`; input `{ post_id: int, per_page?: int }`; output `{ related: array<{ id, title, url, reason }> }`.
- [ ] T505 ⏳ [US2] `acrossai-abilities-manager/content-find-internal-links` — target `includes/Abilities/ContentSearch/Content_Find_Internal_Links.php`; closest existing: `none`; input `{ post_id: int }`; output `{ links: array<{ target_id, target_url, anchor_text, block_id? }> }`. Parses post content for internal `<a href>`s that resolve to site URLs.
- [ ] T506 ⏳ [US2] `acrossai-abilities-manager/content-internal-link-policy` — target `includes/Abilities/ContentSearch/Internal_Link_Policy.php`; closest existing: `none`; input `{}`; output `{ policy_version: string, rules: array<{ rule, description, enabled }> }`. Reads an option-backed policy config.
- [ ] T507 ⏳ [US2] `acrossai-abilities-manager/content-internal-link-suggestions-create` — target `includes/Abilities/ContentSearch/Internal_Link_Suggestions_Create.php`; closest existing: `none`; input `{ post_id: int }`; output `{ suggestion_ids: int[], count: int }`. Persists suggestions to a custom table.
- [ ] T508 ⏳ [US2] `acrossai-abilities-manager/content-internal-link-suggestions-list` — target `includes/Abilities/ContentSearch/Internal_Link_Suggestions_List.php`; closest existing: `none`; input `{ post_id?: int, status?: 'pending'|'reviewed'|'applied' }`; output `{ items: array<...> }`.
- [ ] T509 ⏳ [US2] `acrossai-abilities-manager/content-internal-link-suggestion-review` — target `includes/Abilities/ContentSearch/Internal_Link_Suggestion_Review.php`; closest existing: `none`; input `{ suggestion_id: int, verdict: 'approved'|'rejected', notes?: string }`; output `{ updated: bool }`.
- [ ] T510 ⏳ [US2] `acrossai-abilities-manager/content-internal-link-suggestion-apply` — target `includes/Abilities/ContentSearch/Internal_Link_Suggestion_Apply.php`; closest existing: `Content\Update_Post`; input `{ suggestion_id: int }`; output `{ applied: bool, post_id: int, block_id?: string }`. Mutates post content to insert the approved link.
- [ ] T511 ⏳ [US2] `acrossai-abilities-manager/content-audit-internal-links` — target `includes/Abilities/ContentSearch/Content_Audit_Internal_Links.php`; closest existing: `none`; input `{ per_page?: int }`; output `{ broken: array<{ post_id, target_url, reason }>, ok: int, checked: int }`.

### Domain: Content advanced (2 items) — future spec `NNN-content-block-editing`

- [ ] T601 ⏳ [US2] `acrossai-abilities-manager/content-update-block` — target `includes/Abilities/Content/Content_Update_Block.php`; closest existing: `Content\Update_Post` (whole-post); input `{ post_id: int, block_id: string, attributes?: object, innerHTML?: string }`; output `{ updated: bool }`. Requires block traversal via `parse_blocks()` + `serialize_blocks()`.
- [ ] T602 ⏳ [US2] `acrossai-abilities-manager/content-autosaves-inspect` — target `includes/Abilities/Content/Content_Autosaves_Inspect.php`; closest existing: `Content\Get_Post_Revisions` (revisions, not autosaves); input `{ post_id: int }`; output `{ autosaves: array<{ id, author_id, modified, delta_summary }> }`. Reads `wp_get_post_autosave()`.

### Domain: Taxonomy (1 item)

- [ ] T701 ⏳ [US2] `acrossai-abilities-manager/taxonomy-set-term-image` — target `includes/Abilities/Taxonomies/Set_Term_Image.php`; closest existing: `Taxonomies\Update_Term`; input `{ term_id: int, attachment_id: int|null }`; output `{ updated: bool, term_id: int, attachment_id: int|null }`. Persists `_thumbnail_id` term-meta (matches WooCommerce convention).

### Domain: Media (1 item)

- [ ] T801 ⏳ [US2] `acrossai-abilities-manager/media-rename-file` — target `includes/Abilities/Media/Media_Rename_File.php`; closest existing: `Media\Update_Media`; input `{ attachment_id: int, new_filename: string }`; output `{ renamed: bool, old_path: string, new_path: string }`. Renames on disk via `WP_Filesystem`, updates `_wp_attached_file` meta + `guid`, and regenerates thumbnails via `wp_generate_attachment_metadata()`. Must reject filenames traversing outside the attachment's original directory.

### Domain: Site lifecycle (3 items) — future spec `NNN-site-lifecycle-abilities`

- [ ] T901 ⏳ [US2] `acrossai-abilities-manager/site-maintenance-report` — target `includes/Abilities/SiteHealth/Site_Maintenance_Report.php`; closest existing: `SiteHealth\Site_Health_Info` + `SiteHealth\Site_Health_Status`; input `{}`; output `{ generated_at: int, health_score: int, pending_updates: { core, plugins, themes }, disk_free_bytes: int, php_version: string, active_issues: string[] }`. Aggregates existing signals into one envelope.
- [ ] T902 ⏳ [US2] `acrossai-abilities-manager/plugin-lifecycle-get-plugin` — target `includes/Abilities/Plugins/Plugin_Lifecycle_Get_Plugin.php`; closest existing: `Plugins\Plugin_List`; input `{ plugin: string }` (basename); output `{ basename, name, version, activated_at?: int, deactivated_at?: int, updated_at?: int, update_available?: bool, autoupdate_enabled: bool }`. Requires an option-backed lifecycle-event log.
- [ ] T903 ⏳ [US2] `acrossai-abilities-manager/theme-lifecycle-get-theme` — target `includes/Abilities/Themes/Theme_Lifecycle_Get_Theme.php`; closest existing: `Themes\Theme_List` + `Themes\Theme_Structure_Read`; input `{ stylesheet: string }`; output `{ stylesheet, name, version, activated_at?: int, is_child?: bool, parent?: string, update_available?: bool }`. Same option-backed lifecycle log approach.

### Domain: Comments (1 item)

- [ ] T1001 ⏳ [US2] `acrossai-abilities-manager/comments-bulk-update` — target `includes/Abilities/Comments/Comments_Bulk_Update.php`; closest existing: `Comments\Update_Comment`; input `{ comment_ids: int[], updates: { status?: 'approve'|'unapprove'|'spam'|'trash', post_id?: int } }`; output `{ succeeded: int[], failed: array<{ id, message }> }`. Enforces `moderate_comments`; caps `comment_ids` length (e.g. 100 per call) to bound execution time.

---

## Task summary

- **Phase 1 (this PR)**: 5 tasks — spec-kit artifacts. All completed.
- **Phase 2 (this PR)**: 9 tasks — release housekeeping. Tasks T010–T016 completed by the current session; T017 (verification) and T018 (commit + PR) pending user approval.
- **Phase 3 (future)**: 31 backlog tasks ⏳ — grouped into 7 future specs (Site editor, Admin menu, Navigation, Users, Content search, Content advanced, Site lifecycle) + 3 single-ability follow-ups (Taxonomy, Media, Comments).
