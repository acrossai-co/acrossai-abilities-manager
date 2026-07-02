# Planning: Remove the Logger module (Feature 040)

Remove the entire ability-execution logging feature — the BerlinDB table
`{prefix}acrossai_ability_logs`, the Logger PHP module and its two utility
classes, the REST namespace `acrossai-abilities-log/v1`, the Logs admin
submenu and its React table UI, the Settings API log-retention field, the
Action Scheduler cleanup job, and the associated PHPUnit tests. Logging is
being extracted to a dedicated companion plugin (`acrossai-ability-logs` or
equivalent) that a site owner installs alongside this plugin when they need
per-ability execution logging.

The upstream hooks the Logger currently consumes — `wp_before_execute_ability`,
`wp_after_execute_ability`, `wp_register_ability_args`, and
`mcp_adapter_pre_tool_call` — are provided by WordPress core, the Abilities
API, and the MCP Adapter respectively. They remain available for the
companion plugin (or any other consumer) at their existing hook signatures
and priorities. Nothing about this feature's scope prevents the companion
plugin from re-implementing identical behavior.

Following Feature 039's precedent, the removal is **without backward
compatibility**: legacy `{prefix}acrossai_ability_logs` tables and existing
option values are orphaned on active installs and dropped only on opt-in
uninstall. Release notes document the removal and point admins at manual
cleanup SQL.

---

## Speckit Workflow

```markdown
# 1. Branch
/speckit.git.feature "remove-logs-module"

# 2. Specify
/speckit.specify "Remove the ability-execution Logger module in its entirety
from the acrossai-abilities-manager plugin. Delete the {prefix}acrossai_ability_logs
BerlinDB table (dropped on opt-in uninstall; orphaned on existing installs),
the includes/Modules/Logger/ directory (8 PHP files), the two Logger utility
classes (AcrossAI_Logger_Formatter, AcrossAI_Logger_Source_Detector), the
Logs admin submenu (admin/Partials/LogsMenu.php) and its plugin-action-links
entry, the log_retention_days Settings API field, all Logger boot wiring in
includes/Main.php (5 hook registrations + REST controller + Table setup),
the src/js/index.js + LogsTable.js + logs-table.scss front-end surface, the
webpack.config.js js/logger + css/logger entries, the associated build/ outputs,
and the 5 Logger PHPUnit test files (with phpunit.xml.dist cleanup). Add the
{prefix}acrossai_ability_logs table drop + Action Scheduler cleanup to the
existing uninstall.php delete-data gate. Update README.txt Unreleased changelog
+ Upgrade Notice. Memory hygiene per PATTERN-MEMORY-SUPERSESSION-VS-ANNOTATION:
mark DEC-HOOK-PARAM-EXTRACTION, DEC-DURATION-CALC-TIMESTAMPS, DEC-VARIADIC-CALLBACK-WRAP,
DEC-LOGGER-NAMESPACE-MIGRATION as Superseded (Feature 040); annotate
PATTERN-LOGGER-OPTION-FEED-FILTER + PATTERN-STAGE-NAMING + PATTERN-FEATURE-ASSET-SEPARATION
with forward-pointer notes. No cross-module consumers of the Logger exist
outside of Main.php + Activator + admin/Main.php + SettingsMenu.php — no
replacement is being wired; logging moves to a future companion plugin."
```

### Detailed Description for `/speckit.specify`

