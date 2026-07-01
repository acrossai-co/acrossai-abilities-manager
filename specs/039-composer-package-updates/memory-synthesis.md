# Memory Synthesis

## Current Scope

Feature 039 — Composer Package Updates. Three surfaces change:

1. **Composer manifest** — drop `acrossai-co/addons-page`, bump `acrossai-co/main-menu` to `^0.0.7` (which now bundles AddonsPage), bump `wpboilerplate/wpb-access-control` to `^2.0.0` (per-consumer tables).
2. **AddonsPage call site** (`includes/Main.php:316-348`) — drop the first positional `$menu_slug` argument; class name `\AcrossAI_Addon\AddonsPage` is preserved upstream.
3. **Access Control integration** (`AcrossAI_Abilities_Access_Control`, `AcrossAI_Activator`, `uninstall.php`) — introduce `TABLE_SLUG = 'abilities'`, pass it to `AccessControlManager` and `RuleTable`, point uninstall at `{prefix}abilities_access_control` + `wpb_ac_abilities_db_version`. No backward compatibility for the legacy shared table.

Affected modules: Admin/Composer (Main.php call site), Abilities/AccessControl integration wrapper, Activator, Uninstaller.

## Relevant Decisions

- **DEC-EXTERNAL-PACKAGE-HOOK-CTOR** (Reason: TASK-2 edits the canonical AddonsPage call site; the `class_exists`+constructor pattern is preserved verbatim, only the argument list changes. Status: Active accepted deviation. Source: DECISIONS.md)
- **DEC-FAIL-OPEN-NOTICE** (Reason: Both the AddonsPage `class_exists` guard and the existing `AcrossAI_Abilities_Access_Control::maybe_show_library_notice` rely on this; the upgrade must preserve the `manage_options` capability gate. Status: Active. Source: DECISIONS.md)
- **DEC-FREEMIUS-PER-PLUGIN-INIT** (Reason: AddonsPage continues to receive per-plugin Freemius creds (`fs_product_id`, `fs_public_key`, `fs_slug`) — the move to main-menu does not centralize them; the new transitive `freemius/wordpress-sdk ^2.0` is consistent with this decision. Status: Active. Source: DECISIONS.md)
- **DEC-REVALIDATE-SECURITY-POST-UPGRADE** (Reason: wpb-access-control v1.6.0→v2.0.0 is a library upgrade; SEC-03 (multisite prefix), SEC-04 (strict comparison in AC checks), DEC-PERM-CB, and DEC-FAIL-OPEN-NOTICE must all be re-validated against the new REST namespace `/wpb-ac/v1/abilities/...`. Status: Active. Source: DECISIONS.md)
- **DEC-STABLE-UPGRADE-WINDOW-INTERNAL-ORG** (Reason: `acrossai-co/main-menu` is an internal-org package without a v1.0.0 yet; v0.0.7 fits the established "internal-org exemption with audit + SHA pin in lockfile" carve-out from Feature 038. Status: Active. Source: DECISIONS.md)

## Active Architecture Constraints

