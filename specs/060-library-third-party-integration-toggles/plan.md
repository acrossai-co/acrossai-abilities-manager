# Implementation Plan: Library Third-Party Integration Toggles

**Branch**: `060-library-third-party-integration-toggles` | **Date**: 2026-07-26 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/060-library-third-party-integration-toggles/spec.md`
**Supporting docs**: [memory-synthesis.md](./memory-synthesis.md), [docs/planning/060-library-third-party-integration-toggles.md](../../docs/planning/060-library-third-party-integration-toggles.md)

## Summary

Add a reusable "third-party integration" card pattern to the AcrossAI Ability Library page. Each integration renders as its own tab with a single toggle in the header and a fixed, readonly list of the abilities the target plugin will expose when enabled. When the toggle is on and the target plugin is active, the integration's base class attaches the plugin-specific filter (e.g. `add_filter('acf/settings/enable_acf_ai','__return_true')`) early enough in the request that the target plugin picks it up on the same page load. Toggle state reuses the existing `acrossai_library_config` option; the Ability Library Registry gains one new optional display field (`card_variant`) that flows through the JS render pipeline to suppress the All/Specific radio for integration cards.

First concrete subclass: Advanced Custom Fields (ACF). The abstract base class is the reusable surface for future integrations (WooCommerce, WPForms, etc.), added without any changes to REST endpoints, admin-menu registration, or the ability-library JavaScript bundle build.

## Technical Context

**Language/Version**: PHP 8.1+ (Constitution §II), JavaScript ES2022 via `@wordpress/scripts`
**Primary Dependencies**: WordPress 6.9+, `@wordpress/components` (`ToggleControl`, `RadioControl`), `@wordpress/dataviews` (already loaded by Library page), WordPress Abilities API (Feature 027 module `AbilityAPI/`)
**Storage**: Reuses `acrossai_library_config` site-wide option (network-wide `wp_sitemeta` on multisite via `get_site_option`/`update_site_option`). No new option keys, no new DB tables.
**Testing**: PHPUnit for the base class contract; Jest for the `LibraryCard` variant branch (via `wp-scripts test-unit-js`)
**Target Platform**: WordPress single-site and multisite installs; `wp-admin` in modern browsers
**Project Type**: WordPress plugin (single-project layout under Constitution §I Directory Layout)
**Performance Goals**: Adding the new base class and one concrete integration MUST NOT add measurable latency to the Ability Library page load (target: <5ms extra PHP time when the target plugin is missing; <10ms when active).
**Constraints**: PHPStan level 8, PHPCS WPCS strict, ESLint zero warnings, Plugin Check clean on the production surface (Constitution §II + DEC-PLUGIN-CHECK-PRODUCTION-SURFACE), multisite-compatible (Constitution §II), no forbidden functions, no new REST endpoint.
**Scale/Scope**: One base class + one concrete subclass (ACF) + 4 file modifications + 1 JS rebuild. Design scales to N future integrations by adding one subclass file each.

## Constitution Check

Constitution v1.4.8 (Ratified 2026-05-11, Last Amended 2026-07-01).

| Principle | Status | Notes |
|---|---|---|
| §I Modular Architecture | ✅ Pass | Base class sits under existing `Library` module (`includes/Modules/Library/Integrations/`); concrete subclass at includes-tier (`includes/Abilities/Integrations/`) per Feature 046 precedent (DEC-ABSORBED-CODE-INCLUDES-TIER). No new module. |
| §II WordPress Standards | ✅ Pass (gated on Phase 3) | New PHP passes PHPCS/PHPStan/ESLint/Plugin Check when written per this plan. No SQL. Multisite-safe: `is_plugin_active()` gate is per-site while option storage is network-wide (documented asymmetry in FR-004/FR-012). |
| §III User-Centric Design (DataForm/DataViews) | ✅ Pass (n/a) | Integration card is a single toggle + readonly display, not a form. Reuses existing `@wordpress/components` primitives already used by LibraryCard. No new form UI introduced. |
| §IV Security First (NON-NEGOTIABLE) | ✅ Pass | New capability filter `acrossai_integration_toggle_capability` (default `manage_options`) enforced server-side on the existing REST config-save controller. All input sanitized via existing `AcrossAI_Ability_Library_Config::sanitize_key_field()`. Escaping delegated to existing `LibraryCard` render tree. No new AJAX endpoint, so nonce coverage inherited from existing REST route. |
| §V Extensibility Without Core Modification | ✅ Pass | The feature IS an extensibility pattern. Base class is subclassed, never modified. Filter hooks (`acrossai_abilities_api_init`, `acrossai_integration_toggle_capability`) are the extension points. Integration Resilience: target-plugin absence never blocks the page (FR-004, FR-013). |
| §VI Reusability & DRY | ✅ Pass | Reuses `AcrossAI_Ability_Library_Config::sanitize_key_field()`, existing Registry validation pipeline, existing `LibraryCard`/`LibraryPage` render tree, existing `saveConfig` REST flow. Only one new helper: `is_integration_enabled()` — single source of truth for the inverted default. |
| §VII Definition of Done | ⚠️ Pending | Enforced at implement phase: PHPCS/PHPStan/ESLint/Plugin Check clean, PHPUnit tests for base class contract, Jest test for LibraryCard variant branch, no DRY violations, `acrossai_` prefix on all new hooks. |

### Boot Flow Rule — Constitution §I (Soft Conflict, Documented)

Constitution §I Boot Flow Rule states that only `Main.php` may call `$this->loader->add_action/add_filter` and that no hook-registering code may run in constructors. The proposed base class registers two hooks in its constructor:

```
add_action( 'plugins_loaded', array( $this, 'maybe_enable' ), 20 );
add_filter( 'acrossai_abilities_api_init', array( $this, 'push_definition' ), 10 );
```

This matches the existing `AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition` base class, which already registers `acrossai_abilities_api_init` from its constructor and has ~176 subclasses shipping today (absorbed core abilities from Feature 046). No accepted deviation entry currently formalises this precedent.

**Resolution (chosen for this feature)**: follow the existing precedent, formalise it as a new accepted deviation `DEC-ABILITY-DEFINITION-CTOR-HOOKS` after the plan is approved, and cite both the precedent and the new deviation ID in the base class docblock. This is captured under `## Complexity Tracking` below.

