# Planning: Logger Module — Constitution Compliance (Feature 017)

Fix five hard violations and two warnings found in `includes/Modules/Logger/`
during a compliance audit.

---

## Speckit Workflow

```markdown
# 1. Branch
/speckit.git.feature "logger-module-constitution-compliance"

# 2. Specify
/speckit.specify "Fix five Constitution violations in includes/Modules/Logger/:
Boot Flow Rule, wrong text domain, static singleton bypass, missing sanitize_callback
at REST entry point, and utility class in wrong directory. Also patch Constitution
module list and add BerlinDB constructor exception comment."
```

### Detailed Description for `/speckit.specify`

> **Before writing a single line of code, read and internalize all three of
> these governing documents in full:**
>
> 1. `.agents/skills/wp-plugin-development/SKILL.md` — and every file under
>    `.agents/skills/wp-plugin-development/references/` (boot-flow, security,
>    rest-api, structure, hooks).
> 2. `.specify/memory/CONSTITUTION.md` — pay special attention to §I Modular
>    Architecture, §II WordPress Standards, §IV Security First, §VI DRY,
>    §VII Definition of Done, and the Boot Flow Rule, Module Contract, and REST
>    Controller Pattern in the Architecture section.
> 3. `AGENTS.md` — the singleton pattern block, hook registration rules, and
>    the Before Commit Checklist.
>
> Every decision in this task — file location, namespace, hook registration,
> sanitization, singleton usage — must be justified against one of those three
> documents. If a choice is not explicitly covered, default to the most
> restrictive interpretation of the Constitution.
>
> Do not write code that would fail any Definition-of-Done gate:
> PHPStan level 8, PHPCS, security review, all `__()` calls using the correct
> text domain, and `npm run validate-packages`.
>
> ---
>
> **FIX-1 — Boot Flow Rule**
>
> Files: `includes/Modules/Logger/AcrossAI_Ability_Logger.php`,
>        `includes/Main.php`
>
> Read `.agents/skills/wp-plugin-development/references/boot-flow.md` and
> `CONSTITUTION.md §Architecture — Boot Flow Rule` before touching either file.
>
> `AcrossAI_Ability_Logger::boot()` currently calls `add_filter()` and
> `add_action()` directly for five hooks. The comment `"ARCH-ADV-001 Exception"`
> inside the method is not recognised by the Constitution or AGENTS.md — it
> must be deleted.
>
> Apply the Boot Flow Rule exactly as written:
> - Delete every `add_filter()` / `add_action()` call from `boot()`.
> - Delete the `ARCH-ADV-001 Exception` comment.
> - Delete `boot()` entirely once it is empty; remove the corresponding
>   `plugins_loaded → boot` Loader line from `Main.php`.
> - In `Main.php` `define_public_hooks()`, register all five hooks through the
>   Loader using the `$logger` named variable that already exists in that
>   method. Do not create a second variable. Follow the canonical form from
>   AGENTS.md — named variable first, then Loader call, never inline:
>   ```php
>   $this->loader->add_filter( 'mcp_adapter_pre_tool_call',         $logger, 'capture_mcp_server_id',     5,      4 );
>   $this->loader->add_action( 'wp_before_execute_ability',          $logger, 'start_pending_entry',      10,     2 );
>   $this->loader->add_action( 'wp_after_execute_ability',           $logger, 'finish_pending_entry',     10,     3 );
>   $this->loader->add_filter( 'wp_register_ability_args',           $logger, 'wrap_permission_callback', 100001, 2 );
>   $this->loader->add_action( 'acrossai_ability_logger_cleanup',    $logger, 'cleanup_old_logs',         10      );
>   $this->loader->add_action( 'plugins_loaded',                     $logger, 'schedule_cleanup',         20      );
>   ```
> - The 5th argument (`$accepted_args`) must match each callback's real
>   parameter count: `capture_mcp_server_id` = 4, `start_pending_entry` = 2,
>   `finish_pending_entry` = 3, `wrap_permission_callback` = 2,
>   `cleanup_old_logs` = 0, `schedule_cleanup` = 0.
> - Before writing, check the Loader class signature — verify it accepts a 5th
>   `$accepted_args` parameter. If it does not, do not pass it.
> - PHPStan level 8 must pass with zero errors after this change.
>
> ---
>
> **FIX-2 — Wrong text domain**
>
> Files: `includes/Modules/Logger/Rest/AcrossAI_Logger_Controller.php`,
>        `includes/Modules/Logger/Rest/AcrossAI_Logger_Logs_Controller.php`
>
> Read `CONSTITUTION.md §II` and `AGENTS.md §Before Commit Checklist` before
> touching either file.
>
> Both REST controllers use `'acrossai-abilities'` as the text domain in every
> `__()` call. The plugin text domain is `'acrossai-abilities-manager'`.
> Replace every occurrence of `'acrossai-abilities'` with
> `'acrossai-abilities-manager'` in both files. Scope is strictly these two
> files. PHPCS `WordPress.WP.I18n` must pass after the change.
>
> ---
>
> **FIX-3 — Static method bypasses singleton**
>
> Files: `includes/Modules/Logger/AcrossAI_Logger_Query.php`,
>        `includes/Modules/Logger/Rest/AcrossAI_Logger_Logs_Controller.php`
>
> Read `CONSTITUTION.md §Architecture — Module Contract` and `AGENTS.md
> §Singleton pattern` before touching either file.
>
> `AcrossAI_Logger_Query::get_logs()` is declared `public static`. The Module
> Contract requires the singleton `instance()` as the sole public interface.
> Remove the `static` keyword from `get_logs()`. Update the one call site in
> `AcrossAI_Logger_Logs_Controller::get_logs()`:
> ```php
> // Replace this
> $query_result = AcrossAI_Logger_Query::get_logs( $args );
> // With this
> $query_result = AcrossAI_Logger_Query::instance()->get_logs( $args );
> ```
> Do not change the method body. PHPStan level 8 must pass after this change.
>
> ---
>
> **FIX-4 — Missing `sanitize_callback` at REST entry point**
>
> File: `includes/Modules/Logger/Rest/AcrossAI_Logger_Logs_Controller.php`
>
> Read `CONSTITUTION.md §IV Security First` and
> `.agents/skills/wp-plugin-development/references/security.md` before
> touching this file. §IV is marked NON-NEGOTIABLE.
>
> The `source` and `status` REST route args in `register_routes()` have no
> `sanitize_callback`. Add `'sanitize_callback' => 'sanitize_text_field'` to
> both arg definitions. Do not remove the enum allowlist validation inside
> `AcrossAI_Logger_Query::get_logs()` — sanitization and enum validation are
> two separate concerns and both are required.
>
> ---
>
> **FIX-5 — Utility class in wrong directory**
>
> Source: `includes/Modules/Logger/AcrossAI_Logger_Source_Detector.php`
> Target: `includes/Utilities/AcrossAI_Logger_Source_Detector.php`
>
> Read `CONSTITUTION.md §I Modular Architecture` and
> `CONSTITUTION.md §Architecture — Directory Layout` before moving this file.
>
> `AcrossAI_Logger_Source_Detector` is a pure static utility with no instance
> and no module-specific dependencies. The Constitution requires all shared
> logic to live in `includes/Utilities/`. Use `git mv` to move it. After the
> move:
> - Change the `namespace` declaration to
>   `AcrossAI_Abilities_Manager\Includes\Utilities`.
> - Update the `use` import in `AcrossAI_Ability_Logger.php` to:
>   `use AcrossAI_Abilities_Manager\Includes\Utilities\AcrossAI_Logger_Source_Detector;`
> - Run `composer dump-autoload`.
> - PHPStan and PHPCS must pass with zero errors.
>
> ---
>
> **WARNING-1 — Document the BerlinDB constructor exception**
>
> File: `includes/Modules/Logger/Database/AcrossAI_Ability_Logs_Table.php`
>
> Read `CONSTITUTION.md §Architecture — Module Contract` before editing.
>
> The Module Contract requires `private function __construct()`. This class
> cannot have one because BerlinDB `Table` runs table-registration side-effects
> in its parent constructor — a private constructor would break it. This is a
> justified framework exception but it is currently undocumented. Add a PHPDoc
> block above `instance()` that records the exception and its reason so future
> compliance audits do not flag it as an error:
> ```php
> /**
>  * Note: constructor is intentionally NOT private. BerlinDB\Database\Table
>  * performs table-registration side-effects in parent::__construct().
>  * A private constructor would prevent those from running and break table
>  * registration. Justified exception to the Module Contract.
>  *
>  * @since 0.1.0
>  * @return self
>  */
> ```
>
> ---
>
> **WARNING-2 — Patch the Constitution module list**
>
> File: `.specify/memory/CONSTITUTION.md`
>
> Read the full Constitution before editing it. Follow the Amendment Procedure
> defined in `CONSTITUTION.md §Governance` — do not skip any step.
>
> The Directory Layout lists `PerUser/`, `McpServer/`, `Abilities/`,
> `Webmcp/` but omits `Logger/`. The PHP Namespace Rule section already
> references Logger paths, so the omission is a documentation gap, not a design
> question. Apply the following changes:
> - Add `Logger/` between `Abilities/` and `Webmcp/` in the Directory Layout
>   code block.
> - In §I Modular Architecture, change "four active feature areas" to "five
>   active feature areas" and add "Ability Execution Logging" to the list.
> - Bump the version footer from `1.4.1` to `1.4.2` (PATCH — clarification,
>   no new principle).
> - Update `Last Amended` to today's date.
> - Add a sync impact entry to the HTML comment block at the top:
>   ```
>   Version change: 1.4.1 → 1.4.2
>   Modified sections: Directory Layout (Logger/ added), §I (module count corrected)
>   Rationale: Logger module existed but was omitted from the module list;
>   namespace examples already referenced it correctly.
>   ```
> - Commit this file separately from all PHP changes.
>
> ---
>
> **CONSTRAINTS**
>
> - Do not change any public method signatures except removing `static` from
>   `AcrossAI_Logger_Query::get_logs()`.
> - Do not change the DB schema, REST response shape, or any filtering logic.
> - Do not touch any file under `Database/` except to add the PHPDoc comment
>   in `AcrossAI_Ability_Logs_Table.php`.
> - Every fix must leave PHPStan level 8 and PHPCS passing individually —
>   do not batch fixes and check only at the end.
> - Run `composer dump-autoload` after FIX-5 before running any static analysis.
> - WARNING-2 must be a separate commit from the PHP fixes.

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

