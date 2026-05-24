# Security Constraints — Feature 012: Refactor Sitewide Module into Abilities Module

**Reviewed**: 2026-05-24
**Reviewer**: Security Review (plan phase)
**Plan artifact**: `specs/012-refactor-sitewide-abilities/plan.md`
**Spec artifact**: `specs/012-refactor-sitewide-abilities/spec.md`
**Memory synthesis**: `specs/012-refactor-sitewide-abilities/memory-synthesis.md`

---

## Executive Summary

This is a PHP-only module consolidation. The net security posture **improves**: the Sitewide REST
sub-controllers are deleted, reducing attack surface, and no new endpoints or data paths are added.
All critical security invariants (multisite isolation, JSON size guard, strict type comparisons,
AUTHORIZATION CONTRACT) are correctly identified in the plan and must be preserved verbatim.

**One critical blocking gap was found**: the `bust_cache_hook` registration in `Main.php` will
become a **dead hook** after the Sitewide REST controllers are deleted. The hook producer is
removed; the listener is not re-wired. This silently breaks override cache invalidation, creating
a stale access-control enforcement risk. This MUST be resolved before or during implementation.

One medium gap: SEC-01 slug sanitization is a *new addition* at the DB layer (not a preservation
from source), requiring explicit verification during implementation.

---

## Plan Artifacts Reviewed

| Artifact | Reviewed |
|---|---|
| `specs/012-refactor-sitewide-abilities/plan.md` | ✓ Full read |
| `specs/012-refactor-sitewide-abilities/spec.md` | ✓ Full read |
| `specs/012-refactor-sitewide-abilities/memory-synthesis.md` | ✓ Full read |
| `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Query.php` | ✓ Full read (source baseline) |
| `includes/Modules/Sitewide/Database/AcrossAI_Sitewide_Table.php` | ✓ Read ($global, constructor) |
| `includes/Modules/Sitewide/AcrossAI_Sitewide_Access_Control.php` | ✓ Read (=== comparisons) |
| `includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php` | ✓ Read (current state) |
| `includes/Modules/Abilities/Rest/AcrossAI_Abilities_Write_Controller.php` | ✓ Hook inventory |
| `includes/Main.php` (lines 270–330) | ✓ Hook wiring inventory |

---

## Vulnerability Findings

### CRITICAL-01 — `bust_cache_hook` becomes a dead registration after Sitewide deletion

**Severity**: CRITICAL (stale access-control enforcement)
**Affected changes**: Change 11 (Main.php rewiring), Change 13 (Sitewide deletion)
**OWASP reference**: A01:2025 — Broken Access Control

**Diagnosis**:
`Main.php` line 319 registers:
```php
$this->loader->add_action( 'acrossai_abilities_sitewide_after_save', $override_processor, 'bust_cache_hook' );
```

`acrossai_abilities_sitewide_after_save` is fired by the Sitewide REST controllers only:
- `AcrossAI_Sitewide_Bulk_Controller.php:180`
- `AcrossAI_Sitewide_Override_Controller.php:258`
- `AcrossAI_Sitewide_Override_Controller.php:371`

All three files are **deleted** by Change 13. After deletion, the hook is never fired.
`bust_cache_hook` becomes a permanently-dormant listener.

**Impact**: Override cache is never busted after save/delete operations via the Abilities REST
layer. `AcrossAI_Ability_Override_Processor::boot()` loads overrides from DB on first boot and
caches them; stale cache persists for the life of each PHP request unless explicitly invalidated.
An override deleted via the REST API could remain in effect; a new restrictive override could
fail to apply — in both cases enforcement decisions diverge from stored state.

**Root cause**: The plan's Memory Synthesis correctly states "Hook names unchanged" (referring to
not renaming hooks) but does not address the hook *producer* being deleted. The Abilities Write
Controller fires different hooks: `acrossai_abilities_after_create`, `acrossai_abilities_after_update`,
`acrossai_abilities_after_delete` — none of which are wired to `bust_cache_hook`.

**Required fix (must be in Change 11 or as a new explicit sub-change)**:
Re-wire `bust_cache_hook` to the Abilities module hooks that fire on save/delete:
```php
// REMOVE in define_public_hooks():
$this->loader->add_action( 'acrossai_abilities_sitewide_after_save', $override_processor, 'bust_cache_hook' );

// ADD in define_public_hooks() (post-refactor):
$this->loader->add_action( 'acrossai_abilities_after_create', $override_processor, 'bust_cache_hook' );
$this->loader->add_action( 'acrossai_abilities_after_update', $override_processor, 'bust_cache_hook' );
$this->loader->add_action( 'acrossai_abilities_after_delete', $override_processor, 'bust_cache_hook' );
```
Verify `bust_cache_hook()` signature is compatible with each hook's argument payload
(no required parameters — confirmed; method signature is `public function bust_cache_hook(): void`).