**Alternative rejected**: refactor to instantiate concrete integrations in `Main.php` and wire hook methods via the Loader. Rejected because doing so consistently would also require refactoring every existing `Ability_Definition` subclass and would break the "instantiate once → auto-register" ergonomics that make the base class useful to subclass authors.

## Project Structure

### Documentation (this feature)

```text
specs/060-library-third-party-integration-toggles/
├── spec.md              # /speckit-specify output — business requirements
├── plan.md              # This file — /speckit-architecture-guard-governed-plan output
├── memory-synthesis.md  # /speckit-memory-md-plan-with-memory output
├── security-constraints.md  # /speckit-security-review-plan output (Phase 4 inline; see below)
├── checklists/
│   └── requirements.md  # Spec quality checklist (all items pass)
├── quickstart.md        # Phase 1 output — manual verification steps (created during /speckit-tasks)
└── tasks.md             # /speckit-tasks output — NOT created by this command
```

### Source Code (repository root)

Structure matches Constitution §I Directory Layout. Only the highlighted paths are touched by this feature.

```text
acrossai-abilities-manager/
├── includes/
│   ├── Main.php                         # MODIFIED — instantiate ACF integration at plugins_loaded P20
│   ├── Abilities/
│   │   └── Integrations/
│   │       └── ACF.php                  # NEW — concrete subclass for Advanced Custom Fields
│   └── Modules/
│       └── Library/
│           ├── AcrossAI_Ability_Library_Registry.php   # MODIFIED — add 'card_variant' to OPTIONAL_FIELDS + pass-through
│           ├── AcrossAI_Ability_Library_Config.php     # MODIFIED — add is_integration_enabled() helper
│           ├── Rest/
│           │   └── AcrossAI_Ability_Library_Config_Controller.php   # MODIFIED — apply capability filter on write
│           └── Integrations/
│               └── AcrossAI_Integration_Ability_Base.php   # NEW — abstract base class
├── src/
│   └── js/
│       └── ability-library/
│           └── components/
│               ├── LibraryPage.js       # MODIFIED — propagate card_variant → cardVariant
│               └── LibraryCard.js       # MODIFIED — variant branch: hide RadioControl, default enabled false
├── build/
│   └── js/
│       └── ability-library.js           # REGENERATED via npm run build
└── tests/
    ├── phpunit/
    │   └── Modules/Library/Integrations/
    │       └── AcrossAI_Integration_Ability_Base_Test.php   # NEW — 4-test contract suite
    └── jest/
        └── ability-library/components/
            └── LibraryCard.integration-variant.test.js      # NEW — 3-test variant branch
```

**Structure Decision**: Single-project WordPress plugin layout per Constitution §I. No new module directory required — the feature extends the existing `Library` module and adds two new files at the existing `Abilities/` includes-tier per DEC-ABSORBED-CODE-INCLUDES-TIER.

