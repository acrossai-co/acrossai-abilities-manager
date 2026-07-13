# Feature Specification: DRY audit — `includes/Abilities/Utilities/` vs `includes/Utilities/`

**Feature Branch**: `048-dry-audit-abilities-utilities` (proposed)
**Created**: 2026-07-13
**Status**: Draft (follow-up stub from Feature 046)
**Input**: Feature 046 landed absorbed helper classes at `includes/Abilities/Utilities/`, deliberately nested (not merged) with the manager's existing `includes/Utilities/` set. plan.md V-03 documents this DRY question as a deferred audit. This spec is the audit.

## Context

Two utility sets coexist inside the manager plugin:

1. **`includes/Utilities/`** — manager-owned (`AcrossAI_Abilities_Formatter`,
   `AcrossAI_Sanitizer`, `AcrossAI_Ability_Registry_Query`,
   `AcrossAI_Protected_Abilities`, `AcrossAI_Abilities_Sanitizer`,
   `AcrossAI_Abilities_Validator`, `AcrossAI_Ability_Merger`,
   `AcrossAI_Ability_Source_Detector`). Operate on ability-registry and
   override rows.

2. **`includes/Abilities/Utilities/`** — absorbed from acrossai-core-abilities
   (`Block_Info`, `Cron_Helpers`, `File_Mods_Guard`, `Jet_Engine_Helpers`,
   `Mime_Types_Store`, `Multilang_Helpers`, `Plugin_Helpers`,
   `Theme_Helpers`, `User_Helpers`, plus 5 subfolders:
   `Block_Style_Variations/`, `Global_Styles/`, `Pattern/`, `Template/`,
   `Template_Part/`). Operate on WordPress content (blocks, patterns,
   options, cron, plugins).

The two sets serve different concerns, so a 1:1 duplicate is unlikely — but
Constitution §VI Reusability & DRY mandates an explicit audit before shipping.

## Goal

Enumerate every function in both utility sets, identify semantic overlaps, and
decide for each whether to (a) consolidate, (b) rename to disambiguate, or
(c) leave separate with an ADR-style rationale.

## Recommended approach

1. Extract all public method signatures from both trees:
   ```
   grep -rE 'public (static )?function \w+' includes/Utilities/ includes/Abilities/Utilities/
   ```
2. For each method name that appears in both trees, compare signatures + body
   size. Overlap candidates:
   - `Multilang_Helpers` (absorbed) vs any manager i18n helpers
   - Generic string-manipulation helpers (unlikely; manager code prefers
     WordPress core APIs)
3. For each true overlap, propose: consolidate into the manager tier, or
   keep both with an inline docblock cross-reference.
4. For each false overlap (same name, different concern), rename the
   absorbed helper.

## Success Criteria

- SC-048-01: Audit produces a definitive list of duplicates (may be empty).
- SC-048-02: Each duplicate has a documented resolution (consolidate / rename / justify).
- SC-048-03: Feature 046's V-03 deviation row can be marked resolved.
- SC-048-04: No behavioral change to any absorbed ability.

## Non-goals

- No behavioral change to any ability implementation.
- No refactor of already-non-duplicated helpers.
- No API-shape change on public helper methods that external code might consume.

## Dependencies

- Feature 046 merged.
- Constitution PATCH bump (Feature 047) NOT required — this spec operates
  under the current Constitution.
