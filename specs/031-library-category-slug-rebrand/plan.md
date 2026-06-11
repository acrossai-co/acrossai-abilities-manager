# Implementation Plan: Library Category/Slug Rebrand (Feature 031)

**Branch**: `031-library-category-slug-rebrand` | **Date**: 2026-06-11 | **Spec**: [spec.md](spec.md)

---

## Summary

Rename the Library module's two-level grouping scheme from `main_key`/`sub_key` to
`category`/`slug` across 5 PHP files and 3 JS files. Concurrently, update the Library
admin page to display each ability's existing `name` field as the per-row visible label
(with `slug_label` fallback). The on-disk saved option shape (`acrossai_library_config`)
is intentionally **not** changed — only in-memory runtime field names and component props
are renamed. No new classes, REST routes, DB tables, or JS bundles are introduced.

---

## Technical Context

- **Language/Version**: PHP 8.1+ / JavaScript ES2020 via `@wordpress/scripts`
- **Primary Dependencies**: `@wordpress/components` (ToggleControl, CheckboxControl), `@wordpress/element`, `@wordpress/i18n`
- **Storage**: No DB schema change. `get_site_option('acrossai_library_config')` retains its current shape; `sub_keys` inner map key intentionally preserved (DEC-D6 sparse-storage rule)
- **Testing**: PHPStan L8, PHPCS/WPCS, ESLint, `npm run build`, manual browser smoke test. PHPUnit only if existing test fixtures reference `main_key`/`sub_key` field names
- **Target Platform**: WordPress 6.9+ / PHP 8.1+ / multisite-compatible
- **Scale/Scope**: 5 PHP files + 3 JS files. Zero new files. Zero concrete `Ability_Definition` subclasses in-repo (verified)
- **Performance**: No runtime impact — purely a rename. Registry collection, Processor gating, REST endpoints retain existing wiring

---

## Constitution Check

| Principle | Status | Notes |
|---|---|---|
| §I Modular Architecture | **PASS** | All changes confined to `includes/Modules/Library/` and `src/js/ability-library/`. No cross-module reach. No new modules. |
| §II WordPress Standards | **PASS** | `snake_case` field naming throughout. No new sanitization surface. PHPCS + PHPStan required. Plugin Check surface unchanged. |
| §III User-Centric Design | **ACCEPTED DEVIATION** | Library UI uses `@wordpress/components` cards (ToggleControl + CheckboxControl) rather than DataViews. This is pre-existing DEC-DESIGN-OVERRIDES-DATAVIEWS. Feature 031 does NOT introduce DataViews — out of scope. Deviation remains recorded. |
| §IV Security First | **PASS** | No new attack surface. REST capability + nonce gate unchanged. `wp_kses_post()` + `sanitize_key_field()` remain on all incoming values. |
| §V Extensibility | **PASS** | `acrossai_abilities_api_init` filter hook name unchanged. Zero new hooks. |
| §VI DRY | **PASS** | Vocabulary now consistent with Abilities module (`category`/`slug`). Removes the parallel `main_key`/`sub_key` terminology. |
| §VII Definition of Done | **TRACKED** | PHPCS ✓, PHPStan L8 ✓, ESLint ✓, npm build ✓, browser smoke test ✓. No new logic → existing tests updated (not new tests written). |

---

## Memory-Informed Decisions

Relevant decisions from `docs/memory/INDEX.md` applied to this plan:

| Decision | Impact on this feature |
|---|---|
| **DEC-DESIGN-OVERRIDES-DATAVIEWS** | Library UI keeps custom card pattern; no DataViews introduced. |
| **DEC-UTILITY-STATIC-ONLY** | No new utility classes needed — rename is entirely within existing classes. |
| **DEC-NAMESPACE-CONVENTION** | Class FQCNs unchanged; underscore convention preserved. |
| **DEC-USE-STATEMENT-CONSISTENCY** | No new `use` statements needed. |
| **BUG-LIBRARY-HOOK-SUFFIX** | `is_library_page()` uses dynamic `get_hook_suffix()` — no change needed. |
| **BUG-WP-LOCALIZE-SCRIPT-RENDER** | `wp_add_inline_script('before')` pattern in `Admin\Main::enqueue_scripts()` — no change needed. |
| **AC-ENQUEUE-ADMIN** | Library data injection already corrected in Feature 030 — no change needed. |
| **DEC-ABILITIES-LIST-UX-025** | `window.*` global with `'before'` position pattern — already in place. |
| **BUG-STATIC-METHOD-SINGLETON-BYPASS** | No new static methods introduced. |
| **DEC-SINGLETON-PSR2-PROPERTY** | No new singletons. |

