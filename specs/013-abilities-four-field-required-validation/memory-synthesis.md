# Memory Synthesis

## Current Scope

Feature 013 enforces four required fields (ability_slug, label, description, category) end-to-end: React form validation in `AbilityForm.jsx` (create/edit modes only), PHP validator tightening in `AcrossAI_Abilities_Validator`, processor registrability guard in `AcrossAI_Abilities_Processor`, and REST create-only presence guards in `AcrossAI_Abilities_Write_Controller`. No DB, sanitizer, migration, or webpack changes.

## Relevant Decisions

- **DEC-UTILITY-STATIC-ONLY** (Reason Included: `validate_description()` must be a static method; no singleton on validator utility, Status: Active, Source: DECISIONS.md)
- **DEC-DESIGN-OVERRIDES-DATAVIEWS** (Reason Included: Custom form UI is the approved pattern; inline `<div className="field-error">` is consistent — no DataForm mandate, Status: Active, Source: DECISIONS.md)
- **DEC-NAMESPACE-CONVENTION** (Reason Included: PHP namespace for new method stays `AcrossAI_Abilities_Manager\Includes\Utilities`, Status: Active, Source: DECISIONS.md)
- **DEC-EARLY-404-REST-CHECK** (Reason Included: Presence guards in create_ability fire after sanitize and before validate_ability — same fail-fast pattern, Status: Active, Source: DECISIONS.md)
- **DEC-NODE-20-BUILD-REQUIRED** (Reason Included: Build validation must use `nvm use 20 && npm run build`, Status: Active, Source: DECISIONS.md)

## Active Architecture Constraints

- **AC-HOOKS-MAIN** (Reason Included: No new hook wiring needed; all existing hooks in Main.php remain unchanged, Source: CONSTITUTION.md §I)
- **AC-REST-SPLIT** (Reason Included: `AcrossAI_Abilities_Write_Controller` is a sub-controller; only a 2-guard addition — no split threshold crossed, Source: CONSTITUTION.md §I)
- **ARCH-UNIFIED-ABILITIES-STORAGE** (Reason Included: No DB changes; `is_row_registrable()` guard does not touch storage, Source: ARCHITECTURE.md)

## Accepted Deviations

- **DEV1 / DEC-DESIGN-OVERRIDES-DATAVIEWS** (Reason Included: Custom form UI is the accepted pattern for Abilities Admin; field-level error divs consistent with existing `slugError`/`inputSchemaError` pattern, Status: Accepted-Deviation)

## Relevant Security Constraints

- **SEC-02** (Reason Included: Presence guards fire on sanitized `$fields` — order is sanitize → presence guard → `validate_ability()` → hook, Source: security-constraints.md)
- **SEC-04** (Reason Included: `empty()` vs strict comparison — use `'' === trim(...)` for string checks to avoid type coercion bypass, Source: security-constraints.md)
- **PHPCS strict profile** (Reason Included: All new PHP must use `trim()` + strict `=== ''` comparisons; docblocks with correct `@since`, `@param`, `@return`, Source: CONSTITUTION.md §II)

## Related Historical Lessons

- **BUG-PHPCS-DOCBLOCK-CAPITAL** (Reason Included: New PHP docblocks must have long descriptions prefixed with "The " — phpcbf won't fix capitalization)
- **BUG-PHPCBF-TABS** (Reason Included: PHP files use tabs after phpcbf; str_replace edits on PHP must use `\t` not spaces)
- **BUG-PARTIAL-HOOK-FIELDS** (Reason Included: Presence guards affect create_ability only; after_create hook already receives full row — no change needed)

## Conflict Warnings

- **None identified.** The spec's null-tolerance preservation for `validate_label()` / `validate_category()` in update flows is consistent with the existing decision to keep these nullable. The new `'' === trim()` rejection is additive and does not break the null-pass-through path.
- **SCSS no-op confirmed**: `.field-error` rule already exists at admin.scss:1258 with correct properties (`color: $red; font-size: 11px; margin-top: 4px;`). No SCSS change needed.

## Retrieval Notes

- Index entries considered: 18 of 20 budget
- Source sections read: DECISIONS.md (top 100 lines), ARCHITECTURE.md (top 150 lines), BUGS.md (top 100 lines), CONSTITUTION.md (full)
- Budget status: within limits
- Key source files inspected: AbilityForm.jsx (full), AcrossAI_Abilities_Validator.php (full), AcrossAI_Abilities_Processor.php (is_row_registrable), AcrossAI_Abilities_Write_Controller.php (create_ability), admin.scss (.field-error, .req, .lopt confirmed)
