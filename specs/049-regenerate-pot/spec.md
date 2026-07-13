# Feature Specification: Regenerate `.pot` translation template

**Feature Branch**: `049-regenerate-pot` (proposed)
**Created**: 2026-07-13
**Status**: Draft (follow-up stub from Feature 046)
**Input**: Feature 046 rewrote ~5000 translation strings via the CHANGE-5c text-domain sweep (`'acrossai-core-abilities'` → `'acrossai-abilities-manager'`) and the CHANGE-5e label sweep (`Acrossai Core Abilities` → `Acrossai Abilities Manager`). The plugin's `.pot` file is not regenerated in scope of Feature 046.

## Context

`languages/acrossai-abilities-manager.pot` is the translation template WordPress
translators consume. Feature 046 introduced:

- ~5000 rewritten `__()` / `_e()` / `esc_html__()` / `esc_attr__()` calls in the
  absorbed tree (all now under the `acrossai-abilities-manager` text domain).
- Rewritten visible label text (`Acrossai Core Abilities …` → `Acrossai
  Abilities Manager …`) — most notably the 17 category labels.
- Two new UI strings from the merged Core settings field ("Upload Media
  Abilities" section, "Allowed for upload-media", "Add file types").

The current `.pot` reflects the pre-Feature-046 string catalog. Any translator
consuming it now would produce out-of-date translations.

## Goal

Regenerate `languages/acrossai-abilities-manager.pot` from the current source
tree so translators can produce complete `.po` / `.mo` files.

## Recommended approach

Use WP-CLI's `wp i18n make-pot`:

```
wp i18n make-pot . languages/acrossai-abilities-manager.pot \
  --slug=acrossai-abilities-manager \
  --domain=acrossai-abilities-manager \
  --skip-audit \
  --exclude=vendor,node_modules,build,tests,.specify,docs,specs,src
```

Alternative: `@wordpress/scripts` includes a `build-i18n` command that
achieves the same result via the WordPress i18n build pipeline.

## Success Criteria

- SC-049-01: Regenerated `.pot` contains ~5000+ msgid entries covering all
  moved absorbed strings.
- SC-049-02: msgid entries reference the correct source-file locations
  (e.g. `#: includes/Abilities/Plugins/Plugin_Activate.php:32`) — the
  rewrite matrix updated source paths.
- SC-049-03: No pre-existing manager msgids are lost from the regeneration
  (diff the old `.pot` against the new one).
- SC-049-04: Existing `.po` files in the repo (if any) still resolve against
  the new `.pot` without producing "fuzzy" entries for unchanged msgids.

## Non-goals

- No new translations produced.
- No behavioral change to any user-facing string.
- No `.mo` compilation (translators handle that).

## Dependencies

- Feature 046 merged.
- Requires WP-CLI available in the dev environment.
