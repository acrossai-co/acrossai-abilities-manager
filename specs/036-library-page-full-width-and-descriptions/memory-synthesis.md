# Memory Synthesis

## Current Scope

Feature 036 surfaces `args.description` from each registered ability on the
existing Library admin page (`wp-admin/admin.php?page=acrossai-abilities-library`)
and removes the 900px page cap so the page uses the WordPress admin content
width. Touched layers: React-only (`src/js/ability-library/components/LibraryPage.js`,
`LibraryCard.js`) + a single SCSS file (`src/scss/ability-library/admin.scss`).
**No PHP**, **no REST contract change**, **no saved-config schema change**, **no
DB migration**. The Registry already passes `args.description` on the wire; the
grouping helper drops it before reaching the card.

## Relevant Decisions

- **DEC-LIBRARY-CATEGORY-SLUG-REBRAND** (Reason Included: defines Library data shape we consume; mandates that `sub_keys` on-disk key is preserved across changes — directly applies to FR-010 in the spec; Status: Active; Source: DECISIONS.md)
- **DEC-DESCRIPTION-VALIDATION-PATTERN** (Reason Included: bounds the description field at 1000 chars on the *write* side, so the render path can assume a single-paragraph plain-text payload; no need to design for novel-length values; Status: Active; Source: DECISIONS.md)
- **DEC-NODE-20-BUILD-REQUIRED** (Reason Included: `npm run build` must run under Node ≥20 to refresh the React bundle that ships these changes — implementation pre-flight, not a code constraint; Status: Active; Source: DECISIONS.md)

## Active Architecture Constraints

- **PATTERN-NAMED-EXPORT-JEST** (Reason Included: `LibraryCard.js` already exports `groupBySubGroupPreservingOrder` as a named export for Jest. If Feature 036 adds a unit test asserting `groupDefinitions()` passes `description` through, follow the same named-export pattern from `LibraryPage.js`; Source: ARCHITECTURE.md)
- **AC-ENQUEUE-ADMIN** (Reason Included: data injection for the Library page lives in `admin/Main::enqueue_scripts()`; this feature must NOT add new enqueue logic or move existing logic — descriptions are already on the wire via the existing `wp_add_inline_script('before')` call; Source: CONSTITUTION.md §I)

## Accepted Deviations

- None apply.

## Relevant Security Constraints

- **None directly applicable**. SEC-01..SEC-04 cover REST endpoint sanitization, multisite isolation, and strict access-control comparisons — Feature 036 makes no REST, DB, or authorization changes. Description rendering is a display-only path. Spec's FR-006 (plain-text rendering, no `dangerouslySetInnerHTML`) is the relevant safeguard here and is satisfied implicitly by JSX text-node escaping. (Reason Included: pre-empt a false positive in the post-plan security review.)

## Related Historical Lessons

- **BUG-WP-LOCALIZE-SCRIPT-RENDER** (Reason Included: Library data injection moved from `render()` to `enqueue_scripts()` with `wp_add_inline_script('before')` in Feature 030. Feature 036 must NOT regress this — adding `description` requires zero PHP-side change because the Registry already passes `args` through.)
- **BUG-JEST-MOCK-LIST-STALENESS** (Reason Included: if Feature 036 adds or modifies Jest specs that import from `LibraryCard.js`, the `jest.mock()` allowlist must be updated for any new `@wordpress/*` imports. We do not currently plan new imports — `Fragment`, `useState`, `CheckboxControl`, `RadioControl`, `ToggleControl`, `Button`, `__`, `chevronDown`, `chevronUp` are all already imported.)
- **Feature 033 worklog** (2026-06-14): Library card visibility contract + `sub_group` display + chevron disclosure. Feature 036 layers ability descriptions on top of the same card without changing the visibility contract or sub-group rendering. PHPUnit (85) and Jest (20) counts from 033 establish the test surface we should not regress.
- **Feature 030 worklog** (2026-06-11): Library blank-page fix and the `wp_add_inline_script('before')` pattern. Feature 036 inherits this pattern unchanged.

## Conflict Warnings

- None.

## Retrieval Notes

- **Index entries considered**: 20 (rows scanned in `docs/memory/INDEX.md`).
- **Source sections read**: `docs/memory/security-constraints.md` (whole — 21 lines), `docs/memory/INDEX.md` (whole — 223 lines), `.specify/memory/CONSTITUTION.md` (first 80 lines — sync-impact only; full file 364 lines exceeded small-file budget, but INDEX already surfaces the constraint-derived `AC-*` entries we need).
- **Budget status**: well within all caps — 3 decisions / 2 architecture constraints / 0 deviations / 0 security constraints / 3 lessons / 2 worklog items. Synthesis word count ≤ 900 budget.
- **Optimizer**: not enabled (no `.specify/extensions/memory-md/config.yml`); markdown-only index-first retrieval used.
- **Phase**: Plan — prioritized boundary definitions (Registry data shape, saved-config schema, enqueue location) and architectural drift risks (do not move data injection, do not change REST shape).
- **Excluded by state**: `DEC-MCP-SERVERS-SANITIZE`, `DEC-MCP-CAPABILITY-FILTER-WARN`, `DEC-MCP-TOOLS-PASSTHROUGH-COLUMN`, `DEC-EVAL-PHP-CODE` (all Superseded or feature-removed) — none applied to this scope anyway.