**Alternative fix**: Add `do_action( 'acrossai_abilities_after_save', $slug )` inside the ported
`save_override()` in `AcrossAI_Abilities_Query` and wire `bust_cache_hook` to that single hook.
Either approach is acceptable; the re-wiring approach is preferred (no new hooks in DB layer).

**Verification**: After implementation, add to the Change 13 verification step:
```
grep -n "bust_cache_hook" includes/Main.php   # must show ≥1 active wiring
grep -n "acrossai_abilities_sitewide_after_save" includes/ -r   # must return 0 results
```

---

### MEDIUM-01 — SEC-01 slug sanitization at DB layer is a new addition, not a preservation

**Severity**: MEDIUM (defense-in-depth gap if skipped)
**Affected changes**: Change 4e (ported query methods)
**OWASP reference**: A03:2025 — Injection

**Diagnosis**:
The current `AcrossAI_Sitewide_Query` methods do **not** apply `sanitize_ability_slug()` internally.
Sanitization currently happens exclusively at the REST controller layer:
- Via `sanitize_callback` in `register_rest_route()` argument definitions
- Via explicit `AcrossAI_Sanitizer::sanitize_ability_slug()` calls before DB method invocations

The plan requires the ported methods (`get_override_by_slug`, `save_override`,
`delete_override_by_slug`) to apply `sanitize_ability_slug()` at the top of each method as
defense-in-depth. This is a **new security addition**, not preserved from source.

**Impact if skipped**: Non-REST callers (e.g. WP-CLI integrations, future programmatic callers,
unit tests with unsanitized input) bypass slug sanitization entirely. BerlinDB will pass the
unsanitized slug value directly into the SQL query via prepared statements (preventing SQL injection)
but may produce unexpected query results if the slug contains characters the schema does not expect.

**Constraint**:
```php
// Required at the top of each ported slug-accepting method:
$slug = AcrossAI_Sanitizer::sanitize_ability_slug( $slug );
```
Apply to: `get_override_by_slug`, `save_override`, `delete_override_by_slug`.
Do NOT apply to `get_all_overrides` (no slug parameter).

**Verification step**: `grep -n "sanitize_ability_slug" includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php`
must return 3 matches after Change 4e.

---

### LOW-01 — `save_override()` source has no AUTHORIZATION CONTRACT docblock (new addition)

**Severity**: LOW (documentation / code contract clarity)
**Affected changes**: Change 4e

**Diagnosis**: The current `save_override()`, `get_override_by_slug()`, `delete_override_by_slug()`,
and `get_all_overrides()` in `AcrossAI_Sitewide_Query` carry no `AUTHORIZATION CONTRACT` docblock.
Only `by_source()` has one. The plan mandates adding the contract to all 4 ported methods —
this is a new addition, not a preserved pattern.

**Constraint**: All 4 ported methods MUST carry the verbatim docblock specified in the plan
(DEC-BY-SOURCE-AUTHZ). Do not copy from `by_source()` — use the exact text from plan Change 4:
```php
/**
 * AUTHORIZATION CONTRACT (DEC-BY-SOURCE-AUTHZ):
 * This is an authorization-free DB helper.
 * Every caller surfacing results to an HTTP response MUST enforce
 * current_user_can( 'manage_options' ) before invoking this method.
 * See OWASP A01:2025.
 */
```

---

## Confirmed Secure Patterns

