# Feature Specification: Remove the Logger module

**Feature Branch**: `040-remove-logs-module`
**Created**: 2026-07-01
**Status**: Draft
**Input**: User description: "Remove the ability-execution Logger module in its entirety from the acrossai-abilities-manager plugin. Delete the `{prefix}acrossai_ability_logs` BerlinDB table (dropped on opt-in uninstall; orphaned on existing installs), the `includes/Modules/Logger/` directory (8 PHP files), the two Logger utility classes (`AcrossAI_Logger_Formatter`, `AcrossAI_Logger_Source_Detector`), the Logs admin submenu (`admin/Partials/LogsMenu.php`) and its plugin-action-links entry, the log_retention_days Settings API field, all Logger boot wiring in `includes/Main.php` (5 hook registrations + REST controller + Table setup), the `src/js/index.js` + `LogsTable.js` + `logs-table.scss` front-end surface, the `webpack.config.js` js/logger + css/logger entries, the associated build/ outputs, and the 5 Logger PHPUnit test files (with phpunit.xml.dist cleanup). Add the `{prefix}acrossai_ability_logs` table drop + Action Scheduler cleanup to the existing `uninstall.php` delete-data gate. Update README.txt Unreleased changelog + Upgrade Notice. Memory hygiene per `PATTERN-MEMORY-SUPERSESSION-VS-ANNOTATION`: mark `DEC-HOOK-PARAM-EXTRACTION`, `DEC-DURATION-CALC-TIMESTAMPS`, `DEC-VARIADIC-CALLBACK-WRAP`, `DEC-LOGGER-NAMESPACE-MIGRATION` as Superseded (Feature 040); annotate `PATTERN-LOGGER-OPTION-FEED-FILTER` + `PATTERN-STAGE-NAMING` + `PATTERN-FEATURE-ASSET-SEPARATION` with forward-pointer notes. No cross-module consumers of the Logger exist outside of `Main.php` + Activator + `admin/Main.php` + `SettingsMenu.php` — no replacement is being wired; logging moves to a future companion plugin."

A full implementation breakdown (TASK-1 through TASK-8, file paths, exact code shapes, per-task manual verification checklist) is already authored at `docs/planning/040-remove-logs-module.md` and will feed `/speckit-plan` directly. This specification stays outcome-focused per Spec Kit conventions.

## User Scenarios & Testing *(mandatory)*

### User Story 1 — Admins keep using the plugin after Logger removal (Priority: P1)

After the site owner deploys the plugin release that removes the Logger module, every non-Logger admin feature continues to work without manual repair. The Abilities list, override editor, Access Control panel, Library submenu, Add-ons submenu, and Settings page (with two remaining fields — items-per-page and delete-data-on-uninstall) all render correctly. No PHP fatals occur on activation or any admin page visit. The site owner may notice the "Logs" submenu is gone and the "Log Retention Days" field no longer appears in Settings, but that removal is by design and documented in release notes.

**Why this priority**: This is the primary post-upgrade experience for existing customers. If any non-Logger feature regresses, the removal has caused unintended collateral damage — the exact opposite of the "surgical decommission" goal. Meeting this bar means the removal is invisible to the 95%+ of admins who never actively used execution logging.

**Independent Test**: On a WordPress install running the previous plugin version with the Logger active and configured, replace the plugin folder with the release built from this branch. Reactivate. Walk through: WP-Admin → AcrossAI → Abilities (loads, list renders), pick one ability → Edit → save an override (persists), open Access Control panel (loads, provider list populates), navigate to Library (loads), navigate to Add-ons (loads, Freemius bootstraps), open Settings (shows exactly two fields — per-page and delete-data). All six surfaces succeed within five minutes of clicking, with zero PHP notices and zero JavaScript console errors.

**Acceptance Scenarios**:

