# Planning: Composer Package Updates — wpb-access-control v2 + main-menu absorbs addons-page (Feature 039)

Two upstream composer packages shipped breaking releases. Adopt them both in a
single feature:

1. **`acrossai-co/main-menu` v0.0.7** absorbs the Add-ons page that used to
   live in the standalone `acrossai-co/addons-page` package. The consumer
   class name is unchanged (`\AcrossAI_Addon\AddonsPage`, now autoloaded from
   `vendor/acrossai-co/main-menu/src/Addons/`), but its constructor signature
   changed: the first positional `$menu_slug` argument is gone — `$parent_slug`
   is now an optional third arg defaulting to `'acrossai'`.
   `freemius/wordpress-sdk: ^2.0` is now pulled in transitively, so
   `acrossai-co/addons-page` is removed from `require` entirely.

2. **`wpboilerplate/wpb-access-control` v2.0.0** introduces per-consumer DB
   tables. `AccessControlManager::__construct()` and
   `RuleTable::__construct()` both take a new `$table_slug` argument. With
   slug `'abilities'`, this plugin gets its own `{prefix}abilities_access_control`
   table, `wpb_ac_abilities_db_version` schema option,
   `/wpb-ac/v1/abilities/...` REST routes, and `wpb_ac_abilities` cache group —
   fully isolated from any other plugin embedding the library.

Per the explicit instruction "do not care about backward compatibility", no
migration is performed from the legacy shared `{prefix}wpb_access_control`
table. Activation creates the new per-consumer table fresh, mirroring the
existing `( new XYZ_Table() )->maybe_upgrade();` lines for the other two
plugin tables in `AcrossAI_Activator::activate()`.

---

## Speckit Workflow

```markdown
# 1. Branch
/speckit.git.feature "composer-package-updates"

# 2. Specify
/speckit.specify "Update two composer packages with breaking releases.
First, drop `acrossai-co/addons-page` and bump `acrossai-co/main-menu` to
`^0.0.7` — main-menu now bundles AddonsPage (same class name
`\AcrossAI_Addon\AddonsPage`, new constructor signature: drop the first
positional `$menu_slug` arg; `$parent_slug` is now an optional third arg
defaulting to 'acrossai'). `freemius/wordpress-sdk: ^2.0` is pulled in
transitively. Second, bump `wpboilerplate/wpb-access-control` to `^2.0.0`
which adds a per-consumer `$table_slug` arg to AccessControlManager and
RuleTable. Adopt slug 'abilities' so this plugin owns
`{prefix}abilities_access_control`. Do not migrate from the legacy
`{prefix}wpb_access_control` table — no backward compatibility required.
Update the activator to create the new per-consumer table the same way the
plugin's other tables are created, and point uninstall cleanup at the new
table name and option key (`wpb_ac_abilities_db_version`)."
```

### Detailed Description for `/speckit.specify`

