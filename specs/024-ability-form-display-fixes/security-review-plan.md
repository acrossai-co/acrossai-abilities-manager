# Security Review — Plan: Ability Form and List Display Fixes (Feature 024)

**Reviewer**: speckit.security-review.plan | **Date**: 2026-05-31
**Branch**: `024-ability-form-display-fixes`

---

## Executive Summary

No blocking security issues were found. The five targeted bug-fixes operate entirely within
existing trust boundaries and introduce no new input surfaces, REST routes, nonce requirements,
or capability checks. All security-critical patterns (SEC-04 strict guards, JSX auto-escaping,
admin-only data gating) are explicitly specified in the plan and are consistent with the
project's established security decisions.

Two advisory items require confirmation before or during implementation:

- **ADVISORY-1**: CHANGE-4 enforces read-only in the UI only — confirm whether `callback_type`
  and `callback_config` are currently stored/applied for non-db abilities at the server layer,
  and whether server-side enforcement is needed.
- **ADVISORY-2**: PATH A detection is a performance hint, not a security gate (ARCHITECTURE.md
  Risks section) — confirm CHANGE-5 is unreachable on Manager REST requests (PATH A) at runtime.

---

## Artifacts Reviewed

| Artifact | Status |
|---------|--------|
| `specs/024-ability-form-display-fixes/spec.md` | ✅ Reviewed |
| `specs/024-ability-form-display-fixes/plan.md` | ✅ Reviewed |
| `specs/024-ability-form-display-fixes/memory-synthesis.md` | ✅ Reviewed |
| `docs/planning/024-ability-form-display-fixes.md` | ✅ Reviewed |
| `docs/memory/ARCHITECTURE.md` | ✅ Reviewed |
| `docs/memory/DECISIONS.md` (security entries) | ✅ Reviewed |
| `.github/copilot-instructions.md` (AGENTS.md security requirements) | ✅ Reviewed |

---

## Vulnerability Findings

### ADVISORY-1 — CHANGE-4: UI-Only Read-Only Enforcement for Callback Section

**Severity**: Advisory (no new vulnerability introduced)
**Affects**: `AbilityForm.jsx` CHANGE-4 + implied REST behaviour

**Context**: The plan makes the Callback section read-only in the UI for non-db abilities
(`isNonDb = true`). However, `AbilityForm.jsx` currently renders `CALLBACK_CHIPS` and
`CallbackConfigField` for non-db abilities — which means users could already submit a save
request with `callback_type` / `callback_config` values for non-db abilities before this fix.

**Gap**: The plan does not confirm whether:
1. `callback_type` and `callback_config` are in `$overridable_fields` (i.e., stored in the DB
   for non-db abilities), and
2. Whether `inject_override_args()` applies them at boot time for non-db abilities.

**Why it matters**: If `callback_type` is NOT in `$overridable_fields`, then any REST request
sending it for a non-db ability is silently ignored — the read-only UI merely removes a
confusing affordance and the server already rejects it. If it IS stored/applied, then making
the UI read-only without adding server-side enforcement means an admin can bypass the
restriction via direct REST calls.

**Required action**: Before implementing CHANGE-4, confirm via:
```bash
grep -n "callback_type\|callback_config" includes/Utilities/AcrossAI_Ability_Merger.php | grep -i "overridable"
```
- If absent from `$overridable_fields`: ADVISORY is resolved — UI-only enforcement is correct.
- If present: Consider adding server-side validation (out of scope for this feature, but note
  as a follow-up item).

---

### ADVISORY-2 — CHANGE-5: PATH A Isolation for inject_override_args()

**Severity**: Advisory (architectural clarification, not a new risk)
**Affects**: `AcrossAI_Ability_Override_Processor.php` CHANGE-5

**Context**: `ARCHITECTURE.md` states: "PATH A detection is a performance hint, not a security
gate." CHANGE-5 adds three new `if` blocks inside `inject_override_args()`, which runs on
PATH B only. The hook (`wp_register_ability_args`) fires at `init` — before any REST routing
occurs. PATH A/B detection in `boot()` at `plugins_loaded P20` sets a static flag.

**Gap**: The plan does not explicitly confirm that `inject_override_args()` is unreachable on
Manager REST requests. ARCHITECTURE.md notes that misconfiguration of the REST namespace
constant could cause override injection to fire on Manager requests. On Manager requests, the
UI reads "pure WP registry values, never merged override values" — if CHANGE-5 labels/
descriptions leak through on PATH A, the admin UI would show overridden values instead of
registry values for Identity fields, breaking the UI contract.

