# Memory Synthesis — Feature 042

**Feature**: Library empty-state refresh with Add-ons CTA
**Date**: 2026-07-07
**Query**: "library empty state addons page URL localized data enqueue"

## Retrieved memory entries (pre-implementation)

### Patterns referenced

- **`PATTERN-LIBRARY-ARGS-RAW-PASSTHROUGH`** (ARCHITECTURE.md, Feature 037 provenance) — Registry allowlists `args` keys but does not deep-sanitize passthrough values. **Not applicable** — Feature 042 touches only the display layer; no ability args flow through the empty-state path.

- **`AC-ENQUEUE-ADMIN`** (Feature 027 architecture constraint) — `wp_add_inline_script('acrossai-ability-library-js', 'window.acrossaiAbilityLibraryData = …', 'before')` is the sole injection point for the Library page's localized data blob. **Applied**: Feature 042 adds `addonsUrl` to the existing `wp_json_encode()` array in `admin/Main::enqueue_scripts()`. No second inline script tag; no second window global.

### Decisions referenced

- **`DEC-META-ACROSSAI-NAMESPACE`** (DECISIONS.md, Feature 041) — plugin-specific ability extension fields live under `$args['meta']['acrossai']`. **Not applicable** — the empty-state CTA is a page-level concern, not an ability-args concern.

### Architecture constraints referenced

- **`AC-HOOKS-MAIN`** — only root `Main.php` registers hooks via the Loader. Feature 042 does NOT register or remove any hook; root `Main.php` is untouched.
- **`AC-ENQUEUE-ADMIN`** (see above) — the single localized-data injection point.

### Bug patterns referenced

None applicable. Feature 042 does not touch data merging, override, or cast surfaces.

### Worklog milestones referenced

- **Feature 026** — established `acrossai-addons` as the Add-ons submenu slug via `acrossai-co/main-menu` vendor package. Feature 042 relies on that slug being stable.
- **Feature 027** — introduced the Library submenu and the `acrossaiAbilityLibraryData` localized blob (`AC-ENQUEUE-ADMIN`).
- **Feature 038** — main-menu integration; reaffirmed the `acrossai` parent slug and preserved the Add-ons submenu URL.

## Soft conflicts detected

None. Feature 042 is a display-only polish that reuses established conventions:
- Localized data blob → extends existing payload (no new global).
- Icons → reuses `@wordpress/icons` (already loaded via LibraryCard chevrons).
- SCSS → reuses existing token palette (`$border`, `$border-dk`, `$txt`, `$muted`, `$bg`) and BEM naming (`.acrossai-library-page__*`).
- Textdomain → reuses `'acrossai-abilities-manager'`.

## Applied to plan

- **PHP-side URL production** — `admin_url('admin.php?page=acrossai-addons')` in `admin/Main::enqueue_scripts()`. Matches how `restBase` is produced (`rest_url()` on the server-side, exposed via the localized blob). Keeps the slug knowable to server code.
- **Extend the existing localized blob** rather than introducing a second global. Matches `AC-ENQUEUE-ADMIN` intent.
- **Reuse `@wordpress/icons`** — already loaded (LibraryCard uses `chevronDown` / `chevronUp`); adding `plugins` and `external` adds no new bundle dependency.
- **BEM extension of `.acrossai-library-page__empty`** — the pre-042 stylesheet already had a `.acrossai-library-page__empty` selector (single colour rule). Feature 042 expands it into a five-element BEM block (`__empty-icon`, `__empty-title`, `__empty-description`, `__empty-actions`, `__empty-hint`).

## Post-implementation memory-hygiene actions

**None planned.** Feature 042 is a one-off UX polish that reuses established conventions. No new PATTERN, DEC, or WORKLOG entry is warranted — adding one would inflate memory for no future planning value.

If a second empty-state block is ever added to another admin page in this plugin, the recurring pattern could be captured then as `PATTERN-ADMIN-EMPTY-STATE` — but capturing it prematurely (n=1) violates the "capture on recurrence" memory-hygiene rule.

## Token savings vs full-memory read

Optimizer disabled (`.specify/extensions/memory-md/config.yml` absent). Markdown-only synthesis flow used. No token-report generated.
