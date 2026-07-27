# Planning: Library Third-Party Integration Toggles (Feature 060)

> **Implementation divergences from this plan** (recorded 2026-07-27):
>
> 1. **`is_plugin_active()` uses a compound check.** Per security-review finding SEC-002, the ACF subclass uses `defined( 'ACF_VERSION' ) && function_exists( 'acf_get_setting' )` rather than the originally documented `class_exists( 'ACF' ) || defined( 'ACF_VERSION' )`. The compound predicate is not spoofable by an unrelated plugin that happens to define a class/constant named `ACF`, and it aligns with the same code path ACF reads through when it evaluates its own `enable_acf_ai` setting.
>
> 2. **Sparse-storage bugfix in `AcrossAI_Ability_Library_Config::save_config()`.** The original plan reused the existing sparse-storage optimisation as-is, but that optimisation was hardcoded to `default = enabled=true`. Integration categories invert that default (missing entry = OFF per FR-008), so a `{ enabled: true, mode: 'all', sub_keys: {} }` payload for an integration was being stripped and the toggle appeared to turn itself off on reload. Fix: `save_config()` now looks up the current set of integration slugs via a new public helper `AcrossAI_Ability_Library_Registry::get_integration_slugs()` and computes the correct default per-category before deciding whether to strip. Regression coverage: three PHPUnit tests in `Test_Integration_Ability_Base.php` (`test_save_config_preserves_integration_on_state`, `test_save_config_strips_integration_off_default`, `test_save_config_still_strips_regular_on_default`). The private helper `registered_integration_slugs()` that briefly lived on the REST controller was removed in favour of the Registry method (Constitution §VI DRY).
>
> 3. **Extension surface + `TAB_GROUP` constant + category-registration requirement.** The integration tab is designed to be extensible — a separate third-party plugin can add its own regular ability cards to the same tab as the integration's toggle card. The mechanism was already in place (Feature 037's `tab_group` + `Ability_Definition`) but not documented as a public extension surface. Added: (a) `public const TAB_GROUP = 'acf'` on the ACF subclass (FR-017) so add-ons reference a stable identifier; (b) a full extension-pattern docblock on both `AcrossAI_Integration_Ability_Base` (class-level) and `ACF` (with a worked add-on example); (c) FR-017 + FR-018 in the spec and User Story 5; (d) a "Extension Surface" section in `plan.md`; (e) a worked demo mu-plugin at `wp-content/mu-plugins/060-acf-tab-extension-demo.php` plus quickstart steps. **Critical WP-core rule surfaced during testing**: add-on abilities MUST register their category on `wp_abilities_api_categories_init` (via `wp_register_ability_category()`), otherwise `wp_register_ability()` silently returns null. The Library UI card still renders (Registry-driven), but the underlying abilities never enter `wp_get_abilities()` — so they are absent from the Custom Abilities table and from MCP `discover-abilities`. Not a Feature 060 defect; enforced by `wp-includes/abilities-api/class-wp-abilities-registry.php` lines 132-146. Documented in three places (base-class docblock, ACF subclass docblock, quickstart) so add-on authors can't miss it.
>
> None of these divergences change the storage shape, REST contract, or user-visible behaviour of the integration card itself. All are captured in `specs/060-library-third-party-integration-toggles/tasks.md` (T013 for the compound check, T017a for the bugfix, T017b for the extension pattern + category-registration docs).


Add a "third-party integration" card pattern to the Ability Library page
(`/wp-admin/admin.php?page=acrossai-abilities-library`) so site admins can enable/disable
third-party plugins that gate their WP Abilities API abilities behind their own filter
(e.g. Advanced Custom Fields requires `add_filter( 'acf/settings/enable_acf_ai', '__return_true' )`
before it registers its FieldGroup / PostType / Taxonomy abilities).

Ships an abstract base class that future integrations subclass, plus ACF as the first
concrete reference implementation. Integration cards render as their own tab (one tab per
plugin), show a single enable/disable toggle in the header, and display a fixed readonly
ability list below — no All/Specific radio, no per-ability checkboxes. Toggle state reuses
the existing `acrossai_library_config` option so no new REST endpoint is required.