1. **Given** the previous plugin version is active with `acrossai_abilities_per_page = 25`, `acrossai_abilities_log_retention_days = 90`, `acrossai_abilities_uninstall_delete_data = 1`, **When** the admin upgrades to the Feature 040 release, **Then** the two preserved options (`_per_page`, `_uninstall_delete_data`) still return their pre-upgrade values from `get_option()`, and the Settings page displays those two fields with correct values.
2. **Given** the upgraded plugin is active, **When** the admin visits every non-Logger AcrossAI submenu (Abilities, Library, Add-ons, Settings), **Then** each page renders without PHP fatals, PHP notices, or JavaScript console errors.
3. **Given** the upgraded plugin is active, **When** the admin opens any ability's edit page and saves an override or an Access Control rule, **Then** the save persists correctly — the removal did not accidentally regress the Abilities or Access Control features.

---

### User Story 2 — Fresh installs never see the Logger surface (Priority: P2)

A site owner installing the plugin for the first time on a clean WordPress site never encounters any Logger artifact. No `Logs` submenu appears in the AcrossAI menu. No log-retention field appears in Settings. No `{prefix}acrossai_ability_logs` table is created on activation. No `wpb_ac_abilities_log_*` options are set. No JavaScript logger bundle is enqueued anywhere. The plugin is functionally identical to what a fresh install would have looked like if the Logger had never existed.

**Why this priority**: New customers should see the plugin's intended surface after removal — not vestigial references. Lower priority than P1 because fresh installs are typically fewer in number than upgrades on live sites, but essential for long-term coherence: an admin new to the plugin should not encounter Logger-related documentation, hooks, or references anywhere in the product.

**Independent Test**: On a clean WordPress install (never had this plugin), install and activate the Feature 040 release. Inspect: (a) `wp db query "SHOW TABLES LIKE '%ability_logs%'"` returns zero rows; (b) WP-Admin sidebar shows AcrossAI → Abilities, Library, Add-ons, Settings only (no Logs); (c) Settings page displays exactly two fields; (d) `find build/ -iname 'logger*'` on the installed plugin folder returns zero results.

**Acceptance Scenarios**:

1. **Given** a clean WordPress install with no prior version of this plugin, **When** the admin activates the Feature 040 release, **Then** no `{prefix}acrossai_ability_logs` table is created and no `Logs` submenu appears under the AcrossAI parent menu.
2. **Given** a freshly activated Feature 040 plugin, **When** the admin opens the AcrossAI → Settings page, **Then** exactly two form fields appear (items-per-page and delete-data-on-uninstall) — no third "Log Retention Days" field.

---

### User Story 3 — Companion plugin can consume ability-execution hooks without conflict (Priority: P3)

Because the Logger removal is scope narrowing — logging moves to a future dedicated companion plugin — the upstream WordPress hooks the Logger consumed (`wp_before_execute_ability`, `wp_after_execute_ability`, `wp_register_ability_args`, `mcp_adapter_pre_tool_call`) must remain unconditionally available. A future companion plugin (or any third-party consumer) can hook into these events at any priority without needing to coordinate with this plugin. Specifically: nothing in the abilities-manager after Feature 040 registers callbacks on those hooks, and the plugin does not filter, gate, or wrap them.

**Why this priority**: Forward-looking guarantee. No companion plugin exists today, so this is verification-only. But if the companion plugin ships later and finds that the abilities-manager has left a stub, filter, or "reserved priority" that blocks its consumption of these hooks, the entire scope-narrowing rationale collapses.

**Independent Test**: Verification-only. Run a WordPress test that registers a minimal callback on `wp_after_execute_ability` at priority 10 and confirm the callback fires without interference from the abilities-manager. Also verify: `grep -rn 'wp_before_execute_ability\|wp_after_execute_ability\|wp_register_ability_args\|mcp_adapter_pre_tool_call' includes/ admin/` returns zero matches after Feature 040 ships.

**Acceptance Scenarios**:

1. **Given** the abilities-manager Feature 040 release is active and a hypothetical companion plugin registers `add_action( 'wp_after_execute_ability', $companion_callback, 10, 3 )`, **When** any ability is executed, **Then** the companion's callback fires with the expected arguments — nothing in the abilities-manager intercepts, filters, or wraps the hook.
2. **Given** a developer greps the abilities-manager source for the four upstream hook names, **When** the grep completes, **Then** zero matches are returned — the plugin makes no assumption about these hooks post-Feature 040.

