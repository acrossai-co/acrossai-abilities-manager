---
description: "Use when maintaining the Abilities Editor WordPress plugin, especially for runtime override logic, site allow or disallow behavior, admin list filters, REST override routes, or documentation updates."
mode: primary
model: GPT-5.4
---

# Abilities Editor Agent

You are working on the Abilities Editor plugin for WordPress.

## Use this agent when

- The task changes ability runtime behavior.
- The task changes site allow or disallow logic.
- The task changes the list or edit admin flows.
- The task changes REST override routes or payloads.

## Canonical runtime rules

1. Metadata overrides are applied through wp_register_ability_args.
2. Site disallow is not implemented by mutating permission callbacks.
3. Site disallow is enforced by unregistering the ability late in wp_abilities_api_init.
4. The late unregister pass is the canonical behavior for site_allowed = false.
5. Do not replace that registry-level behavior with provider-specific filters such as wpai_feature_*_enabled.

## Canonical admin rules

1. The plugin has a list screen and an edit screen.
2. There is no separate view-only screen.
3. The list row actions are Edit, Allow or Disallow, and Reset when an override exists.
4. The ability name links directly to the edit screen.
5. The edit screen is the only screen for inspecting and modifying a single ability.

## Canonical data rules

1. Override rows are diff-based and should store only values that differ from the current live ability state.
2. site_allowed controls site-level allow or disallow behavior.
3. custom_meta is merged last into the normalized meta structure.
4. If a saved override no longer differs from the live ability state, prefer removing the row instead of keeping redundant data.

## Change filters

1. If a change affects whether an ability exists on the site, prefer registry-level logic over provider-specific feature flags.
2. If a change affects annotations or visibility metadata, keep it in the wp_register_ability_args merge path.
3. If a change adds a new screen or route, default to the existing list-plus-edit workflow unless there is a clear product reason.
4. If a change adds new fields, preserve diff-only storage.
5. If hooks, routes, or actions change, update README.md, readme.txt, and these instructions in the same task.

## Anti-patterns

- Reintroducing a separate view screen.
- Reintroducing AI feature-specific gating filters to implement site disallow.
- Replacing the late unregister pass with a permission-callback-only block.
- Documenting routes or actions that do not exist in the code.
- Leaving workspace instructions, agent files, README.md, and readme.txt out of sync with runtime behavior.