Integration cards default to OFF (inverting the sparse-storage default that regular library
categories use) so enabling an integration is always an explicit admin decision. And every
render path is gated on `is_plugin_active()` returning true — when the target third-party
plugin is missing or deactivated, the tab and card do not appear at all.

---

## Spec-kit Workflow

```markdown
# 1. Branch
/speckit.git.feature "060-library-third-party-integration-toggles"

# 2. Specify
/speckit.specify "Add a 'third-party integration' card pattern to the Ability Library page
so site admins can toggle plugins whose WP Abilities API abilities are gated behind a plugin-specific filter.
First integration: Advanced Custom Fields (ACF), which requires add_filter('acf/settings/enable_acf_ai','__return_true')
before advanced-custom-fields/src/AI/AI.php registers its abilities.

Locked design decisions:
(1) One tab per integration — each subclass declares its own tab_group (e.g. 'acf', later 'woocommerce'). Falls out of the existing tab_group mechanism from Feature 037; no changes to collectTabGroups.
(2) Toggle state reuses acrossai_library_config — each integration is a synthetic 'category' entry with enabled=true|false, mode='all', sub_keys={}. No new REST endpoint or controller.
(3) Card UI is toggle-only + fixed readonly ability list — no All/Specific radio (RadioControl at LibraryCard.js lines 135-159 is suppressed), no per-ability checkboxes. Slug rows fall through the existing readonly render path (LibraryCard.js lines 216-229) that is already used when mode==='all'.
(4) Integration cards default to enabled=false. Regular categories default to enabled=true via sparse storage; this feature adds an integration-specific getter (AcrossAI_Ability_Library_Config::is_integration_enabled) that inverts the default only for integration categories. The JS card mirrors the same inversion behind cardVariant==='integration'.
(5) The base class must gate all behavior on subclass-provided is_plugin_active(). Both hook callbacks (plugins_loaded P20 for enable_filter side-effect; acrossai_abilities_api_init P10 for definition row) return early when false. Plugin state is checked per-request, not cached at construction, so activating/deactivating the target plugin takes effect on the very next page load.

Add:
(a) includes/Modules/Library/Integrations/AcrossAI_Integration_Ability_Base.php — abstract class with subclass contract slug()/label()/is_plugin_active()/enable_filter()/abilities(). Base class handles hook wiring, definition-row push, config read, and the is_plugin_active gate.
(b) includes/Abilities/Integrations/ACF.php — concrete subclass. slug='acf'; label='Advanced Custom Fields (AI)'; is_plugin_active returns class_exists('ACF') || defined('ACF_VERSION'); enable_filter calls add_filter('acf/settings/enable_acf_ai','__return_true'); abilities is a static array mirroring what advanced-custom-fields/src/AI/Abilities/{FieldGroup,PostType,Taxonomy}.php register (labels + descriptions).

Modify:
(c) includes/Main.php — instantiate the ACF integration at plugins_loaded P20 (same tier used by absorbed core abilities in Feature 046).
(d) includes/Modules/Library/AcrossAI_Ability_Library_Registry.php — add 'card_variant' to OPTIONAL_FIELDS and pass through in validate_and_normalize() using AcrossAI_Ability_Library_Config::sanitize_key_field, mirroring the tab_group block at lines 236-241. Display-only; never persisted; same contract as tab_group and sub_group.
(e) includes/Modules/Library/AcrossAI_Ability_Library_Config.php — add is_integration_enabled(string $category): bool helper that reads get_config() and treats missing entries as false. Single source of truth for the integration default inversion.
(f) src/js/ability-library/components/LibraryPage.js — in groupDefinitions() (line 25), read card_variant and expose it on grouped items as cardVariant. Pass through to <LibraryCard>. No changes to collectTabGroups / filterItemsByTabGroup.
(g) src/js/ability-library/components/LibraryCard.js — accept cardVariant prop on item. When cardVariant==='integration': suppress the RadioControl block, default enabled to false when config[category] is missing, and always render slugs readonly (existing else-branch handles it once the radio is gone).
(h) Rebuild build/js/ability-library.js (npm run build or the plugin-standard equivalent).

Constraints:
- Do not modify the acrossai_library_config option shape. Integration entries reuse the existing sanitize_entry() shape.
- Do not silently mutate config when a target plugin is deactivated. Saved enabled=true persists across ACF deactivate/reactivate cycles.
- Do not touch third-party plugin classes when the plugin is missing. is_plugin_active() must guard every path that could reference an ACF symbol.
- Do not add a new REST endpoint. Reuse GET/POST /acrossai-abilities-library/v1/abilities/config."
```