---

### Edge Cases

- **Legacy Action Scheduler queue with pending log-cleanup actions**: On an upgraded install, the Action Scheduler queue may still contain scheduled `acrossai_ability_logger_cleanup` actions from before the upgrade. Because the callback no longer exists post-upgrade, WordPress's `do_action()` becomes a no-op when those queued actions fire — no fatal, no side effect. The queued items remain in the AS database table as inert history until opt-in uninstall drains them (or admin manually clears via `wp action-scheduler cancel`).
- **Bookmarks pointing at the removed Logs admin URL**: Any admin who bookmarked `wp-admin/admin.php?page=acrossai-abilities-logs` receives the standard WordPress "page does not exist" admin response after upgrade. Consistent with Feature 038's handling of removed URLs.
- **External integrations polling the removed REST namespace**: Any external client hitting `GET /wp-json/acrossai-abilities-log/v1/logger/logs` receives 404 post-upgrade. Release notes document this so integration owners can migrate to the companion plugin (once available) or their own logging solution.
- **`acrossai_ability_logs_db_version` and `acrossai_abilities_log_retention_days` options on existing installs**: Both options remain in `wp_options` on upgraded installs. They are inert (nothing reads them). Dropped only on opt-in uninstall or via the README's manual cleanup SQL. Same "no backward compat" strategy Feature 039 used for the legacy `wpb_access_control` table.
- **Feature 006/017/019 memory entries**: Four Logger-consumer decisions (`DEC-HOOK-PARAM-EXTRACTION`, `DEC-DURATION-CALC-TIMESTAMPS`, `DEC-VARIADIC-CALLBACK-WRAP`, `DEC-LOGGER-NAMESPACE-MIGRATION`) become obsolete. Per `PATTERN-MEMORY-SUPERSESSION-VS-ANNOTATION`, they must be marked Superseded (entry body intact) rather than silently deleted, so future readers of `docs/memory/DECISIONS.md` can still see the historical reasoning and its supersession context.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The plugin MUST NOT create the `{prefix}acrossai_ability_logs` database table on activation. The activator MUST provision exactly two custom tables after Feature 040 (`{prefix}acrossai_abilities`, `{prefix}abilities_access_control`).
- **FR-002**: The plugin MUST NOT render a `Logs` submenu under the AcrossAI parent menu after Feature 040. Visiting `wp-admin/admin.php?page=acrossai-abilities-logs` MUST produce the standard WordPress "page does not exist" admin response.
- **FR-003**: The Settings page MUST expose exactly two options (items-per-page and delete-data-on-uninstall) after Feature 040. The log-retention field, its render callback, and its sanitize callback MUST be absent.
- **FR-004**: The plugin MUST NOT register the REST namespace `acrossai-abilities-log/v1` after Feature 040. Requests to `GET /wp-json/acrossai-abilities-log/v1/logger/logs` MUST return 404.
- **FR-005**: The plugin MUST NOT register any callback on the upstream ability-execution hooks after Feature 040. `grep`-ing `includes/`, `admin/`, and `src/` for the four hook names (`wp_before_execute_ability`, `wp_after_execute_ability`, `wp_register_ability_args`, `mcp_adapter_pre_tool_call`) MUST return zero matches.
- **FR-006**: The plugin MUST NOT migrate, copy, or otherwise touch data in an existing install's `{prefix}acrossai_ability_logs` table. That data is explicitly orphaned; admins who want cleanup follow the README's manual SQL or opt into delete-data at uninstall.
- **FR-007**: When the admin opts into data deletion on uninstall (via the existing `acrossai_abilities_uninstall_delete_data` setting), the plugin MUST drop `{prefix}acrossai_ability_logs`, delete `acrossai_ability_logs_db_version`, delete `acrossai_abilities_log_retention_days`, and unschedule any pending `acrossai_ability_logger_cleanup` Action Scheduler actions.
- **FR-008**: The two preserved plugin settings (`acrossai_abilities_per_page`, `acrossai_abilities_uninstall_delete_data`) MUST retain their pre-upgrade values across the Feature 040 release — no rewrite, no reset, no default-value substitution.
- **FR-009**: The plugin's other custom tables (`{prefix}acrossai_abilities`, `{prefix}abilities_access_control`) and their schema-version markers MUST be unaffected by Feature 040.
- **FR-010**: The upstream ability-execution hooks (`wp_before_execute_ability`, `wp_after_execute_ability`, `wp_register_ability_args`, `mcp_adapter_pre_tool_call`) MUST remain unconditionally available to any consumer. The plugin makes no assumption about their presence, priority, or arguments post-Feature 040.
- **FR-011**: The release notes accompanying Feature 040 MUST inform site owners that (a) the Logs submenu and log-retention setting are removed, (b) pre-existing `{prefix}acrossai_ability_logs` data is orphaned on their install and can be cleaned via provided manual SQL or opt-in uninstall, (c) any external integration polling the removed REST namespace will 404 and should migrate, and (d) ability-execution denials previously recorded via the Logs page are no longer captured — site owners relying on that observability signal should install a compatible logging plugin or hook `wp_after_execute_ability` directly (per plan-time security review finding SEC-003).
- **FR-012**: The four Logger-consumer decisions in `docs/memory/DECISIONS.md` (`DEC-HOOK-PARAM-EXTRACTION`, `DEC-DURATION-CALC-TIMESTAMPS`, `DEC-VARIADIC-CALLBACK-WRAP`, `DEC-LOGGER-NAMESPACE-MIGRATION`) MUST be marked Superseded (Feature 040) with their entry bodies kept intact. The three Logger-referenced patterns in `docs/memory/ARCHITECTURE.md` (`PATTERN-LOGGER-OPTION-FEED-FILTER`, `PATTERN-STAGE-NAMING`, `PATTERN-FEATURE-ASSET-SEPARATION`) MUST receive forward-pointer annotations noting the loss of the Logger consumer while preserving the pattern's general applicability.