## Phase 0 — Research

No open technical unknowns. All prior research is captured in `docs/planning/060-library-third-party-integration-toggles.md` and `memory-synthesis.md`. Key resolved questions:

1. **How do integrations flow through the existing Library pipeline?** — Base class pushes synthetic definition rows into `acrossai_abilities_api_init` filter at init P10; Registry collects at init P99; `LibraryPage.groupDefinitions` folds by category into cards. No changes needed to `collectTabGroups`, `filterItemsByTabGroup`, or `PINNED_FIRST_TAB_GROUP`.
2. **Where does the toggle state live?** — Reuse `acrossai_library_config` (site-wide option, network-wide on multisite via `get_site_option`). Each integration becomes a synthetic category entry with `enabled=true|false, mode='all', sub_keys={}`. No new REST endpoint, no new DB table.
3. **How does the card differ from existing library cards?** — New optional `card_variant='integration'` field flows from the definition row through the Registry (sanitized via `sanitize_key_field()` at boundary) into `LibraryCard` props. When `cardVariant === 'integration'`, the JS suppresses the `RadioControl` block and defaults `enabled` to `false` for missing config entries.
4. **When must the enabling filter be attached?** — At `plugins_loaded` P20 (same tier the absorbed core abilities use in Feature 046). This runs before ACF's own `acf/init` hook, so ACF picks up `enable_acf_ai` as truthy on the same request.
5. **Capability for toggling?** — Filterable via `acrossai_integration_toggle_capability`, default `manage_options` (per clarification session 2026-07-26). Enforced server-side on the existing REST config-save write path.

**Output**: no separate `research.md` needed — the planning doc already contains the research and no new questions surfaced.

## Phase 1 — Design & Contracts

### Data model

No custom DB tables. One existing option (`acrossai_library_config`) gains new entry keys for integration categories, with the shape defined by the existing `AcrossAI_Ability_Library_Config::sanitize_entry()`:

```
Option key : acrossai_library_config
Shape      : array<category_slug, { enabled: bool, mode: 'all'|'specific', sub_keys: array<slug, bool> }>
Storage    : get_site_option/update_site_option (single-site → wp_options; multisite → wp_sitemeta)
```

Integration entries always use `mode: 'all'` and empty `sub_keys`. The only meaningful field for an integration is `enabled`. Sparse-storage semantics (Config.php lines 58-65) still strip entries at their default; because integration defaults are **inverted** (missing → OFF), sparse storage still applies: an integration in the OFF-default state won't be persisted, and only explicit `enabled=true` entries live in the DB.

### API contracts

**REST**: no new routes. The existing controller at `includes/Modules/Library/Rest/AcrossAI_Ability_Library_Config_Controller.php` handles both GET and POST for `/acrossai-abilities-library/v1/abilities/config`. It gets one internal change: the write path (POST) MUST run `current_user_can( apply_filters( 'acrossai_integration_toggle_capability', 'manage_options', $category ) )` for any incoming entry whose `category` matches a registered integration slug. Existing capability check (`manage_options`) is preserved as the floor.

**PHP contract — subclass of `AcrossAI_Integration_Ability_Base`** (abstract methods):

```
abstract protected function slug(): string;
    Returns the sanitized identifier used as BOTH the Library category slug AND the
    tab_group. Must be non-empty after sanitize_key_field(). Recommended: 3-20 chars,
    lowercase, hyphens only.

abstract protected function label(): string;
    Returns the human-readable display label for the card and tab. Translated at
    the callsite by the subclass.

abstract protected function is_plugin_active(): bool;
    Returns true iff the target third-party plugin's PHP code is currently loaded
    on the current site. Recommended check: class_exists() or defined() on a stable
    public symbol. Called on EVERY request; MUST NOT cache the result across the
    plugin activation lifecycle.

abstract protected function enable_filter(): void;
    Attaches the third-party plugin's own filter. Called by the base class at
    plugins_loaded P20 iff the toggle is on AND is_plugin_active() is true.

abstract protected function abilities(): array;
    Returns array<int, array{slug: string, label: string, description?: string}>.
    Fixed, readonly list for card display. Used only for the UI; the target plugin
    is responsible for actually registering the abilities.
```

**PHP contract — new capability filter**:

```
apply_filters(
    'acrossai_integration_toggle_capability',
    'manage_options',        // default; MUST be a valid WordPress capability string
    string $integration_slug // sanitized integration slug from the incoming save payload
) : string                   // capability string enforced via current_user_can()
```

