# Memory Synthesis

## Current Scope

Feature 032 fixes three post-launch bugs discovered after Feature 031 shipped:
(A) BerlinDB Query subclass contract violations causing PHP Fatals in the Logger module;
(B) MCP tools injection bypassing the ability's own `permission_callback` due to fail-open
AC rule behavior; (C) Library submenu appearing in the wrong position.

---

## Relevant Decisions

- **BUG-BERLINDDB-QUERY-OVERRIDE-COMPAT** — BerlinDB Query overrides must match parent
  visibility (`public`) and full signature. PHPStan L8 catches these. (Source: BUGS.md, Feature 032)
- **BUG-WP-ABILITY-CHECK-PERMISSIONS** — `WP_Ability` has no `get_permission_callback()`;
  always use `check_permissions()`. Non-existent method probes silently pass. (Source: BUGS.md, Feature 032)
- **BUG-INJECT-MCP-TOOLS-PERMISSION-BYPASS** — `inject_mcp_tools()` needs `check_permissions()`
  as 4th gate; AC rules are fail-open. (Source: BUGS.md, Feature 032)

---

## Active Architecture Constraints

- **AC-HOOKS-MAIN** — All submenu registrations wired in `includes/Main.php::define_admin_hooks()`.
  Registration order = display order within the same hook priority. (Source: CONSTITUTION.md §I)
- **Boot Flow Rule** — `inject_mcp_tools()` is registered inside `boot()` conditionally (PATH B).
  Check 4 added inside the existing injection loop — no new hook registration. (Source: CONSTITUTION.md §I)

---

## Security Constraints

- **SC-032-01** — `WP_Ability::check_permissions()` result must treat both `false` AND
  `WP_Error` as denied. Checking only `false` misses the error case.
- AC rules are fail-open by design (FR-011); `check_permissions()` is the authoritative gate
  that cannot be bypassed by absence of a rule.

---

## Related Historical Lessons

- **BUG-BERLINDDB-QUERY-PRIVATE-CTOR** — `AcrossAI_Abilities_Query` private constructor
  caught by arch review in Feature 029. BerlinDB contract violations recur — PHPStan is the
  prevention tool. (Source: BUGS.md)
- **BUG-PERMISSION-CALLBACK-TRUTHY-RESPONSE** — `WP_REST_Response` from permission_callback
  is truthy → access granted. Similar pattern: permission check returning a non-bool silently
  grants access. (Source: BUGS.md, Feature 028)

---

## Conflict Warnings

None. All three fixes are independent, low-risk, and do not interact with each other.

---

## Retrieval Notes

- Index entries considered: BerlinDB bugs, MCP pass-as-tool, permission patterns
- Full memory read: false (index-first, targeted)
- Optimizer: disabled (markdown-only flow)