### Key Entities *(include if feature involves data)*

- **Legacy Logs Storage** — the previous-version storage location for ability execution records: table `{prefix}acrossai_ability_logs`, schema-version option `acrossai_ability_logs_db_version`, log-retention setting `acrossai_abilities_log_retention_days`. Explicitly orphaned on existing installs post-Feature 040. Neither read nor written by the plugin. Dropped only on opt-in uninstall.
- **Preserved Settings** — the two Settings API options that survive Feature 040 unchanged: `acrossai_abilities_per_page` (list pagination) and `acrossai_abilities_uninstall_delete_data` (opt-in cleanup gate). Option names, stored values, and Settings page presentation are unchanged.
- **Preserved Custom Tables** — the two plugin-owned tables that remain after Feature 040: `{prefix}acrossai_abilities` (ability overrides, owned by the Abilities module) and `{prefix}abilities_access_control` (per-consumer access-control storage from Feature 039, owned via the wpb-access-control library).
- **Ability Execution Hooks** — the four upstream events the removed Logger consumed. All four are provided by WordPress core / the Abilities API / the MCP Adapter, not by this plugin. Their existence and semantics are independent of Feature 040 and remain available for any consumer.
- **Retired Memory Entries** — four Decisions in `docs/memory/DECISIONS.md` that are marked Superseded by Feature 040 (bodies preserved for historical traceability). Three Patterns in `docs/memory/ARCHITECTURE.md` that retain conceptual validity but receive forward-pointer annotations noting their original Logger consumer was retired.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: After the Feature 040 release is deployed to a representative test install, 100% of the six non-Logger admin surfaces (Abilities list, ability edit + save, Access Control panel + save, Library, Add-ons, Settings) work on first attempt with zero PHP notices and zero JavaScript console errors.
- **SC-002**: On a fresh plugin activation against a clean WordPress install, zero `{prefix}acrossai_ability_logs`-named tables exist, zero `Logs` submenu items appear under the AcrossAI parent menu, and zero `logger*`-named files exist under the built `build/` directory — verified within one admin request after activation.
- **SC-003**: After upgrading an existing install with all three pre-existing settings set to non-default values, 100% of the two preserved settings' values carry across the upgrade without admin intervention.
- **SC-004**: On an upgraded install (pre-existing logs table + option present), 100% of the legacy Logger artifacts (table + two options + AS queue entries) remain untouched by the upgrade itself — Feature 040 does not add code paths that read, drop, or modify them outside of the opt-in uninstall gate.
- **SC-005**: After uninstalling the plugin with the delete-data setting enabled, zero rows of `{prefix}acrossai_ability_logs`, zero `wp_options` rows for `acrossai_ability_logs_db_version`, zero rows for `acrossai_abilities_log_retention_days`, and zero pending Action Scheduler actions for `acrossai_ability_logger_cleanup` remain in the database.
- **SC-006**: Removing the vendor directory entirely and visiting the WP admin continues to produce the existing fail-open admin notice (Feature 039's `Main::load_composer_dependencies()` guard) within one page load — no fatal error reaches the browser, no new degraded-mode path introduced by Feature 040.
- **SC-007**: The number of direct entries in the plugin's composer `require` block is unchanged by Feature 040 — zero additions, zero removals.
- **SC-008**: A test WordPress installation that registers a minimal callback on `wp_after_execute_ability` at priority 10 receives that callback firing with the expected arguments 100% of the time — the abilities-manager after Feature 040 does not intercept, filter, or wrap the hook.

## Assumptions

- The scope-narrowing rationale (logging moves to a future companion plugin) is the accepted framing. No admin-facing replacement UI is provided in this plugin — admins who need execution logging install the companion plugin (or an alternative) once it becomes available. If a site owner needs logging before the companion plugin ships, they either stay on the pre-Feature-040 release or wire up their own consumer of the upstream hooks.
- The `PATTERN-MODULE-DECOMMISSION` 8-step protocol from `docs/memory/ARCHITECTURE.md` is the canonical reference for the deletion order. Feature 040 is the second example of the "no-replacement" variant of that pattern (after Feature 012's Sitewide decommission, which had a same-plugin replacement).
- The `PATTERN-MEMORY-SUPERSESSION-VS-ANNOTATION` protocol governs the memory hygiene step. Retired decisions must be marked Superseded (entry bodies kept intact); related patterns that lose their consumer but stay conceptually valid get forward-pointer annotations. Silent deletion is not permitted per project convention.
- The `PATTERN-UNINSTALL-DATA-GATE` protocol governs the uninstall additions. All new destructive operations MUST land inside the existing `if ( $acrossai_delete_data )` block. Adding cleanup outside the gate would trigger `BUG-UNINSTALL-OPTIONS-OUTSIDE-GATE`.
- The upstream ability-execution hooks (`wp_before_execute_ability`, `wp_after_execute_ability`, `wp_register_ability_args`, `mcp_adapter_pre_tool_call`) are stable contracts owned by WordPress core, the Abilities API, and the MCP Adapter plugin respectively. Feature 040 makes no assumption about their evolution and does not attempt to preserve or shim their behavior.
- The "no backward compatibility" data strategy (orphan legacy tables + options on upgrade; drop only on opt-in uninstall) matches Feature 039's precedent and is the project's default for post-hoc removals. Release notes are the primary communication vehicle.
- `phpunit.xml.dist` uses explicit `<file>` entries (per `BUG-PHPUNIT-AUTODISCOVERY-PREFIX`); stale entries pointing at deleted test files would fail PHPUnit hard-error, so xml.dist cleanup must happen atomically with test file deletion.
- The two Logger utility files (`AcrossAI_Logger_Formatter`, `AcrossAI_Logger_Source_Detector`) are logically Logger-scoped despite living in `includes/Utilities/`. A pre-deletion grep will confirm no cross-module consumer references them; if any does surface, `PATTERN-HELPER-DELETION-GREP-FIRST` requires keeping the utility rather than causing a fatal.
