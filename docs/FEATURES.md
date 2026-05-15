# Features

## Sitewide Ability Management

**Spec**: `specs/001-sitewide-ability-management/` | **Status**: Complete

Lets site administrators view, search, sort, filter, and override the metadata of every ability registered via the WordPress Abilities API (`wp_get_ability()`). Overrides are stored per-site in `{prefix}acrossai_abilities_overwrite` via BerlinDB; the registry is the source of truth — only fields that differ from registry defaults are persisted.

### What admins can do

- **Browse** all registered abilities in a searchable, sortable, paginated DataViews table
- **Toggle** per-ability site-wide allow/disallow with a single click (US2)
- **Edit** ability metadata in a slide-in drawer: readonly, destructive, idempotent, show_in_rest, show_in_mcp, mcp_type, mcp_servers — stored as tri-state overrides (Yes / No / Inherit) (US3)
- **Reset** individual overrides to restore registry defaults (US4)
- **Bulk allow / disallow / reset** up to 50 abilities at once (US5)
- **MCP server list** — the MCP tab in the edit drawer shows all registered MCP servers sourced from the `wpboilerplate/wpb-mcp-servers-list` package

### REST endpoints

All endpoints require `manage_options` + nonce (`wp_rest`).

| Method | Path | Description |
|--------|------|-------------|
| GET | `/acrossai-abilities-manager/v1/sitewide/abilities` | Paginated ability list with merged effective values |
| GET | `/acrossai-abilities-manager/v1/sitewide/abilities/{slug}` | Single ability detail |
| POST | `/acrossai-abilities-manager/v1/sitewide/abilities/{slug}` | Save override fields |
| DELETE | `/acrossai-abilities-manager/v1/sitewide/abilities/{slug}` | Delete override row |
| POST | `/acrossai-abilities-manager/v1/sitewide/abilities/{slug}/toggle` | Toggle site_allowed |
| POST | `/acrossai-abilities-manager/v1/sitewide/abilities/bulk` | Bulk allow/disallow/reset |
| GET | `/acrossai-abilities-manager/v1/sitewide/mcp-servers` | List registered MCP servers |

### Key technical decisions

- **Storage**: BerlinDB custom table — override-only, no registry duplication
- **Tri-state fields**: PHP `true` / `false` / `null` map to MySQL `1` / `0` / `NULL` (Inherit)
- **MCP server data**: collected via `wpboilerplate/wpb-mcp-servers-list` at `rest_api_init` priority 20
- **UI**: DataViews table + DataForms slide-in drawer (createPortal)
