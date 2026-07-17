# Memory Synthesis

## Current Scope

Feature 053 bumps `acrossai-co/main-menu 0.0.14 → 0.0.23` (through three iterations: 0.0.21, 0.0.22, 0.0.23), removes the Freemius integration surface entirely (the `\AcrossAI_Addon\AddonsPage` class is gone from `main-menu 0.0.21+`), restyles the Library page header so title + `Enable All` / `Disable All` buttons render on a single horizontal row, and adds a filter callback that removes the plugin's own entry from the shared `acrossai_addons` list. Touches: `composer.json`, `composer.lock`, `includes/Main.php` (deleted AddonsPage block; wired new filter), `admin/Main.php` (new `filter_out_self_from_addons` method), `admin/Partials/LibraryMenu.php` (removed server-rendered H1), `src/js/ability-library/components/LibraryPage.js` (added H1 + `__header-actions` wrapper), `src/scss/ability-library/admin.scss` (flipped to `space-between`), `README.txt` (4 non-historical sections rewritten), plus regenerated `build/` and `vendor/` trees. No version bump, no new REST routes, no new capability boundaries.

## Relevant Decisions

- **DEC-EXTERNAL-PACKAGE-HOOK-CTOR-SHARED-MENU** (Reason: the plugin still bootstraps `\AcrossAI_Main_Menu\SettingsPage` from `acrossai-abilities-manager.php` at `plugins_loaded @ P0` — that path is unchanged and continues to be an accepted deviation from Boot Flow Rule for external shared-menu packages. Status: Active, Source: DECISIONS.md)
- **DEC-STABLE-UPGRADE-WINDOW-INTERNAL-ORG** (Reason: `acrossai-co` is an AcrossAI-org internal package — exempt from the "wait for v1.0.0" rule per this decision. Feature 053 bumps three times (0.0.21 → 0.0.22 → 0.0.23) which is well within the exception. SHA-pinned in `composer.lock` after each update. Status: Active, Source: DECISIONS.md)
- **DEC-FREEMIUS-PER-PLUGIN-INIT** (Reason: **Becomes SUPERSEDED by Feature 053**. The decision codified `fs_dynamic_init()` keying per product_id with never-hardcoded credentials; Feature 053 removes the Freemius integration entirely from this plugin. To be marked Superseded (Feature 053) in DECISIONS.md via the post-implementation memory-md-capture step. Status: Active → Superseded, Source: DECISIONS.md)
- **DEC-DESIGN-OVERRIDES-DATAVIEWS** (Reason: Library page continues to use `<Button>` + `<h1>` primitives; the header-row restructure does not introduce a new form or list surface. Pre-approved deviation unchanged. Status: Active, Source: DECISIONS.md)
- **DEC-NODE-20-BUILD-REQUIRED** (Reason: Bundle regeneration during CHANGE-B (header-row layout) ran on Node 22.22.0, well above the ≥ 20 floor. Status: Active, Source: DECISIONS.md)

## Active Architecture Constraints

- **AC-HOOKS-MAIN** (Reason: New `acrossai_addons` filter registered via `$this->loader->add_filter(...)` in `includes/Main.php::define_admin_hooks()` on the existing `$plugin_admin` named-variable — variable-first per Boot Flow Rule. Source: CONSTITUTION.md §I)
- **AC-ENQUEUE-ADMIN** (Reason: No new localized data keys added; the existing `bulkToggleState` from Feature 052 stays inside the same `wp_add_inline_script('before')` block. Source: CONSTITUTION.md §I)
- **AC-FILE-HEADER-PATTERN** (Reason: No new files created; header pattern preserved on all modified files. Source: ARCHITECTURE.md)
- **PATTERN-ADMIN-NOTICE-SELF-CONTAINED** (Reason: The Feature-038 entry-file bootstrap for `SettingsPage` continues to use this pattern. Feature 053 does NOT re-introduce a try/catch admin_notices fallback because the `\AcrossAI_Addon\AddonsPage` block that had one is entirely deleted (the class no longer exists). Source: ARCHITECTURE.md)
- **PATTERN-SHARED-MENU-CONSUMER-IDEMPOTENCY** (Reason: The entry-file `did_action('acrossai_main_menu_bootstrapped')` guard remains — every consuming plugin's copy of `SettingsPage` continues to be idempotent. Feature 053 does not touch this. Source: ARCHITECTURE.md)

## Accepted Deviations

- **DEC-DESIGN-OVERRIDES-DATAVIEWS** (Reason: Library page's use of `<Button>` primitives predates this feature and remains pre-approved. Status: Accepted-Deviation, Source: DECISIONS.md)
- **DEC-EXTERNAL-PACKAGE-HOOK-CTOR-SHARED-MENU** (Reason: `SettingsPage` bootstrap still happens in the plugin entry file rather than via the Loader in `includes/Main.php`. Feature 053 does not touch this bootstrap. Status: Accepted-Deviation, Source: DECISIONS.md)

## Relevant Security Constraints

- Existing baseline holds: the Add-ons page is rendered by `\AcrossAI_Main_Menu\AddonsPageRenderer` which the plugin does not re-render or wrap. No new authentication / authorization surface. Free add-on installs use the WordPress core plugin installer which enforces the `install_plugins` capability. Per the standing user rule (`Skip permission_callback REST audit`), no permission_callback re-audit is included.
- **Freemius removal REDUCES external service surface**: the plugin previously depended on `freemius/wordpress-sdk` which — when connected — could send site metadata to `freemius.com`. Post Feature 053 the plugin makes zero external HTTP requests on its own.

## Related Historical Lessons

- **BUG-CLASS-EXISTS-AUTOLOAD-FALSE-SILENT** (Reason: Feature 053 removes rather than adds a `class_exists()` guard. The old `if ( class_exists( \AcrossAI_Addon\AddonsPage::class ) )` guard is gone. Not adding new `class_exists()` calls, so this bug pattern is not exercised by this feature. Source: BUGS.md)
- **BUG-WP-LOCALIZE-SCRIPT-RENDER** (Reason: Feature 053 does not add new localization. The existing Feature 052 `bulkToggleState` localization is unchanged. Source: BUGS.md)
- **BUG-JEST-MOCK-LIST-STALENESS** (Reason: `LibraryPage.js` gains an H1 element (added inside existing JSX; requires no mock) and no new `@wordpress/*` imports. Existing Jest mock allowlists (updated in Feature 052 for `Button` + `useLibraryTabSync`) require no further changes. Verified: 82/82 Jest tests pass post-implementation. Source: BUGS.md)

## Conflict Warnings

None. All Feature 053 changes are compatible with active decisions and constraints. `DEC-FREEMIUS-PER-PLUGIN-INIT` becomes superseded — this is a state transition, not a conflict.

## Retrieval Notes

- Optimizer not enabled — markdown-only, index-first retrieval used. Read `docs/memory/INDEX.md` (260 lines) once at planning time; consulted specific `DECISIONS.md` and `ARCHITECTURE.md` entries for the shared-menu bootstrap, `acrossai_addons` filter contract, and Freemius per-plugin-init history.
- Index entries considered: ~15 (out of ~140). Selected: 5 decisions (2 pre-approved deviations + 1 to-be-superseded + 2 governance), 5 architecture constraints, 3 historical bug patterns.
- Skipped entirely: BerlinDB, Abilities REST, Access Control, Merger, MCP, Freemius per-plugin-init details (about to be superseded), all Library-page decisions beyond DESIGN-OVERRIDES-DATAVIEWS.
- Full durable-memory reads: NOT performed. Budget status: within limits (~600 words, under the 900-word cap).
