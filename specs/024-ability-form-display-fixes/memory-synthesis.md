# Memory Synthesis

## Current Scope

Feature 024 fixes 5 targeted bugs across 4 source files:
- **CHANGE-1** — `AcrossAI_Ability_Merger.php:183`: `source` default `null` (was `'plugin'`)
- **CHANGE-2** — `AbilitiesList.jsx` `TypeCell`: add `_registry?.callback_type` fallback
- **CHANGE-3** — `AbilityForm.jsx`: fix 6 hint refs to use `._registry.`; add 3 Identity hints
- **CHANGE-4** — `AbilityForm.jsx` Callback section: read-only display for `isNonDb`
- **CHANGE-5** — `AcrossAI_Ability_Override_Processor.php` `inject_override_args()`: add label/description/category top-level arg injection

SC-006 clarified (2026-05-31): Automated PHPUnit/Jest tests covering Variant A (source=db) behaviour are a required deliverable. SC-005 clarified: override injection takes effect on next request cycle (`wp_register_ability_args` fires at `init`).

---

## Relevant Decisions

- **DEC-ABILITIES-DUAL-MODE-LIST** (Reason: `format_merged_ability()` is the formatter being fixed in CHANGE-1/2; `_registry` sub-object is already included — no REST shape changes needed, Status: Active, Source: DECISIONS.md)
- **DEC-DESIGN-OVERRIDES-DATAVIEWS** (Reason: AbilityForm uses custom rendering not DataForm — CHANGE-3/4 may add custom `<div className="desc">` elements; this is accepted, Status: Active, Source: DECISIONS.md)
- **DEC-HACTIONS-BUTTON-DEPTH** (Reason: AbilityForm.jsx has mixed tab depths per element; all str_replace operations must verify exact indentation first, Status: Active, Source: DECISIONS.md)
- **DEC-NODE-20-BUILD-REQUIRED** (Reason: `npm run build` required by SC-006; must use Node ≥ 20, Status: Active, Source: DECISIONS.md)
- **DEC-DESCRIPTION-VALIDATION-PATTERN** (Reason: description field has DESCRIPTION_MAX_LENGTH=1000; hints must not render validation markup — read-only `<div className="desc">` only, Status: Active, Source: DECISIONS.md)

---

## Active Architecture Constraints

- **AC-HOOKS-MAIN** — Only `Main.php` calls `loader->add_action/add_filter`. CHANGE-5 does NOT add new hook registrations — `inject_override_args` hook is already registered; constraint satisfied. (Reason: Verify no new loader calls needed, Source: CONSTITUTION.md §I)
- **ARCH-ADV-001** — Override Processor's `boot()` wires hooks directly (PATH-A/B), accepted deviation. CHANGE-5 only adds 3 `if` blocks inside an existing method body; no new hook wiring, deviation scope unchanged. (Reason: Confirm CHANGE-5 stays within accepted deviation scope, Source: DECISIONS.md)
- **ARCH-ABILITYFORM-SECTION-ORDER** — Section order: 1=Identity, 2=SitePermissions, 3=MCP, 4=Annotations, 5=UserAccess, 6=Callback, 7=Schema. CHANGE-3 touches sections 1, 3, 4. CHANGE-4 touches section 6. All within existing sections; no new sections added. (Reason: Section order must be preserved; numbers in `.sect` divs must not change, Source: ARCHITECTURE.md)
- **AC-FILE-HEADER-PATTERN** — `@package AcrossAI_Abilities_Manager, @subpackage full/path, @since 0.1.0`. Verify headers are not disturbed when editing PHP files. (Reason: PHPCS gate enforces this, Source: ARCHITECTURE.md)
- **ARCH-PHPUNIT-BOOTSTRAP** — `ABSPATH define` before autoloader in `tests/bootstrap.php`; exclude BerlinDB-loading test files from stub-bootstrap suite. SC-006 requires Variant A automated tests; new test files must follow this bootstrap pattern. (Reason: Automated tests are a deliverable per SC-006 Q2, Source: ARCHITECTURE.md)

---

## Accepted Deviations

