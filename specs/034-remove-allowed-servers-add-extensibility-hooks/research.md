# Phase 0 Research: Remove Allowed Servers + Add Extensibility Hooks

All `NEEDS CLARIFICATION` markers in spec.md were resolved during `/speckit-clarify` (Session 2026-06-14, 3 Q→A). This file documents the remaining technical decisions that needed research before Phase 1 design, organized by topic.

## Decision: No upgrade migration shipped — schema-only change

- **Decision**: Do NOT ship any upgrade migration code. Remove `mcp_servers` from `AcrossAI_Abilities_Schema` only. For fresh installs, BerlinDB creates the table without the column (FR-011). For dev installs that already have the column populated, the user manually drops the abilities table and reactivates the plugin (per spec Input + Assumptions).
- **Rationale**: The plugin has not been launched yet. There are no production sites to migrate. Shipping a `__110()` versioned upgrade method, bumping `$version`, and adding `SHOW COLUMNS` + guarded `ALTER TABLE DROP COLUMN` logic would be ~30 lines of code, a forever-line of migration history, and ongoing PHPCS/PHPStan/Plugin Check surface — all to handle a population of installs that does not exist. FR-012 explicitly forbids shipping migration code for this reason.
- **Alternatives considered**:
  - **Ship the versioned migration anyway "for safety"**: rejected. No production installs exist. Future code never needs to read this migration's history. Carries ongoing maintenance burden (security-review SEC-INFO-001 on `%i` placeholder, BerlinDB v3 conventions, multisite per-blog testing) with zero benefit.
  - **Add a one-time activation hook that drops the column**: rejected. Same problem as the migration plus the activation-hook drawback that it only fires on activation, not on plugin update from an active install.
  - **Leave the column in the schema but stop reading/writing it**: rejected. Constitution §VI DRY — dead schema is technical debt; clean removal is preferable when there's no migration cost.

## Decision: JS hook library — `@wordpress/hooks` (no new dependency)

- **Decision**: Import `applyFilters` and `doAction` from `@wordpress/hooks` directly in `AbilityForm.jsx`. No new package install.
- **Rationale**: `@wordpress/hooks` is transitively bundled via `@wordpress/scripts` (confirmed in `package-lock.json` per B-6 in the planning doc). WordPress core globals provide the same API on `window.wp.hooks` for external subscribers; the import path on this plugin's side is the npm package.
- **Alternatives considered**:
  - **Custom event bus / pub-sub**: rejected. Reinvents WordPress's own primitive; extensions would not be able to use the standard `wp.hooks.addFilter()` registration; adds new surface to test.
  - **Redux store action / selector**: rejected. The point of the extension surface is to NOT couple extensions to this plugin's Redux store implementation, which IS implementation detail (extensions read context, not store state).
  - **DOM CustomEvent**: rejected. Loses static typing benefits, awkward for filter-style pass-through.

## Decision: JS hook naming — dot-notation `acrossai_abilities.form.*`

- **Decision**: Use `acrossai_abilities.form.extra_sections`, `acrossai_abilities.form.draft_changed`, `acrossai_abilities.form.save_payload` for JS hooks. PHP hooks keep the existing snake_case `acrossai_abilities_*` convention.
- **Rationale**: This is the FIRST JS hook surface in the plugin (per B-5). Dot-notation is the WordPress-ecosystem convention for `@wordpress/hooks` namespaces — used throughout Gutenberg core (`blocks.registerBlockType`, `editor.PostSidebar`, etc.) and lets extensions visually distinguish JS-side namespacing from PHP-side. Establishing the convention now is cheaper than retrofitting after the first extension ships.
- **Alternatives considered**:
  - **Snake_case for JS too**: rejected. Inconsistent with `@wordpress/hooks` ecosystem conventions; harder for extension authors familiar with Gutenberg patterns.
  - **Camel-case (`acrossaiAbilitiesFormExtraSections`)**: rejected. Same reason — diverges from the ecosystem convention.

## Decision: Localize global name — `window.acrossaiAbilitiesManager` (pre-existing)

- **Decision**: Do NOT rename or replace the existing JS global. The PHP filter `acrossai_abilities_admin_localize_data` wraps the data array that is already injected as `window.acrossaiAbilitiesManager` via `wp_add_inline_script` in `admin/Main.php:254-256`.
- **Rationale**: This name is already used by the admin React bundle (`abilities/index.js:12`, `AbilityForm.jsx:39`, `AbilitiesList.jsx:125-126,243`, `api/client.js:11`) and is documented in `DEC-ABILITIES-LIST-UX-025` and `PATTERN-PROTECTED-SLUGS-JS-LOCALIZE`. The clarify Q3 answer pinned it as part of the public contract. Replacing it would be a breaking churn for code that's already correct.
- **Pattern reinforcement**: `BUG-WP-LOCALIZE-SCRIPT-RENDER` confirms `wp_add_inline_script` (not `wp_localize_script`) is the canonical mechanism — Spec FR-008 reflects this; the planning doc's `wp_localize_script` example was illustrative only.

## Decision: Hook callsite placement in `AbilityForm.jsx`

- **Decision**: The three React hook callsites live exclusively inside `AbilityForm.jsx`:
  1. `extra_sections` filter — at the JSX location where the deleted Allowed Servers block lived (the "MCP" section per `ARCH-ABILITYFORM-SECTION-ORDER`, position 3).
  2. `draft_changed` action — inside a `useEffect( () => doAction(...), [draft] )` block adjacent to other draft-watching effects.
  3. `save_payload` filter — immediately before the REST POST body is constructed.
