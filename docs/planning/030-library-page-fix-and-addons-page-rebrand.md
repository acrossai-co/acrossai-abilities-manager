# Planning: Library Page Fix + AddonsPage Rebrand (Feature 030)

Two independent fixes bundled into one feature branch because both are small and low-risk.

---

## Fix A — Ability Library page renders blank

### Problem

`/wp-admin/admin.php?page=acrossai-abilities-library` shows nothing.

The Library React app (`ability-library.js`) depends on `window.acrossaiAbilityLibraryData`
being present before it boots. Currently, `LibraryMenu::render()` calls `wp_localize_script()`
during the page-body callback — after `admin_enqueue_scripts` has fired. While this works for
footer scripts in theory, it diverges from the established pattern in this plugin (the Abilities
Manager page uses `wp_add_inline_script()` inside `enqueue_scripts()`) and is the likely cause of
the data arriving too late or not at all.

Secondary cause: `AcrossAI_Ability_Library_Registry::collect()` is hooked at `init P99`. If
`localize_data()` is called before that hook fires (e.g. during an early admin hook), definitions
will be empty and the React app will render the "No abilities registered yet" empty state — which
looks blank if the CSS is also missing.

### Root cause

`LibraryMenu::localize_data()` uses `wp_localize_script()` from inside the page render callback
instead of `wp_add_inline_script()` inside `admin\Main::enqueue_scripts()`, which is where all
other pages inject their data. `wp_localize_script()` is reliable only when called before scripts
are printed; `wp_add_inline_script()` is the safer, established pattern.

### Fix

**File: `admin/Main.php`** — inside the library block in `enqueue_scripts()`, after
`wp_enqueue_script('acrossai-ability-library-js')`, add:

```php
wp_add_inline_script(
    'acrossai-ability-library-js',
    'window.acrossaiAbilityLibraryData = ' . wp_json_encode(
        array(
            'definitions' => AcrossAI_Ability_Library_Registry::instance()->get_definitions(),
            'restBase'    => rest_url( AcrossAI_Ability_Library_Rest_Controller::REST_NAMESPACE ),
            'nonce'       => wp_create_nonce( 'wp_rest' ),
        )
    ) . ';',
    'before'
);
```

**File: `admin/Partials/LibraryMenu.php`** — remove the `localize_data()` call from `render()`
and delete the private `localize_data()` method entirely. The `render()` method becomes just the
HTML wrapper.

Note: `get_definitions()` is safe to call inside `enqueue_scripts()` because
`admin_enqueue_scripts` fires after `init`, so `collect()` at `init P99` has already run.

---

## Fix B — AddonsPage package rename: `wpboilerplate/addons-page` → `acrossai-co/addons-page`

### Problem

The `wpboilerplate/addons-page` package has been rebranded and republished as
`acrossai-co/addons-page`. The current `composer.json` still references the old vendor slug.
The new package may have a different namespace (to be confirmed when the new repo is inspected).

### Current state

| What | Old value |
|---|---|
| Composer require | `wpboilerplate/addons-page: ^0.0.17` |
| VCS repository | `https://github.com/WPBoilerplate/wpb-addons-page` |
| PHP namespace | `WPBoilerplate\AddonsPage\AddonsPage` |
| Usage in code | `includes/Main.php` L266 — `class_exists(\WPBoilerplate\AddonsPage\AddonsPage::class)` |
| Autoloader suffix | `WPBoilerplate_AddonsPage` |

### Changes required

1. **`composer.json`**
   - Replace `"wpboilerplate/addons-page": "^0.0.17"` with `"acrossai-co/addons-page": "<version>"`
   - Replace the VCS entry `https://github.com/WPBoilerplate/wpb-addons-page` with the new repo URL
     (or remove if published to Packagist under `acrossai-co`)
   - Run `composer update acrossai-co/addons-page`

2. **`includes/Main.php`** L266 — update `class_exists()` guard and `new` call to the new
   namespace. If the new package uses `AcrossAI\AddonsPage\AddonsPage` (likely), change:
   ```php
   // Before
   if ( class_exists( \WPBoilerplate\AddonsPage\AddonsPage::class ) ) {
       new \WPBoilerplate\AddonsPage\AddonsPage( ... );
   // After
   if ( class_exists( \AcrossAI\AddonsPage\AddonsPage::class ) ) {
       new \AcrossAI\AddonsPage\AddonsPage( ... );
   ```
   **Confirm the actual namespace from the new package's `composer.json` before implementing.**

3. **`docs/memory/DECISIONS.md`** — update `DEC-EXTERNAL-PACKAGE-HOOK-CTOR` entry to reflect
   new package name.

4. **Quality gates** — `composer phpcs` and `composer phpstan` must pass after the swap.

---

## Spec-Kit Workflow

```
# 1. Branch
/speckit.git.feature "030-library-page-fix-and-addons-page-rebrand"

# 2. Specify
/speckit.specify "Two fixes:
  A) Blank Library admin page: move wp_localize_script out of LibraryMenu::render() and
     replace with wp_add_inline_script inside admin/Main::enqueue_scripts() — matching
     the existing pattern on the Abilities Manager page.
  B) Rename Composer dependency from wpboilerplate/addons-page to acrossai-co/addons-page.
     Update composer.json repository entry and require key. Update PHP namespace reference
     in includes/Main.php (class_exists guard and new instantiation).
     Confirm new namespace from new package's composer.json before implementing."

# 3. Plan (memory-aware)
/speckit.memory-md.plan-with-memory

# 4. Tasks
/speckit.architecture-guard.governed-tasks

# 5. Implement
/speckit.architecture-guard.governed-implement

# 6. Quality gates
npm run build
composer phpcs
composer phpstan

# 7. Review
/speckit.architecture-guard.architecture-review
/speckit.security-review.staged

# 8. Close
/speckit.memory-md.capture-from-diff
/speckit.git.commit
```

---

## Scope

### In scope

**Fix A**
- `admin/Main.php` — move library data localization into `enqueue_scripts()` using
  `wp_add_inline_script()`; add `use` statement for Library classes if not already present
- `admin/Partials/LibraryMenu.php` — remove `localize_data()` call from `render()` and delete
  the private method

**Fix B**
- `composer.json` — require key + VCS repository entry
- `includes/Main.php` — namespace update in `define_admin_hooks()`
- `docs/memory/DECISIONS.md` — update `DEC-EXTERNAL-PACKAGE-HOOK-CTOR` package reference

### Out of scope

- No React changes to `src/js/ability-library/`
- No REST route changes
- No DB schema changes
- No new admin pages

---

## Key Pre-implementation Checks

1. **New namespace**: Before writing any code for Fix B, read
   `vendor/acrossai-co/addons-page/composer.json` (after `composer require`) to confirm the
   PSR-4 autoload namespace. Do not assume it matches the old `WPBoilerplate\AddonsPage` path.

2. **Library registry timing**: Verify `admin_enqueue_scripts` fires after `init P99` in the
   WordPress admin bootstrap order. (It does — `init` runs before `admin_init` and
   `admin_enqueue_scripts`.) This confirms `get_definitions()` will have collected data when
   called from `enqueue_scripts()`.

3. **`DEC-EXTERNAL-PACKAGE-HOOK-CTOR` update**: The decision records the old package name;
   update it atomically with the composer.json change so the memory stays accurate.