| ID | Constraint | Status | Evidence |
|---|---|---|---|
| SEC-03 | `$global = false` in `AcrossAI_Sitewide_Table` | ✓ CONFIRMED IN SOURCE | Line ~49 of `AcrossAI_Sitewide_Table.php`; plan Change 1 explicitly requires preservation |
| DEC-TABLE-SOFT-SINGLETON | No `private __construct()` on Table class | ✓ CONFIRMED IN SOURCE | Source has only `instance()` + `protected static $_instance`; no private constructor on Table |
| DEC-JSON-SIZE-GUARD | `$max_json_bytes = 65536` in `save_override()` | ✓ CONFIRMED IN SOURCE | Source line ~148; plan Change 4e explicitly requires preservation |
| BUG-BERLINDB-UNLIMITED | `get_all_overrides()` uses `number => 0` | ✓ CONFIRMED IN SOURCE | Source confirmed `array( 'number' => 0 )` |
| BUG-PARTIAL-HOOK-FIELDS | `save_override()` does not fire hooks with partial `$fields` | ✓ CONFIRMED IN SOURCE | INSERT/UPDATE paths both fire after full `$fields` assembly; plan requires preservation |
| DEC-BY-SOURCE-AUTHZ | Callers gate via `permission_callback`, not query class | ✓ CONFIRMED IN PLAN | REST sub-controllers use `permission_callback` → `current_user_can( 'manage_options' )` |
| SEC-04 / BUG-LOOSE-COMPARISON-BYPASS | `===` strict comparisons in Access Control | ✓ CONFIRMED IN SOURCE | Source uses `null === self::$_instance`, `null === $manager`; plan Change 6 requires preservation |
| AC-HOOKS-MAIN | Named variable before `add_action()` in all plan changes | ✓ CONFIRMED IN PLAN | All plan Changes 11a–11c show named variable resolution before `$this->loader->add_action()` |
| Enum guards in `save_override()` | `status`, `callback_type`, `mcp_type` are validated | ✓ CONFIRMED IN SOURCE | All 3 enum columns have allowlist guards before BerlinDB write |
| Attack surface reduction | Deleting Sitewide REST layer removes `/sitewide/` routes | ✓ CONFIRMED IN PLAN | FR-005, SC-005; Change 13 deletes all Sitewide REST controllers |
| No capability escalation in Activator | `AcrossAI_Activator` reference update is class-name-only | ✓ CONFIRMED IN PLAN | Change 12: `new AcrossAI_Sitewide_Table()` → `new AcrossAI_Abilities_Table()`; no logic change |
| BerlinDB prepared statements | Slug values pass through BerlinDB's query builder | ✓ CONFIRMED | BerlinDB uses `$wpdb->prepare()`; SQL injection risk is low even without slug sanitization |

---

## Implementation Constraints (Mandatory)

The following constraints MUST be enforced during implementation. Treat as requirements, not suggestions.

### SC-IMPL-001 (CRITICAL — blocks implementation completion)
Change 11 (`includes/Main.php`) MUST re-wire `bust_cache_hook` from
`acrossai_abilities_sitewide_after_save` to `acrossai_abilities_after_create`,
`acrossai_abilities_after_update`, and `acrossai_abilities_after_delete`.
The existing registration on `acrossai_abilities_sitewide_after_save` MUST be removed.
No other hook name changes are permitted.

### SC-IMPL-002
Change 4e — `get_override_by_slug()`, `save_override()`, `delete_override_by_slug()` MUST call
`AcrossAI_Sanitizer::sanitize_ability_slug( $slug )` as the **first statement** before any DB or
logic operations. Use statement `use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Sanitizer`
must be present at the top of `AcrossAI_Abilities_Query.php`.

### SC-IMPL-003
Change 1 (`AcrossAI_Abilities_Table.php`) MUST declare `protected $global = false;` — verbatim.
The PHPCS comment (`// SAC-02`) and the explanatory docblock comment from the source MUST be copied.

### SC-IMPL-004
Change 4e — All 4 ported methods MUST carry the verbatim `AUTHORIZATION CONTRACT (DEC-BY-SOURCE-AUTHZ)`
docblock. Do not paraphrase or shorten the contract text.

### SC-IMPL-005
Change 6 (`AcrossAI_Abilities_Access_Control.php`) — All comparisons to `null`, `false`, and
boolean values MUST use `===` / `!==`. A reviewer MUST search for `==` (loose) in the new file
and confirm zero occurrences outside of string comparisons.

### SC-IMPL-006
The verification checklist in the plan MUST include, before Change 13 (delete Sitewide):
```
grep -rn "acrossai_abilities_sitewide_after_save" includes/ admin/ --include="*.php"
```
This grep MUST return zero results in non-Sitewide files. The hook must be fully gone from
bootstrap before deletion proceeds.

### SC-IMPL-007
Change 13 verification — after deletion, run:
```
grep -rn "bust_cache_hook" includes/Main.php
```
MUST return at least one active `add_action` line targeting `acrossai_abilities_after_*` hooks.

---

## Open Issues (Must Resolve Before or During Implementation)

| ID | Issue | Blocking? | Owner |
|---|---|---|---|
| OI-001 | CRITICAL-01: `bust_cache_hook` dead hook | ~~YES~~ **RESOLVED** | Plan patched: Sub-change 11d added to Change 11 (2026-05-24) — re-wires to `after_create`, `after_update`, `after_delete` |
| OI-002 | MEDIUM-01: SEC-01 slug sanitization addition — must verify it is implemented, not assumed present | NO | Reviewer (Change 4e verification) |
| OI-003 | LOW-01: AUTHORIZATION CONTRACT docblock — new addition on 4 methods; must verify verbatim text | NO | Reviewer (Change 4e verification) |