> **Before writing a single line of code, read and internalize all three of
> these governing documents in full:**
>
> 1. `.agents/skills/wp-plugin-development/SKILL.md` — and every file under
>    `.agents/skills/wp-plugin-development/references/` (boot-flow, security,
>    structure, hooks, rest-api).
> 2. `.specify/memory/CONSTITUTION.md` — pay special attention to §I Modular
>    Architecture, §II WordPress Standards, §V Integration Resilience,
>    §VI DRY, §VII Definition of Done, and the Boot Flow Rule and Module
>    Contract in the Architecture section.
> 3. `AGENTS.md` — the singleton pattern block, hook registration rules, and
>    the Before Commit Checklist.
>
> Then read the upstream package documentation:
>
> - `https://github.com/WPBoilerplate/wpb-access-control/blob/main/README.md`
>   — the new constructor signatures, table-naming convention, schema-version
>   option key (`wpb_ac_{slug}_db_version`), REST namespace shape
>   (`/wpb-ac/v1/{slug}/...`), and the "Two Plugins on One Site" section.
> - `https://github.com/acrossai-co/main-menu/blob/0.0.7/README.md` (or the
>   `main` branch if 0.0.7 is the head tag) — confirm the AddonsPage shipping
>   location and the new constructor positional order.
>
> Public API the work depends on:
> `\AcrossAI_Addon\AddonsPage::__construct( ?string $consumer_main_file = null,
> array $args = [], string $parent_slug = 'acrossai' )` (autoloaded under
> PSR-4 `AcrossAI_Addon\\` → `vendor/acrossai-co/main-menu/src/Addons/`);
> `\WPBoilerplate\AccessControl\AccessControlManager::__construct(
> string $providers_filter, string $table_slug = '' )`;
> `\WPBoilerplate\AccessControl\Database\Rule\RuleTable::__construct(
> string $table_slug )` — required, validated against `^[a-z0-9_]{1,32}$`.
>
> Every decision in this task — composer pinning, slug choice, constructor
> argument changes, activator/uninstall edits — must be justified against the
> three governing documents above. If a choice is not explicitly covered,
> default to the most restrictive interpretation of the Constitution. Do not
> write code that would fail any Definition-of-Done gate: PHPStan level 8,
> PHPCS, security review, all `__()` calls using the correct text domain
> `'acrossai-abilities-manager'`, and `npm run validate-packages`.
>
> ---
>
> **TASK-1 — Update `composer.json` and regenerate `composer.lock`**
>
> Files: `composer.json`, `composer.lock`
>
> Read `.agents/skills/wp-plugin-development/references/boot-flow.md` and
> `CONSTITUTION.md §V Integration Resilience` before editing.
>
> In `composer.json` `require`:
>
> ```diff
> -    "wpboilerplate/wpb-access-control": "^1.6.0",
> +    "wpboilerplate/wpb-access-control": "^2.0.0",
>      "berlindb/core": "^3.0.0",
> -    "acrossai-co/addons-page": "^0.0.19",
> -    "acrossai-co/main-menu": "^0.0.4"
> +    "acrossai-co/main-menu": "^0.0.7"
> ```
>
> Leave the `repositories` VCS entry for `wpb-access-control` untouched. Do
> not add `freemius/wordpress-sdk` to `require` — it arrives transitively via
> main-menu v0.0.7. Do not add an explicit `allow-plugins` entry for it
> unless `composer update` complains; if it does, add the minimal entry
> only.
>
> Run:
>
> ```bash
> composer update --with-all-dependencies \
>   wpboilerplate/wpb-access-control \
>   acrossai-co/main-menu \
>   acrossai-co/addons-page
> ```
>
> Confirm the resulting `composer.lock`:
>
> - `wpboilerplate/wpb-access-control` is `v2.0.0`.
> - `acrossai-co/main-menu` is `v0.0.7`.
> - `acrossai-co/addons-page` is completely absent from `packages`.
> - `freemius/wordpress-sdk` `^2.0` is present in `packages`.
> - The Jetpack Autoloader classmap regenerates so
>   `AcrossAI_Addon\AddonsPage` now maps to a file under
>   `vendor/acrossai-co/main-menu/src/Addons/`.
>
> Commit `composer.json` and `composer.lock` together. Do not touch any
> file under `vendor/` by hand.
>
> ---
>
> **TASK-2 — Migrate the AddonsPage constructor call**
>
> Files: `includes/Main.php`
>
> Read `CONSTITUTION.md §I Modular Architecture` and `§V Integration
> Resilience` before editing. The Add-ons bootstrap currently lives at
> `includes/Main.php:316-348`, inside the `class_exists` + `try`/`catch`
> block established in Feature 026 and rebranded in Feature 030.
>
> Drop the first positional `'acrossai'` argument. `$parent_slug` is now an
> optional third arg defaulting to `'acrossai'`, so omit it entirely:
>
> ```php
> new \AcrossAI_Addon\AddonsPage(
>     ACROSSAI_ABILITIES_MANAGER_PLUGIN_FILE,
>     array(
>         'fs_product_id' => '31230',
>         'fs_public_key' => 'pk_0f116582ac1b8e608827094024b1f',
>         'fs_slug'       => 'acrossai-abilities-manager',
>     )
> );
> ```
>
> Keep the `class_exists( \AcrossAI_Addon\AddonsPage::class )` guard, the
> `try`/`catch ( \Throwable $e )` wrapper, and the `admin_notices` fallback
> callback (lines 322–347) unchanged. The fail-open admin notice already
> gates on `current_user_can( 'manage_options' )` per DEC-FAIL-OPEN-NOTICE.
>
> Update the leading comment at lines 316–321 to reflect the new package
> origin — replace the "Feature 030 rebrand" note with one explaining that
> AddonsPage now ships from `acrossai-co/main-menu` (Feature 039) while the
> class name `AcrossAI_Addon\AddonsPage` was deliberately preserved by
> upstream to keep consumer call sites stable. Continue to cite
> `DEC-EXTERNAL-PACKAGE-HOOK-CTOR`.
>
> Do not change anything else in this block. Do not add a fallback to
> `acrossai-co/addons-page` — that package no longer exists in this plugin's
> dependency tree.
>
> ---
>
> **TASK-3 — Pass the per-consumer table slug to `AccessControlManager`**
>
> Files: `includes/Modules/Abilities/AcrossAI_Abilities_Access_Control.php`
>
> Read `CONSTITUTION.md §I Modular Architecture` and the
> "Two Plugins on One Site" section of the wpb-access-control README before
> editing.
>
> Beside the existing `PROVIDERS_FILTER` constant (line 36), add a sibling
> `TABLE_SLUG` constant. The slug must match `^[a-z0-9_]{1,32}$` (the
> library validates at construction time and throws
> `\InvalidArgumentException` on mismatch); use `'abilities'` so the
> resulting names — `{prefix}abilities_access_control`,
> `wpb_ac_abilities_db_version`, `wpb_ac_abilities` cache group, REST
> namespace `wpb-ac/v1/abilities` — read cleanly:
>
> ```php
> const PROVIDERS_FILTER = 'acrossai_abilities_access_control_providers';
> const TABLE_SLUG       = 'abilities';
> ```
>
> In `boot_manager()` (line 74), pass `TABLE_SLUG` as the second positional
> argument:
>
> ```php
> $this->manager = new AccessControlManager( self::PROVIDERS_FILTER, self::TABLE_SLUG );
> ```
>
> Do not change the singleton pattern, the `is_available()` guard, the
> lazy `get_manager()` call, the `register_rest_api()` flow, or the
> `maybe_show_library_notice` admin notice. The notice's manage_options
> gate stays as-is per DEC-FAIL-OPEN-NOTICE.
>
> Do not introduce a filter for the slug — it is a deliberate, plugin-level
> identifier, not a runtime decision. Hardcode it.
>
> ---
>
> **TASK-4 — Create the per-consumer table on activation**
>
> Files: `includes/AcrossAI_Activator.php`
>
> Read `.agents/skills/wp-plugin-development/references/boot-flow.md`,
> `§V Integration Resilience`, and `CONSTITUTION.md §VI DRY` before editing.
> The activator already uses the
> `( new XYZ_Table() )->maybe_upgrade();` shape for the two plugin-owned
> tables; mirror that exactly so the access-control table reads as one of
> three sibling lines.
>
> Update the import at line 14 — the namespace stays, the call shape
> changes:
>
> ```php
> use WPBoilerplate\AccessControl\Database\Rule\RuleTable;
> use AcrossAI_Abilities_Manager\Includes\Modules\Abilities\AcrossAI_Abilities_Access_Control;
> ```
>
> Replace the line-43 call with one that passes the same TABLE_SLUG constant
> introduced in TASK-3 (so the slug lives in one place):
>
> ```php
> ( new RuleTable( AcrossAI_Abilities_Access_Control::TABLE_SLUG ) )->maybe_upgrade();
> ```
>
> Update the docblock at lines 34–35 to read
> `{prefix}abilities_access_control` instead of `{prefix}wpb_access_control`.
> The "creates or upgrades" wording stays — `maybe_upgrade()` still handles
> both first-install and subsequent schema bumps via BerlinDB.
>
> Do not call `dbDelta()` directly. Do not write a separate installer
> method. Do not add any code that drops or renames the legacy
> `{prefix}wpb_access_control` table — per the constraint "do not care
> about backward compatibility", the legacy table is left in place on
> existing installs and the new table is created alongside it.
>
> ---
>
> **TASK-5 — Update uninstall cleanup to target the new table + option**
>
> Files: `uninstall.php`
>
> Read `CONSTITUTION.md §IV Security First` and `§V Integration Resilience`
> before editing. The uninstall path runs only when the user has opted in
> via `acrossai_abilities_uninstall_delete_data`; that gate stays unchanged.
>
> Inside the existing `if ( $acrossai_delete_data ) { ... }` block (lines
> 22–46), rename the access-control table variable and update the option
> key:
>
> ```diff
> -    $acrossai_access_control_table = $wpdb->prefix . 'wpb_access_control';
> +    $acrossai_access_control_table = $wpdb->prefix . 'abilities_access_control';
>      // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
>      $wpdb->query(
>          $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $acrossai_access_control_table )
>      );
> -    \delete_option( 'wpb_access_control_db_version' );
> +    \delete_option( 'wpb_ac_abilities_db_version' );
> ```
>
> The new option key `wpb_ac_abilities_db_version` matches the format
> documented in the wpb-access-control README
> (`wpb_ac_{slug}_db_version`). Do not hardcode the table or option name in
> two places — if a future refactor introduces a helper to derive them,
> reuse it here; for now, the literal strings are acceptable because
> `uninstall.php` runs before the plugin code is loaded and cannot reference
> `AcrossAI_Abilities_Access_Control::TABLE_SLUG`.
>
> Keep the surrounding `$wpdb->prefix . 'acrossai_abilities'` drop, the
> three `delete_option()` calls for plugin settings, and the
> `delete_site_option( 'acrossai_library_config' )` call exactly as they
> are. Do not add cleanup for the legacy `wpb_access_control` table or its
> old option — the constraint "do not care about backward compatibility"
> means existing installs simply leave that orphaned data in place.
>
> ---
>
> **CONSTRAINTS**
>
> - Do not migrate data from `{prefix}wpb_access_control` to
>   `{prefix}abilities_access_control`. The legacy table is left alone on
>   existing installs.
> - Do not rename, alias, or re-export `\AcrossAI_Addon\AddonsPage`. The
>   class name is preserved upstream specifically to keep consumer call sites
>   stable; consumers reference it directly.
> - Do not touch any file under `vendor/`. The Jetpack Autoloader handles
>   the move of `AddonsPage` from `acrossai-co/addons-page` to
>   `acrossai-co/main-menu/src/Addons/` automatically when the lockfile
>   updates.
> - Do not introduce a runtime filter for the access-control table slug. It
>   is a plugin-level identity, not a configuration value.
> - Do not call `dbDelta()` directly in the activator. The library owns the
>   schema; the activator only triggers BerlinDB's idempotent
>   `maybe_upgrade()` to create the table eagerly at activation time.
> - Do not add code that drops the legacy `wpb_access_control` table on
>   update — leave it orphaned.
> - Do not change the React asset enqueue paths in `admin/Main.php` (still
>   `vendor/wpboilerplate/wpb-access-control/assets/build/`); the bundle
>   location is preserved across the v1 → v2 upgrade.
> - Do not change the `js` localization config `wpbAcConfig` `namespace`
>   value — the per-consumer slug introduced by v2 is server-side only;
>   the React component's `namespace` prop remains the plugin's existing
>   access-control namespace string.
> - Every task must leave PHPStan level 8 and PHPCS passing individually —
>   do not batch tasks and check only at the end.
> - Manually verify the WP admin after each TASK — confirm the Add-ons
>   submenu still renders, the access-control React UI still loads on the
>   per-ability panel, and rules read/write succeed against the new REST
>   namespace.

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

