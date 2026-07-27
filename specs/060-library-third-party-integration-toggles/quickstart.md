# Quickstart: Feature 060 — Library Third-Party Integration Toggles

Manual verification of the delivered surface. Run these steps against a real
WordPress install (the `wordpress-7-0` Local by Flywheel site) after the branch
is checked out and `npm run build` has been executed.

Each step lists an **Action**, an **Expected result**, and a **Pass?** column
to check off. All six steps MUST pass to consider the feature verified.

---

## Prerequisites

- WordPress 6.9+ + PHP 8.1+.
- Plugin `acrossai-abilities-manager` on branch `060-library-third-party-integration-toggles`, activated.
- Plugin `advanced-custom-fields` (ACF 6.x) present in `wp-content/plugins/` (may or may not be activated per step).
- Admin user with `manage_options` capability.
- WP_DEBUG_LOG enabled (`wp-config.php`) so PHP notices, if any, land in the debug log.

---

## Step 1 — ACF not installed / deactivated

**Action**
Deactivate ACF (`wp plugin deactivate advanced-custom-fields`, or via Plugins screen).
Visit `/wp-admin/admin.php?page=acrossai-abilities-library`.

**Expected result**
- No "Acf" tab appears in the tab bar.
- No ACF card anywhere on the page.
- `wp-content/debug.log` contains no new PHP notices, warnings, or fatals.

**Verifies** FR-004, FR-013, SC-060-03, User Story 3 acceptance scenario 1.

Pass? [ ]

---

## Step 2 — ACF activated, integration OFF by default (fresh state)

**Action**
Activate ACF (`wp plugin activate advanced-custom-fields`).
Reload the Ability Library page.

**Expected result**
- New "Acf" tab appears in the tab bar (sorted alphabetically among other tabs).
- Card is present with header label **"Advanced Custom Fields (AI)"**.
- Toggle is **OFF**.
- Readonly ability list below the toggle shows three entries: **Field Groups**,
  **Post Types**, **Taxonomies**, each with a short description.
- **No** All/Specific radio button visible.
- **No** per-ability checkboxes visible.

**Verifies** FR-004, FR-005, FR-008, User Story 1 acceptance scenario 1, User Story 4.

Pass? [ ]

---

## Step 3 — Enable the integration; verify auto-save + ACF abilities register

**Action**
Flip the toggle ON.

**Expected result**
- No visible error notice.
- Change auto-saves (no "Save" button click required).
- Reload the page — toggle stays ON.
- Query the ability list via one of:
  - MCP: call `mcp__claude_ai_acrossai__mcp-adapter-discover-abilities`.
  - WP-CLI: `wp abilities list` (if available).
  - The "All" tab of the Library page.
  - New ACF categories/tabs appear (Field Groups, Post Types, Taxonomies as their own abilities).

**Verifies** FR-006, FR-007, FR-009, FR-010, SC-001, SC-004, User Story 1 acceptance scenarios 2 + 3.

**Storage inspection (optional but recommended)**:
Use `mcp__agents__mcp-local-wp__mysql_query` or `wp option get acrossai_library_config --format=json` to inspect the raw option. The result MUST contain an `"acf"` entry with `"enabled": true`. If it does not, the sparse-storage bugfix regression has re-appeared — see the 2026-07-27 fix in `AcrossAI_Ability_Library_Config::save_config()`.

Pass? [ ]

---

## Step 4 — Disable the integration; verify ACF abilities disappear

**Action**
Flip the toggle OFF.
Reload the page.

**Expected result**
- Toggle stays OFF.
- Next call to the ability list (MCP `discover-abilities`, WP-CLI, or the "All" tab) shows **no** ACF abilities.

**Verifies** FR-011, User Story 1 acceptance scenario 4.

Pass? [ ]

---

## Step 5 — Deactivate ACF while the toggle is on; page must stay clean

**Action**
Toggle the integration ON.
Then deactivate ACF (`wp plugin deactivate advanced-custom-fields`).
Reload the Library page.

**Expected result**
- The "Acf" tab and card **disappear** entirely (no orphan UI).
- `wp-content/debug.log` contains **no** notices about undefined ACF classes.
- `wp option get acrossai_library_config` still contains `"acf": { "enabled": true, ... }` — saved config is preserved across the deactivation window (FR-012).

Then re-activate ACF and reload — the "Acf" tab returns with the toggle still ON.

**Verifies** FR-012, FR-013, User Story 3 acceptance scenarios 1 + 2.

