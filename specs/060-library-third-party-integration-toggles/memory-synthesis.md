# Memory Synthesis

## Current Scope

Feature 060 adds a third-party integration card pattern to the Ability Library page:
- New abstract base class `AcrossAI_Integration_Ability_Base` (`includes/Modules/Library/Integrations/`) subclassed by per-plugin integrations. Constructor auto-wires two hooks (`plugins_loaded` P20 + `acrossai_abilities_api_init` P10). Both callbacks gate on subclass `is_plugin_active()`.
- First concrete subclass `ACF` (`includes/Abilities/Integrations/ACF.php`) attaches `add_filter('acf/settings/enable_acf_ai','__return_true')` when toggled on.
- Registry (`AcrossAI_Ability_Library_Registry`) gains an optional `card_variant` top-level field, sanitised at boundary — display-only, never persisted, same contract as `sub_group`/`tab_group`.
- Config helper (`AcrossAI_Ability_Library_Config::is_integration_enabled`) inverts the sparse-storage default for integration categories only (missing entry → OFF).
- JS `LibraryPage`/`LibraryCard` propagates `cardVariant`; when `'integration'` the RadioControl is suppressed and enabled defaults to false.
- New capability filter `acrossai_integration_toggle_capability` (default `manage_options`) enforced server-side on the toggle write path.

## Relevant Decisions

- **DEC-META-ACROSSAI-NAMESPACE** (Active, DECISIONS.md) — Reason Included: card_variant is a display-only Library field like sub_group/tab_group; must be understood as a *top-level definition-row optional field* set by the base class, not a user-authored `args['meta']['acrossai']` field. The Feature 041 hard-cut does not affect this path because the value is emitted by our own base class, not by ability authors.
- **DEC-ABSORBED-CODE-INCLUDES-TIER** (Active, DECISIONS.md) — Reason Included: our concrete integrations (`includes/Abilities/Integrations/ACF.php`) sit at the includes-tier, matching the precedent set by absorbed core abilities in Feature 046; the abstract base sits under `Modules/Library/Integrations/` where library machinery already lives.
- **DEC-NAMESPACE-CONVENTION** (Active, DECISIONS.md) — Reason Included: new namespaces `AcrossAI_Abilities_Manager\Includes\Modules\Library\Integrations` and `\Includes\Abilities\Integrations` follow the underscore convention.
- **DEC-PLUGIN-CHECK-PRODUCTION-SURFACE** (Active, DECISIONS.md) — Reason Included: any new PHP added by this feature must be Plugin Check clean on the production surface; no forbidden functions, `%i` for SQL identifiers (none expected here), local-only suppressions.
- **DEC-EXTERNAL-PACKAGE-HOOK-CTOR** (Active + Accepted Deviation, DECISIONS.md) — Reason Included: precedent for constructor-registered hooks that bypass the Loader. Our integration base's constructor-registered hooks are an *internal* variant of the same shape; the plan phase should decide whether to formalise as a new accepted deviation or refactor to Main.php-registered.

## Active Architecture Constraints

- **AC-HOOKS-MAIN** (CONSTITUTION §I Boot Flow Rule) — Reason Included: only `Main.php` may call `$this->loader->add_action/add_filter` (see Conflict Warnings). Our new base class hooks `add_action` / `add_filter` directly inside its constructor, mirroring the existing `Ability_Definition::__construct` precedent.
- **AC-FILE-HEADER-PATTERN** (ARCHITECTURE.md) — Reason Included: new PHP files must carry `@package AcrossAI_Abilities_Manager`, `@subpackage <full/path>`, `@since 0.1.0` file-doc headers.
- **PATTERN-ADDON-FILTER-LATE-INIT** (ARCHITECTURE.md) — Reason Included: add-on registration filters must fire at init P99; the Registry already does so, and our base class's `push_definition` runs at the filter's standard P10 which the Registry collects at P99. Order is correct.
- **PATTERN-META-ACROSSAI-NAMESPACE** (ARCHITECTURE.md) — Reason Included: same field-namespace policy as DEC-META-ACROSSAI-NAMESPACE; keep `card_variant` outside `args` (top-level row field) exactly like `tab_group`.

## Accepted Deviations

- **DEC-EXTERNAL-PACKAGE-HOOK-CTOR** (Plugin-wide/External, permanent) — Reason Included: the strongest existing precedent for constructor hook wiring bypassing the Loader. The plan should extend this reasoning to internal abstract base classes (Ability_Definition, and now AcrossAI_Integration_Ability_Base) as a natural sibling deviation.
- **ARCH-ADV-001** (Sitewide/Override) — Reason Included: general precedent that a base class's own hook wiring can legitimately deviate from Boot Flow Rule when the pattern requires per-instance registration.

## Relevant Security Constraints

- **SEC-03** (multisite / per-site prefix, security-constraints.md) — Reason Included: `acrossai_library_config` is stored via `get_site_option`/`update_site_option`, which on multisite is **network-wide** (`wp_sitemeta`), not per-site. Combined with the per-site `is_plugin_active()` gate, the integration toggle state is network-scoped but the visible card is per-site. Plan phase must document this asymmetry (matches spec edge case "Multisite with ACF network-active on some sites but not others").
- **SEC-04** (strict type comparison, security-constraints.md) — Reason Included: capability checks (`current_user_can`) and any `in_array` on config keys must use strict comparison; feature adds a new capability filter whose result flows into `current_user_can` on the REST write path.

## Related Historical Lessons

- **BUG-ABSPATH-STATIC-CLASS** (Reason Included: new PHP files under `includes/Modules/Library/Integrations/` and `includes/Abilities/Integrations/` must include `defined( 'ABSPATH' ) || exit;` per-file, not per-instantiation.)
- **BUG-UNIMPLEMENTED-HOOK** (Reason Included: spec introduces `apply_filters('acrossai_integration_toggle_capability', ...)` — implementation must actually call the filter; a spec-only declaration silently breaks stricter-capability sites.)
- **BUG-JS-SLUG-PREFIX-FALLTHROUGH** (Reason Included: avoid hardcoding `'acf'` or `'integration'` prefix stripping in JS; use explicit `cardVariant === 'integration'` equality check with a jest test using a real slug.)

## Conflict Warnings

- **Soft conflict: AC-HOOKS-MAIN vs constructor-registered hooks.** The new integration base class registers hooks inside its constructor (`add_action('plugins_loaded', …)`, `add_filter('acrossai_abilities_api_init', …)`). This mirrors the existing `Ability_Definition` base class pattern (~176 subclasses already ship this way, unflagged) but does not have an explicit accepted-deviation entry the way `DEC-EXTERNAL-PACKAGE-HOOK-CTOR` does. The plan must either (a) cite ARCH-ADV-001/`Ability_Definition` precedent and formalise it as a fresh accepted deviation for internal abstract base classes, or (b) refactor to instantiate concrete integrations in `Main.php` and wire hook methods via the Loader. Recommended: (a), because option (b) would also require refactoring every existing `Ability_Definition` subclass to break the constructor pattern, which is out of scope.

## Retrieval Notes

- Index entries considered: ~20 (Active Decisions 6, Architecture Constraints 4, Patterns 4, Security 2, Bugs 3, Deviations 1).
- Full memory files not read (INDEX-first, per config default `retrieval.full_read: false`).
- Source sections read: INDEX.md (full), CONSTITUTION.md §I Boot Flow Rule (lines 270-292), security-constraints.md SEC-04 (full — small file).
- Budget: under 900 words. Within cap.