> **Before writing a single line of code, read and internalize all four of
> these governing documents in full:**
>
> 1. `.agents/skills/wp-plugin-development/SKILL.md` — and every file under
>    `.agents/skills/wp-plugin-development/references/` (boot-flow, security,
>    structure, hooks, rest-api).
> 2. `.specify/memory/CONSTITUTION.md` — pay special attention to §I Modular
>    Architecture (module boundaries), §II WordPress Standards, §V Integration
>    Resilience, §VI DRY, §VII Definition of Done.
> 3. `AGENTS.md` — the singleton pattern block, hook registration rules, and
>    the Before Commit Checklist.
> 4. `docs/memory/ARCHITECTURE.md` — specifically `PATTERN-MODULE-DECOMMISSION`
>    (8-step ordered decommission), `PATTERN-MEMORY-SUPERSESSION-VS-ANNOTATION`
>    (retire decisions vs. annotate patterns), `PATTERN-HELPER-DELETION-GREP-FIRST`
>    (grep-before-delete a private helper), and `PATTERN-UNINSTALL-DATA-GATE`
>    (uninstall.php wraps destructive ops in opt-in delete-data gate).
>
> Every decision — file deletion order, uninstall additions, memory
> supersession vs. annotation — must be justified against the above. If a
> choice is not explicitly covered, default to the most restrictive
> interpretation of the Constitution. Do not write code that would fail any
> Definition-of-Done gate: PHPStan level 8, PHPCS, security review, all
> `__()` calls using the correct text domain `'acrossai-abilities-manager'`,
> and `npm run validate-packages`.
>
> Public API artifacts to enumerate before deletion:
> `\AcrossAI_Abilities_Manager\Includes\Modules\Logger\AcrossAI_Ability_Logger`,
> `\AcrossAI_Abilities_Manager\Includes\Modules\Logger\Database\AcrossAI_Ability_Logs_Table`,
> `\AcrossAI_Abilities_Manager\Includes\Modules\Logger\Rest\AcrossAI_Logger_Controller`,
> `\AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Logger_Formatter`,
> `\AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Logger_Source_Detector`,
> `\AcrossAI_Abilities_Manager\Admin\Partials\LogsMenu`. Cache group
> `acrossai-ability-logs`. REST namespace `acrossai-abilities-log/v1`. Option
> keys `acrossai_abilities_log_retention_days` + `acrossai_ability_logs_db_version`.
> Action Scheduler hook `acrossai_ability_logger_cleanup`. WordPress hook
> priorities the Logger owned: `mcp_adapter_pre_tool_call` P5,
> `wp_before_execute_ability` P10, `wp_after_execute_ability` P10,
> `wp_register_ability_args` P100001, `acrossai_ability_logger_cleanup` P10,
> `plugins_loaded` (schedule_cleanup) P20.
>
> ---
>
> **TASK-1 — Remove REST + Logs admin surface**
>
> Files: `includes/Main.php`, `admin/Main.php`, `admin/Partials/LogsMenu.php`
>
> Read `.agents/skills/wp-plugin-development/references/boot-flow.md`,
> `references/hooks.md`, and `PATTERN-ENQUEUE-PAGE-GUARD` before editing.
>
> In `includes/Main.php` at lines 300–303, delete the block that instantiates
> `LogsMenu::instance()` and hooks `register_submenu` on `admin_menu`. In the
> same file at line 412, delete the `add_action( 'rest_api_init',
> $logger_rest_controller, 'register_routes' )` line and the
> `$logger_rest_controller = AcrossAI_Logger_Controller::instance();`
> assignment above it (if present).
>
> In `admin/Main.php`, delete every Logger-related surface:
> - `$logger_asset_file` private property (~lines 80–86)
> - The `require ... build/js/logger.asset.php` block (~lines 122–124)
> - The `is_logs_page`-guarded CSS enqueue block (~lines 152–166)
> - The `is_logs_page`-guarded JS enqueue + `wp_localize_script` block
>   (~lines 214–235)
> - The `is_logs_page()` private helper method (~lines 332–334)
> - The `Logs` entry in the plugin action links array (~lines 377–401)
>
> Delete `admin/Partials/LogsMenu.php` entirely. No consumers remain after
> the Main.php + admin/Main.php edits.
>
> Do not add fallback "legacy Logs URL" redirects. Bookmarks pointing at
> `wp-admin/admin.php?page=acrossai-abilities-logs` receive the WordPress
> default "page not found" admin response, consistent with Feature 038's
> Settings-URL constraint.
>
> ---
>
> **TASK-2 — Remove Settings API log_retention_days field**
>
> Files: `admin/Partials/SettingsMenu.php`
>
> Read `CONSTITUTION.md §IV Security First` and `DEC-SETTINGS-API-DEVIATION`
> before editing. The Settings page hosts three options today
> (`_per_page`, `_log_retention_days`, `_uninstall_delete_data`); this task
> removes exactly the middle one.
>
> Delete the three related calls:
> - `register_setting( 'acrossai-settings', 'acrossai_abilities_log_retention_days', ... )`
>   (~line 113 area)
> - `add_settings_section` for the Log section (~line 165 area)
> - `add_settings_field` for the Log Retention Days field + its render callback
>   (~lines 239 + 241)
>
> Also delete the corresponding `sanitize_log_retention_days()` +
> `render_log_retention_days_field()` methods on the class. Keep the two
> remaining settings (per-page, uninstall-delete-data) fully intact,
> including their sanitize + render methods and their existing text domain
> strings.
>
> Do NOT delete the option row from the DB during this task — that lives in
> uninstall.php per TASK-5.
>
> ---
>
> **TASK-3 — Remove Logger boot wiring from `includes/Main.php`**
>
> Files: `includes/Main.php`
>
> Read `CONSTITUTION.md §I Boot Flow Rule`, `ARCH-ADV-001` (accepted
> deviation), and `AC-HOOKS-MAIN` (only Main.php calls the loader) before
> editing.
>
> Delete lines 400–412 (the "Ability Execution Logger" block) in one atomic
> edit:
> - `AcrossAI_Ability_Logs_Table::instance();` — table setup call
> - `$logger = AcrossAI_Ability_Logger::instance();` — logger singleton
> - `add_filter( 'mcp_adapter_pre_tool_call', $logger, 'capture_mcp_server_id', 5, 4 );`
> - `add_action( 'wp_before_execute_ability', $logger, 'start_pending_entry', 10, 2 );`
> - `add_action( 'wp_after_execute_ability', $logger, 'finish_pending_entry', 10, 3 );`
> - `add_filter( 'wp_register_ability_args', $logger, 'wrap_permission_callback', 100001, 2 );`
> - `add_action( 'acrossai_ability_logger_cleanup', $logger, 'cleanup_old_logs', 10, 0 );`
> - `add_action( 'plugins_loaded', $logger, 'schedule_cleanup', 20, 0 );`
> - The `$logger_rest_controller = AcrossAI_Logger_Controller::instance();`
>   + `add_action( 'rest_api_init', ... )` pair (may already be handled in
>   TASK-1 depending on the exact adjacent-line grouping).
>
> Also delete the file-top `use` imports for the four Logger FQCNs at the
> top of `includes/Main.php` (grep after the block deletion to confirm none
> are referenced elsewhere in the file).
>
> Do not add a defensive `class_exists( AcrossAI_Ability_Logger::class )`
> guard around the deletion — after this task the class does not exist and
> should not be probed.
>
> ---
>
> **TASK-4 — Delete Logger module + utilities**
>
> Files: `includes/Modules/Logger/**` (8 files), `includes/Utilities/AcrossAI_Logger_Formatter.php`,
> `includes/Utilities/AcrossAI_Logger_Source_Detector.php`
>
> Read `PATTERN-MODULE-DECOMMISSION` and `PATTERN-HELPER-DELETION-GREP-FIRST`
> before deleting.
>
> Before deletion, run this grep to confirm no cross-module consumer of the
> two utilities remains:
> ```
> grep -rEn '(AcrossAI_Logger_Formatter|AcrossAI_Logger_Source_Detector)' \
>     --include='*.php' --include='*.js' \
>     includes/ admin/ src/ tests/ acrossai-abilities-manager.php uninstall.php
> ```
> Expected result: matches ONLY inside `includes/Modules/Logger/**` and
> `includes/Utilities/AcrossAI_Logger_*.php` themselves + their PHPUnit
> tests. If any consumer surfaces outside those paths, KEEP the utility
> per `PATTERN-HELPER-DELETION-GREP-FIRST` and lift the Logger-boundary
> lessons from BUGS.md into the utility's docblock.
>
> If the grep is clean, delete:
> - `includes/Modules/Logger/AcrossAI_Ability_Logger.php`
> - `includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Table.php`
> - `includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Schema.php`
> - `includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Row.php`
> - `includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Query.php`
> - `includes/Modules/Logger/Rest/AcrossAI_Logger_Controller.php`
> - `includes/Modules/Logger/Rest/AcrossAI_Logger_Logs_Controller.php`
> - The `includes/Modules/Logger/` directory itself (and any `Database/`,
>   `Rest/` subdirectories)
> - `includes/Utilities/AcrossAI_Logger_Formatter.php`
> - `includes/Utilities/AcrossAI_Logger_Source_Detector.php`
>
> After deletion, run `composer dump-autoload` to regenerate the PSR-4
> classmap.
>
> ---
>
> **TASK-5 — Activator + uninstall cleanup**
>
> Files: `includes/AcrossAI_Activator.php`, `uninstall.php`
>
> Read `PATTERN-UNINSTALL-DATA-GATE`, `BUG-UNINSTALL-OPTIONS-OUTSIDE-GATE`,
> and `CONSTITUTION.md §V Integration Resilience` before editing.
>
> In `includes/AcrossAI_Activator.php`:
> - Remove line 14 `use AcrossAI_Abilities_Manager\Includes\Modules\Logger\Database\AcrossAI_Ability_Logs_Table;`
> - Remove line 43 `( new AcrossAI_Ability_Logs_Table() )->maybe_upgrade();`
> - Update the docblock at lines 34–35 to drop the `{prefix}acrossai_ability_logs`
>   mention. Two sibling table-create lines remain
>   (`{prefix}acrossai_abilities` + `{prefix}abilities_access_control`),
>   consistent with Feature 039's activator shape.
>
> In `uninstall.php`, INSIDE the existing `if ( $acrossai_delete_data ) { ... }`
> gate (per `PATTERN-UNINSTALL-DATA-GATE`), add three new lines:
> ```php
> $acrossai_logs_table = $wpdb->prefix . 'acrossai_ability_logs';
> // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
> $wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $acrossai_logs_table ) );
> \delete_option( 'acrossai_ability_logs_db_version' );
>
> // Action Scheduler cleanup — safe when AS is inactive.
> if ( function_exists( 'as_unschedule_all_actions' ) ) {
>     as_unschedule_all_actions( 'acrossai_ability_logger_cleanup' );
> }
> ```
> Place the block immediately after the existing `_abilities_access_control`
> drop from Feature 039 to keep table-drop order visually grouped. The
> existing `\delete_option( 'acrossai_abilities_log_retention_days' );` line
> inside the gate stays (already there per inventory).
>
> Do NOT add cleanup outside the gate. Do NOT auto-drop the table on upgrade
> — the "no backward compatibility" strategy per Feature 039 leaves the
> orphaned table in place on existing installs; admins who want cleanup
> follow the README's manual SQL or opt into delete-data at uninstall.
>
> ---
>
> **TASK-6 — Front-end (JS/SCSS/build) cleanup**
>
> Files: `src/js/index.js`, `src/js/components/LogsTable.js`, `src/scss/logs-table.scss`,
> `webpack.config.js`, `build/js/logger*`, `build/css/logger*`
>
> Read `PATTERN-ASSET-DECOMMISSION-ORDER` (memory: PHP include first → webpack
> entry + source files → clean build) before editing. TASK-2 already removed
> the PHP asset-enqueue side; this task removes the JS source + webpack
> entries + build outputs.
>
> Delete source files:
> - `src/js/index.js`
> - `src/js/components/LogsTable.js`
> - `src/scss/logs-table.scss`
>
> In `webpack.config.js` (lines 79–81 area), delete the two entries:
> ```js
> 'js/logger': path.resolve( __dirname, 'src/js', 'index.js' ),
> 'css/logger': path.resolve( __dirname, 'src/scss', 'logs-table.scss' ),
> ```
>
> Run `npm run build`. Verify the regenerated `build/` no longer contains
> `logger.*` outputs. If any stale files remain (webpack does not
> auto-delete removed entries), manually delete:
> - `build/js/logger.js`
> - `build/js/logger.asset.php`
> - `build/js/logger.css`
> - `build/js/logger-rtl.css`
> - `build/css/logger.css`
> - `build/css/logger.asset.php`
> - `build/css/logger-rtl.css`
>
> ---
>
> **TASK-7 — Tests + phpunit.xml.dist cleanup**
>
> Files: `tests/phpunit/Modules/Logger/**`, `tests/phpunit/Utilities/AcrossAI_Logger_Source_Detector_Test.php`,
> `phpunit.xml.dist`
>
> Read `BUG-PHPUNIT-AUTODISCOVERY-PREFIX` before editing. Every PHPUnit
> `<file>` entry in `phpunit.xml.dist` is explicit (project uses the
> `Test_*.php` prefix pattern that PHPUnit 10 does NOT auto-discover), so
> stale entries would keep the runner referencing deleted files.
>
> Delete the 5 test files:
> - `tests/phpunit/Modules/Logger/AcrossAI_Ability_Logger_Test.php`
> - `tests/phpunit/Modules/Logger/Database/AcrossAI_Ability_Logs_Table_Test.php`
> - `tests/phpunit/Modules/Logger/Database/AcrossAI_Logger_Query_Test.php`
> - `tests/phpunit/Modules/Logger/Rest/AcrossAI_Logger_Logs_Controller_Test.php`
> - `tests/phpunit/Utilities/AcrossAI_Logger_Source_Detector_Test.php`
> - The `tests/phpunit/Modules/Logger/` directory (with subdirs) itself.
>
> In `phpunit.xml.dist`, remove every `<file>` entry that references one of
> the paths above from whichever `<testsuite>` block it lives in. Do NOT
> remove or rename the surviving suites — the abilities-unit / library-unit
> / includes-unit blocks stay.
>
> Confirm `composer test` reports the reduced test-count (was 103) and all
> remaining tests pass.
>
> ---
>
> **TASK-8 — Release notes + memory hygiene**
>
> Files: `README.txt`, `docs/memory/DECISIONS.md`, `docs/memory/ARCHITECTURE.md`,
> `docs/memory/WORKLOG.md`, `docs/memory/INDEX.md`
>
> Read `PATTERN-MEMORY-SUPERSESSION-VS-ANNOTATION` before editing. Retired
> decisions must be marked Superseded (with the entry body kept intact);
> related patterns that lose their consumer but stay conceptually valid get
> a forward-pointer annotation, never silent deletion.
>
> In `README.txt`, extend the Unreleased changelog with a new bullet
> announcing the Logger removal, and add a corresponding Upgrade Notice
> line. Include manual cleanup SQL:
> ```sql
> DROP TABLE {prefix}acrossai_ability_logs;
> DELETE FROM {prefix}options WHERE option_name IN
>   ('acrossai_abilities_log_retention_days', 'acrossai_ability_logs_db_version');
> ```
> Reference the fact that any external integration polling
> `/wp-json/acrossai-abilities-log/v1/logger/logs` now 404s and any bookmark
> to `wp-admin/admin.php?page=acrossai-abilities-logs` receives the standard
> WordPress "page not found" admin response.
>
> In `docs/memory/DECISIONS.md`, mark the following four decisions as
> **Superseded (Feature 040)** — keep the entry body intact per
> `PATTERN-MEMORY-SUPERSESSION-VS-ANNOTATION`:
> - `DEC-HOOK-PARAM-EXTRACTION` (Logger hook adapter — no longer consumed)
> - `DEC-DURATION-CALC-TIMESTAMPS` (Logger duration measurement — no longer consumed)
> - `DEC-VARIADIC-CALLBACK-WRAP` (Logger permission_callback wrapper at P100001)
> - `DEC-LOGGER-NAMESPACE-MIGRATION` (Logger REST namespace — no longer registered)
>
> In `docs/memory/ARCHITECTURE.md`, annotate the following patterns with a
> forward-pointer note (they lose their Logger consumer but the pattern
> itself is still valid for other Settings-API or module contexts):
> - `PATTERN-LOGGER-OPTION-FEED-FILTER` — annotate: "Original consumer
>   removed in Feature 040; the pattern still applies to any Settings-API
>   option that feeds an `apply_filters()` default."
> - `PATTERN-STAGE-NAMING` — audit; if the Logger was the primary example,
>   add a note that the pattern remains generally valid.
> - `PATTERN-FEATURE-ASSET-SEPARATION` — audit similarly.
>
> Add a Feature 040 WORKLOG milestone entry to `docs/memory/WORKLOG.md`
> following the format from Feature 039's entry (Why durable / Future
> mistake prevented / Evidence / Where to look). Highlight the durable
> lesson: **module decommission without replacement — Feature 040 is the
> canonical example of the "no consumers outside boot wiring" case, distinct
> from Feature 012 which had a same-plugin replacement**.
>
> In `docs/memory/INDEX.md`, update the rows for the four Superseded
> decisions (change Status column to `Superseded (Feature 040)`) and add a
> new WORKLOG row + rows for any Feature 040 spec/plan/tasks/security-review
> artifacts.
>
> ---
>
> **CONSTRAINTS**
>
> - **Do not migrate** any data from `{prefix}acrossai_ability_logs`. The
>   legacy table is orphaned on existing installs consistent with Feature
>   039's precedent.
> - **Do not touch any file under `vendor/`.** No composer dependency is
>   removed or bumped by this feature.
> - **Do not add a companion-plugin dependency check** — the companion
>   plugin lives independently and this plugin makes no assumption about
>   its presence.
> - **Do not re-register the WordPress ability hooks the Logger consumed**
>   under different names. `wp_before_execute_ability`,
>   `wp_after_execute_ability`, `wp_register_ability_args`, and
>   `mcp_adapter_pre_tool_call` are upstream contracts and remain
>   available for any consumer including the future companion plugin.
> - **Do not delete Logger memory entries silently.** Every retired
>   decision must be marked Superseded with a Feature 040 citation, per
>   `PATTERN-MEMORY-SUPERSESSION-VS-ANNOTATION`.
> - **Do not rename `admin/Partials/SettingsMenu.php` or its remaining two
>   fields.** Only the log-retention field goes; per-page and
>   uninstall-delete-data survive with unchanged option names + section IDs.
> - **Do not remove the `acrossai_abilities_log_retention_days` deletion
>   line from `uninstall.php`.** That line already exists per the current
>   inventory and correctly cleans the orphan option on opt-in uninstall.
> - **Every task must leave PHPStan level 8 + PHPCS individually green
>   before moving to the next.** Constitution §VII per-task gating applies.
> - **Grep after every task** for any residual Logger reference. The final
>   grep in the verification checklist (below) MUST return zero matches.
> - **`npm run build`** MUST succeed after TASK-6; `find build/ -iname
>   'logger*'` MUST return zero results after the manual cleanup step.
> - **Manually verify the WP admin after each TASK** — confirm the Logs
>   submenu disappears (post TASK-1), the Log Retention Days field
>   disappears (post TASK-2), and no PHP fatal on any admin page (all tasks).

