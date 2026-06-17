# Upgrade Notes — Feature 035 (Remove Pass as Tool)

## Audience

Developers running the plugin on pre-launch single-site or multisite dev environments. No
production sites are affected — this is a pre-launch removal.

## What changed

The `pass_as_tool` ability flag and its supporting column on the abilities table are removed.
After upgrading to a build that includes Feature 035:

- No production code path reads or writes the `pass_as_tool` column.
- REST API responses no longer contain `pass_as_tool`. Inbound writes that include the key are
  silently ignored (default WP REST unknown-param behavior).
- The admin "Pass as Tool" column and ability-form Section 4 are gone.
- Per-ability MCP tool-registry injection is no longer a responsibility of this plugin. The
  future `acrossai-mcp-manager` plugin will own that surface.

Behind the scenes, the runtime hook (`mcp_adapter_init` P20) and the Reflection-based reach into
mcp-adapter's private `McpServer::$component_registry` are removed entirely.

## Recommended manual cleanup procedure

Feature 035 ships **without** an automated `maybe_upgrade()` ALTER routine. Carrying one past
launch would be dead weight. Existing dev environments that already have the obsolete
`pass_as_tool` column should clean it up manually using the procedure below.

The leftover column is **inert** — no production code reads or writes it. Cleaning it is
hygiene, not a correctness requirement.

### Single-site

```bash
wp plugin deactivate acrossai-abilities-manager
wp db query "DROP TABLE \`$(wp db prefix)acrossai_abilities\`;"
wp plugin activate acrossai-abilities-manager
```

The reactivation creates a fresh table without the obsolete column.

### Multisite (per `SEC-03` per-site prefix isolation)

The abilities table is **per-blog** (`$global = false`). Each subsite has its own physical
table. Drop the table on every blog before reactivation:

```bash
wp plugin deactivate acrossai-abilities-manager --network
wp site list --field=url | while read -r url; do
    wp --url="$url" db query "DROP TABLE \`$(wp --url="$url" db prefix)acrossai_abilities\`;" || true
done
wp plugin activate acrossai-abilities-manager --network
```

Skip a blog if its abilities table doesn't exist — the `|| true` swallows the SQL error.

## What if you skip the cleanup?

Nothing breaks. Runtime code never reads the `pass_as_tool` column after Feature 035 lands.
The orphan bytes persist in the DB until the developer chooses to drop them.

## Fresh installs

New installs (no prior abilities table) are unaffected: the schema definition no longer
emits the column. Activating the plugin creates a table without it.

## Verification

After running the cleanup, confirm the column is gone:

```bash
wp db query "SHOW COLUMNS FROM \`$(wp db prefix)acrossai_abilities\` LIKE 'pass_as_tool';"
```

Empty result = success.

## See also

- `docs/memory/DECISIONS.md` → `DEC-PASS-AS-TOOL-REMOVED` for the decision record.
- `specs/035-remove-pass-as-tool/spec.md` for the feature specification.
- `specs/035-remove-pass-as-tool/security-constraints.md` finding SEC-035-002 for the rationale
  behind manual-not-automated migration.
