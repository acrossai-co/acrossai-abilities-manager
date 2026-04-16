# Abilities Editor Workspace Instructions

These instructions apply to all work in this plugin repository.

## Project identity

- This repository is the standalone Abilities Editor WordPress plugin.
- The plugin is an admin and governance layer for the WordPress Abilities API.
- Keep this plugin separate from WordPress/ai and other provider repos.
- Prefer changes that work at the abilities registry layer instead of provider-specific feature gates.

## Canonical runtime model

- Metadata overrides belong in the wp_register_ability_args merge path.
- Site disallow belongs in the late unregister pass inside wp_abilities_api_init.
- Do not implement site disallow with permission-callback-only behavior.
- Do not implement site disallow with provider-specific filters such as wpai_feature_*_enabled.
- The runtime should skip mutation on the plugin admin page so the editor can inspect and save data safely in the same request.

## Canonical admin model

- The plugin has a list screen and an edit screen.
- There is no separate view-only screen.
- Ability names should link directly to edit.
- Row actions are Edit, Allow or Disallow, and Reset when an override exists.
- Preserve the Save, Save and Exit, and Reset Override workflow on the edit screen.

## Data and storage rules

- Override rows are diff-based and should store only deviations from the current live ability state.
- site_allowed controls whether an ability is allowed on the site.
- custom_meta is merged last into the normalized meta structure.
- If an override no longer differs from the live ability, prefer deleting the row instead of preserving redundant data.

## REST contract

- Namespace: abilities-hub/v1
- Routes: GET and POST and DELETE operate on /overrides and /overrides/{slug}.
- Writable fields include site_allowed, readonly, destructive, idempotent, show_in_rest, mcp_public, mcp_type, and custom_meta.
- When documentation changes, keep the route examples aligned with the real controller.

## Maintenance workflow

- Before changing runtime behavior, decide whether the change is about metadata or about registry presence.
- Metadata changes belong in the merge path. Registry presence changes belong in the unregister path.
- Prefer minimal changes that fit the existing plugin structure and coding style.
- Keep the plugin separate from provider repositories and avoid coupling to provider-specific UI toggles unless there is no registry-level option.
- Update README.md and readme.txt in the same task whenever hooks, routes, or admin actions change.

## Validation expectations

- Lint touched PHP files after edits.
- Prefer verifying site disallow by checking whether the ability is still registered.
- Prefer verifying admin-flow changes by confirming list actions and edit behavior match the current instructions.

## Anti-patterns

- Reintroducing a separate view-only screen.
- Reintroducing AI feature-specific gating filters to implement site disallow.
- Replacing the late unregister pass with a permission-callback-only block.
- Writing documentation that describes routes, screens, or buttons that do not exist in the code.
- Leaving README.md, readme.txt, and agent files out of sync with runtime behavior.