---

## Scope Rules

### In scope

- Abstract base class for third-party integration cards (`AcrossAI_Integration_Ability_Base`).
- One concrete reference implementation: ACF.
- Registry pass-through of a new optional `card_variant` field.
- JS render tweaks in `LibraryPage.js` and `LibraryCard.js` for `cardVariant==='integration'`.
- Rebuild of `build/js/ability-library.js`.

### Out of scope

- No changes to REST endpoint paths, controllers, or response shapes.
- No changes to the `acrossai_library_config` on-disk shape or `sanitize_entry()` behavior.
- No changes to the WordPress Abilities API registration pipeline downstream of ACF's own `wp_abilities_api_init` hook.
- No auto-migration or auto-cleanup when a target plugin is deactivated (saved config persists across activation cycles).
- No additional integrations beyond ACF (WooCommerce, WPForms, etc. are follow-ups that subclass the same base class).
- Permission-callback compliance audit (per DEC-SKIP-PERMISSION-CALLBACK-AUDIT).

---

## Architecture Overview

Two independent pieces of behavior flow through the existing Library pipeline:

1. **Synthetic Library definition rows.** For each subclass whose `is_plugin_active()` returns
   true, the base class pushes one row per declared ability into the
   `acrossai_abilities_api_init` filter at init P10. Rows carry the standard fields
   (`category`, `category_label`, `slug`, `slug_label`, `name`, `args`) plus the existing
   optional `tab_group` (set to the subclass slug) and the new optional `card_variant='integration'`.
   `AcrossAI_Ability_Library_Registry::validate_and_normalize()` accepts them alongside real
   abilities. `LibraryPage.groupDefinitions()` folds them into one card per category
   automatically.

2. **Pre-registration filter attachment.** At `plugins_loaded` P20 the base class reads
   `acrossai_library_config`. If the integration is enabled AND the target plugin is active,
   the base class invokes the subclass's `enable_filter()`, which attaches the third-party
   plugin's own `add_filter(...)` early enough that the plugin picks it up on the current
   request.

Both hook callbacks call `is_plugin_active()` first. When the target plugin is missing or
deactivated, no synthetic row is pushed (no tab, no card) and no filter is attached.

---

## CHANGE-1 — Abstract Base Class

**File**: `includes/Modules/Library/Integrations/AcrossAI_Integration_Ability_Base.php` *(new)*

**Namespace**: `AcrossAI_Abilities_Manager\Includes\Modules\Library\Integrations`

Subclass contract (all abstract):

| Method | Return | Purpose |
| --- | --- | --- |
| `slug(): string` | key | Used as both `category` and `tab_group`; sanitized via `AcrossAI_Ability_Library_Config::sanitize_key_field()` |
| `label(): string` | string | Display label for the card and tab (JS `titleCaseTabLabel()` will handle tab casing) |
| `is_plugin_active(): bool` | bool | The plugin-existence gate — see below |
| `enable_filter(): void` | void | Subclass attaches its `add_filter(...)` here |
| `abilities(): array` | `array<array{slug:string, label:string, description?:string}>` | Fixed readonly list rendered inside the card |

Base class behavior:

- Constructor wires two hooks:
  - `add_action( 'plugins_loaded', array( $this, 'maybe_enable' ), 20 )`
  - `add_filter( 'acrossai_abilities_api_init', array( $this, 'push_definition' ), 10 )`

- `maybe_enable()` — early return if `is_plugin_active()` is false; else read
  `AcrossAI_Ability_Library_Config::is_integration_enabled( $this->slug() )` (see CHANGE-3);
  if true, call `$this->enable_filter()`.

- `push_definition( array $definitions ): array` — early return with `$definitions` unchanged
  if `is_plugin_active()` is false. Otherwise loop `$this->abilities()` and push one row per
  ability sharing the same `category`/`category_label`/`tab_group` and
  `card_variant='integration'`. Each row includes the required `args` array (with a placeholder
  `execute_callback` returning `WP_Error` if invoked, since these rows are display-only for the
  library UI and the actual abilities register through the third-party plugin's own pipeline
  when the filter is attached).