### TASK-1 — Composer.json and lockfile
- [ ] `composer.json` `require` lists `"wpboilerplate/wpb-access-control": "^2.0.0"`.
- [ ] `composer.json` `require` lists `"acrossai-co/main-menu": "^0.0.7"`.
- [ ] `composer.json` `require` no longer references `acrossai-co/addons-page`.
- [ ] `composer.lock` resolves `wpboilerplate/wpb-access-control` to `v2.0.0`.
- [ ] `composer.lock` resolves `acrossai-co/main-menu` to `v0.0.7`.
- [ ] `composer.lock` no longer contains `acrossai-co/addons-page`.
- [ ] `composer.lock` contains `freemius/wordpress-sdk ^2.0` as a transitive dep of main-menu.
- [ ] `vendor/acrossai-co/addons-page/` directory is gone after `composer install` on a fresh clone.
- [ ] `vendor/composer/jetpack_autoload_classmap.php` maps `AcrossAI_Addon\AddonsPage` to a path under `vendor/acrossai-co/main-menu/src/Addons/`.

### TASK-2 — AddonsPage constructor migration
- [ ] `includes/Main.php` `new \AcrossAI_Addon\AddonsPage(...)` call passes exactly two arguments: the plugin file constant and the Freemius args array.
- [ ] The `class_exists`, `try`/`catch`, and `admin_notices` fallback block are intact.
- [ ] Comment header at lines 316–321 cites Feature 039 and explains the move from `acrossai-co/addons-page` to `acrossai-co/main-menu`.
- [ ] WP admin → AcrossAI → Add-ons submenu renders, Freemius bootstraps without errors, and the admin notice for the missing AccessControl library does NOT appear (it's a separate code path).
- [ ] With the vendor directory removed, the plugin still activates with only the existing degraded-mode admin notice — no fatal from a missing AddonsPage class because the `class_exists` guard is unchanged.

### TASK-3 — Per-consumer manager slug
- [ ] `AcrossAI_Abilities_Access_Control` declares `const TABLE_SLUG = 'abilities';`.
- [ ] `boot_manager()` instantiates `new AccessControlManager( self::PROVIDERS_FILTER, self::TABLE_SLUG )`.
- [ ] `wp option get wpb_ac_abilities_db_version` (after first admin request) returns the schema version string (e.g. `202605120001`).
- [ ] `curl https://example.test/wp-json/wpb-ac/v1/abilities/providers` (with `X-WP-Nonce`) returns the provider list, including `wp_role`, `wp_user`, `wp_capability`.
- [ ] No request hits the legacy `/wpb-ac/v1/...` (non-slug) path — the v2 manager binds to the slugged namespace only.
- [ ] The React access-control UI on per-ability panels still loads and reads/writes rules successfully against `/wpb-ac/v1/abilities/rules/...`.

### TASK-4 — Activation creates the new table
- [ ] `includes/AcrossAI_Activator.php` import block includes `AcrossAI_Abilities_Access_Control`.
- [ ] `activate()` calls `( new RuleTable( AcrossAI_Abilities_Access_Control::TABLE_SLUG ) )->maybe_upgrade();` as the third of three table-create lines.
- [ ] After deactivate + delete + activate on a clean WP install, `wp db query "SHOW TABLES LIKE '%abilities_access_control%'"` returns one row (`{prefix}abilities_access_control`).
- [ ] On an install that previously held `{prefix}wpb_access_control`, both tables coexist after reactivation — the legacy table is not touched.
- [ ] PHPStan level 8 — zero errors introduced (the new `use` and call shape pass).

### TASK-5 — Uninstall cleanup targets the new table + option
- [ ] `uninstall.php` builds `$acrossai_access_control_table = $wpdb->prefix . 'abilities_access_control';`.
- [ ] `uninstall.php` calls `\delete_option( 'wpb_ac_abilities_db_version' );`.
- [ ] With `acrossai_abilities_uninstall_delete_data = 1`, uninstalling the plugin drops `{prefix}abilities_access_control` and removes the `wpb_ac_abilities_db_version` option.
- [ ] The legacy `{prefix}wpb_access_control` table and `wpb_access_control_db_version` option are NOT touched by uninstall (intentional — left orphaned per the no-backward-compat constraint).

### Quality gates
- [ ] PHPStan level 8 — zero errors.
- [ ] PHPCS — zero errors.
- [ ] `composer test` (PHPUnit) — green, including `tests/phpunit/sitewide/AccessControlBootstrapTest.php`. Update that test if it asserts the single-arg manager constructor.
- [ ] `npm run lint:js` — zero errors (no JS changes expected, but confirm).
- [ ] `npm run validate-packages` — passes; no stale references to `acrossai-co/addons-page` in the bundled output.
- [ ] Existing access-control rules written on a v1 install do NOT carry over (expected) — a fresh install of the v2 table starts empty.