**Required action**: During Phase 0 (Pre-flight), confirm PATH A guard:
```bash
grep -n "PATH_A\|path_a\|IS_MANAGER\|is_manager_request\|rest_namespace" \
  includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php | head -10
```
Document the confirmed guard mechanism in the implementation notes. No code change required
if the existing boot-time PATH detection is correctly scoped.

---

## Confirmed Secure Patterns

### PHP (CHANGE-1, CHANGE-5)

| Pattern | Evidence |
|---------|---------|
| **SEC-04 strict guards** | Plan specifies `null !== $row->label && '' !== $row->label` for all three new fields in CHANGE-5 — prevents empty-string injection into `$args['label']` |
| **No new user input boundary** | CHANGE-5 reads already-sanitized DB values (`$row->label` etc.); save pipeline is unchanged (B-7) — values were sanitized at save time via SEC-02 |
| **No new REST routes** | Plan explicitly states "No new REST endpoints or parameters" |
| **DB-layer authorization contract** | `AcrossAI_Abilities_Query` methods are authorization-free accessors per DEC-QUERY-AUTHZ — access control belongs in REST `permission_callback`, not in the DB layer; this is satisfied |
| **CHANGE-1 is type-narrowing** | `null` default instead of `(string) 'plugin'` — allows `empty()` guard to fire. Null narrows the possible value space, does not widen it. No new injection surface. |

### JavaScript (CHANGE-2, CHANGE-3, CHANGE-4)

| Pattern | Evidence |
|---------|---------|
| **JSX auto-escaping** | All hint content rendered as JSX text children (`{value}`) — React's reconciler HTML-encodes string children automatically; no `dangerouslySetInnerHTML` |
| **Optional chaining everywhere** | Plan mandates `savedAbility?._registry?.field` for all new `_registry` reads (CHANGE-3/4) — prevents TypeError when `_registry` or a field is absent |
| **Admin-only data** | `_registry` data is already included in every REST response; the hints expose no new information to non-admin users — the Abilities REST endpoint requires `manage_options` |
| **No new network calls** | Hints use data already in the component state (`savedAbility`) — no new `apiFetch` calls required |
| **i18n call safety** | `__('Plugin declares:', 'acrossai-abilities-manager')` uses a literal string — no dynamic data interpolated into the i18n call itself |

### Authorization & Sessions

| Area | Status |
|------|--------|
| REST permission gate | All Abilities REST endpoints already enforce `manage_options` via `check_permission()` — no new gate needed |
| Nonces | No new form submissions or admin-ajax endpoints — existing nonces unchanged |
| Capability escalation | No new capabilities introduced; read-only UI change (CHANGE-4) only removes affordances for non-admins |
| DEC-PERM-CB (permission_callback injection) | CHANGE-5 adds code inside `isset(self::$overrides_cache[$slug])` block; `permission_callback` injection in `inject_override_args()` runs OUTSIDE this block (DECISIONS.md) — these two paths are independent and CHANGE-5 does not interfere |

### Dependencies & Platform

| Area | Status |
|------|--------|
| No new dependencies | Changes use only existing `@wordpress/i18n`, React/JSX, and BerlinDB |
| DEC-NODE-20-BUILD-REQUIRED | `npm run build` gate in Phase 2.4 ensures correct Node version |
| `npm run validate-packages` | Included in Phase 4 quality gates |

---

## Implementation Watchpoints

1. **CHANGE-5 docblock update**: The `inject_override_args()` docblock field map must list the
   three new fields (`label`, `description`, `category → $args['label/description/category']`).
   PHPStan L8 enforces accurate doc types — missing or incorrect docblock can cause a PHPStan
   failure at the quality gate.

2. **CHANGE-5 placement**: New `if` blocks must be placed AFTER `site_allowed` and BEFORE the
   `meta` injections. The `permission_callback` injection runs outside the cache block — do not
   move it or insert new code between the cache block and the permission_callback block.

3. **CHANGE-3 hint text domain**: Confirm `'acrossai-abilities-manager'` matches the plugin's
   registered text domain in all six existing hint calls — do not introduce a second text domain
   string by typo.

4. **Override cache TTL (W-001 pattern)**: CHANGE-5 reads from `self::$overrides_cache`
   which is a 12h transient. No new write path is introduced — existing `bust_cache()` calls
   on save/delete are sufficient. No new cache invalidation needed.

---

## Status

**No blocking issues. Two advisory items for confirmation during Phase 0 (Pre-flight).**

Safe to proceed to `/speckit.tasks`.