**New decision introduced by this feature** (to be captured in memory after implementation):
- **DEC-LIBRARY-CATEGORY-SLUG-REBRAND**: Library abstract contract renamed from `main_key`/`sub_key` to `category`/`slug`; on-disk `sub_keys` wire key intentionally preserved for backwards-compat.

---

## Architecture

### Boundary Model

```
┌─────────────────────────────────────────────────────┐
│  Entry: admin/Partials/LibraryMenu.php               │
│    render() → HTML wrapper only (no data logic)      │
│    enqueue path: Admin\Main::enqueue_scripts()        │
└──────────────────────┬──────────────────────────────┘
                       │  window.acrossaiAbilityLibraryData
                       ▼
┌─────────────────────────────────────────────────────┐
│  JS: src/js/ability-library/                         │
│    LibraryPage.js  ← groupDefinitions() (RENAMED)    │
│    LibraryCard.js  ← per-category card (RENAMED)     │
│    api.js          ← docblock only                   │
└──────────────────────┬──────────────────────────────┘
                       │  REST: acrossai-abilities-library/v1
                       ▼
┌─────────────────────────────────────────────────────┐
│  Domain: includes/Modules/Library/                   │
│    Ability_Definition.php  ← abstract (RENAMED)      │
│    AcrossAI_Ability_Library_Registry.php  (RENAMED)  │
│    AcrossAI_Ability_Library_Processor.php (RENAMED)  │
│    AcrossAI_Ability_Library_Config.php    (docblock) │
│    Rest/ controllers  ← no code change               │
└──────────────────────┬──────────────────────────────┘
                       │  get_site_option('acrossai_library_config')
                       ▼
┌─────────────────────────────────────────────────────┐
│  Data: WordPress site options                        │
│    Top-level keys: category identifiers (unchanged)  │
│    Inner: { enabled, mode, sub_keys: {slug→bool} }   │
│    sub_keys map key: INTENTIONALLY UNCHANGED         │
└─────────────────────────────────────────────────────┘
```

### Critical Invariant: `args.category` vs top-level `category`

`AcrossAI_Ability_Library_Registry::ALLOWED_ARGS_FIELDS` already contains `'category'` as an allowed key inside `args` (this is the WordPress Abilities API ability category). The new top-level `category` field on definitions is at a **different array depth** and is validated separately in `REQUIRED_FIELDS`. These two do not collide. The docblock on `validate_and_normalize()` MUST explicitly document this distinction.

---

## Rename Map (authoritative)