**Plugin-existence gate contract.** The `is_plugin_active()` check is the single gate that
determines whether an integration is visible or wired at all. Both hook callbacks call it
first and return early when it is false. Because it runs per-request rather than being cached
at construction, activating or deactivating the target plugin takes effect on the very next
page load — no cache-flush step required. Subclasses should use stable public symbols such as
`class_exists('ACF')` or `defined('ACF_VERSION')` for the check; these do not require
`wp-admin/includes/plugin.php` to be loaded (which `is_plugin_active()` does). For
network-active plugins, subclasses may additionally check `is_plugin_active_for_network()`
after `require_once ABSPATH . 'wp-admin/includes/plugin.php'`.

**Safety property.** If the target plugin is deactivated after the admin turned the toggle
on, the stored `enabled=true` config entry remains untouched in `acrossai_library_config`,
but zero UI is shown and zero filters are applied. Re-activating the plugin restores the card
in its previous on/off state. Do not silently mutate saved config in response to a plugin's
activation lifecycle.

---

## CHANGE-2 — ACF Concrete Subclass

**File**: `includes/Abilities/Integrations/ACF.php` *(new)*

**Namespace**: `AcrossAI_Abilities_Manager\Includes\Abilities\Integrations`

Returns:

- `slug()` → `'acf'`
- `label()` → `__( 'Advanced Custom Fields (AI)', 'acrossai-abilities-manager' )`
- `is_plugin_active()` → `class_exists( 'ACF' ) || defined( 'ACF_VERSION' )`
- `enable_filter()` → `add_filter( 'acf/settings/enable_acf_ai', '__return_true' )`
- `abilities()` → static array with entries for the three ACF ability groups documented in
  `advanced-custom-fields/src/AI/Abilities/{FieldGroup,PostType,Taxonomy}.php`, each with
  `slug`, `label`, and a short `description` copied so the readonly card is informative even
  before the toggle is flipped.

Instantiated once, at `plugins_loaded` P20, via CHANGE-4.

---

## CHANGE-3 — Config Helper: Integration Default Inversion

**File**: `includes/Modules/Library/AcrossAI_Ability_Library_Config.php`

Add one static helper:

```php
/**
 * Whether a third-party integration category is enabled.
 *
 * Unlike regular categories (which default to enabled=true via sparse storage),
 * integration categories default to enabled=false — a missing config entry means OFF.
 * Enabling an integration must always be an explicit admin decision.
 *
 * @param string $category Integration slug (used as the category key).
 * @return bool
 */
public static function is_integration_enabled( string $category ): bool {
	$config = self::get_config();
	if ( ! isset( $config[ $category ] ) ) {
		return false;
	}
	return isset( $config[ $category ]['enabled'] ) && (bool) $config[ $category ]['enabled'];
}
```

Rules:

- Do not change the shape of `get_config()`, `save_config()`, or `sanitize_entry()`.
- Do not change the sparse-storage semantics for regular categories.
- The integration inversion lives in exactly two places: this helper (PHP) and the
  `cardVariant === 'integration'` branch in CHANGE-6 (JS). Nothing else in the codebase needs
  to know about the asymmetry.

---

## CHANGE-4 — Wire ACF Integration at plugins_loaded P20

**File**: `includes/Main.php`

Instantiate `AcrossAI_Abilities_Manager\Includes\Abilities\Integrations\ACF` at
`plugins_loaded` P20, alongside the absorbed core abilities instantiation from Feature 046.

Rules:

- Do not require `wp-admin/includes/plugin.php` here (base class constructor is safe to run
  regardless of plugin state; `is_plugin_active()` gates all side effects).
- Do not add any autoloader entry for `Integrations\ACF` beyond the plugin's existing PSR-4
  namespace mapping (already covers `includes/`).

---

## CHANGE-5 — Registry Pass-Through of `card_variant`

**File**: `includes/Modules/Library/AcrossAI_Ability_Library_Registry.php`

Two edits, both mirroring the existing `tab_group` pattern from Feature 037:

1. Add `'card_variant'` to `OPTIONAL_FIELDS` (currently at line 84 alongside `sub_group`,
   `sub_group_label`, `tab_group`).

