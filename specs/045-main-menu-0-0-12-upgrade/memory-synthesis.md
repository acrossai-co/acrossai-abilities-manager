# Memory Synthesis — Feature 045

**Feature**: Upgrade `acrossai-co/main-menu` 0.0.11 → 0.0.12 (preserve Abilities Settings tab)
**Date**: 2026-07-08
**Query**: "main-menu composer bump SettingsPage tab_page_slug shared settings API jetpack-autoloader"

## Retrieved memory entries (pre-implementation)

### Patterns referenced

- **AC-ENQUEUE-ADMIN** (Feature 027) — not applicable. Feature 045 does not touch any JS bundle or its enqueue.
- **PATTERN-NAMED-EXPORT-JEST** (ARCHITECTURE.md) — not applicable. Pure-PHP change.
- **Feature 043's URL scheme** — not consumed by this feature. Different admin surface (`?page=acrossai-abilities-manager`, not `?page=acrossai-settings`).

### Decisions referenced

- **DEC-MENU-HOOK-SUFFIX** — hook suffix caching pattern for this plugin's own admin pages. Not applicable here — the Settings page's hook suffix is derived by WordPress from the parent menu title (`acrossai_page_acrossai-settings`) and does not depend on `main-menu`'s API changes.

### Architecture constraints referenced

- **AC-HOOKS-MAIN** — only root `includes/Main.php` registers hooks via the Loader. Feature 045 does NOT register or remove any hook. The `acrossai_settings_tabs` filter wiring at `includes/Main.php:309` and the `admin_init` wiring for `register_settings` at `includes/Main.php:310` are unchanged.

### Bug patterns referenced

None applicable.

### Worklog milestones referenced

- **Feature 026** — Add-ons page integration; established Add-ons submenu ownership by `main-menu`. Confirmed unchanged in 0.0.12 (Add-ons page is still shipped from `main-menu`).
- **Feature 027** — Ability Library submenu bootstrap and the `AC-ENQUEUE-ADMIN` constraint. Not affected — Library uses its own admin partial, not `main-menu`'s Settings surface.
- **Feature 038** — AcrossAI main-menu integration; established that `main-menu` owns the shared `acrossai` parent slug and the `acrossai-settings` submenu. Feature 045 reinforces this pattern: `main-menu` owns rendering; consumer plugins own their tabs and sections.

## Soft conflicts detected

**One cross-plugin coordination hazard** — not a memory conflict but worth logging here:

`jetpack-autoloader` picks the highest version of `acrossai-co/main-menu` across all active plugins. The sibling `acrossai-mcp-manager` still ships 0.0.11 in its own `vendor/` and still calls the removed static `SettingsPage::tab_page_slug()`. Once this plugin ships 0.0.12, the runtime picks 0.0.12 for BOTH plugins, and the sibling's Settings tab fatals until the sibling is migrated in the same way.

The user explicitly accepted this risk via AskUserQuestion. The PR body carries a **⚠ Blocking release note** paragraph so this fact is impossible to miss during merge/deploy review.

## Applied to plan

- **`SettingsPage::get_settings_renderer()` + `SettingsPageRenderer::tab_page_slug()`** — the new 0.0.12 consumer API. Adopted verbatim per the 0.0.12 README's own example at README.md:172-175.
- **Null-guard on the accessor return** — mirrors the 0.0.12 README example. Defensive against future boot-order changes even though our current bootstrap guarantees a non-null return by `admin_init`.
- **`acrossai_settings_tabs` filter — unchanged in 0.0.12** — our existing `register_tab()` callback and `TAB_SLUG` constant continue to work verbatim. No filter-signature migration needed.
- **`option_group` stays `acrossai-settings`** — per the 0.0.12 README's explicit note in the "Adding sections/fields to a tab" section. This is the shared option_group across all consumer plugins' tabs.
- **`TabbedPageRenderer` NOT consumed** — the new abstract base is the plumbing for adding *additional* tabbed admin pages, not for extending the existing Settings page. The Abilities tab lives on the existing page and does not require subclassing.
- **Docblock reference updated** — the docblock at `admin/Partials/SettingsMenu.php:97-107` mentions "acrossai-co/main-menu v0.0.4+" (stale). Bumped to `v0.0.12+` and the removed API name replaced.
- **Sibling coordination hazard documented in spec.md Assumptions** — future readers/reviewers see it without having to page through the plan.

## Post-implementation memory-hygiene actions

**None planned.** Feature 045 is a first-time bump of the `main-menu` package with a small breaking-change surface. Capturing a `PATTERN-MAIN-MENU-BUMP-CHECKLIST` at n=1 would violate the "capture on recurrence" rule.

**If a second breaking bump ships later** (e.g. 0.0.12 → 0.1.0 with another API change), capture the recurring convention then: bump `composer.json`, run `composer update --with-dependencies`, grep-scan for removed APIs, refactor call sites, update docblocks, verify tab URLs, and — critically — audit the sibling plugins for the same removed API before shipping.

## Token savings vs full-memory read

Optimizer disabled (`.specify/extensions/memory-md/config.yml` absent). Markdown-only synthesis flow used. No token-report generated.