| Old identifier | New identifier | File | Line(s) |
|---|---|---|---|
| `abstract protected function main_key()` | `abstract protected function category()` | `Ability_Definition.php` | ~34 |
| `abstract protected function main_key_label()` | `abstract protected function category_label()` | `Ability_Definition.php` | ~37 |
| `abstract protected function sub_key()` | `abstract protected function slug()` | `Ability_Definition.php` | ~40 |
| `abstract protected function sub_key_label()` | `abstract protected function slug_label()` | `Ability_Definition.php` | ~43 |
| `'main_key' => $this->main_key()` | `'category' => $this->category()` | `Ability_Definition.php` | ~66 |
| `'main_key_label' => $this->main_key_label()` | `'category_label' => $this->category_label()` | `Ability_Definition.php` | ~67 |
| `'sub_key' => $this->sub_key()` | `'slug' => $this->slug()` | `Ability_Definition.php` | ~68 |
| `'sub_key_label' => $this->sub_key_label()` | `'slug_label' => $this->slug_label()` | `Ability_Definition.php` | ~69 |
| `'main_key'` in `REQUIRED_FIELDS` | `'category'` | `Registry.php` | ~45 |
| `'main_key_label'` in `REQUIRED_FIELDS` | `'category_label'` | `Registry.php` | ~46 |
| `'sub_key'` in `REQUIRED_FIELDS` | `'slug'` | `Registry.php` | ~47 |
| `'sub_key_label'` in `REQUIRED_FIELDS` | `'slug_label'` | `Registry.php` | ~48 |
| Local `$main_key` / `$item['main_key']` | `$category` / `$item['category']` | `Registry.php` | ~166 |
| Local `$sub_key` / `$item['sub_key']` | `$slug` / `$item['slug']` | `Registry.php` | ~167 |
| Output keys `'main_key'`, `'main_key_label'` | `'category'`, `'category_label'` | `Registry.php` | ~177-178 |
| Output keys `'sub_key'`, `'sub_key_label'` | `'slug'`, `'slug_label'` | `Registry.php` | ~179-180 |
| `$definition['main_key']` → `$main_key` | `$definition['category']` → `$category` | `Processor.php` | ~95 |
| `$definition['sub_key']` → `$sub_key` | `$definition['slug']` → `$slug` | `Processor.php` | ~96 |
| `$config[$main_key]` | `$config[$category]` | `Processor.php` | ~99, ~103 |
| `$entry['sub_keys'][$sub_key]` | `$entry['sub_keys'][$slug]` | `Processor.php` | ~119 |
| Docblock FR refs: `main_key absent`, `sub_key absent` | `category absent`, `slug absent` | `Processor.php` | ~83-87 |
| Docblock: "main_key entry" | "category entry" | `Config.php` | `sanitize_entry()` docblock |
| `main_key: mainKey` JS destructure | `category` | `LibraryPage.js` | `groupDefinitions()` |
| `main_key_label: mainKeyLabel` | `category_label: categoryLabel` | `LibraryPage.js` | `groupDefinitions()` |
| `sub_key: subKey` | `slug` | `LibraryPage.js` | `groupDefinitions()` |
| `sub_key_label: subKeyLabel` | `slug_label: slugLabel` | `LibraryPage.js` | `groupDefinitions()` |
| Map key `mainKey`, group shape `mainKeyLabel, subKeys: []` | `category, categoryLabel, slugs: []` | `LibraryPage.js` | `groupDefinitions()` |
| Sub-entry shape `{ subKey, subKeyLabel }` | `{ slug, slugLabel, name }` | `LibraryPage.js` | `groupDefinitions()` |
| `handleChange(mainKey, updatedEntry)` | `handleChange(category, updatedEntry)` | `LibraryPage.js` | |
| `key={item.mainKey}` | `key={item.category}` | `LibraryPage.js` | card render |
| `{ mainKey, mainKeyLabel, subKeys }` destructure | `{ category, categoryLabel, slugs }` | `LibraryCard.js` | head |
| `config[mainKey]` | `config[category]` | `LibraryCard.js` | head |
| `subKeysConfig` local var | `slugsConfig` | `LibraryCard.js` | |
| `onChange(mainKey, ...)` | `onChange(category, ...)` | `LibraryCard.js` | `update()` |
| `<strong>{mainKeyLabel}</strong>` | `<strong>{categoryLabel}</strong>` | `LibraryCard.js` | ToggleControl label |
| `subKeys.length > 0` | `slugs.length > 0` | `LibraryCard.js` | Specific mode guard |
| `subKeys.map(({ subKey, subKeyLabel })` | `slugs.map(({ slug, slugLabel, name })` | `LibraryCard.js` | |
| `label={subKeyLabel}` | `label={name \|\| slugLabel}` | `LibraryCard.js` | CheckboxControl |
| `key={subKey}` | `key={slug}` | `LibraryCard.js` | |
| `subKeysConfig[subKey]` | `slugsConfig[slug]` | `LibraryCard.js` | |
| `{ ...subKeysConfig, [subKey]: value }` | `{ ...slugsConfig, [slug]: value }` | `LibraryCard.js` | |
| CSS class `acrossai-library-card__sub-keys` | `acrossai-library-card__slugs` (if style exists) | `LibraryCard.js` + SCSS | optional |
| `@return keyed by main_key` docblock | `keyed by category` | `api.js` | |
| `@param config keyed by main_key` | `keyed by category` | `api.js` | |

**INTENTIONALLY NOT RENAMED** (on-disk wire format preserved):
- `sub_keys` map key in `AcrossAI_Ability_Library_Config::sanitize_entry()` return value
- `$entry['sub_keys']` access in `Processor::is_permitted()`
- `entry.sub_keys` in `LibraryCard.js` REST patch object

---

## Implementation Phases

### Phase 1 — PHP Rename (Parallel-safe)

**T001** `Ability_Definition.php` — rename 4 abstract methods + 4 `push_definition()` array keys
**T002** `AcrossAI_Ability_Library_Registry.php` — rename `REQUIRED_FIELDS`, local vars, output array keys; update `validate_and_normalize()` docblock to note the `args.category` vs top-level `category` distinction
**T003** `AcrossAI_Ability_Library_Processor.php` — rename `$main_key`/`$sub_key` local vars and definition reads; update FR-013–FR-017 docblock references; preserve `$entry['sub_keys'][$slug]` (on-disk key)
**T004** `AcrossAI_Ability_Library_Config.php` — docblock-only: rename "main_key entry" → "category entry" in `sanitize_entry()`; optionally add `const MAX_SLUGS = self::MAX_SUB_KEYS;`

