# Memory Synthesis

## Current Scope

Feature 037 adds an optional `tab_group` field to the `Ability_Definition` return-value contract. The field carries through the existing collection pipeline (Registry validate_and_normalize), reaches the React Library page via the existing `window.acrossaiAbilityLibraryData` channel, and drives a new in-SPA tab bar above the existing category cards. Display-only — no persisted config, no REST surface change, no execution effect. Affected modules: `includes/Modules/Library/` (Ability_Definition + Registry), `src/js/ability-library/components/LibraryPage.js`, `src/scss/ability-library/admin.scss`, plus the existing Jest test for `groupDefinitions()` and the existing PHP test that covers Registry `sub_group` pass-through.

## Relevant Decisions

- **DEC-LIBRARY-CATEGORY-SLUG-REBRAND** (Active, DECISIONS.md) — Reason Included: Feature 037 extends the same single-method `ability()` contract this decision locks in. External `Ability_Definition` subclasses must stay compatible — `tab_group` MUST be a strictly optional addition; declarations that omit it MUST continue to work without code change.
- **DEC-DESCRIPTION-VALIDATION-PATTERN** (Active, DECISIONS.md) — Reason Included: Establishes the precedent that display-only fields on `ability()` are validated/sanitized at the Registry boundary (description max-length, single-paragraph plain text). `tab_group` follows the same boundary — sanitization belongs in Registry, not in React.
- **DEC-NODE-20-BUILD-REQUIRED** (Active, DECISIONS.md) — Reason Included: `npm run build` for the React edit must run on Node ≥20. Older Node silently drops `toSorted` and similar — this feature uses array sorting on the unique tab_group set and must build under the documented version.

## Active Architecture Constraints

- **AC-ENQUEUE-ADMIN** (CONSTITUTION.md §I) — Reason Included: New data needed by the tab bar (tab_group strings) reaches React through the existing `window.acrossaiAbilityLibraryData.definitions[].tab_group`. Adding it requires no change to `Admin\Main::enqueue_scripts()` — the existing `wp_add_inline_script('before')` already serialises the whole definition row. Do NOT introduce a new enqueue site.
- **AC-HOOKS-MAIN** (CONSTITUTION.md §I) — Reason Included: Registry's `init` P99 hook wiring is in `includes/Main.php` already. This feature must NOT introduce a new `add_filter`/`add_action` call outside Main.php for tab_group collection — the existing `acrossai_abilities_api_init` filter is the only collection point.
- **PATTERN-ADDON-FILTER-LATE-INIT** (ARCHITECTURE.md) — Reason Included: Add-on filters firing at init P99 is the contract. `Ability_Definition`'s constructor already hooks `acrossai_abilities_api_init` at default priority 10, and Registry collects at 99. No timing change needed — the existing late-init pattern carries `tab_group` for free.

## Accepted Deviations

- None apply to this feature. `tab_group` is an additive optional field; no Constitution exception is required.

## Relevant Security Constraints

- **PATTERN-LIBRARY-ARGS-RAW-PASSTHROUGH** (ARCHITECTURE.md, captured Feature 036) — Reason Included: Library Registry currently key-allowlists `args` but does NOT value-sanitize. Without action, a malicious add-on could pass arbitrary HTML/JS through `args.tab_group` into JSX. Mitigation: this feature sanitizes `tab_group` at the Registry boundary via the existing `AcrossAI_Ability_Library_Config::sanitize_key_field()` helper (which also handles `category`, `slug`, `sub_group`). That helper restricts to `sanitize_key()` charset + 100-char cap. Result: `tab_group` becomes a safe alphanumeric/hyphen/underscore key by the time it reaches React — XSS-class injection is precluded, and the React consumer can render it without explicit escaping. This closes the args-raw-passthrough gap for this specific field; the broader gap remains for other args.* fields.

## Related Historical Lessons

- **Feature 033 — sub_group display-only field** (WORKLOG 2026-06-14) — Reason Included: Direct precedent. `sub_group` was added through the same pipeline Feature 037 now extends: optional `args` key → Registry `ALLOWED_ARGS_FIELDS` + `OPTIONAL_FIELDS` → row-level pass-through → React consumer renders. Feature 037 should be a literal mirror of that pattern, one layer up (tab bar vs in-card sub-heading). Reuse the same `OPTIONAL_FIELDS` allowlist, the same `sanitize_key_field()` call, the same display-only contract.
- **Feature 036 — Library description threading + args-passthrough lesson** (WORKLOG 2026-06-23) — Reason Included: Most recent Library change. Established that `args.*` reaches React via the existing data-injection path with no new enqueue plumbing required. Also captured PATTERN-LIBRARY-ARGS-RAW-PASSTHROUGH — directly informs Feature 037's sanitization choice.
- **PATTERN-NAMED-EXPORT-JEST** (ARCHITECTURE.md) — Reason Included: `groupDefinitions()` is already a named export precisely so Jest can test it without rendering. If Feature 037 extracts a new tab-filter helper (`filterItemsByTabGroup` or similar), it MUST be a named export from the same file for the same reason.
- **BUG-JEST-MOCK-LIST-STALENESS** (BUGS.md) — Reason Included: Helper-import Jest specs break when host JSX file gains new `@wordpress/*` imports. Feature 037 will likely import `TabPanel` from `@wordpress/components` in `LibraryPage.js` — the corresponding Jest mock allowlist MUST be updated in the same commit, or unit tests silently regress.

## Conflict Warnings

- None. The plan does not conflict with any active decision, architecture constraint, or security constraint. The `tab_group` design intentionally mirrors `sub_group` (Feature 033 precedent) and intentionally sanitizes at the Registry boundary (PATTERN-LIBRARY-ARGS-RAW-PASSTHROUGH mitigation).

## Retrieval Notes

- Index entries scanned: full INDEX.md (~230 entries) — selected 11 entries with direct scope match (Library, Ability_Definition, args allowlist, React/Jest, enqueue, sanitization).
- Source sections read: INDEX.md (full), spec.md (full). No deeper reads from DECISIONS.md / ARCHITECTURE.md / BUGS.md needed — index summaries are sufficient at the planning stage.
- Constitution: not read (364 lines exceeds "small" threshold). §I rules captured via AC-HOOKS-MAIN and AC-ENQUEUE-ADMIN index entries.
- Budget status: 3 decisions (under 5), 3 architecture constraints (under 5), 0 accepted deviations (under 3), 1 security constraint pattern (under 3), 4 historical lessons (under generous 5), synthesis ~770 words (under 900-word cap). Full durable-memory read avoided.