```markdown
# 3. Plan + guard + security
/speckit.memory-md.plan-with-memory
/speckit.architecture-guard.governed-plan
/speckit.security-review.plan

# 4. Tasks + guard
/speckit.tasks
/speckit.architecture-guard.governed-tasks

# 5. Implement + quality checks
/speckit.architecture-guard.governed-implement
composer dump-autoload
composer run phpcs
composer run phpstan
npm run build
npm run lint:js

# 6. Review + memory + commit
/speckit.analyze
/speckit.architecture-guard.architecture-review
/speckit.security-review.staged
/speckit.memory-md.capture-from-diff
/speckit.git.commit
```

---

## Manual Verification Checklist

### TASK-1 — REST + Logs admin surface removed
- [ ] `admin/Partials/LogsMenu.php` deleted from working tree.
- [ ] `includes/Main.php` lines 300–303 (Logs menu wiring) removed.
- [ ] `includes/Main.php` REST controller registration for Logger removed.
- [ ] `admin/Main.php` no longer references `$logger_asset_file`,
      `is_logs_page`, or any Logs plugin-action link.
- [ ] WP admin sidebar shows no "Logs" submenu under AcrossAI.
- [ ] Visiting `wp-admin/admin.php?page=acrossai-abilities-logs` renders the
      standard WordPress "page does not exist" admin response.