- **PATTERN-ADMIN-NOTICE-SELF-CONTAINED** (Reason: The existing try/catch wrapper around `new \AcrossAI_Addon\AddonsPage(...)` registers an `admin_notices` closure that already complies — only WP globals inside, `manage_options` gate, `esc_html()`. TASK-2 must not regress this. Source: ARCHITECTURE.md)
- **PATTERN-UNINSTALL-DATA-GATE** (Reason: TASK-5's table-drop + option-delete must stay inside the existing `if ( $acrossai_delete_data )` block in `uninstall.php`. The pattern is already followed — preserve it. Source: ARCHITECTURE.md)
- **PATTERN-VENDOR-ASSET-FAMILY-HANDLE** (Reason: `admin/Main.php` enqueues the AC library's React bundle under an `acrossai-...` handle name. v2.0.0 keeps the bundle path `vendor/wpboilerplate/wpb-access-control/assets/build/`; the handle naming must remain unchanged. Source: ARCHITECTURE.md)
- **DEC-TABLE-SOFT-SINGLETON** (Reason: TASK-4's `( new RuleTable( ... ) )->maybe_upgrade();` direct instantiation in the activator is explicitly sanctioned — BerlinDB Table subclasses stay soft-singleton when activation or tests instantiate them directly. Source: DECISIONS.md)
- **ARCH-ZERO-CODE-DEPENDENCY-UPGRADE** (Reason: The pattern frames our preferred upgrade shape (singleton + service locator → no plugin-code change). Feature 039 deviates by necessity (breaking constructor signatures); see Conflict Warnings. Source: ARCHITECTURE.md)

## Accepted Deviations

- **DEC-EXTERNAL-PACKAGE-HOOK-CTOR** (Reason: AddonsPage constructor still self-registers WordPress hooks and is still instantiated outside the Loader inside the `class_exists` block — the deviation continues unchanged. Status: Accepted-Deviation, permanent. Source: DECISIONS.md)

## Relevant Security Constraints

- **SEC-03** (Reason: New table `{prefix}abilities_access_control` inherits the BerlinDB per-site prefix. Verification: confirm `RuleTable::$global` is not `true` upstream — multisite isolation must be preserved across the v2 upgrade. Source: security-constraints.md)
- **SEC-04** (Reason: Strict type comparison in AC checks. The library upgrade changes REST namespace but not the `user_has_access()` contract; re-confirm strict comparison behavior in v2.0.0. Source: security-constraints.md)
- **DEC-REVALIDATE-SECURITY-POST-UPGRADE** (cross-referenced under Decisions; treat as a mandatory plan-time gate. Source: DECISIONS.md / security-constraints.md)

## Related Historical Lessons

- **BUG-EXTERNAL-PACKAGE-CTOR-SILENT** (Reason: The existing AddonsPage try/catch already adds an `admin_notices` callback (not silent) — TASK-2 must preserve that callback. A regression would re-introduce the silent-failure bug.)
- **BUG-UNINSTALL-OPTIONS-OUTSIDE-GATE** (Reason: TASK-5 changes the option key from `wpb_access_control_db_version` to `wpb_ac_abilities_db_version`. The new `\delete_option()` line MUST stay inside the `$acrossai_delete_data` gate — a copy-paste outside would wipe the option on every uninstall regardless of the user's opt-in.)
- **Worklog 2026-06-23 (Feature 036)** — last `wpboilerplate/wpb-access-control` bump (1.2.1→1.6.0); blueprint for the dependency-update workflow.
- **Worklog 2026-06-30 (Feature 038)** — established `acrossai-co/main-menu` as the shared menu host. Feature 039 directly extends this by absorbing addons-page into the same package.

## Conflict Warnings

- **Soft conflict — ARCH-ZERO-CODE-DEPENDENCY-UPGRADE vs. Feature 039 reality**: The architecture pattern says "Singleton + service locator pattern enables dependency upgrades without plugin code changes." Feature 039 cannot satisfy this — both upstream packages ship genuinely breaking constructor signatures (AddonsPage drops `$menu_slug`; AccessControlManager/RuleTable require `$table_slug`). The plugin must change. **Resolution**: proceed with code edits; the pattern remains aspirational and Feature 039 is documented as the second upstream-driven exception (Feature 036 was the first). Capture as a refinement of the pattern if needed post-implementation.
- **Soft conflict — implicit "preserve user data on upgrade" norm vs. explicit "no backward compatibility" instruction**: Feature 039's spec FR-005 + FR-012 explicitly orphan the legacy `{prefix}wpb_access_control` table. No memory entry mandates migration, but the project's general posture (e.g., uninstall data gate, settings preservation) favors data continuity. **Resolution**: proceed per explicit user instruction; release-note communication (spec FR-012) is the mitigation; an admin notice on first run could be considered if migration friction emerges in QA.

## Retrieval Notes

- Index entries considered: ~20 (cap respected)
- Source sections read: INDEX.md (full); spec.md (full, this feature); docs/planning/039-composer-package-updates.md (full, reference implementation breakdown). No durable memory file was opened in full — index entries were sufficient.
- Feature memory file (`specs/039-composer-package-updates/memory.md`): not present; not required.
- Budget: 5/5 decisions, 5/5 architecture constraints, 1/3 deviations, 3/3 security constraints, 2/3 bug patterns, 2/2 worklog items, ~870 words. Within all caps.
- Optimizer: not enabled (`.specify/extensions/memory-md/config.yml` absent); markdown-only index-first retrieval used.
