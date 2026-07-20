# Feature Specification: Ability gap audit — track missing abilities vs. external tool inventory (0.0.13)

**Feature Branch**: `054-ability-gap-audit`
**Created**: 2026-07-20
**Status**: Draft (audit-only; runtime unchanged)
**Input**: User description — external AI-tool inventories (working name: "acrossai ability set") list domains and abilities the `acrossai-abilities-manager` plugin is expected to expose as first-class WP Abilities API entries. Conversation-scoped filtering narrowed the list to 31 missing abilities across 10 domains. Ship the frozen gap table as a durable backlog artifact so future waves do not re-run discovery, and combine it with the release housekeeping (wp.org assets + version bump).

## Clarifications

### Session 2026-07-20

- Q: Should this PR also implement any of the missing abilities? → A: No. Audit-only. Each missing ability becomes its own follow-up spec later, one spec per domain.
- Q: What counts as "present" in the audit? → A: Only abilities registered via `wp_register_ability()` under the `acrossai-abilities-manager/` namespace inside `includes/Abilities/**` — MCP-adapter passthrough tools (`mcp-adapter-*`) are explicitly out of scope; they belong to a separate plugin.
- Q: How is the "closest existing ability" chosen for each gap item? → A: Closest by domain + verb shape (e.g. `taxonomy.set_term_image` → `update-term` because both mutate a term). Where no reasonable analogue exists, the entry is marked `none` and the domain is called out as "entire domain absent" when every requested item in that domain has no analogue.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Maintainer sees the gap without re-running discovery (Priority: P1)

A maintainer wants to know which abilities the plugin is expected to provide but does not yet expose. They open a single spec file and read the requested-vs-present matrix, grouped by domain, without re-running `grep` or cross-referencing external ability lists.

**Why this priority**: without a durable audit, this discovery repeats every time the topic comes up (weekly at minimum during the current build-out). The audit converts an ephemeral chat artifact into tracked backlog.

**Independent Test**: open `specs/054-ability-gap-audit/spec.md` end-to-end; every gap item names its closest existing ability (or is marked `none`). No prior context required.

**Acceptance Scenarios**:

1. **Given** a maintainer with no prior context on the external inventory, **When** they open `spec.md`, **Then** they can identify (a) every missing ability, (b) which domain it belongs to, and (c) the nearest existing ability in the plugin.
2. **Given** the audit says "31 missing abilities across 10 domains" and "187 registered abilities under `acrossai-abilities-manager/*`", **When** the maintainer runs the verification grep from the plan, **Then** the numbers match reality — if they drift, that's a spec-update task, not a "the audit lied" bug.

---

### User Story 2 — Contributor picks a missing ability and can draft its per-ability spec (Priority: P2)

A contributor picks any single row from the gap table and wants enough context to open a follow-up spec (e.g. `specs/055-content-search-abilities/`) without re-doing the "does this already exist?" work.

**Why this priority**: the audit's value compounds when it seeds future waves — if each row includes the closest existing ability and its file path, the follow-up spec starts from a copy-modify baseline instead of a blank page.

**Independent Test**: pick any single gap row (e.g. `media.rename_file`); the task in `tasks.md` names the proposed slug, the input/output sketch, the category folder, and the closest existing ability class to model after.

**Acceptance Scenarios**:

1. **Given** the `taxonomy.set_term_image` gap row, **When** a contributor opens the matching task in `tasks.md`, **Then** they see: proposed slug `acrossai-abilities-manager/taxonomy-set-term-image`, target file `includes/Abilities/Taxonomies/Set_Term_Image.php`, closest existing class `Taxonomies\Update_Term` at `includes/Abilities/Taxonomies/Update_Term.php`, and the input schema sketch `{ term_id: int, attachment_id: int }`.
2. **Given** the `plugin_lifecycle.get_plugin` gap row (no exact analogue), **When** the contributor opens its task, **Then** they see that `plugin-list` returns the full inventory and their new ability should return the per-plugin subset — not a redesign of `plugin-list`.

---

### User Story 3 — Release housekeeping lands alongside the audit (Priority: P3)

The `0.0.12 → 0.0.13` version bump, changelog + upgrade-notice blocks, wp.org banner + 6th screenshot, and `Version:` / `Stable tag:` / `ACROSSAI_ABILITIES_MANAGER_VERSION` constants all update in the same PR so the WordPress.org plugin listing stays coherent.

