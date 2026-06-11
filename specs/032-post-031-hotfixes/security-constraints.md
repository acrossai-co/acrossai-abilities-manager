# Security Constraints: Post-031 Hotfixes (Feature 032)

**Review date**: 2026-06-11
**Overall risk**: LOW (one security fix; two are compatibility/UX)
**Findings**: 0 Critical / 1 High (resolved) / 0 Medium / 0 Low

---

## Resolved Security Finding

### SEC-032-01 — MCP Tools Permission Bypass (RESOLVED)

**Severity before fix**: HIGH
`inject_mcp_tools()` relied solely on AC rules to gate user access. AC rules are fail-open:
`user_has_ability_access()` returns `true` when no rule is configured. Abilities with
`permission_callback` restrictions (e.g. `current_user_can('manage_options')`) were exposed
to all users when Pass as Tool was enabled and no AC rule existed.

**Fix**: Check 4 added to `inject_mcp_tools()` — calls `\wp_get_ability($slug)->check_permissions()`
and skips injection when it returns `false` or `WP_Error`.

**Verification**: Log in as a subscriber; confirm admin-only ability absent from MCP tools list.

---

## Active Constraints

### SC-032-01 — check_permissions() result handling

`inject_mcp_tools()` MUST treat both `false` AND `WP_Error` returns from
`check_permissions()` as denied. Checking only `=== false` would miss the `WP_Error` path
(returned when the permission_callback itself is invalid or uncallable).

```php
if ( \is_wp_error( $perm_result ) || false === $perm_result ) {
    continue;
}
```

---

## Preserved Security Controls

- `AcrossAI_Ability_Library_Rest_Controller::check_permission()` — unchanged ✅
- Existing Checks 1–3 in `inject_mcp_tools()` — unchanged ✅
- `user_has_ability_access()` fail-open behavior — intentional per FR-011 ✅