**PHP contract — new config helper**:

```
AcrossAI_Ability_Library_Config::is_integration_enabled( string $category ): bool
    Returns true iff the config has an explicit entry for $category with enabled=true.
    Missing entry returns false. This inverts the default that applies to non-integration
    categories, and MUST be the ONLY code path in the PHP layer that reads integration
    config state — all other consumers go through this helper.
```

**JS contract — new prop on Library items**:

```
item.cardVariant : string | undefined
    Present when the item's underlying definitions declared a non-empty card_variant
    field. When cardVariant === 'integration', LibraryCard:
      1. Suppresses the RadioControl block entirely.
      2. Defaults `enabled` to false when config[category] is missing.
      3. Always renders slugs via the readonly path (which is already the else-branch
         when !(enabled && mode === 'specific')).
    For all other values (or when undefined), rendering is identical to today.
```

### Quickstart (manual verification, one page)

Will live at `specs/060-.../quickstart.md` — generated during `/speckit-tasks`. Bullet-form outline:

1. Fresh install with ACF activated → visit Library page → confirm "Acf" tab present, card toggle **off** by default, readonly list shows FieldGroup / PostType / Taxonomy.
2. Flip toggle on → confirm auto-save → reload → confirm state persists → run MCP `discover-abilities` → confirm ACF abilities present.
3. Flip toggle off → reload → confirm ACF abilities absent.
4. Deactivate ACF while toggle=on → reload → confirm tab and card disappear, no PHP notices in debug log.
5. Re-activate ACF → confirm tab returns in "on" state.
6. Add `add_filter( 'acrossai_integration_toggle_capability', fn() => 'manage_network_options' )` in a mu-plugin → confirm a `manage_options`-only user can no longer flip the toggle.

## Phase 2 — Implementation Approach

Handled by `/speckit-tasks` (this command does not produce `tasks.md`). Ordering summary the task generator should honor:

1. **Server-side scaffolding first** (base class + Config helper + Registry pass-through + Controller capability check).
2. **Concrete ACF subclass + Main.php wiring** — the earliest point where a browser test surfaces the tab.
3. **JS render tweaks** (`LibraryPage.js` + `LibraryCard.js`).
4. **Build regeneration** (`npm run build`).
5. **PHPUnit + Jest tests**.
6. **Constitution §VII gates**: PHPCS/PHPStan/ESLint/Plugin Check.

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|---|---|---|
| Constitution §I Boot Flow Rule — base class registers hooks in constructor (`add_action`/`add_filter`, not via `$this->loader`) | Mirrors the existing `Ability_Definition` base class pattern used by ~176 core-ability subclasses. Subclass authors must be able to instantiate a class once and have it auto-register; requiring each new integration to also touch `Main.php` and add loader-wired methods would triple the friction of adding an integration. | Alternative: refactor to per-instance singletons wired in `Main.php`. Rejected because it would require refactoring every existing `Ability_Definition` subclass and eliminates the auto-registration ergonomic. To be captured as a new accepted deviation `DEC-ABILITY-DEFINITION-CTOR-HOOKS` after plan approval; sibling to existing `DEC-EXTERNAL-PACKAGE-HOOK-CTOR`. |
| Integration-only inverted default in `acrossai_library_config` (missing entry means OFF for integrations, ON for everything else) | Enabling AI schema tools on a live site must be an explicit admin decision (FR-008, SC-003). Reusing the existing config store keeps the storage single-sourced. | Alternative 1: new option key just for integrations. Rejected — adds a second REST endpoint, a second sanitizer, and a second surface admins must reason about. Alternative 2: seed default `enabled=false` entries at plugin activation. Rejected — requires DB writes on activation and creates hard-to-reason-about state when a new integration ships in a future update. The chosen approach isolates the asymmetry to a single documented helper. |

## Security Review Summary (inline — Phase 4)

Full write-up in [security-constraints.md](./security-constraints.md). Highlights:

- **Trust boundary**: the toggle write path. Enforced with two capability checks — the existing REST controller's floor (`manage_options`) AND the new filter-derived capability. Both must pass. No client-side-only enforcement.
- **Data isolation**: multisite. `is_plugin_active()` is checked per-site so the integration UI only appears on sites where the target plugin is loaded; the toggle state, however, is network-wide because `acrossai_library_config` uses `get_site_option`/`update_site_option`. Behaviour is consistent with the rest of the Library page.
- **Validation**: all integration slugs pass `AcrossAI_Ability_Library_Config::sanitize_key_field()` at the Registry boundary. The `card_variant` field is sanitized the same way. No unescaped output — `LibraryCard` renders through `@wordpress/components`, which handles escaping.
- **Async security context**: not applicable — this feature performs no async or background work.
- **Third-party integration failure modes**: covered by FR-004 / FR-012 / FR-013. Target plugin missing, deactivated, or fatally errored MUST NOT compromise the Library page.