**Why this priority**: the wp.org assets have been sitting untracked since before this branch; folding them into the audit PR avoids a trivial "wp.org assets only" PR immediately after.

**Independent Test**: `git status` on `main` before this PR shows the six PNGs as untracked; after merge, `git ls-files .wordpress-org/*.png` returns all six.

**Acceptance Scenarios**:

1. **Given** the PR merged, **When** WordPress.org syncs from the `.wordpress-org/` folder, **Then** the plugin directory listing shows the 1544×500 header banner and a 6th screenshot (Settings — Display + Upload Media Abilities).
2. **Given** a site running 0.0.12 auto-updates to 0.0.13, **When** the admin reads the Upgrade Notice, **Then** the notice reads "Docs + wp.org assets only. … No functional changes; safe upgrade." — no user-visible behavior change.

### Edge Cases

- **Ability inventory drift between spec authoring and PR merge**: if a new ability lands on `main` before this PR merges, the "187 registered abilities" number in `spec.md` must be updated. The verification grep in `plan.md` is the source of truth.
- **Orphan ability class not wired in Bootstrap**: verification step re-confirms every ability class file under `includes/Abilities/*/` is instantiated in `AcrossAI_Core_Abilities_Bootstrap.php`. Any orphan is a **bug** to fix, not a gap item.
- **Ambiguous "closest existing" mapping**: some gap items span two categories (e.g. `admin_menu.get_context` touches Settings, Content, and Users). The audit picks one closest analogue per item — the follow-up spec can revise.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Ship `specs/054-ability-gap-audit/` with `spec.md`, `plan.md`, `tasks.md`, and the standard ancillary files (`memory-synthesis.md`, `security-constraints.md`, `architecture-review.md`, `checklists/`).
- **FR-002**: `spec.md` MUST contain the frozen gap table (below) grouped by domain in the order: Site editor / structure, Admin menu, Navigation, Users, Content index / search / linking, Content advanced, Taxonomy, Media, Site lifecycle, Comments.
- **FR-003**: Every gap row MUST name either the closest existing ability slug in the plugin or the literal string `none` (when no reasonable analogue exists).
- **FR-004**: `README.txt` MUST bump `Stable tag: 0.0.12 → 0.0.13`, add a `= 0.0.13 =` Changelog block above `= 0.0.12 =`, add a `= 0.0.13 =` Upgrade Notice block above `= 0.0.12 =`, and add a Screenshot #6 entry.
- **FR-005**: `acrossai-abilities-manager.php` MUST bump `Version: 0.0.12 → 0.0.13`.
- **FR-006**: `includes/Main.php` MUST bump `ACROSSAI_ABILITIES_MANAGER_VERSION` constant from `'0.0.12'` to `'0.0.13'`.
- **FR-007**: `.wordpress-org/banner1544x500.png`, `.wordpress-org/banner772x250.png`, `.wordpress-org/screenshot-1.png` … `.wordpress-org/screenshot-6.png` MUST be tracked (currently untracked per initial `git status`).
- **FR-008**: No PHP source file under `includes/Abilities/**` is added, modified, or deleted by this PR. No new abilities are registered.
- **FR-009**: The audit numbers cited in `spec.md` MUST be verified by grep before commit: 187 registered abilities under `acrossai-abilities-manager/*`, 31 missing abilities across 10 domains.

### Key Entities

