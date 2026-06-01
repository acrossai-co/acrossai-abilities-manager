# Security Constraints: Feature 024 — Ability Form and List Display Fixes

**Generated**: 2026-05-31 | **Reviewer**: Governed Plan Workflow
**Status**: No blocking issues found. Advisory constraints below.

## Trust Boundary Analysis

| Boundary | Change | Risk | Mitigation |
|---------|--------|------|-----------|
| REST input | No new endpoints or parameters | None | N/A |
| DB write | No new write paths (save pipeline unchanged per B-7) | None | N/A |
| `inject_override_args()` filter | Reads `$row->label/description/category` from already-sanitized DB values | Low | Values were sanitized at REST save time (SEC-02); `null !== $row->field && '' !== $row->field` strict guards (SEC-04) prevent empty-string injection |
| JSX hint rendering | Renders string values from `savedAbility._registry.*` | Low | React auto-escapes string children in JSX expressions; no `dangerouslySetInnerHTML` used |
| `_registry` data exposure | Hints surface registry-declared values already included in REST response | Low | Data already visible to admin-level users via REST; no information escalation |

## Authorization Assumptions

- All hint data (`savedAbility._registry.*`) is scoped to the Abilities admin page which already enforces `manage_options` capability via the REST controller's `check_permission()` method — no new authorization surface.
- `inject_override_args()` is a WordPress filter running at `init` in server-side context — no client-supplied input reaches this path directly; the DB row was validated at save time.

## Data Validation Constraints

### PHP (CHANGE-5)

- **REQUIRED**: Use strict double-guard for all three new fields: `null !== $row->label && '' !== $row->label`. This matches SEC-04 (strict type comparison) and prevents empty-string injection that would overwrite the plugin-declared default with an empty value.
- **REQUIRED**: Do NOT use loose comparison (`!$row->label`) — empty string `''` is falsy but should NOT be injected.

### JavaScript (CHANGE-3, CHANGE-4)

- **REQUIRED**: All `savedAbility._registry.*` reads must use optional chaining (`?.`) to prevent TypeError when `_registry` or the field is absent.
- **REQUIRED**: Hint content must be rendered as JSX text children, not via `innerHTML` or any other raw HTML injection path.
- **ADVISORY**: Confirm `__('Plugin declares:', 'acrossai-abilities-manager')` uses the correct text domain string — no interpolation into the i18n call itself.

## Async Security Context

- None — `inject_override_args()` is a synchronous filter at `init` priority 100000. No async context, no background processing, no Action Scheduler dependency.

## Findings

**No blocking security issues identified.**

All risks are Low or None. The three advisory constraints above (strict double-guard, optional chaining, JSX text children) are already specified in the planning doc and the plan.md.

---

## Addendum — Task Review (2026-05-31)

**Reviewed by**: governed-tasks security review on `tasks.md`

### New Finding — CHANGE-1 Negative Regression

**Severity**: Advisory
**Task affected**: T032 (PHPUnit) or a new assertion within it

CHANGE-1 changes the `source` default from `'plugin'` to `null`. This is safe for abilities with
no `source` meta item (the intended fix). However, T032 does not currently assert that abilities
with an **explicit** `source = 'plugin'` meta item continue to return `'plugin'` — not `null`,
not `'core'`.

**Required**: Add one assertion to T032 (or a separate assertion block in the PHPUnit test):
- Given an ability with `source = 'plugin'` in its meta, `normalize_registry()` must still return
  `'plugin'` after CHANGE-1 — the `get_meta_item()` call returns the stored value and the
  `empty()` guard is not reached.

This is a regression guard for Variant A (db) abilities that may have an explicit source stored.