Pass? [ ]

---

## Step 6 — Stricter capability filter blocks the toggle via REST (SC-060-02)

**Action**
Drop this into a mu-plugin (`wp-content/mu-plugins/060-test-capability.php`):

```php
<?php
add_filter( 'acrossai_integration_toggle_capability', function () {
    return 'manage_network_options';
}, 10, 2 );
```

As a user holding only `manage_options` (single-site admin, NOT super-admin), attempt to flip the ACF toggle from the Library page.

**Expected result**
- The REST POST returns HTTP 403.
- The card visually reverts to its saved state (does not persist the new value).
- The `acrossai_integration_toggle_denied` action fires — if you register a temporary hook that writes to the debug log, you'll see one log line per denial.

Delete `060-test-capability.php` after this step.

**Verifies** FR-016, SC-060-02, Security-review AZ-01.

Pass? [ ]

---

## Step 7 — Extension pattern: third-party plugin adds custom cards to the ACF tab (User Story 5)

**Verifies** FR-017, FR-018, User Story 5 (P2). This step confirms an add-on plugin can plug
its own regular ability cards into an integration's tab without any changes to this plugin.

**Action**
Copy the worked demo mu-plugin to your install (if not already present):

```bash
cp /Users/raftaar1191/local-sites/wordpress-7-0/app/public/wp-content/mu-plugins/060-acf-tab-extension-demo.php \
   /Users/raftaar1191/local-sites/wordpress-7-0/app/public/wp-content/mu-plugins/060-acf-tab-extension-demo.php
```

(It should already exist from the extension-pattern documentation work — this file is
temporary and is deleted at the end of this step.)

Reload `/wp-admin/admin.php?page=acrossai-abilities-library&tab=acf`.

**Expected result**
- The tab shows **THREE cards**:
  1. **Advanced Custom Fields (AI)** — the integration toggle card (read-only ability list, no
     All/Specific radio) — unchanged.
  2. **Demo Acf Helpers** — a **regular** library card (toggle + expand chevron + All/Specific
     radio), containing `Ping ACF Helper` and `Count ACF Field Groups`. Default toggle: **ON**.
  3. **Demo Acf Reports** — a **regular** library card, containing `ACF Summary Report`.
     Default toggle: **ON**.
- Load the Custom Abilities admin page (`/wp-admin/admin.php?page=acrossai-abilities-manager`)
  and confirm the count goes from **9 → 12 items** — the three demo abilities appear with
  `Source: Plugin`, `Category: demo-acf-helpers` (or `demo-acf-reports`), `Status: Default`.
- Query MCP: `mcp__claude_ai_acrossai__mcp-adapter-discover-abilities` — the three
  `demo/*` abilities should be present.

**What proves the extension pattern works**
- The demo abilities live in a completely separate PHP file (the mu-plugin) with no changes to
  the acrossai-abilities-manager plugin.
- They use the published `ACF::TAB_GROUP` constant to route onto the ACF tab.
- Their cards render as REGULAR library cards (with real toggles), NOT as integration cards.
- Toggling "Demo Acf Helpers" off and reloading removes both `demo/acf-helpers-*` abilities
  from the Custom Abilities table (the Library Processor's per-category gate).

**Common failure mode (WP-core rule)**
If you author a NEW add-on plugin and its abilities don't appear in the Custom Abilities table
even though the card renders on the Library page: check that you registered your ability
category on `wp_abilities_api_categories_init` via `wp_register_ability_category()`. WP core
silently rejects abilities whose category isn't pre-registered — the Library UI card renders
regardless (it reads from our Registry, not from `wp_get_abilities()`), but the abilities
themselves never register. See the base-class docblock for the full 3-step contract.

**Clean up**
Delete the demo mu-plugin when done:

```bash
rm /Users/raftaar1191/local-sites/wordpress-7-0/app/public/wp-content/mu-plugins/060-acf-tab-extension-demo.php
```

Pass? [ ]

---

## After all seven steps pass

- Delete any temporary test users, mu-plugins, or debug hooks introduced above.
- Re-run `./vendor/bin/phpunit tests/phpunit/Modules/Library/Integrations/Test_Integration_Ability_Base.php` and `npx wp-scripts test-unit-js tests/jest/ability-library/LibraryCard.integration-variant.test.js` and confirm both are still green.
- Run `composer run phpstan` and confirm no errors introduced on the changed files.
- The feature is ready for `/speckit-git-commit` → PR → review.