- **Gap row**: one line in the audit table. Fields: `requested_name` (external inventory's dotted name, e.g. `media.rename_file`), `closest_existing` (plugin slug, e.g. `update-media`, or `none`), `notes` (optional single-clause justification when the mapping is non-obvious).
- **Domain group**: a heading grouping related gap rows (e.g. "Media", "Site lifecycle"). Domains flagged "entire domain absent" have zero rows with `closest_existing != none`.
- **Registered ability**: any invocation of `wp_register_ability( 'acrossai-abilities-manager/<slug>', … )` inside `includes/Abilities/**`. This is the "present" set the audit compares against.

## Frozen gap table (audit source of truth)

Format: **requested name — closest existing plugin ability, or `none`**.

**Site editor / structure** — 4 items, mixed
- `site_editor.get_context` — `none`
- `site_editor.refresh_context` — `none`
- `site_structure.list_reusable_blocks` — `block-pattern-list`
- `site_structure.list_block_areas` — `template-part-list`

**Admin menu** — 5 items, entire domain absent
- `admin_menu.get_context` — `none`
- `admin_menu.refresh_context` — `none`
- `admin_menu.list_pages` — `none`
- `admin_menu.get_navigation_target` — `none`
- `admin_menu.list_settings` — `none`

**Navigation** — 2 items, mostly absent
- `navigation.get_context` — `list-menus` (close but not a "context" object)
- `navigation.list_locations` — `none`

**Users** — 1 item
- `users.current_access` — `user-role-capabilities` (introspects capabilities but not a scoped "what can *this* current user do" object)

**Content index / search / linking** — 11 items, entire domain absent
- `content_index.refresh_batch` — `none`
- `content_search.items` — `none`
- `content_search.chunks` — `none`
- `content_find.related` — `none`
- `content_find.internal_links` — `none`
- `content_internal_link.policy` — `none`
- `content_internal_link.suggestions_create` — `none`
- `content_internal_link.suggestions_list` — `none`
- `content_internal_link.suggestion_review` — `none`
- `content_internal_link.suggestion_apply` — `none`
- `content_audit.internal_links` — `none`

**Content advanced** — 2 items
- `content.update_block` — `update-post` (whole-post only; no block-level surgical update)
- `content_autosaves.inspect` — `none`

**Taxonomy** — 1 item
- `taxonomy.set_term_image` — `update-term` (updates term fields; no `_thumbnail_id`-meta helper)

**Media** — 1 item
- `media.rename_file` — `update-media` (updates attachment post row; does not rename the on-disk file)

**Site lifecycle** — 3 items
- `site.maintenance_report` — `site-health-info` + `site-health-status` (raw signals; no synthesized report envelope)
- `plugin_lifecycle.get_plugin` — `plugin-list` (list-only; no per-plugin lifecycle-context object)
- `theme_lifecycle.get_theme` — `theme-list` + `theme-structure-read` (list + inspect; no synthesized per-theme lifecycle object)

**Comments** — 1 item
- `comments.bulk_update` — `update-comment` (per-comment only; no batched envelope)

**Domain absence summary** — 2 of 10 domains are entirely absent (Admin menu, Content index / search / linking); the other 8 have partial coverage where a related-but-not-equivalent ability already exists.

## Success Criteria

- **SC-001**: Grep count `grep -rhE "'name'\s*=>\s*'acrossai-abilities-manager/" includes/Abilities/ | grep -oE "acrossai-abilities-manager/[a-z0-9_/-]+" | sort -u | wc -l` returns `187`.
- **SC-002**: `find includes/Abilities/ -name '*.php' -exec grep -l 'extends Ability_Definition' {} \; | wc -l` returns `187` (one class file per registered ability, no duplicates, no orphans).
- **SC-003**: `grep -oE "new [A-Za-z]+\\\\[A-Z][A-Za-z_]+\(\)" includes/Abilities/AcrossAI_Core_Abilities_Bootstrap.php | sort -u | wc -l` returns `187` (Bootstrap wiring is 1:1 with ability classes).
- **SC-004**: `README.txt` renders cleanly against the [WordPress.org readme validator](https://wordpress.org/plugins/developers/readme-validator/) — no warnings.
- **SC-005**: `README.txt` `Stable tag`, `acrossai-abilities-manager.php` `Version:` header, `includes/Main.php` `ACROSSAI_ABILITIES_MANAGER_VERSION` constant, and the topmost Changelog + Upgrade Notice entries all read `0.0.13`.
- **SC-006**: `git ls-files .wordpress-org/*.png | wc -l` returns `8` (two banners + six screenshots).
- **SC-007**: `composer run phpstan` (if defined) and `composer run phpcs` (if defined) both green — a doc-only PR must not regress static-analysis budgets.
- **SC-008**: No file under `includes/Abilities/**`, `src/`, `admin/`, `public/`, `build/`, `vendor/`, `node_modules/`, `tests/` is added, modified, or deleted (excluding `includes/Main.php` version constant + `acrossai-abilities-manager.php` header).