2. Add a pass-through block in `validate_and_normalize()` after the existing `tab_group`
   block (lines 236-241):

```php
// Feature 060 — optional card_variant pass-through.
// Display-only; never written to saved configuration. Sanitized at the
// Registry boundary via sanitize_key_field() so the JS receives a
// predictable key shape (e.g. 'integration').
if ( isset( $item['card_variant'] ) && '' !== $item['card_variant'] ) {
	$clean_variant = AcrossAI_Ability_Library_Config::sanitize_key_field( (string) $item['card_variant'] );
	if ( '' !== $clean_variant ) {
		$entry['card_variant'] = $clean_variant;
	}
}
```

Rules:

- Do not modify `REQUIRED_FIELDS` or `ALLOWED_ARGS_FIELDS`.
- Do not persist `card_variant` in `acrossai_library_config` — display-only, same contract
  as `sub_group` and `tab_group`.

---

## CHANGE-6 — JS: LibraryPage & LibraryCard Render Adjustments

**File**: `src/js/ability-library/components/LibraryPage.js`

In `groupDefinitions()` (line 25):

- Destructure `card_variant: cardVariant` from each definition.
- Store the first non-empty `cardVariant` seen for a category on the grouped item (regular
  categories will simply have `cardVariant === undefined`, matching today's behavior).
- Pass `cardVariant` through to `<LibraryCard>` alongside `item`, or fold it into the `item`
  object itself for a smaller prop surface.

No changes needed to `collectTabGroups()`, `filterItemsByTabGroup()`, or `titleCaseTabLabel()`.
One-tab-per-integration falls out naturally because each subclass sets its own `tab_group`.

**File**: `src/js/ability-library/components/LibraryCard.js`

- Accept a `cardVariant` field on `item` (destructure alongside `category`, `categoryLabel`,
  `slugs`, `tabGroups`).
- When `cardVariant === 'integration'`:
  - Suppress the entire `RadioControl` block (lines 135-159). The `mode` state stays at
    `'all'` by default so the slug list falls through to the existing readonly branch
    (lines 216-229) that is already used when `!(enabled && mode === 'specific')`.
  - Default `enabled` to `false` when `config[category]` is missing (change the default at
    line 63 to be conditional on `cardVariant`). Regular categories continue to default to
    `true`.
- Otherwise render exactly as today.

Rebuild:

```bash
npm run build
```

Refreshes `build/js/ability-library.js` (loaded by `admin/Main.php`).

---

## Storage Shape (unchanged, new consumer)

On-disk entry once the admin toggles ACF on:

```json
{
  "acf": { "enabled": true, "mode": "all", "sub_keys": {} }
}
```

Toggled off explicitly:

```json
{
  "acf": { "enabled": false, "mode": "all", "sub_keys": {} }
}
```

Missing key → treated as `enabled=false` **only** for integration categories (regular
categories still default to `true` per existing sparse-storage behavior). The asymmetry
lives inside `AcrossAI_Ability_Library_Config::is_integration_enabled()` (PHP) and the
`cardVariant === 'integration'` branch in `LibraryCard.js` (JS). Nothing else needs to know.

---

## What Must NOT Change

- Do not change the shape of `acrossai_library_config` or the behavior of `sanitize_entry()`.
- Do not add a new REST endpoint — reuse GET/POST `/acrossai-abilities-library/v1/abilities/config`.
- Do not persist `card_variant` in saved config; it is display-only like `sub_group`/`tab_group`.
- Do not touch ACF classes when ACF is not active — every path that could reference an ACF
  symbol must sit behind `is_plugin_active()`.
- Do not silently mutate saved config in response to a plugin's activation lifecycle.
- Do not modify `collectTabGroups()`, `filterItemsByTabGroup()`, or the pinned-first tab
  constant `PINNED_FIRST_TAB_GROUP`.
- Do not add per-ability checkboxes or an All/Specific radio for integration cards.
- Do not add auto-migration for integration entries when a target plugin is deactivated.

---

## Expected Files Changed

```text
includes/Modules/Library/Integrations/AcrossAI_Integration_Ability_Base.php   (new)
includes/Abilities/Integrations/ACF.php                                       (new)
includes/Modules/Library/AcrossAI_Ability_Library_Registry.php
includes/Modules/Library/AcrossAI_Ability_Library_Config.php
includes/Main.php
src/js/ability-library/components/LibraryPage.js
src/js/ability-library/components/LibraryCard.js
build/js/ability-library.js                                                   (regenerated)
```

Optional (if included as part of this feature):

```text
tests/Modules/Library/Integrations/AcrossAI_Integration_Ability_Base_Test.php (new)
src/js/ability-library/components/__tests__/LibraryCard.test.js               (new/appended)
```

---

## Validation Checklist

### Registry & PHP

- [ ] `card_variant` is present in `OPTIONAL_FIELDS` in `AcrossAI_Ability_Library_Registry.php`.
- [ ] The registry pass-through sanitizes via `sanitize_key_field()` and only propagates
      non-empty values (mirrors the `tab_group` block from Feature 037).
- [ ] `AcrossAI_Ability_Library_Config::is_integration_enabled()` returns `false` for missing
      entries and `true` only when the entry explicitly sets `enabled` to true.
- [ ] `AcrossAI_Integration_Ability_Base::push_definition()` is a no-op when
      `is_plugin_active()` returns false (no rows added to `$definitions`).
- [ ] `AcrossAI_Integration_Ability_Base::maybe_enable()` does not call `enable_filter()` when
      `is_plugin_active()` is false, and does not call `enable_filter()` when the config entry
      is missing.
- [ ] ACF subclass reports `is_plugin_active()` as false when neither `ACF` class nor
      `ACF_VERSION` constant is defined.
- [ ] ACF is instantiated at `plugins_loaded` P20 from `includes/Main.php`.

### JS & Card UI

- [ ] `groupDefinitions()` propagates `card_variant` from raw definitions to grouped items
      as `cardVariant`.
- [ ] `LibraryCard` hides the `RadioControl` block entirely when `cardVariant === 'integration'`.
- [ ] `LibraryCard` defaults `enabled` to `false` for missing config entries when
      `cardVariant === 'integration'`; regular categories still default to `true`.
- [ ] Slug list renders readonly (no `CheckboxControl`) for integration cards regardless of
      toggle state.
- [ ] `build/js/ability-library.js` is regenerated and reflects the source changes.

### Manual browser verification

- [ ] **ACF not installed** — the library page shows no "Acf" tab and no ACF card on any tab.
- [ ] **ACF installed but deactivated** — same result: no tab, no card.
- [ ] **ACF activated** — an "Acf" tab appears; the card is present with the toggle **off**
      by default; the readonly ability list lists FieldGroup / PostType / Taxonomy with
      descriptions.
- [ ] Toggling on auto-saves (existing `saveConfig` flow); reload persists the toggle state.
- [ ] After toggling on, ACF's own AI abilities appear elsewhere in the library on the next
      page load (verify via the "All" tab or via MCP `discover-abilities`).
- [ ] Toggling off removes ACF's AI abilities on the next request.
- [ ] The "Acf" card shows **no** All/Specific radio and **no** per-ability checkboxes.
- [ ] **Deactivate ACF while the toggle is on** — reload the library page: the tab and card
      disappear entirely (no orphan UI), and the debug log shows no PHP notices about
      undefined ACF classes.
- [ ] **Re-activate ACF** — reload: the tab returns with the toggle still on (saved config
      was preserved during the deactivation window).

### Storage inspection

- [ ] Inspect `wp_options` (or `wp_sitemeta` on multisite) via
      `mcp__agents__mcp-local-wp__mysql_query` before/after toggling to confirm the raw
      `acrossai_library_config` shape matches the "Storage Shape" section above.
- [ ] Confirm no new option keys are introduced (all state stays in
      `acrossai_library_config`).

### Quality gates

- [ ] `composer run phpstan` passes.
- [ ] PHPCS on the changed production PHP files reports no new errors.
- [ ] `npm run build` succeeds and no ESLint errors are introduced on the changed JS files.
- [ ] If tests are included: `composer test` passes for the new PHPUnit case; Jest passes
      for the new `LibraryCard` case.

---

## Spec-kit Commands

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
composer run phpstan
composer run phpcs
npm run build

# 6. Review + memory + commit
/speckit.analyze
/speckit.architecture-guard.architecture-review
/speckit.security-review.staged
/speckit.memory-md.capture-from-diff
/speckit.git.commit
```
