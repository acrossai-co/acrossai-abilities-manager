# Implementation Plan: Library display fields under `$args['meta']['acrossai']`

**Branch**: `041-library-fields-meta-acrossai-namespace` | **Date**: 2026-07-03 | **Spec**: [spec.md](./spec.md)

## Summary

Refactor the three optional Library display fields (`sub_group`, `sub_group_label`, `tab_group`) from top-level `$args` into `$args['meta']['acrossai']`. Hard cut — legacy top-level shape retired without backward-compat fallback. Establishes `meta.acrossai` as the canonical namespace for plugin-specific ability extension fields, sibling of `meta.mcp` and `meta.annotations`.

Two-file code change; test suite fixture migration; new memory pattern + decision entries; four Spec-Kit artefacts.

## Technical Context

**Language/Version**: PHP 8.1+ (CONSTITUTION §II).
**Primary Dependencies**: Unchanged. No composer add/remove.
**Storage**: No change — none of the three fields have DB columns; they are registry-time display-only fields.
**Testing**: PHPUnit 10.5+. Test count changes from 103 → 105 (two new negative regression tests added; no test removed).
**Target Platform**: WordPress 6.9+ admin, PHP 8.1+, multisite-compatible.
**Project Type**: WordPress plugin — single project.
**Performance Goals**: Zero regression. `push_definition()` extraction now walks one nested level (`$args['meta']['acrossai']['sub_group']`) instead of one top-level (`$args['sub_group']`) — negligible.
**Constraints**: No JS-side change. No DB schema change. No REST controller change. Downstream row shape unchanged. Registry consumers unchanged.
**Scale/Scope**: 2 code files edited, 2 test files edited, 4 memory files edited, 4 new Spec-Kit files. Approximately +223 lines added and -69 lines removed (per commit 1 stat).

## Constitution Check

| Principle (CONSTITUTION.md v1.4.8) | Applies? | Status | Notes |
|---|---|---|---|
| §I Modular Architecture — Library module ownership | Yes | ✅ Pass | Refactor entirely inside `includes/Modules/Library/`. No cross-module dependency introduced. |
| §I Boot Flow Rule | Yes | ✅ Pass | No hook registration change. `Main.php` untouched. |
| §I Admin Partials Rule | Yes | ✅ Pass | No admin/Partials edits. |
| §I Module Contract (singleton) | Yes | ✅ Pass | `Ability_Definition` is abstract (not a singleton — subclasses instantiate normally). `AcrossAI_Ability_Library_Registry` singleton unchanged. |
| §II WordPress Standards | Yes | ✅ Pass | PHPStan L8 clean; PHPCS 45/45 clean. See "Quality Gates" section. |
| §II `acrossai_` prefix | Yes | ✅ Pass | No new identifiers introduced. |
| §II Multisite compatible | Yes | ✅ Pass | No `$wpdb`, no site-scoped code. |
| §III UI Contract (DataForm / DataViews) | Yes | ✅ Pass | No UI change; JS side unchanged. |
| §IV Security First | Yes | ✅ Pass | Registry `sanitize_key_field()` + `wp_kses_post()` still gate values at the Registry boundary. Feature 041 only changes WHERE `push_definition()` reads inputs from, not what sanitization applies. |
| §V Extensibility Without Core Modification | Yes | ✅ Pass — **strengthened**. | Feature 041 CLARIFIES the extension contract: plugin-specific fields go under `meta.acrossai`. Consistent with the plugin's own established `meta.mcp` / `meta.annotations` patterns. |
| §VI DRY | Yes | ✅ Pass — **strengthened**. | Three top-level keys consolidated under one namespace. Consistent with sibling patterns. |
| §VII Definition of Done | Yes | ✅ Verified via per-task gates in [tasks.md](./tasks.md). |

**Constitution Gate**: **PASS**. No amendment required. No accepted-deviation change.

## Memory Synthesis Findings

Synthesized in [memory-synthesis.md](./memory-synthesis.md). Highlights applied to this plan:

- **`PATTERN-LIBRARY-ARGS-RAW-PASSTHROUGH`** — Registry allowlists args keys but does not deep-sanitize passthrough values. Post-041, `meta.acrossai.*` sub-keys still pass through the `'meta'` allowlist entry; the row-top values (which the JS consumes) get sanitized at Registry boundary as before. The pattern is unchanged; only the input-side extraction moves.
- **`BUG-INJECT-MISSING-TOP-LEVEL-FIELDS` + FR-009 field-path table** (spec 004) — that table documents OVERRIDABLE fields. `sub_group` / `tab_group` / `sub_group_label` are NOT overridable (they never enter the DB); no table update needed.
- **MCP / annotations meta shape precedent** — `meta.mcp` and `meta.annotations` sub-arrays are the canonical model for how nested meta namespaces work in this plugin. `meta.acrossai` mirrors that shape at the input side; the difference is that Feature 041 fields have no persistence path (unlike mcp fields which have flat DB columns).
- **Two new entries captured**: `PATTERN-META-ACROSSAI-NAMESPACE` and `DEC-META-ACROSSAI-NAMESPACE`. See [memory-synthesis.md](./memory-synthesis.md).