- **Rationale**: Centralizing the hook surface in one file (a) makes the public contract grep-able from one location, (b) avoids leaking form internals into module-level files, (c) keeps the `extra_sections` slot at the visually intuitive position where Allowed Servers used to render. Per `ARCH-ABILITYFORM-SECTION-ORDER`, the form sections currently number 1–7 with MCP as 3; this feature removes section 3 and the slot effectively becomes the new section 3 (extension-provided content). Memory entry will be updated post-implementation.
- **JSX-edit hazard mitigation**: per `BUG-ABILITYFORM-PANEL-PREMATURE-CLOSE`, `BUG-ABILITYFORM-JSX-MIXED-DEPTHS`, `DEC-HACTIONS-BUTTON-DEPTH`:
  - Edits MUST be performed manually (no script-driven `str_replace`); the 154-line block (lines 1268–1422) has mixed tab depths.
  - After the deletion + slot insertion, the section `.panel` closing `</div>` position MUST be visually verified (no script tooling).
  - Tab depth for `.sect` children is 5; the inserted hook-slot JSX uses the same depth as the deleted block's outermost element.

## Decision: PHP callsite placement in `admin/Main.php`

- **Decision**:
  - `apply_filters( 'acrossai_abilities_admin_localize_data', $data )` wraps the `$data` array immediately before `wp_add_inline_script` JSON-encodes it (`admin/Main.php:254-256`).
  - `do_action( 'acrossai_abilities_form_settings_registered' )` fires inside `enqueue_scripts()` AFTER the `wp_enqueue_script` call for the abilities admin bundle and AFTER the `wp_add_inline_script` data injection — so subscribers can rely on both the script handle being registered AND the data already being localized when they hook in to enqueue dependent bundles.
- **Rationale**: Per `AC-ENQUEUE-ADMIN`, `wp_enqueue_script` lives only in `admin/Main::enqueue_scripts()`. Placing the action there satisfies the constraint and gives extension plugins the only useful timing point. Firing the action AFTER inline-script injection means an extension's own `wp_add_inline_script` enqueued after this point will fire later in page execution but before the React bundle runs (since both use the 'before' position).
- **Alternatives considered**:
  - **Fire the action from `init` or `admin_init`**: rejected. Too early — the abilities admin script handle is not yet registered.
  - **Fire from `admin_print_scripts-{hook}`**: rejected. Tied to the WP `$hook_suffix`, which complicates testing and risks the `BUG-LIBRARY-HOOK-SUFFIX` family of issues.

## Decision: REST behavior for inbound obsolete `mcp_servers` field — silent accept

- **Decision**: REST controllers do NOT add `additional_properties: false` to their argument schemas; the `mcp_servers` key in inbound create/update bodies is silently dropped (WP REST validator ignores unknown args by default). This satisfies FR-003 with zero new code — simply removing the registered arg is sufficient.
- **Rationale**: A clean break (400 on the obsolete field) would break any external clients still sending it during the upgrade window with no benefit. WP REST's default behavior (drop unknown args) is exactly the desired semantic. No deprecation header is emitted because there is no successor location for the data inside this plugin — the field is gone, end of story.
- **Acceptance Scenario 3** in spec.md is satisfied without writing new validation code.

## Decision: Tests — delete `mcp-servers-checkbox.test.js` and `mcp_servers` PHPUnit asserts only

- **Decision**: Remove the dedicated Jest file in full. For PHPUnit, locate every `mcp_servers` reference via `grep -r mcp_servers tests/phpunit/` and delete only the asserting lines (or the smallest test method) — do NOT delete entire test classes. No new test file added.
- **Rationale**: FR-013 + FR-014. Surrounding tests still cover the other abilities fields' round-trip behavior; we strip only the assertions on the removed field. Adding a Jest test for hook pass-through would test `@wordpress/hooks` itself, not the plugin's contract (see Complexity Tracking in plan.md and DEC-FEATURE-027-NO-TESTS precedent).
- **PHPUnit gotchas to remember**: `BUG-PHPUNIT-AUTODISCOVERY-PREFIX` (file-prefix discovery), `BUG-PHPUNIT-BERLINDDB-SCOPE` (BerlinDB stub bootstrap), `BUG-PHPUNIT-ABSPATH-SILENT-EXIT` (define ABSPATH before autoloader) — all baseline-stable; this feature does not perturb them.

## Out-of-scope confirmations (recorded so future reviewers don't re-litigate)

- **`pass_as_tool` column (Feature 029) is NOT touched.** That column governs MCP tool exposure for an ability; `mcp_servers` (this feature removes) governed which servers could see the ability. They are distinct concerns. `BUG-INJECT-MCP-TOOLS-PERMISSION-BYPASS` applies to `inject_mcp_tools()` and remains in force.
- **`wpb_mcp_servers_list_rest_capability` filter wiring in `Main.php` is NOT touched.** That filter belongs to the `wpboilerplate/wpb-mcp-servers-list` Composer package's capability control for the MCP servers listing REST endpoint — orthogonal to per-ability allowed-server tracking. `DEC-MCP-CAPABILITY-FILTER-WARN` remains active.
- **AbilityForm's React Redux store architecture is NOT refactored.** Only the single `mcp_servers` entry in `OVERRIDABLE_FIELDS` is removed. `DEC-DESIGN-OVERRIDES-DATAVIEWS` remains the standing exemption.
- **No new composer or npm package is added.** All new code uses already-bundled `@wordpress/hooks` (JS) and WordPress core `do_action`/`apply_filters` (PHP).