## Architecture Validation Summary (inline — Phase 5)

Full write-up feeds directly into the Governance Summary that follows this file. Highlights:

- **Constitution violations**: 1 documented (Boot Flow Rule, §I), justified above.
- **Security-Architecture Conflict**: none — the new capability filter is filter-only, not a bypass mechanism; the server-side check gates the write path irrespective of the JS render outcome.
- **Drift risk**: LOW — reuses existing Registry, Config, and REST pipeline. All new surfaces are additive (one new optional field, one new helper, one new filter, one new base class).
- **Consistency with prior features**: aligns with Feature 037 (tab_group), Feature 041 (meta['acrossai'] namespace boundary), Feature 046 (includes-tier for absorbed abilities), Feature 052 (bulk toggle URL sync), Feature 056 (bulk actions overhaul REST shape).

## Extension Surface — Third-Party Plugins Adding Cards to an Integration Tab (FR-017, FR-018)

Beyond the integration toggle card itself, an integration's tab is designed to be an
**extensibility surface**: any separate WordPress plugin can add its own regular ability cards
to the same tab, alongside the integration card, using ONLY existing infrastructure — no new
filter, no new admin API, no changes to this plugin required.

### Three-step contract

1. **Register the ability category on `wp_abilities_api_categories_init`.**
   WP core enforces this at `wp-includes/abilities-api/class-wp-abilities-registry.php` lines
   132-146 — `wp_register_ability()` silently returns null for any ability whose category is
   not pre-registered. Skipping this step means: (a) the Library UI card WILL render (it reads
   from our Registry, which doesn't validate categories), but (b) the underlying ability is
   dropped, so it will NOT appear in the Custom Abilities table or MCP `discover-abilities`.
   This is a WP core rule, not a Feature 060 defect. Discovery from real testing on
   2026-07-27 — captured in the base-class docblock, ACF subclass docblock, and quickstart.
2. **Extend `AcrossAI_Abilities_Manager\Includes\Modules\Library\Ability_Definition`** and
   implement `ability()` returning a full ability spec.
3. **Set `meta.acrossai.tab_group` on `args.meta.acrossai`** to the integration's published
   `TAB_GROUP` constant (e.g. `ACF::TAB_GROUP`), NOT a hardcoded string. Each concrete
   integration exposes this constant (FR-017) so add-on plugins have a stable reference.

### Why this is the right shape

- Reuses the exact same `Ability_Definition` → `acrossai_abilities_api_init` → Registry →
  Library Processor → `wp_register_ability()` pipeline used by the 176 core abilities.
- Reuses the existing `tab_group` display mechanism from Feature 037.
- No new REST endpoint, no new admin surface, no new option key.
- Add-on cards render as **regular** library cards (toggle, expand, All/Specific, per-ability
  checkboxes) so they visually align with the Core cards on other tabs.
- Uses a **distinct category slug per add-on** (e.g. `'my-plugin-acf'` vs the integration's
  own `'acf'`) so the add-on's card is separate from the integration's read-only card. Merging
  would (a) fail WP core's category check on the add-on side and (b) create a confusing
  single-card view.

### Worked demo mu-plugin

A complete working example lives at `wp-content/mu-plugins/060-acf-tab-extension-demo.php`
(temporary, delete after testing). It registers two categories (`demo-acf-helpers` and
`demo-acf-reports`) on `wp_abilities_api_categories_init`, then instantiates three anonymous
`Ability_Definition` subclasses targeting `ACF::TAB_GROUP`. The result is 3 extra cards on the
ACF tab plus 3 extra rows in the Custom Abilities table.

### What this plan explicitly does NOT add

- No auto-registration of categories on behalf of add-ons — add-on plugins own their category
  registration. Attempting to auto-register would require this plugin to enumerate categories
  from add-on-provided data at load time, which is complex and easy to get wrong. Requiring
  each add-on to declare its own category is cleaner and matches how the 20+ core categories
  work in Feature 046.
- No "add-on manager" UI. Each add-on is a normal WordPress plugin the admin activates
  through the standard Plugins screen. The Ability Library page only surfaces what's
  registered; it does not manage the add-ons themselves.
