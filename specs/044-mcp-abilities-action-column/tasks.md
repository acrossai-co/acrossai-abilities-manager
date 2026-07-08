---
description: "Task list for Feature 044 — MCP Manager Abilities Tab Action column with Edit deep-link + MCP Exposure warning"
---

# Tasks: MCP Manager Abilities Tab — "Action" Column with Edit Deep-Link + MCP Exposure Warning

**Input**: Design documents from `/specs/044-mcp-abilities-action-column/`
**Prerequisites**: [spec.md](./spec.md), [plan.md](./plan.md), [memory-synthesis.md](./memory-synthesis.md)

**Tests**: No new PHPUnit tests. Test count unchanged. Manual walkthrough (T012) is the accepted verification path.

**Organization**: Tasks grouped by phase; each task has an exact target file + verification step.

## Format: `[ID] [P?] Description`

- **[P]**: Can run in parallel (different files, no dependencies).

## Phase 1: Setup

- [x] **T001** Confirm branch `044-mcp-abilities-action-column` created from `main` post-0.0.5 (Feature 043 already landed via PR #62). Working tree clean apart from unrelated `.claude/settings.local.json` + `.phpunit.cache/test-results` local dev state.

## Phase 2: Foundational (Blocking Prerequisites — read-only audits)

- [ ] **T002** Grep audit — confirm the MCP Manager plugin exposes the filter and enqueue handle documented in `acrossai-mcp-manager/docs/abilities-tab-js-filters.md`. Any drift breaks this feature.
  ```
  grep -n "acrossaiMcpManager.abilities.fields\|acrossai-mcp-manager-abilities" \
    ../acrossai-mcp-manager/src/js/abilities.js \
    ../acrossai-mcp-manager/admin/Main.php
  ```
  Expected: filter apply at `src/js/abilities.js:584-588`; enqueue handle at `admin/Main.php:229-236`. **Verified during Phase 1 exploration.**

- [ ] **T003** Grep audit — confirm the MCP Manager's abilities bundle depends on `wp-hooks` so our extension will have `wp.hooks` at run time:
  ```
  cat ../acrossai-mcp-manager/build/js/abilities.asset.php
  ```
  Expected: `dependencies` array contains `wp-hooks`. **Verified during Phase 1 exploration.**

- [ ] **T004** Grep audit — confirm this plugin's existing manifest-load + guarded-enqueue pattern in `admin/Main.php` is the pattern to mirror:
  ```
  grep -n "abilities_asset_file\|ability_library_asset_file\|is_manager_page\|is_library_page" admin/Main.php
  ```
  Expected: constructor loader at `admin/Main.php:108-125`, enqueue block at `admin/Main.php:192-200`, private guards named `is_*_page( $hook_suffix )`. Our new guard will be named `is_mcp_manager_abilities_tab()` (no `$hook_suffix` arg — it checks `$_GET` directly). **Verified during Phase 1 exploration.**

**Checkpoint**: Scope confirmed as 1 new JS entry + 1 webpack edit + 1 `admin/Main.php` edit + 1 rebuilt bundle + 4 spec-kit files. No changes to `acrossai-mcp-manager/`.

## Phase 3: New JS extension bundle

- [x] **T005** Create directory + file `src/js/mcp-abilities-extension/index.js`. Contents:
  - File header comment naming the filter consumed (`acrossaiMcpManager.abilities.fields`), the target plugin (`acrossai-mcp-manager`), and the docs reference (`acrossai-mcp-manager/docs/abilities-tab-js-filters.md`).
  - Imports:
    - `addFilter` from `@wordpress/hooks`
    - `createElement` from `@wordpress/element`
    - `Button` from `@wordpress/components`
    - `__` from `@wordpress/i18n`
    - `addQueryArgs` from `@wordpress/url`
  - Read `window.acrossaiAbilitiesManagerMcpExtension?.editBaseUrl` with fallback `'admin.php?page=acrossai-abilities-manager'`.
  - Call `addFilter( 'acrossaiMcpManager.abilities.fields', 'acrossai-abilities-manager/action-edit', ( fields ) => [ ...fields, actionField ] )` where `actionField` is:
    ```js
    {
      id: 'aam_action',
      label: __( 'Action', 'acrossai-abilities-manager' ),
      enableSorting: false,
      enableHiding: false,
      render: ( { item } ) =>
        createElement(
          Button,
          {
            variant: 'secondary',
            size: 'small',
            href: addQueryArgs( baseEditUrl, { action: 'edit', slug: item.slug } ),
            target: '_blank',
            rel: 'noopener noreferrer',
          },
          __( 'Edit', 'acrossai-abilities-manager' )
        ),
    }
    ```
  - **`target: '_blank'` + `rel: 'noopener noreferrer'`**: Edit always opens in a new tab so the MCP-tab audit context is preserved. `rel` isolates the new tab from the source page (WCAG-safe).

## Phase 4: Webpack entry

- [x] **T006** Edit `webpack.config.js`. Add one entry alongside the existing `js/abilities` / `js/ability-library` (see `webpack.config.js:79-98`):
  ```js
  'js/mcp-abilities-extension': path.resolve(
      process.cwd(),
      'src/js/mcp-abilities-extension',
      'index.js'
  ),
  ```
  No CSS entry (button styling comes from `wp-components`).

## Phase 5: PHP enqueue

- [x] **T007** Edit `admin/Main.php` — add a private property alongside `$abilities_asset_file` / `$library_asset_file`:
  ```php
  /**
   * MCP-Extension asset manifest (dependencies + version).
   *
   * @var array{dependencies: string[], version: string}|null
   */
  private $mcp_extension_asset_file = null;
  ```

- [x] **T008** Edit `admin/Main.php` constructor — load the manifest defensively next to the existing `abilities_asset_file` / `ability_library_asset_file` loads (see pattern at `admin/Main.php:108-125`):
  ```php
  $mcp_ext_asset_path = \ACROSSAI_ABILITIES_MANAGER_PLUGIN_PATH . 'build/js/mcp-abilities-extension.asset.php';
  if ( file_exists( $mcp_ext_asset_path ) ) {
      $this->mcp_extension_asset_file = include $mcp_ext_asset_path;
  } elseif ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
      error_log( 'acrossai-abilities-manager: build/js/mcp-abilities-extension.asset.php not found — run npm run build.' );
  }
  ```

- [x] **T009** Edit `admin/Main.php` — add a new private guard method `is_mcp_manager_abilities_tab(): bool`. Checks (in order, short-circuit on any miss):
  - `isset( $_GET['page'] )` and `sanitize_key( wp_unslash( $_GET['page'] ) ) === 'acrossai_mcp_manager'`
  - `isset( $_GET['action'] )` and `sanitize_key( wp_unslash( $_GET['action'] ) ) === 'edit'`
  - `isset( $_GET['tab'] )` and `sanitize_key( wp_unslash( $_GET['tab'] ) ) === 'abilities'`

  Prefix with `phpcs:ignore WordPress.Security.NonceVerification.Recommended` (matches MCP Manager's guard at `../acrossai-mcp-manager/admin/Main.php:216-222`).

- [x] **T010** Edit `admin/Main.php` `enqueue_scripts()` — add a register + inline + enqueue block gated by `$this->mcp_extension_asset_file && $this->is_mcp_manager_abilities_tab()`:
  ```php
  if ( $this->mcp_extension_asset_file && $this->is_mcp_manager_abilities_tab() ) {
      $deps = array_merge(
          $this->mcp_extension_asset_file['dependencies'],
          array( 'acrossai-mcp-manager-abilities' )
      );
      wp_register_script(
          'acrossai-abilities-manager-mcp-extension',
          \ACROSSAI_ABILITIES_MANAGER_PLUGIN_URL . 'build/js/mcp-abilities-extension.js',
          $deps,
          $this->mcp_extension_asset_file['version'],
          true
      );
      wp_add_inline_script(
          'acrossai-abilities-manager-mcp-extension',
          'window.acrossaiAbilitiesManagerMcpExtension = ' . wp_json_encode(
              array(
                  'editBaseUrl' => admin_url( 'admin.php?page=acrossai-abilities-manager' ),
              )
          ) . ';',
          'before'
      );
      wp_enqueue_script( 'acrossai-abilities-manager-mcp-extension' );
  }
  ```

## Phase 5b: MCP Exposure warning callout

- [x] **T010a** Edit `src/scss/abilities/admin.scss` — append `.sect-note-warn` immediately after the existing `.sect-desc` block. Use the `$red = #d63638` token already declared at the top of the file:
  ```scss
  .sect-note-warn {
      margin:        10px 0 0 32px;
      padding:       8px 12px;
      background:    #fceaea;
      border-left:   3px solid $red;
      border-radius: 2px;
      color:         $red;
      font-size:     12px;
      line-height:   1.5;

      strong { font-weight: 600; }
  }
  ```
  The 32px left margin aligns the callout under the existing 32px-indented `.sect-desc` so the section header visually contains it.

- [x] **T010b** Edit `src/js/abilities/components/AbilityForm.jsx` — inside Section 3 (MCP Exposure), insert a `<div className="sect-note-warn" role="note">` block between the closing `</div>` of `sect-desc` and the closing `</div>` of `sect-hdr`. Content:
  ```jsx
  <div className="sect-note-warn" role="note">
      <strong>{__('Heads up:', 'acrossai-abilities-manager')}</strong>{' '}
      {__(
          'Enabling MCP here applies this ability to all MCP servers on this site.',
          'acrossai-abilities-manager'
      )}
  </div>
  ```
  Unconditional (no `isNonDb` guard) — the site-wide side-effect applies regardless of ability source.

## Phase 6: Build + verify

- [x] **T011** [P] Run `npm run build`. Confirm zero errors and that `build/js/mcp-abilities-extension.js` + `build/js/mcp-abilities-extension.asset.php` exist:
  ```
  ls -la build/js/mcp-abilities-extension.* && cat build/js/mcp-abilities-extension.asset.php
  ```
  Expected: `dependencies` array contains at minimum `wp-hooks`, `wp-components`, `wp-element`, `wp-i18n`, `wp-url`.

- [ ] **T012** Manual walkthrough on the local WordPress 7.0 install with both plugins active and at least one MCP server registered exposing at least one ability:
  1. Visit `http://wordpress-7-0.local/wp-admin/admin.php?page=acrossai_mcp_manager&action=edit&server=<id>&tab=abilities`.
  2. Expect a right-most **Action** column with one **Edit** button per row.
  3. Click **Edit** on the row for `acrossai-core-abilities/block-pattern-list`. Expect a **new browser tab** to open at `admin.php?page=acrossai-abilities-manager&action=edit&slug=acrossai-core-abilities%2Fblock-pattern-list`; the Abilities Manager edit form loads that ability directly (no list flash — Feature 043 code path). The source MCP-Abilities tab remains loaded and focused-preserving in the background.
  4. Right-click / cmd-click the same Edit button → "Open in new tab". Also works (belt-and-braces; the default click already opens a new tab via `target="_blank"`).
  5. On the newly-opened edit form, verify Section 3 (MCP Exposure) shows a red-tinted callout `Heads up: Enabling MCP here applies this ability to all MCP servers on this site.` positioned between the section description and the "Show in MCP" tri-chip.
  6. Navigate to a custom (db-source) ability's edit form. Verify the same red callout renders (unconditional — not gated on source).
  7. Deactivate `acrossai-mcp-manager`. Reload any wp-admin page. Expect no console errors; verify `wp_scripts()->registered['acrossai-abilities-manager-mcp-extension']` is not enqueued anywhere.
  8. Reactivate `acrossai-mcp-manager`. Deactivate `acrossai-abilities-manager`. Reload the MCP Manager Abilities tab. Expect the built-in columns to render without an Action column and without console errors.
  9. Reactivate both. Return to the MCP Manager Abilities tab. Expect the Action column to reappear.

- [ ] **T013** Commit changes on `044-mcp-abilities-action-column` (spec-kit + code + build). Push to origin.

- [ ] **T014** Open PR against `main` titled "Feature 044 — MCP Manager Abilities Tab Action column + MCP Exposure warning" with a body referencing this spec directory.

**Final Checkpoint**: T001–T004 grep-audit checkpoints, T005–T010b code + config changes, T011 build success, T012 manual walkthrough, T013–T014 commit + PR.