- **ARCH-ADV-001** (Reason: Override Processor boot() is sole accepted deviation from Boot Flow Rule; CHANGE-5 stays within this scope, Status: Accepted-Deviation)
- **DEC-DESIGN-OVERRIDES-DATAVIEWS** (Reason: AbilityForm custom rendering accepted; CHANGE-3/4 add `<div className="desc">` hints inside existing custom form sections, Status: Accepted-Deviation)
- **DEC-SETTINGS-API-DEVIATION** (Reason: Not relevant to this feature — noted only to confirm it does not conflict, Status: Accepted-Deviation)

---

## Relevant Security Constraints

- **SEC-04** — Strict type comparison (`null !== $row->label && '' !== $row->label`). CHANGE-5 must use strict guards — already specified in planning doc. (Reason: DB write guards in inject_override_args must match strict comparison pattern, Source: security-constraints.md)
- **SEC-01** — `sanitize_ability_slug()` at every REST endpoint receiving a slug. CHANGE-5 does not touch REST routes; no new slug intake. Constraint not triggered. (Reason: Confirm no new REST endpoints introduced, Source: security-constraints.md)
- **SEC-02** — `before_save` hook fires on sanitized `$fields` only. CHANGE-5 does not touch the save pipeline (DB save already works per B-7). Constraint not triggered. (Reason: Confirm save pipeline unchanged, Source: security-constraints.md)

---

## Related Historical Lessons

- **BUG-FLAT-ARGS-PATH** (Reason: CHANGE-5 is the continuation of this exact bug class — `inject_override_args()` previously used flat top-level keys for annotations; this fix adds 3 more correctly-positioned top-level keys. Verify $args['label'] not $args['meta']['label'] or similar nested path)
- **BUG-ABILITYFORM-JSX-MIXED-DEPTHS** (Reason: AbilityForm.jsx has inconsistent tab depths per element type. All str_replace/edit operations must read surrounding lines and match exact tab character count before applying changes)
- **BUG-PHPCBF-TABS** (Reason: phpcbf converts space indentation to tabs. PHP str_replace scripts must use `\t` not spaces; direct file edits in editor use tabs)
- **BUG-PHPCS-DOCBLOCK-CAPITAL** (Reason: PHPDoc long descriptions must start with capital letter. CHANGE-5 adds/updates docblock in inject_override_args() — first word of long description must be capitalized; phpcbf will not auto-fix)
- **BUG-PHPSTAN-SILENT-PASS** (Reason: PHPStan exit 0 with no output = clean pass. Don't mistake silence for an error)

---

## Conflict Warnings

- **Soft** — ARCH-ABILITYFORM-SECTION-ORDER table lists Callback (6) and Schema (7) as "A (db) only". Both currently render for non-db abilities (Callback: interactive chips — bug; Schema: read-only pre block — intentional). CHANGE-4 aligns Callback with Schema's read-only pattern. This is an architecture _alignment_, not a new deviation. No blocking conflict.
- **Soft** — SC-006 now requires automated tests (Q2 answer). No existing test suite covers the Variant A form edit path in Jest. ARCH-PHPUNIT-BOOTSTRAP and PATTERN-NAMED-EXPORT-JEST / PATTERN-JEST-SECTION-SCOPE are the patterns to follow. Plan must include a test task.

---

## Retrieval Notes

- Index entries considered: 20 (DEC-ABILITIES-DUAL-MODE-LIST, DEC-DESIGN-OVERRIDES-DATAVIEWS, DEC-HACTIONS-BUTTON-DEPTH, DEC-NODE-20-BUILD-REQUIRED, DEC-DESCRIPTION-VALIDATION-PATTERN, ARCH-ADV-001, AC-HOOKS-MAIN, ARCH-ABILITYFORM-SECTION-ORDER, AC-FILE-HEADER-PATTERN, ARCH-PHPUNIT-BOOTSTRAP, BUG-FLAT-ARGS-PATH, BUG-ABILITYFORM-JSX-MIXED-DEPTHS, BUG-PHPCBF-TABS, BUG-PHPCS-DOCBLOCK-CAPITAL, BUG-PHPSTAN-SILENT-PASS, SEC-01, SEC-02, SEC-04, ARCH-ADV-001 deviation, DEC-DESIGN-OVERRIDES-DATAVIEWS deviation)
- Source sections read: BUGS.md (grep, lines 33–140), ARCHITECTURE.md (lines 436–510, grep)
- Budget status: 5/5 decisions, 5/5 architecture constraints, 3/3 bug patterns, 3/3 security constraints, 3/3 accepted deviations, 2/2 worklog items — within budget