- [ ] `curl /wp-json/acrossai-abilities-log/v1/logger/logs` (with valid
      nonce) returns 404.

### TASK-2 — log_retention_days Settings field removed
- [ ] `admin/Partials/SettingsMenu.php` no longer registers, sanitizes, or
      renders `acrossai_abilities_log_retention_days`.
- [ ] AcrossAI → Settings shows only per-page and uninstall-delete-data
      controls; no Log Retention section or field.
- [ ] Existing option value in `wp_options` remains untouched (verified via
      `wp option get acrossai_abilities_log_retention_days` returning the
      pre-upgrade value on an upgraded install).

### TASK-3 — Main.php boot wiring removed
- [ ] `grep -n 'AcrossAI_Ability_Logger\|AcrossAI_Logger_Controller\|AcrossAI_Ability_Logs_Table'
      includes/Main.php` returns zero matches.
- [ ] `grep -n 'wp_before_execute_ability\|wp_after_execute_ability\|
      mcp_adapter_pre_tool_call\|acrossai_ability_logger_cleanup'
      includes/Main.php` returns zero matches.
- [ ] Plugin loads without fatal error on `plugins_loaded`.

### TASK-4 — Logger module + utilities deleted
- [ ] `includes/Modules/Logger/` directory does not exist.
- [ ] `includes/Utilities/AcrossAI_Logger_Formatter.php` +
      `AcrossAI_Logger_Source_Detector.php` deleted.
