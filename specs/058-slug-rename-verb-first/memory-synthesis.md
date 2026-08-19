# Memory Synthesis

## Current Scope

Feature 058 renames every ability slug in `acrossai-abilities-manager` from the pre-0.0.16 mixed subject-first / verb-first form under `acrossai-abilities-manager/` to a uniform `acrossai/<verb>-<subject>` form. 219 abilities total: 163 suffixes flipped, 56 already-verb-first suffixes only had their namespace shortened. 162 PHP class files renamed to match slugs (e.g. `Site_Title_Get.php` → `Get_Site_Title.php`, `class Site_Title_Get` → `class Get_Site_Title`). Client-side `SLUG_PREFIX` constant and server-side prefix injection updated in lockstep. Plugin REST namespace also shortens from `acrossai-abilities-manager/v1` to `acrossai/v1`. Breaking change with NO data migration (per user directive — plugin has very few users; users clear old rows manually from the admin UI). Ships as v0.0.16.

Modules touched: entire `includes/Abilities/**` tree (166 files), `includes/Modules/Abilities/Rest/AcrossAI_Abilities_{Read,Write,Rest}_Controller.php` (namespace + prefix injection), `includes/Utilities/AcrossAI_Abilities_{Sanitizer,Validator}.php` (byte cap + length check), `includes/AcrossAI_Activator.php` + `includes/Main.php` (migration wiring added then removed), `src/js/abilities/components/{AbilityForm,AbilitiesList}.jsx` (SLUG_PREFIX constant + rebuilt `build/js/*`), `admin/Main.php` (rest_namespace + one bug-fix revert of plugin_basename false positive). Tests: 12 PHPUnit + 2 Jest fixtures updated. Docs: `README.txt` changelog, `docs/FEATURES.md`, `docs/memory/{ARCHITECTURE,BUGS,DECISIONS,WORKLOG}.md`.

## Relevant Decisions

- **DEC-META-ACROSSAI-NAMESPACE** — `meta.acrossai` is reserved for this plugin; sibling AcrossAI-org plugins use their own keys. (Reason Included: Namespace shortening to `acrossai/` extends this convention from meta-field names to slug prefixes — sibling plugins own their own slug prefixes too, so `acrossai/*` is safe to reserve for this plugin. Status: Active. Source: DECISIONS.md)
- **DEC-EXTERNAL-PACKAGE-HOOK-CTOR** — External-package constructor-self-registration allowed at `plugins_loaded` P0 for shared admin surfaces. (Reason Included: Feature 058's original migration wiring in commit bc23e6e added an `admin_init` hook to the module Loader; the pattern of registering hooks from Main.php was preserved for the ~24-hour lifespan of that code before removal in 88dd7c0. Status: Active. Source: DECISIONS.md)
- **DEC-AC-RENDERING-GATE** — `access_control_available` is a client-side rendering gate only. (Reason Included: The Slug input's UI fix (US3) is orthogonal to AC — the fix is a pure JS constant change, no AC gate interaction. Recorded here for future feature planners so they know the AC gate doesn't factor into slug-prefix UX. Status: Active. Source: DECISIONS.md)

## Active Architecture Constraints

- **ARCH-UNIFIED-ABILITIES-STORAGE** — `{prefix}acrossai_abilities` is the single source of truth for override rows. (Reason Included: A data migration WOULD have needed to UPDATE `ability_slug` on this table; that migration was added in bc23e6e then removed in 88dd7c0. Future readers may look for the migration and be confused about the missing SQL — it existed briefly, then was intentionally deleted. Source: ARCHITECTURE.md)
- **PATTERN-META-ACROSSAI-NAMESPACE** — Plugin-specific ability extension fields live under `meta['acrossai']`. (Reason Included: Extends by convention to slug prefix `acrossai/` — same namespace-ownership discipline. Source: ARCHITECTURE.md)
- **AC-HOOKS-MAIN** — Every runtime hook must literally add_action from `Main.php::define_*_hooks()`. (Reason Included: The migration `admin_init` hook added in bc23e6e followed this pattern. It was later removed but the pattern is preserved for future migration wiring. Source: CONSTITUTION.md §I)

## Accepted Deviations

- **No data migration ships** — user directive (2026-07-25) that the plugin's very-few-users profile makes a one-shot migration more mental overhead than value. Any pre-existing overrides or ACL rules under old slugs are cleared manually from the admin UI. This deviates from the normal pattern (Feature 046's absorbed-options migration, Feature 043's schema upgrade) of shipping data migrations on breaking releases. Recorded because a future feature that reintroduces a slug/schema rename should NOT assume this precedent — for a broader-userbase release, the migration is the right call.

## Relevant Security Constraints

- **SEC-01** — All slug values on URL paths must be sanitised by `sanitize_ability_slug` before storage or query. (Reason Included: Feature 058's namespace change preserves this — sanitizer's regex `[^a-zA-Z0-9\-_\/]` untouched; byte-length cap adjusted to `255 - len('acrossai/') = 246`. Source: security-constraints.md)
- **SEC-04** — Strict type comparison for access-control checks. (Reason Included: The ACL library's namespace `acrossai-abilities` (a string constant used as the `namespace` column value) is distinct from our slug prefix `acrossai/`. The perl look-behind `(?<!/)` correctly preserves the ACL namespace while rewriting slug prefixes — no strict-type check is affected because both sides use string equality on already-sanitised values. Source: security-constraints.md)

## Related Historical Lessons

- **Feature 046 (`acrossai-core-abilities` absorb, 2026-07-13)** — First plugin-absorption feature. Bulk PHP rewrite matrix (9 ordered sed/perl transforms). Feature 058 is a direct sibling: a bulk rewrite matrix on 258 files. The 046 lesson `PATTERN-BULK-REWRITE-MATRIX` applies verbatim — per-file synchronous writes, no batched string replacement, work in the same file only once per pass. Also relevant: `BUG-PYTHON-STRREPLACE-PARTIAL-WRITE` — avoided in 058 because we used perl in-place edits, not Python's `str.replace()`.
- **Feature 040 (Logger namespace migration, 2026-06-06)** — REST namespace migrated atomically across 5 files. Feature 058's `acrossai-abilities-manager/v1` → `acrossai/v1` REST namespace change follows the same pattern: client + server + admin partial updated in the same commit. Lesson: atomically-update-every-touch-point-in-one-commit; feature 058 respects this.
- **Feature 056 (`BUG-COMPOSER-SLUG-ENCODE-STRIPS`, 2026-07-20)** — `encodeURIComponent(slug)` on the composer ACL PUT URL was stripping `%2F` producing corrupt keys (`acrossai-abilities-managerblock-pattern-delete`). Feature 058 does NOT re-encode slugs on URL paths — the ACL client passes raw slugs as before. But this bug's name (`acrossai-abilities-managerblock-pattern-delete` — the corruption pattern) will look different post-058 if it ever recurs, because the prefix is now `acrossai/`. Any future post-058 recurrence would look like `acrossaidelete-block-pattern` (namespace + slug with the `/` stripped).

## Follow-ups Deferred to Future Features

- **Redundant `db-` inside Database slugs** (e.g. `run-db-select-query`, `get-db-stats`, `list-db-tables`) — proposed follow-up in the design conversation. Not shipped in 058 because it would be another breaking change on top of an already-breaking release. Candidate for a 059+ pass if the plugin's user base grows and slug economy becomes worth another rev.
- **Class file rename for the 56 unchanged-suffix abilities** — those files (e.g. `Approve_Comment.php` for slug `comments/approve-comment`) are already verb-first because their pre-058 slugs already were. No rename needed. Zero action item.
