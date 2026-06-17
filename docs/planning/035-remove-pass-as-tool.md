# Planning: Remove "Pass as Tool" Code Completely (Feature 035)

Remove the `pass_as_tool` per-ability MCP tool-injection feature introduced by Feature 029
(`DEC-MCP-TOOLS-PASSTHROUGH-COLUMN`). This includes the DB column, the BerlinDB Schema/Row/Query
plumbing, the `inject_mcp_tools()` runtime hook, the REST formatter/sanitizer/merger entries, the
React UI (column toggle in `AbilitiesList.jsx`, Section 4 in `AbilityForm.jsx`), the tests, and the
related memory entries.

Feature 034 already deleted the per-server `mcp_servers` allowlist that previously gated this
injection. Feature 035 removes the remaining `pass_as_tool` surface in the same fail-closed
direction: abilities will no longer be auto-registered into MCP servers' tool registries by this
plugin. Any equivalent capability becomes the responsibility of the future `acrossai-mcp-manager`
plugin (consistent with Feature 034's spec.md "Security posture change").

This is a pre-launch removal — no production sites are affected and no user-data migration is
required. The DB column is dropped, not deprecated.

---

## Spec-kit Workflow

```markdown
# 1. Branch
/speckit.git.feature "035-remove-pass-as-tool"

# 2. Specify
/speckit.specify "Remove the 'Pass as Tool' feature (Feature 029) completely from the plugin.

Background: Feature 029 added a tri-state tinyint column 'pass_as_tool' to the acrossai_abilities table and a runtime hook (AcrossAI_Ability_Override_Processor::inject_mcp_tools, registered at mcp_adapter_init P20) that injects opted-in ability slugs into every connected MCP server's private component_registry via Reflection. Feature 034 already deleted the per-server 'mcp_servers' allowlist that previously gated this injection. Feature 035 removes the remaining pass_as_tool surface.

Scope (production code — must remove):
(1) includes/Modules/Abilities/Database/AcrossAI_Abilities_Schema.php — delete the 'pass_as_tool' column array (lines 168–174, between show_in_mcp and mcp_type).
(2) includes/Modules/Abilities/Database/AcrossAI_Abilities_Row.php — delete the @property docblock entry (line 43), the public \$pass_as_tool = null; property (line 165 + its docblock), the 'pass_as_tool' entry in \$blocked_scalar_columns (line 248), and 'pass_as_tool' from the \$tri_state_fields array in the constructor (line 285).
(3) includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php — delete the entire public get_pass_as_tool_slugs() method (lines 493–502, including its docblock from ~line 480), and remove 'pass_as_tool' from the \$tri_state array in prepare_fields_for_write() (line 642).
(4) includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php — delete the add_action('mcp_adapter_init', …, 'inject_mcp_tools', 20) registration inside boot() (line 175 + its preceding comment block at lines 170–174), the entire inject_mcp_tools() method including its docblock and Reflection body (lines 410–523 — the '// MCP tools pass-through' section header plus the method), and the now-unused private user_has_ability_access() helper at lines 525–546 IF nothing else calls it (verify with a grep before deletion — if other callers exist, keep it). KEEP every other method in this file (inject_override_args, unregister_blocked_abilities, load_overrides_cache, bust_cache, is_manager_rest_request).
(5) includes/Utilities/AcrossAI_Ability_Merger.php — remove 'pass_as_tool' from \$overridable_fields (line 39).
(6) includes/Utilities/AcrossAI_Abilities_Sanitizer.php — remove 'pass_as_tool' from the \$tri_state_fields array (line 285).
(7) includes/Utilities/AcrossAI_Abilities_Formatter.php — remove the 'pass_as_tool' key from format_for_response() (line 56), format_for_exposure() (line 97), and format_merged_ability() (line 146).
(8) includes/Main.php — delete the 'mcp_adapter_init P20 (pass_as_tool injection)' comment block (lines 374–378) inside register_dependencies().
(9) src/js/abilities/components/AbilityForm.jsx — delete Section 4 (lines 1264–1336 — the '── Section 4 — Pass as Tool ──' div) and remove 'pass_as_tool: data.pass_as_tool,' from the non-db edit payload (line 524). Renumber the surviving sections (Section 5 Annotations → Section 4, etc.) for visual continuity.
(10) src/js/abilities/components/AbilitiesList.jsx — delete the entire PassAsToolCell component (lines 128–155), the 'pass_as_tool: true' entry in COLUMN_DEFAULTS (line 192), the column header (lines 733–740), and the column body cell (lines 842–860).
(11) src/js/abilities/store/index.js — remove 'pass_as_tool' from OVERRIDABLE_FIELDS (line 46).

Tests (delete entire files — these are pass_as_tool-only suites):
- tests/phpunit/Utilities/AcrossAI_Abilities_Sanitizer_PassAsTool_Test.php
- tests/phpunit/Utilities/AcrossAI_Abilities_Formatter_PassAsTool_Test.php
- tests/phpunit/Modules/Abilities/Database/AcrossAI_Abilities_Row_PassAsTool_Test.php
- tests/phpunit/Modules/Abilities/Database/AcrossAI_Abilities_Schema_PassAsTool_Test.php
- tests/phpunit/Modules/Abilities/Database/AcrossAI_Abilities_Query_PassAsTool_Test.php
- tests/phpunit/Modules/McpToolsPassthrough/AcrossAI_Mcp_Tools_Passthrough_Test.php
- Delete the empty tests/phpunit/Modules/McpToolsPassthrough/ directory after the file above is removed.

Tests (mixed-concern — edit, do not delete):
- Any other PHPUnit test that constructs AcrossAI_Abilities_Row, calls the Sanitizer, or asserts on the REST response shape MUST drop 'pass_as_tool' from its fixtures and assertions. Find with: grep -rn 'pass_as_tool\\\\|PassAsTool\\\\|get_pass_as_tool_slugs\\\\|inject_mcp_tools\\\\|PassAsToolCell' tests/.

Database migration:
- The plugin is pre-launch. Bump AcrossAI_Abilities_Table::\$version to '1.1.0' so BerlinDB's maybe_upgrade() fires on activation, and add an upgrade routine that drops the 'pass_as_tool' column with \$wpdb->prepare('ALTER TABLE %i DROP COLUMN pass_as_tool', \$table) wrapped in a column-exists check (SHOW COLUMNS LIKE 'pass_as_tool'). The alternative — instructing developers to drop and reactivate, as Feature 034 did for mcp_servers — is acceptable if the implementer prefers it; document the choice in DECISIONS.md. The fresh-install path automatically excludes the column once Schema.php no longer defines it.

Memory and governance:
- docs/memory/DECISIONS.md — append a superseding entry that retires DEC-MCP-TOOLS-PASSTHROUGH-COLUMN. State that per-ability MCP tool registry injection is no longer a responsibility of this plugin; deferred to acrossai-mcp-manager. Mark BUG-MCP-TYPE-PASSTOOL-CONFLICT, BUG-INJECT-MCP-TOOLS-PERMISSION-BYPASS, and BUG-BERLINDDB-QUERY-PRIVATE-CTOR's pass_as_tool context as 'Resolved (feature removed)' or move them to a historical section — do not silently delete them, since the underlying lessons (private constructor singletons, AC-rule fail-open with check_permissions fallback) remain durable.
- docs/memory/INDEX.md — update the entries for DEC-MCP-TOOLS-PASSTHROUGH-COLUMN, BUG-MCP-TYPE-PASSTOOL-CONFLICT, BUG-INJECT-MCP-TOOLS-PERMISSION-BYPASS to reflect Resolved/Historical status; update the WORKLOG row that references PassAsToolCell.
- docs/memory/WORKLOG.md — add a Feature 035 entry summarizing the removal and the supersedes link.
- .specify/memory/CONSTITUTION.md — review §II for any pass_as_tool / MCP-injection mention; if present, remove and bump the constitution version PATCH (e.g. 1.4.4 → 1.4.5) with the standard sync-impact report.

Verification:
- An exhaustive grep for 'pass_as_tool\\\\|pass-as-tool\\\\|PassAsTool\\\\|get_pass_as_tool_slugs\\\\|inject_mcp_tools\\\\|PassAsToolCell\\\\|McpToolsPassthrough\\\\|Mcp_Tools_Passthrough' across includes/, admin/, public/, src/, tests/, acrossai-abilities-manager.php, uninstall.php, composer.json, package.json, and the top-level *.md files returns ZERO hits, except for historical references inside docs/memory/* (WORKLOG/DECISIONS/INDEX), docs/planning/029-*.md, docs/planning/035-*.md, and specs/029-*, specs/032-*, specs/034-* (all historical artifacts — not edited by this feature).
- Plugin Check, PHPCS, PHPStan level 8, Jest, and PHPUnit all green.
- npm run build emits zero warnings.
- The ability edit page renders without Section 4; the abilities list renders without the 'Pass as Tool' column; no console errors; no PHP notices.
- A freshly installed plugin (drop the abilities table, reactivate) creates the table WITHOUT a pass_as_tool column.
- For an existing install, the upgrade routine drops the column on the next plugins_loaded after activation; verify SHOW COLUMNS afterward.

Do NOT touch (out of scope):
- show_in_mcp — this is the general MCP exposure flag and remains untouched.
- inject_override_args / unregister_blocked_abilities / load_overrides_cache / bust_cache / is_manager_rest_request — the rest of AcrossAI_Ability_Override_Processor.
- AcrossAI_Protected_Abilities and the protected_slugs localization (used by other UI cells in AbilitiesList.jsx).
- AcrossAI_Sanitizer::cast_tri_state (still used by remaining tri-state fields).
- Any tri-state field other than pass_as_tool (site_allowed, readonly, destructive, idempotent, show_in_rest, show_in_mcp).
- REST endpoint paths and other response shape fields.
- The Library, Logger, or Access Control modules.

This is purely a feature removal — no behavior change for any other ability flag, no schema change for any other column."
```

---

## Scope Rules

### Must remove

- Every production-code reference to `pass_as_tool` across `includes/`, `src/`, `admin/`, `public/`, `acrossai-abilities-manager.php`, and `uninstall.php`.
- Every test file dedicated to `pass_as_tool` (full deletion).
- Every `pass_as_tool` fixture / assertion inside mixed-concern tests (edit, not delete).
- The DB column on existing installs via a `maybe_upgrade()` ALTER, or via the documented "drop-and-reactivate" alternative used in Feature 034.

### Must update

- Memory / governance entries that reference the feature, so future Spec Kit runs do not regenerate it.

### Must NOT touch

- `show_in_mcp` and all other tri-state fields.
- The rest of `AcrossAI_Ability_Override_Processor` (override injection + unregister_blocked_abilities are the surviving responsibilities of the file).
- `AcrossAI_Protected_Abilities` / `protected_slugs` localization (used by other UI cells).
- REST endpoint paths and any response field other than `pass_as_tool`.
- Library / Logger / Access Control modules.
- Historical spec dirs (`specs/029-*`, `specs/032-*`, `specs/034-*`) and the historical planning doc `docs/planning/029-mcp-tools-passthrough.md` — they are archival records and stay as-is.

---

## Background — What `pass_as_tool` Was

Feature 029 (2026-06-11) shipped a tri-state tinyint column `pass_as_tool` on `acrossai_abilities`:

- `NULL` (default) — no injection.
- `1` — inject this ability's slug into **every** connected MCP server's tool registry at `mcp_adapter_init` P20.
- `0` — reserved for future per-server deny (currently equivalent to `NULL`).

The runtime path is `AcrossAI_Ability_Override_Processor::inject_mcp_tools()`, registered inside
`boot()` at `mcp_adapter_init` priority 20. It uses Reflection to reach the private
`McpServer::$component_registry` field because the installed mcp-adapter version does not expose
the `mcp_adapter_server_config` filter. The injection is gated by three checks per
(server × ability) pair: `pass_as_tool === 1`, AC-rule access for the current user, and the
ability's own `check_permissions()` callback.

Feature 034 deleted the per-server `mcp_servers` allowlist (Check 2 in the same loop) and made the
injection unconditional across all servers. Feature 035 removes the remaining `pass_as_tool`
surface entirely — the responsibility moves out of this plugin and into the future
`acrossai-mcp-manager` plugin, consistent with the architectural inversion documented in Feature
034's `spec.md` "Security posture change" section.

---

## Production Reference Inventory

| File | Line(s) | Action |
|------|---------|--------|
| `includes/Modules/Abilities/Database/AcrossAI_Abilities_Schema.php` | 168–174 | Delete the `pass_as_tool` column array (between `show_in_mcp` and `mcp_type`). |
| `includes/Modules/Abilities/Database/AcrossAI_Abilities_Row.php` | 43, 160–165, 248, 285 | Delete the `@property` line, the property declaration + its docblock, the `$blocked_scalar_columns` entry, and the `$tri_state_fields` entry. |
| `includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php` | ~480–502, 642 | Delete the full `get_pass_as_tool_slugs()` method (including its docblock); remove `'pass_as_tool'` from the `$tri_state` array inside `prepare_fields_for_write()`. |
| `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php` | 170–175 (registration), 410–523 (method + section header), 525–546 (helper, only if no other caller) | Delete the `mcp_adapter_init` `add_action` plus its comment, the `// MCP tools pass-through` section comment, the entire `inject_mcp_tools()` method, and `user_has_ability_access()` IF unused elsewhere. |
| `includes/Utilities/AcrossAI_Ability_Merger.php` | 39 | Remove `'pass_as_tool'` from `$overridable_fields`. |
| `includes/Utilities/AcrossAI_Abilities_Sanitizer.php` | 285 | Remove `'pass_as_tool'` from `$tri_state_fields`. |
| `includes/Utilities/AcrossAI_Abilities_Formatter.php` | 56, 97, 146 | Remove the three `'pass_as_tool' => …` array entries. |
| `includes/Main.php` | 374–378 | Delete the 4-line "Note: mcp_adapter_init P20 (pass_as_tool injection)" comment block. |
| `src/js/abilities/components/AbilityForm.jsx` | 524, 1264–1336 | Drop the `pass_as_tool` key from the non-db edit payload; delete the entire Section 4 JSX block; renumber surviving sections. |
| `src/js/abilities/components/AbilitiesList.jsx` | 128–155, 192, 733–740, 842–860 | Delete `PassAsToolCell`, the `COLUMN_DEFAULTS` entry, the `<th>` header, and the `<td>` body cell. |
| `src/js/abilities/store/index.js` | 46 | Remove `'pass_as_tool'` from `OVERRIDABLE_FIELDS`. |

### Test files to delete in full

```text
tests/phpunit/Utilities/AcrossAI_Abilities_Sanitizer_PassAsTool_Test.php
tests/phpunit/Utilities/AcrossAI_Abilities_Formatter_PassAsTool_Test.php
tests/phpunit/Modules/Abilities/Database/AcrossAI_Abilities_Row_PassAsTool_Test.php
tests/phpunit/Modules/Abilities/Database/AcrossAI_Abilities_Schema_PassAsTool_Test.php
tests/phpunit/Modules/Abilities/Database/AcrossAI_Abilities_Query_PassAsTool_Test.php
tests/phpunit/Modules/McpToolsPassthrough/AcrossAI_Mcp_Tools_Passthrough_Test.php
```

Remove the empty `tests/phpunit/Modules/McpToolsPassthrough/` directory afterward.

### Test files to edit (mixed-concern)

Any PHPUnit test that constructs `AcrossAI_Abilities_Row`, calls the Sanitizer/Formatter/Merger,
or asserts on the REST list/edit response shape MUST have `pass_as_tool` removed from its fixtures
and assertions. Final grep target:

```bash
grep -rn 'pass_as_tool\|PassAsTool\|get_pass_as_tool_slugs\|inject_mcp_tools\|PassAsToolCell' tests/
```

Must return zero hits after this feature lands (excluding the dedicated suites already deleted).

---

## CHANGE-1 — Drop the Schema Column

**File**: `includes/Modules/Abilities/Database/AcrossAI_Abilities_Schema.php`

Delete the column definition between `show_in_mcp` and `mcp_type`:

```php
array(
    'name'       => 'pass_as_tool',
    'type'       => 'tinyint',
    'length'     => '1',
    'allow_null' => true,
    'default'    => null,
),
```

The class docblock currently reads "all 24 columns" — update the count to `23` to match.

---

## CHANGE-2 — Drop the Row Property

**File**: `includes/Modules/Abilities/Database/AcrossAI_Abilities_Row.php`

Four edits:

1. Remove `@property bool|null   $pass_as_tool` from the class-level docblock (line 43).
2. Delete the property declaration with its docblock at lines ~160–165:
   ```php
   /**
    * Whether to pass this ability as a tool to every MCP server. NULL = default (no injection).
    *
    * @var bool|null
    */
   public $pass_as_tool = null;
   ```
3. Remove `'pass_as_tool',` from `$blocked_scalar_columns` inside `get_json_fields()` (line 248).
4. Remove `'pass_as_tool'` from `$tri_state_fields` in the constructor (line 285).

---

## CHANGE-3 — Drop the Query Method and Tri-State Entry

**File**: `includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php`

1. Delete the full `get_pass_as_tool_slugs()` method including its multi-line docblock
   (lines ~480–502). Confirm with a grep that nothing else calls it before deleting.
2. Remove `'pass_as_tool'` from the `$tri_state` array inside `prepare_fields_for_write()`
   (line 642).

---

## CHANGE-4 — Delete the Runtime Injection Hook

**File**: `includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php`

This is the most surgical edit in the feature. The file mixes multiple concerns; preserve them
all except the `pass_as_tool` injection.

### Inside `boot()` (lines 170–175)

Delete this block:

```php
		// Register opted-in ability slugs into every MCP server's callable tool registry.
		// Runs at mcp_adapter_init P20, after DefaultServerFactory (P10) and
		// acrossai-mcp-manager database servers (P11) are both created.
		// Uses Reflection to reach McpServer::$component_registry (private) because
		// mcp_adapter_server_config does not exist in the installed mcp-adapter version.
		add_action( 'mcp_adapter_init', array( __CLASS__, 'inject_mcp_tools' ), 20 );
```

Keep the rest of `boot()` (PATH A early-return, `wp_register_ability_args`, `wp_abilities_api_init`).

### Method block (lines ~410–523)

Delete:

- The `// MCP tools pass-through` section comment header (lines 410–412).
- The entire `inject_mcp_tools( $adapter )` method including its docblock (lines ~414–523).

### Helper method (lines 525–546)

`user_has_ability_access()` is currently called only from `inject_mcp_tools()`. Before deleting:

```bash
grep -rn 'user_has_ability_access' includes/ src/ tests/
```

If the only remaining hits are inside the deleted method and tests being removed, delete the helper
too (it has no other consumer). If anything else calls it, keep it.

### What stays untouched in this file

- `inject_override_args`
- `unregister_blocked_abilities`
- `load_overrides_cache`
- `bust_cache`
- `is_manager_rest_request`
- The PATH A / PATH B architectural docblock (the surviving `inject_override_args` hook still
  benefits from the same ARCH-ADV-001 reasoning).

---

## CHANGE-5 — Drop From Utilities (Merger / Sanitizer / Formatter)

**Files**:

- `includes/Utilities/AcrossAI_Ability_Merger.php` (line 39)
- `includes/Utilities/AcrossAI_Abilities_Sanitizer.php` (line 285)
- `includes/Utilities/AcrossAI_Abilities_Formatter.php` (lines 56, 97, 146)

Single-line removals in each file. No other change.

---

## CHANGE-6 — Tidy `Main.php`

**File**: `includes/Main.php` (lines 374–378)

Delete the 4-line comment block:

```php
		// Note: mcp_adapter_init P20 (pass_as_tool injection) is registered inside
		// AcrossAI_Ability_Override_Processor::boot() — PATH B only, per ARCH-ADV-001.
		// Runs after all servers are created (P10 default, P11 database servers).
		// Uses Reflection on McpServer::$component_registry because mcp_adapter_server_config
		// does not exist in the installed mcp-adapter version.
```

The surrounding `register_dependencies()` flow is unaffected.

---

## CHANGE-7 — React UI Removal

### `src/js/abilities/components/AbilityForm.jsx`

1. Line 524 — remove `pass_as_tool: data.pass_as_tool,` from the non-db edit payload object.
2. Lines 1264–1336 — delete the entire `{/* ── Section 4 — Pass as Tool ── */}` `<div>` (TriChips
   + warning notice + "Set Show in MCP" helper text).
3. Section numbering — the section labelled `5` (Annotations) becomes `4`. Walk the surviving
   section comments and `<span className="sect-num">` values forward by one so the user-visible
   numbering stays contiguous.

### `src/js/abilities/components/AbilitiesList.jsx`

1. Lines 128–155 — delete the `PassAsToolCell` function component.
2. Line 192 — remove `pass_as_tool: true,` from `COLUMN_DEFAULTS`.
3. Lines 733–740 — delete the `{!!visibleColumns.pass_as_tool && (<th>…</th>)}` block from the
   `<thead>`.
4. Lines 842–860 — delete the matching `<td>` block in the `<tbody>` row template.
5. There is no `COLUMN_LABELS['pass_as_tool']` entry to remove — the label is hardcoded inside the
   JSX, so step 3 is sufficient.

### `src/js/abilities/store/index.js`

Line 46 — remove `'pass_as_tool',` from `OVERRIDABLE_FIELDS`.

### Build artifact

`build/js/abilities.js` is generated by `npm run build`. Do not hand-edit; rebuild the bundle as
the final step so the artifact matches the source.

---

## CHANGE-8 — Database Migration on Existing Installs

**File**: `includes/Modules/Abilities/Database/AcrossAI_Abilities_Table.php`

Two options. Choose one and record the choice in `docs/memory/DECISIONS.md`.

### Option A (preferred) — bump table version and ALTER on upgrade

1. Bump `$version` from `'1.0.0'` to `'1.1.0'` so `maybe_upgrade()` fires.
2. Add a guarded upgrade routine that drops the column only if it still exists:

```php
public function maybe_upgrade(): void {
    if ( ! $this->exists() ) {
        delete_option( $this->db_version_key );
    }
    parent::maybe_upgrade();
    $this->drop_pass_as_tool_column_if_present();
}

private function drop_pass_as_tool_column_if_present(): void {
    global $wpdb;
    $table = $this->table_name; // BerlinDB-resolved name with prefix.
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
    $present = (string) $wpdb->get_var(
        $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $table, 'pass_as_tool' )
    );
    if ( '' === $present ) {
        return;
    }
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange
    $wpdb->query(
        $wpdb->prepare( 'ALTER TABLE %i DROP COLUMN pass_as_tool', $table )
    );
}
```

Rules:
- Use `%i` for the table identifier (Feature 021 / `DEC-PLUGIN-CHECK-PRODUCTION-SURFACE`).
- Wrap the ALTER in a column-exists guard so re-activation is idempotent.
- Keep the existing `maybe_upgrade()` "phantom version" recovery block intact.

### Option B — drop and reactivate (Feature 034 precedent)

Skip the migration entirely. Document in `DECISIONS.md` that developers must drop the abilities
table manually before reactivating to clear the obsolete column. Acceptable in pre-launch only;
not acceptable once the plugin ships publicly.

---

## CHANGE-9 — Tests

### Full deletions

```text
tests/phpunit/Utilities/AcrossAI_Abilities_Sanitizer_PassAsTool_Test.php
tests/phpunit/Utilities/AcrossAI_Abilities_Formatter_PassAsTool_Test.php
tests/phpunit/Modules/Abilities/Database/AcrossAI_Abilities_Row_PassAsTool_Test.php
tests/phpunit/Modules/Abilities/Database/AcrossAI_Abilities_Schema_PassAsTool_Test.php
tests/phpunit/Modules/Abilities/Database/AcrossAI_Abilities_Query_PassAsTool_Test.php
tests/phpunit/Modules/McpToolsPassthrough/AcrossAI_Mcp_Tools_Passthrough_Test.php
```

Then `rmdir tests/phpunit/Modules/McpToolsPassthrough`.

### Mixed-concern edits

For every other test that mentions `pass_as_tool`, `PassAsToolCell`, `get_pass_as_tool_slugs`, or
`inject_mcp_tools`:

- Remove the key from fixture arrays.
- Drop assertions tied to that key.
- Keep all other assertions (tri-state behaviour, JSON encoding, REST response shape for surviving
  fields).

Run the grep at the bottom of "Test files to edit" until it returns zero hits.

---

## CHANGE-10 — Memory and Governance

### `docs/memory/DECISIONS.md`

Append a new dated entry that supersedes `DEC-MCP-TOOLS-PASSTHROUGH-COLUMN`:

```markdown
### 2026-06-16 — DEC-PASS-AS-TOOL-REMOVED (supersedes DEC-MCP-TOOLS-PASSTHROUGH-COLUMN)

The `pass_as_tool` column and `AcrossAI_Ability_Override_Processor::inject_mcp_tools()` are
removed. Per-ability MCP tool-registry injection is no longer a responsibility of this plugin;
the future `acrossai-mcp-manager` plugin will own that surface (continuing the Feature 034
architectural inversion). The DB column is dropped on existing installs via the table's
`maybe_upgrade()` routine, or by drop-and-reactivate if Option B is taken.

Durable lessons retained even though the feature is removed:
- `BUG-BERLINDDB-QUERY-PRIVATE-CTOR` — `new AcrossAI_Abilities_Query()` is a fatal; always use
  `::instance()`. Applies to every BerlinDB query class in the plugin.
- `BUG-INJECT-MCP-TOOLS-PERMISSION-BYPASS` — AC rules are fail-open when absent; any code that
  relies on AC alone must also call `WP_Ability::check_permissions()` for an authoritative gate.
  Pattern survives the feature removal.
- `BUG-MCP-TYPE-PASSTOOL-CONFLICT` — closed (the conflict surface is removed).
- `PATTERN-PROTECTED-SLUGS-JS-LOCALIZE` — still in active use by other UI cells.
```

Then mark the original `DEC-MCP-TOOLS-PASSTHROUGH-COLUMN` entry with `**Status**: Superseded by
DEC-PASS-AS-TOOL-REMOVED (2026-06-16)` — do not delete it.

### `docs/memory/INDEX.md`

- Update the `DEC-MCP-TOOLS-PASSTHROUGH-COLUMN` row status to `Superseded`.
- Update the `BUG-MCP-TYPE-PASSTOOL-CONFLICT` row to `Resolved (feature removed 2026-06-16)`.
- Update the `BUG-INJECT-MCP-TOOLS-PERMISSION-BYPASS` row to note the immediate trigger is gone
  but the lesson remains active.
- Add a row for `DEC-PASS-AS-TOOL-REMOVED`.
- Update the Feature-029 worklog row to point at the new superseding entry.

### `docs/memory/WORKLOG.md`

Add a Feature 035 entry under today's date with a short summary, the supersedes link, and the
exhaustive-grep verification result.

### `.specify/memory/CONSTITUTION.md`

Grep for `pass_as_tool` / `inject_mcp_tools` / `McpToolsPassthrough`. If anything is present, remove
it and bump the constitution version with a PATCH increment plus the standard sync-impact report
block. If nothing references the feature there, no constitution change is required.

### `AGENTS.md`

Grep first. If `pass_as_tool` / MCP-tool-injection guidance is present, remove it. The file
otherwise stays as-is.

---

## What Must NOT Change

- `show_in_mcp` and every other tri-state column.
- `inject_override_args`, `unregister_blocked_abilities`, `load_overrides_cache`, `bust_cache`,
  `is_manager_rest_request` in `AcrossAI_Ability_Override_Processor`.
- `AcrossAI_Protected_Abilities` and the `protected_slugs` JS localization (consumed by other
  cells).
- `AcrossAI_Sanitizer::cast_tri_state` (still used by remaining tri-state fields).
- REST endpoint paths.
- Any REST response field other than `pass_as_tool`.
- Library, Logger, Access Control modules.
- Historical artifacts: `specs/029-*`, `specs/032-*`, `specs/034-*`, and
  `docs/planning/029-mcp-tools-passthrough.md` (records of past decisions; not edited).

---

## Expected Files Changed

```text
includes/Modules/Abilities/Database/AcrossAI_Abilities_Schema.php
includes/Modules/Abilities/Database/AcrossAI_Abilities_Row.php
includes/Modules/Abilities/Database/AcrossAI_Abilities_Query.php
includes/Modules/Abilities/Database/AcrossAI_Abilities_Table.php          (Option A migration)
includes/Modules/Abilities/AcrossAI_Ability_Override_Processor.php
includes/Utilities/AcrossAI_Ability_Merger.php
includes/Utilities/AcrossAI_Abilities_Sanitizer.php
includes/Utilities/AcrossAI_Abilities_Formatter.php
includes/Main.php
src/js/abilities/components/AbilityForm.jsx
src/js/abilities/components/AbilitiesList.jsx
src/js/abilities/store/index.js
build/js/abilities.js                                                     (regenerated; do not hand-edit)
tests/phpunit/Utilities/AcrossAI_Abilities_Sanitizer_PassAsTool_Test.php  (delete)
tests/phpunit/Utilities/AcrossAI_Abilities_Formatter_PassAsTool_Test.php  (delete)
tests/phpunit/Modules/Abilities/Database/AcrossAI_Abilities_Row_PassAsTool_Test.php       (delete)
tests/phpunit/Modules/Abilities/Database/AcrossAI_Abilities_Schema_PassAsTool_Test.php    (delete)
tests/phpunit/Modules/Abilities/Database/AcrossAI_Abilities_Query_PassAsTool_Test.php     (delete)
tests/phpunit/Modules/McpToolsPassthrough/AcrossAI_Mcp_Tools_Passthrough_Test.php         (delete)
tests/phpunit/Modules/McpToolsPassthrough/                                                 (rmdir)
tests/**                                                                  (mixed-concern fixture edits)
docs/memory/DECISIONS.md
docs/memory/INDEX.md
docs/memory/WORKLOG.md
.specify/memory/CONSTITUTION.md                                           (only if it references the feature)
AGENTS.md                                                                 (only if it references the feature)
```

---

## Validation Checklist

### Exhaustive grep — zero hits in production scope

```bash
grep -rn 'pass_as_tool\|pass-as-tool\|PassAsTool\|get_pass_as_tool_slugs\|inject_mcp_tools\|PassAsToolCell\|McpToolsPassthrough\|Mcp_Tools_Passthrough' \
  includes/ admin/ public/ src/ tests/ acrossai-abilities-manager.php uninstall.php composer.json package.json AGENTS.md *.md
```

Must return zero hits (excluding the WORKLOG/DECISIONS/INDEX entries inside `docs/memory/*`, the
historical planning doc `docs/planning/029-*.md`, this planning doc `docs/planning/035-*.md`, and
`specs/029-*`, `specs/032-*`, `specs/034-*`).

### Production code

- [ ] `AcrossAI_Abilities_Schema.php` has no `pass_as_tool` column.
- [ ] `AcrossAI_Abilities_Row.php` has no `pass_as_tool` property, docblock, blocked-scalar, or
  tri-state entry.
- [ ] `AcrossAI_Abilities_Query.php` has no `get_pass_as_tool_slugs()` and no `pass_as_tool` in the
  tri-state array.
- [ ] `AcrossAI_Ability_Override_Processor.php` has no `inject_mcp_tools`, no
  `mcp_adapter_init` registration, no `// MCP tools pass-through` section, and no
  `user_has_ability_access` helper (unless an external caller justified keeping it).
- [ ] `AcrossAI_Ability_Merger.php`, `AcrossAI_Abilities_Sanitizer.php`, and
  `AcrossAI_Abilities_Formatter.php` have no `pass_as_tool` references.
- [ ] `Main.php` has no `pass_as_tool` comment block.
- [ ] `AbilityForm.jsx`, `AbilitiesList.jsx`, and `store/index.js` have no `pass_as_tool` /
  `PassAsToolCell` references; section numbering in `AbilityForm.jsx` is contiguous.

### Database

- [ ] Fresh install (drop the abilities table and reactivate) creates the table WITHOUT a
  `pass_as_tool` column.
- [ ] If Option A is taken: upgrading from `1.0.0` to `1.1.0` drops the existing
  `pass_as_tool` column; running activation a second time is a no-op (idempotent guard works).
- [ ] If Option B is taken: documented in `DECISIONS.md`; developers know to drop the table
  manually.

### Tests

- [ ] The six dedicated `pass_as_tool` test files are gone.
- [ ] `tests/phpunit/Modules/McpToolsPassthrough/` directory is gone.
- [ ] Mixed-concern test grep is clean.
- [ ] `composer test` (PHPUnit) passes.
- [ ] `npm test` (Jest) passes.

### Quality gates

- [ ] `composer phpstan` passes (level 8).
- [ ] PHPCS on changed production PHP files introduces no new errors.
- [ ] Plugin Check workflow passes (Feature 020 / 021 production-surface scope).
- [ ] `npm run build` emits zero warnings; bundled JS contains no `pass_as_tool` references.

### UI smoke test

- [ ] Abilities list page renders without a `Pass as Tool` column; column-visibility menu has no
  `pass_as_tool` toggle.
- [ ] Ability edit page renders without Section 4; surviving sections are contiguous.
- [ ] Browser console: zero errors / zero warnings.
- [ ] PHP debug log: zero notices/warnings on save and on list refresh.

### Memory and governance

- [ ] `docs/memory/DECISIONS.md` has `DEC-PASS-AS-TOOL-REMOVED` and the original
  `DEC-MCP-TOOLS-PASSTHROUGH-COLUMN` is marked `Superseded`.
- [ ] `docs/memory/INDEX.md` rows for the feature are updated.
- [ ] `docs/memory/WORKLOG.md` has a Feature 035 entry.
- [ ] If `.specify/memory/CONSTITUTION.md` referenced the feature, it is updated and version-
  bumped with a sync-impact report.

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
composer phpstan
composer phpcs
npm run build
npm test
composer test

# 6. Review + memory + commit
/speckit.analyze
/speckit.architecture-guard.architecture-review
/speckit.security-review.staged
/speckit.memory-md.capture-from-diff
/speckit.git.commit
```