### FIX-1 — Boot Flow Rule
- [ ] `AcrossAI_Ability_Logger::boot()` deleted — no `add_filter`/`add_action` calls remain in it.
- [ ] All five hooks wired in `Main.php` `define_public_hooks()` via Loader with named `$logger` variable.
- [ ] Logger still captures MCP server ID, starts/finishes pending entries, wraps permission callbacks, and runs cleanup.

### FIX-2 — Text domain
- [ ] No occurrence of `'acrossai-abilities'` in either REST controller.
- [ ] All `__()` calls in Logger REST controllers use `'acrossai-abilities-manager'`.

### FIX-3 — Singleton consistency
- [ ] `AcrossAI_Logger_Query::get_logs()` has no `static` keyword.
- [ ] `AcrossAI_Logger_Logs_Controller` calls `AcrossAI_Logger_Query::instance()->get_logs( $args )`.

### FIX-4 — Sanitize at entry point
- [ ] `source` arg in `register_routes()` has `'sanitize_callback' => 'sanitize_text_field'`.
- [ ] `status` arg in `register_routes()` has `'sanitize_callback' => 'sanitize_text_field'`.
- [ ] Enum allowlist check in `AcrossAI_Logger_Query::get_logs()` is unchanged.

### FIX-5 — Utility class location
- [ ] File is at `includes/Utilities/AcrossAI_Logger_Source_Detector.php`.
- [ ] Namespace is `AcrossAI_Abilities_Manager\Includes\Utilities`.
- [ ] `use` import in `AcrossAI_Ability_Logger.php` updated.
- [ ] `composer dump-autoload` run with no errors.

### WARNING-1 — BerlinDB constructor exception
- [ ] PHPDoc above `instance()` documents the justified constructor exception.

### WARNING-2 — Constitution module list
- [ ] `Logger/` in Directory Layout in `CONSTITUTION.md`.
- [ ] §I updated to "five active feature areas".
- [ ] Version `1.4.2`, `Last Amended` updated, sync impact comment added.
- [ ] Committed separately from PHP fixes.

### Quality gates
- [ ] PHPStan level 8 — zero errors.
- [ ] PHPCS — zero errors.
- [ ] Logger behaviour unchanged — execution logs still written correctly.