---

## Decommissioned Sitewide Routes — Verification

After Change 13, the following endpoints MUST return 404:

```
GET  /wp-json/acrossai-abilities-manager/v1/sitewide/abilities
GET  /wp-json/acrossai-abilities-manager/v1/sitewide/abilities/{slug}
POST /wp-json/acrossai-abilities-manager/v1/sitewide/abilities/{slug}
DELETE /wp-json/acrossai-abilities-manager/v1/sitewide/abilities/{slug}
POST /wp-json/acrossai-abilities-manager/v1/sitewide/abilities/bulk
GET  /wp-json/acrossai-abilities-manager/v1/sitewide/mcp/abilities
```

These were registered by `AcrossAI_Sitewide_Rest_Controller` and sub-controllers. After
their deletion, WordPress REST API will naturally return 404 for unregistered routes.
No explicit 404 response code is needed — non-registration is sufficient.

---

## Task Phase Security Review — 2026-05-24

**Reviewer**: Security Review (tasks phase)
**Task artifact**: `specs/012-refactor-sitewide-abilities/tasks.md` (29 tasks)
**Reviewer notes**: One minor gap found and resolved during review. No new blocking items.

### Task Coverage Audit

| Constraint | Task Coverage | Status |
|---|---|---|
| SC-IMPL-001 (bust_cache_hook re-wiring) | T014 (implementation) + T024 (verification grep) | ✅ Covered |
| SC-IMPL-002 save_override slug sanitization | T006 initial draft missing it → **patched 2026-05-24** | ✅ Fixed |
| SC-IMPL-002 get_override_by_slug slug sanitization | T006 | ✅ Covered |
| SC-IMPL-002 delete_override_by_slug slug sanitization | T006 | ✅ Covered |
| SC-IMPL-002 verification (3 grep matches) | T025 | ✅ Covered |
| SC-IMPL-003 `$global = false` in Table | T002 | ✅ Covered |
| SC-IMPL-004 AUTHORIZATION CONTRACT docblock ×4 | T006 | ✅ Covered |
| SC-IMPL-005 No loose `==` in Access Control | T008 | ✅ Covered |
| SC-IMPL-006 Pre-deletion grep gate | T016 | ✅ Covered |
| SC-IMPL-007 Post-change bust_cache_hook verify | T024 | ✅ Covered |
| BUG-BERLINDB-UNLIMITED (`number => 0`) | T006 | ✅ Covered |
| DEC-TABLE-SOFT-SINGLETON (no private __construct) | T002 + T013 | ✅ Covered |
| DEC-JSON-SIZE-GUARD (`$max_json_bytes = 65536`) | T006 | ✅ Covered |
| AC-HOOKS-MAIN (named variable before Loader) | T014 | ✅ Covered |
| Pre-deletion reference scan | T019 | ✅ Covered |
| Post-deletion zero-reference verify | T021 | ✅ Covered |
| Static analysis gate | T022 (phpstan) + T023 (phpcs) | ✅ Covered |
| Smoke test + enforcement verify | T026 | ✅ Covered |

### Resolved Task-Phase Finding

**TF-001 (MINOR — resolved)**: `T006` original description for `save_override()` (method 2 of 4) omitted the `sanitize_ability_slug()` first-statement instruction. The instruction was present for `get_override_by_slug` (method 1) and `delete_override_by_slug` (method 3) but missing for `save_override`. T025 verification would have caught it at implementation time, but the implementation instruction is now explicit.
**Resolution**: T006 patched 2026-05-24 to add: *"add `$slug = AcrossAI_Sanitizer::sanitize_ability_slug($slug)` as first statement (SC-IMPL-002, SEC-01)"* to the `save_override` description.

### Security Sequencing Assessment

- **Deletion gates are properly sequenced**: T016 (pre-deletion hook grep) precedes T017–T018 (REST deletion); T019 (pre-deletion reference scan) precedes T020 (full Sitewide deletion).
- **Security foundations complete before deletion**: All consumer updates (T005–T014) complete before any Sitewide file is deleted, ensuring no PHP fatal errors during the transition window.
- **No parallel task bypasses a security prerequisite**: T007–T012 run in parallel but none deletes Sitewide files; deletion is gated in later phases.
- **Static analysis as security gate**: T022 (PHPStan L8) acts as an automated type-safety and interface-mismatch detector; sequenced after all code changes and before smoke test.