## Project Structure

### Documentation (this feature)

```text
specs/041-library-fields-meta-acrossai-namespace/
├── spec.md              # 8 FRs, 6 SCs, 2 user stories, 5 edge cases
├── plan.md              # This file
├── tasks.md             # 7 task groups
└── memory-synthesis.md  # Memory hygiene synthesis
```

### Source Code (repository root)

**Files EDITED** (2 code + 2 test):

```text
includes/Modules/Library/
├── Ability_Definition.php                                  # push_definition() extraction refactor
└── AcrossAI_Ability_Library_Registry.php                   # ALLOWED_ARGS_FIELDS constant trim

tests/phpunit/Modules/Library/
├── Test_Ability_Definition.php                             # fixtures migrated + 2 new negative tests
└── Test_Ability_Library_Registry.php                       # fixtures migrated + 2 allowlist tests rewritten
```

**Files EDITED** (memory):

```text
docs/memory/
├── ARCHITECTURE.md      # New PATTERN-META-ACROSSAI-NAMESPACE entry
├── DECISIONS.md         # New DEC-META-ACROSSAI-NAMESPACE entry
├── WORKLOG.md           # Feature 041 milestone (top-of-file per convention)
└── INDEX.md             # Three new routing rows (PATTERN + DEC + WORKLOG)
```

**Structure Decision**: Single-project WordPress plugin. Refactor-only feature. No new directories, no new modules, no new tables, no new dependencies.

## Phase 0 — Research Findings

| Question | Decision | Rationale |
|---|---|---|
| Backward compat with legacy top-level shape? | **Hard cut — no fallback**. Legacy shape silently dropped by the allowlist filter and ignored by extraction. | User decision. Fields introduced 2-3 weeks ago; no known third-party consumer in production yet. Deprecation with fallback would leave dual-shape support in perpetuity. |
| In-plugin `Ability_Definition` subclasses? | **Zero found**. Abstract base exposed only for external add-on developers. | `grep -rEn 'extends Ability_Definition' includes/ admin/` returns zero hits. Refactor is fully self-contained for this plugin. |
| Register a WP_DEBUG_LOG deprecation warning? | No. | Per the hard-cut decision. Would be noise for developers who haven't migrated yet; migration is a trivial edit; add-on authors will find out fast if their Library heading disappears. |
| Change the `$row` shape too? | No. Row shape stays exactly as Features 033/037 defined. | The row is an internal contract between `push_definition()` and `AcrossAI_Ability_Library_Registry::validate_and_normalize()`, then serialized to JS. Changing it would ripple through `LibraryPage.js`, `LibraryCard.js` for no meaningful benefit. |
| Change the JS consumption shape? | No. `LibraryPage.js` / `LibraryCard.js` continue to read `definition.sub_group` / `.sub_group_label` / `.tab_group` at row-top. | Same reason as above — the refactor is fully hidden below the row layer. |
| Reserve the `meta.acrossai` namespace project-wide? | Yes. Document via `PATTERN-META-ACROSSAI-NAMESPACE` that this namespace is reserved for `acrossai-abilities-manager`-specific fields. Future sibling AcrossAI-org plugins use their own key. | Prevents future collision if a sibling plugin ships (e.g. `acrossai-mcp-manager`, `acrossai-ability-logs`). |

## Phase 1 — Design

### Data Model

No change. Zero DB schema modifications. `sub_group` / `sub_group_label` / `tab_group` remain registry-time display-only fields, never persisted.

### Contracts

Post-Feature 041 external contracts:

- **Add-on developer input**: `ability()` returns `['args' => ['meta' => ['acrossai' => ['sub_group' => 'x', 'sub_group_label' => 'X Label', 'tab_group' => 'y']]]]`.
- **Registry row output**: unchanged — row-top `sub_group`, `sub_group_label`, `tab_group`.
- **JS consumption**: unchanged — `window.acrossaiAbilityLibraryData` still exposes `definition.sub_group` etc. at the definition-object top level.
- **REST**: unchanged. No REST field added or removed.

### Quickstart

Per-task verification recipes in [tasks.md](./tasks.md). Feature 041 does not need a separate quickstart.md — the manual walkthrough is a single WP-Admin → Library page check.

## Complexity Tracking

Nothing to track. Zero deviations. Zero accepted-deviation-status changes. Zero Constitution amendments. Zero new modules, tables, or dependencies.
