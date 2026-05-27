# Memory Synthesis

## Current Scope

Feature 016 touches exactly two files: `includes/Main.php` (one new `add_action`
call) and `src/js/abilities/components/AbilityForm.jsx` (replace free-text input
with a checkbox list). No new PHP classes, no new Redux state, no new REST
controller, no DB changes.

## Relevant Decisions

- **DEC-UTILITY-STATIC-ONLY** (Reason Included: RestEndpoint is a pure-static
  vendor class — it has no `instance()`. Use class string as `$component` and
  `'register'` as `$callback` in Loader::add_action. Status: Active, Source:
  DECISIONS.md §2026-05-19)

- **DEC-DESIGN-OVERRIDES-DATAVIEWS** (Reason Included: AbilityForm uses plain
  HTML `.sect`/`.sbox` sections, not DataForm. The checkbox list must be
  implemented as plain JSX inside the existing `.sbox` — do NOT introduce
  `@wordpress/dataforms` components. Status: Active, Source: DECISIONS.md
  §2026-05-24)

- **DEC-HACTIONS-BUTTON-DEPTH** (Reason Included: AbilityForm.jsx has a known
  inconsistent tab-depth pattern; any element in `.hactions` at button=5-tab.
  Cross-reference when placing the checkbox list. Status: Active, Source:
  DECISIONS.md)

- **DEC-ABILITIES-DUAL-MODE-LIST** (Reason Included: REST GET /abilities branches
  on source type and merges registry data. Not directly changed, but confirms
  the _registry / _override shape we read in the form. Status: Active, Source:
  DECISIONS.md)

- **DEC-NODE-20-BUILD-REQUIRED** (Reason Included: webpack build requires Node ≥
  20; must verify Node version before running `npm run build`. Status: Active,
  Source: DECISIONS.md)

## Active Architecture Constraints

- **AC-HOOKS-MAIN** (Reason Included: The RestEndpoint registration is a new
  `add_action` call — MUST go in `Main.php::define_admin_hooks()`, named variable
  first. Pattern: `$mcp_servers_rest = \WPBoilerplate\McpServersList\
  RestEndpoint::class; $this->loader->add_action('rest_api_init',
  $mcp_servers_rest, 'register', 20)`. Source: CONSTITUTION.md §I)

- **AC-ENQUEUE-ADMIN** (Reason Included: Confirm no asset enqueues are added in
  AbilityForm.jsx or a new file — all enqueues must stay in Admin\Main::
  enqueue_scripts/styles. This feature adds no new assets, so constraint is
  satisfied by omission. Source: CONSTITUTION.md §I)

- **AC-FILE-HEADER-PATTERN** (Reason Included: If any new PHP file is created,
  must include `@package AcrossAI_Abilities_Manager @subpackage full/path
  @since 0.1.0`. This feature adds no new PHP files; constraint is satisfied
  by omission. Source: ARCHITECTURE.md)

- **ARCH-UNIFIED-ABILITIES-STORAGE** (Reason Included: Background — override
  rows in unified table; `mcp_servers` is a JSON field stored by the write
  controller. No DB changes in this feature. Source: ARCHITECTURE.md)

## Accepted Deviations

- **DEV1 / DEC-DESIGN-OVERRIDES-DATAVIEWS** (Reason Included: AbilityForm
  continues using plain `.sect`/`.sbox` HTML sections. Checkbox list is a new
  control inside an existing `.sbox`; no migration to DataForm required.
  Status: Accepted-Deviation, Source: DECISIONS.md §2026-05-24)

## Relevant Security Constraints

- **SEC-01** (Reason Included: RestEndpoint::register() uses `manage_options`
  capability check by default (confirmed in vendor source). This matches our
  existing permission model. No slug-sanitize concern — this endpoint is
  read-only. Source: security-constraints.md)

- **SEC-04** (Reason Included: RestEndpoint::permission_callback uses
  `current_user_can()` returning bool — strict bool check is correct. Client-
  side: apiFetch is nonce-aware; the existing WP REST nonce satisfies auth.
  No new permission_callback code needed on our side. Source: security-
  constraints.md)

- **SEC-03** (Reason Included: RestEndpoint is a vendor REST route. It uses
  WordPress `register_rest_route()` internally — per-site routing is handled
  by WP core. No multisite concern for this feature. Source: security-
  constraints.md)

## Related Historical Lessons

- **BUG-ABILITYFORM-JSX-MIXED-DEPTHS** (Reason Included: CRITICAL — AbilityForm
  .jsx has inconsistent tab depths per element type. Before any str_replace or
  insertion in AbilityForm.jsx, read the exact section with `read_file` and
  count actual tabs on the target line. Do NOT assume uniform depth. Feature
  013 caused multiple failures here. Source: BUGS.md §2026-05-25)

- **BUG-PHPCBF-TABS** (Reason Included: If phpcbf is run after the Main.php
  change, it converts spaces to tabs. Python str_replace scripts on PHP files
  must use `\t`. Source: BUGS.md §2026-05-25)

- **BUG-PYTHON-STRREPLACE-PARTIAL-WRITE** (Reason Included: Write per-step in
  Python scripts; do not accumulate all edits and write at the end — partial
  writes on error leave files in corrupt state. Source: BUGS.md §2026-05-25)

## Conflict Warnings

- None. The feature is tightly scoped (two files, no new classes), all
  constraints are well-established, and no active decision conflicts with the
  planned approach.
- **Watchpoint**: `RestEndpoint::register()` is static. The Loader's
  `add_action($hook, $component, $callback)` signature expects `$component` to
  be an object or class string. Using a class string for a static method is
  valid in WordPress but is a deviation from the instance-based pattern used
  for all other hooks in Main.php. This is acceptable per DEC-UTILITY-STATIC-ONLY
  and confirmed in the planning doc Tips section. Not a conflict — document in
  implementation task.

## Retrieval Notes

- Index entries considered: 21 (all rows)
- Entries selected: 5 decisions, 4 architecture constraints, 1 accepted
  deviation, 3 security, 3 bug patterns, 2 worklog
- Source sections read: BUGS.md §387-430, DECISIONS.md §674-700, Main.php
  §278-295, RestEndpoint.php (vendor, grep only), AcrossAI_Loader.php (grep)
- Budget status: Within 900-word limit. Full memory read: false.
- Feature memory.md: not present (new feature, no prior notes).
