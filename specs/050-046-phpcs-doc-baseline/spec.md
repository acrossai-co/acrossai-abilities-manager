# Feature Specification: Bring the Feature-046 absorbed tree to PHPCS zero-errors

**Feature Branch**: `050-046-phpcs-doc-baseline` (proposed)
**Created**: 2026-07-13
**Status**: Draft (follow-up stub from Feature 046)
**Input**: Feature 046 (Absorb Core Abilities Companion Into Manager) landed with 766 residual PHPCS errors in the absorbed tree at `includes/Abilities/`. This spec plans the doc-quality pass to bring the tree to zero PHPCS errors, in line with Constitution §II and §VII.

## Context

Feature 046 relocated 226 PHP files from `acrossai-core-abilities` into
`acrossai-abilities-manager`. After the mechanical migration and two rounds
of automated docblock injection, the tree still carries 766 PHPCS errors
concentrated in a handful of sniff categories:

| Sniff | Count | Nature |
|---|---|---|
| `Squiz.Commenting.FunctionComment.MissingParamComment` | 303 | @param entries with type + var but no description |
| `Squiz.Commenting.FunctionComment.MissingParamTag` | 252 | Docblocks lacking @param for one or more args |
| `Generic.Commenting.DocComment.MissingShort` | 82 | Docblocks lacking a short description line |
| `Squiz.Commenting.FunctionComment.SpacingAfterParamType` | 64 | Column-alignment of @param type/var |
| `WordPress.WP.AlternativeFunctions.file_system_operations_is_writable` | 43 | Use `WP_Filesystem::is_writable()` instead of `is_writable()` |
| `Universal.Operators.DisallowShortTernary.Found` | 21 | `$x ?: $y` short-ternary syntax |
| `WordPress.PHP.YodaConditions.NotYoda` | 16 | Yoda condition style |
| `Squiz.Commenting.InlineComment.InvalidEndChar` | 15 | Inline comment terminal punctuation |
| `WordPress.WP.I18n.MissingTranslatorsComment` | 8 | `/* translators: */` comments before `sprintf( __() )` |
| `WordPress.WP.AlternativeFunctions.rename_rename` | 4 | Use `WP_Filesystem::move()` instead of `rename()` |
| Other | 3 | Minor |

These are all in the absorbed helper code (Utilities, private methods,
formatters). Manager-owned code (`includes/Main.php`, `includes/Modules/*`)
remains clean.

## Non-goals

- No behavior change. This is a pure doc/style pass.
- No new abilities, no new admin surface, no new REST endpoints.

## Recommended approach

1. Extend the Feature-046 docblock injector script (`scripts/046-add-fn-docblocks.pl`)
   to add:
   - Real @param descriptions (one-line, derived from param name)
   - Column-aligned type/var spacing
2. Hand-fix the 43 filesystem-alternative errors (adopt `WP_Filesystem::is_writable()`).
3. Hand-fix the 21 short-ternary and 16 Yoda-condition errors.
4. Hand-add 8 `/* translators: */` comments.
5. Re-run `composer phpcs -- includes/Abilities` until zero errors.
6. Confirm the run is still PHPStan L8 clean.

## Success Criteria

- SC-050-01: `composer phpcs -- includes/Abilities admin/Partials/Core_Settings_Menu.php` exits 0 with zero errors.
- SC-050-02: `composer phpstan` still exits 0.
- SC-050-03: `composer test` still passes 105/105.
- SC-050-04: Bulk audits (`Acrossai_Core_Abilities` etc.) still return zero hits.

## Dependencies

- Feature 046 merged.
- No dependency on any other in-flight feature.