- [ ] Pre-deletion grep for the two utility class names outside the module
      dir returned zero matches (captured in commit body).
- [ ] `composer dump-autoload` succeeds with zero warnings.

### TASK-5 — Activator + uninstall.php updated
- [ ] `includes/AcrossAI_Activator.php` no longer imports or instantiates
      `AcrossAI_Ability_Logs_Table`.
- [ ] `uninstall.php` inside the opt-in gate drops
      `{prefix}acrossai_ability_logs` via `%i` placeholder + deletes
      `acrossai_ability_logs_db_version` option + calls
      `as_unschedule_all_actions( 'acrossai_ability_logger_cleanup' )`
      when Action Scheduler is available.
- [ ] Fresh activation on a clean WP install: `wp db query "SHOW TABLES
      LIKE '%ability_logs%'"` returns zero rows.

### TASK-6 — Front-end cleanup
- [ ] `src/js/index.js`, `src/js/components/LogsTable.js`,
      `src/scss/logs-table.scss` all deleted.
- [ ] `webpack.config.js` no longer contains `js/logger` or `css/logger`
      entries.
- [ ] `npm run build` succeeds with zero errors.
- [ ] `find build/ -iname 'logger*'` returns zero results after cleanup.
- [ ] `npm run lint:js` reports no new errors introduced by this feature
      (BUG-ESLINT9-JEST-GLOBALS pre-existing errors permitted).

