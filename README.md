# Abilities Editor

Abilities Editor is a standalone WordPress plugin for reviewing and overriding WordPress Abilities metadata from a classic wp-admin screen.

## Runtime model

The plugin uses two separate runtime paths and they should stay separate:

- Metadata overrides are merged through wp_register_ability_args.
- Site disallow is enforced by unregistering the ability late in wp_abilities_api_init.

That split is intentional. Metadata changes stay lightweight, while site-level disallow behaves like an audit or governance rule at the abilities registry level.

## Admin workflow

- Tools -> Ability Overrides shows the searchable and sortable list screen.
- Ability names link directly to the edit screen.
- Row actions are Edit, Allow or Disallow, and Reset when an override exists.
- There is no separate view-only screen.
- The edit screen exposes the ability slug, provider, category, site allow toggle, metadata flags, REST exposure, MCP visibility, and MCP type.
- Save actions are Save, Save and Exit, and Reset Override.

## Stored fields

- site_allowed
- readonly
- destructive
- idempotent
- show_in_rest
- mcp_public
- mcp_type
- custom_meta

The repository stores only values that differ from the current live ability state. If an override no longer differs from the live defaults, the row should be removed instead of kept as redundant data.

## REST API

- GET /wp-json/abilities-manager/v1/overrides
- GET /wp-json/abilities-manager/v1/overrides/{slug}
- POST /wp-json/abilities-manager/v1/overrides/{slug}
- DELETE /wp-json/abilities-manager/v1/overrides/{slug}

Supported writable fields include site_allowed, readonly, destructive, idempotent, show_in_rest, mcp_public, mcp_type, and custom_meta.

## Maintenance rules

- Use wp_register_ability_args for metadata overrides.
- Use site_allowed = false plus late wp_unregister_ability() for site-level blocking.
- Do not reintroduce provider-specific filters such as wpai_feature_*_enabled for behavior that belongs at the abilities registry level.
- Keep the admin flow list-plus-edit only unless the product intentionally adds another screen.
- Update both readmes whenever hooks, routes, or row actions change.

## Installation

1. Copy the plugin into wp-content/plugins/abilities-manager.
2. Activate Abilities Editor.
3. Visit Tools -> Ability Overrides as an administrator.