### Phase 2 — JS Rename (Parallel-safe with Phase 1)

**T005** `LibraryPage.js` — update `groupDefinitions()` destructure, group shape, sub-entry shape, `handleChange` param, card `key` prop
**T006** `LibraryCard.js` — rename destructured props, local vars, ToggleControl label, CheckboxControl map (switch label source to `name || slugLabel`), update `onChange` arg; keep `sub_keys` in REST patch
**T007** `api.js` — docblock-only: rename "keyed by main_key" → "keyed by category"

### Phase 3 — Verify + Cleanup

**T008** Verify `admin/Partials/LibraryMenu.php` — grep for `main_key`/`sub_key`; update any comment references; no code changes expected
**T009** Check SCSS — grep `src/scss/` for `.acrossai-library-card__sub-keys`; if found, rename to `.acrossai-library-card__slugs` and update `LibraryCard.js` className simultaneously
**T010** Check PHPUnit test fixtures — grep `tests/phpunit/` for `main_key`/`sub_key` as array keys; update any definition fixtures; no new test logic needed
**T011** Residual grep — confirm zero occurrences of `main_key`, `sub_key`, `mainKey`, `subKey` as identifiers (excluding comments and the `sub_keys` wire key); fix any found

### Phase 4 — Quality Gate

**T012** `composer dump-autoload` — verify clean (no class renames; autoloader output unchanged)
**T013** `composer phpcs` — zero errors for all modified PHP files
**T014** `composer phpstan` — level 8 zero errors for all modified PHP files
**T015** `npm run build` — clean build; confirm `ability-library.js` artifact regenerates
**T016** ESLint — zero errors for modified JS files
**T017** Manual smoke test:
  - Load `wp-admin → Abilities Manager → Library`
  - Cards display `categoryLabel` titles
  - Specific mode shows ability `name` (or `slugLabel` fallback) per row
  - Toggle + Specific mode checkbox persists across reload
  - `wp option get acrossai_library_config --format=json` confirms saved shape unchanged

---

## What Must NOT Change

- `sub_keys` key in the on-disk config (top-level + inner map)
- `AcrossAI_Ability_Library_Config` constants: `MAX_KEY_LENGTH`, `MAX_KEYS`, `MAX_SUB_KEYS`, `VALID_MODES`
- REST namespace `acrossai-abilities-library/v1`
- `check_permission()` two-gate (capability + nonce) — `true|WP_Error` return type (BUG-PERMISSION-CALLBACK-TRUTHY-RESPONSE)
- `acrossai_abilities_api_init` filter hook name and `init` P99 priority
- `AcrossAI_Ability_Library_Processor` wiring at `wp_abilities_api_init` P5
- `is_library_page()` dynamic `get_hook_suffix()` pattern (BUG-LIBRARY-HOOK-SUFFIX)
- `wp_add_inline_script('before')` localization pattern (BUG-WP-LOCALIZE-SCRIPT-RENDER)
- The `args.category` key inside `ALLOWED_ARGS_FIELDS` (WordPress Abilities API arg — different depth)
- `update({ sub_keys: { ...slugsConfig, [slug]: value } })` in LibraryCard — REST wire key

---

## Security Assessment

This feature introduces **zero new attack surface**:
- No new REST endpoints, no new capability checks needed
- No new input sanitization paths (existing `sanitize_key_field()` + `wp_kses_post()` apply identically to renamed fields)
- No new database queries or option writes
- The `check_permission()` gate (`manage_options` + nonce) is unchanged

**Existing security controls that must remain intact**:
- `AcrossAI_Ability_Library_Config::sanitize_key_field()` applied to `$item['category']` and `$item['slug']` (same function, renamed input keys)
- `wp_kses_post()` applied to `category_label` and `slug_label`
- `name` field sanitized via regex `/[^a-z0-9_\-\/]/` (unchanged)

---

## Dependency Execution Order

```
Phase 1 (T001–T004) ──────────────── parallel-safe
Phase 2 (T005–T007) ──────────────── parallel-safe with Phase 1
Phase 3 (T008–T011) ──────────────── after Phase 1+2 complete
Phase 4 (T012–T017) ──────────────── after Phase 3 complete
```

T009 (SCSS rename) depends on T006 (LibraryCard className update) — must be synchronized if the CSS class exists.