### TASK-7 — Tests + phpunit.xml.dist updated
- [ ] `tests/phpunit/Modules/Logger/` directory does not exist.
- [ ] `tests/phpunit/Utilities/AcrossAI_Logger_Source_Detector_Test.php`
      deleted.
- [ ] `phpunit.xml.dist` contains no `<file>` entry referencing a deleted
      test path.
- [ ] `composer test` reports a reduced test count (was 103) and all
      remaining tests pass with zero failures.

### TASK-8 — Release notes + memory hygiene
- [ ] `README.txt` Unreleased changelog includes the Logger removal bullet
      and manual cleanup SQL.
- [ ] `README.txt` Upgrade Notice `= Unreleased =` block warns admins about
      the Logs page disappearing and the REST namespace 404s.
- [ ] `docs/memory/DECISIONS.md`: four Logger decisions marked Superseded
      with Feature 040 citation (entry bodies intact).
- [ ] `docs/memory/ARCHITECTURE.md`: `PATTERN-LOGGER-OPTION-FEED-FILTER` +
      any other Logger-consumer-referenced patterns annotated with
      forward-pointer notes.
- [ ] `docs/memory/WORKLOG.md`: Feature 040 milestone entry added
      (Why durable / Future mistake prevented / Evidence / Where to look).
- [ ] `docs/memory/INDEX.md`: Superseded status updated on four Decisions
      rows; new WORKLOG row + any spec/plan/tasks/security-review artifact
      rows appended.

### Final full-repo audit (blocker before merge)

```bash
grep -rEn 'Logger|acrossai_ability_logs|acrossai-abilities-log|acrossai_ability_logger|LogsTable|LogsMenu|is_logs_page|logger_asset_file|log_retention' \
    --include='*.php' --include='*.js' --include='*.jsx' --include='*.json' \
    includes/ admin/ src/ tests/ acrossai-abilities-manager.php uninstall.php \
    webpack.config.js
```

- [ ] Grep returns **zero matches**. Non-zero indicates incomplete
      decommission — inspect and clean before merge.

### Quality gates (all must be green before commit)
- [ ] PHPStan level 8 — zero errors.
- [ ] PHPCS — zero errors.
- [ ] `composer test` — PHPUnit all remaining tests pass.
- [ ] `npm run validate-packages` — clean.
- [ ] `npm run build` — succeeds; `find build/ -iname 'logger*'` = zero.
- [ ] `composer dump-autoload` — succeeds with zero warnings.
