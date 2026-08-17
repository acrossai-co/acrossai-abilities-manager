# Security Review — Feature 069: Rank Math Ability Suite

**Date:** 2026-08-17
**Branch:** `069-rank-math-abilities` (`8cb5f18` → `7c17cd5`)
**Scope:** 61 new abilities, 18 helper classes, bootstrap wiring. 79 new files.
**Method:** identification pass, then an independent validation pass per candidate; findings below confidence 8/10 discarded.

## Outcome

One HIGH finding, **fixed in `7c17cd5`**. One candidate dismissed as a false positive after verification.

## SEC-069-001 — Missing per-object authorisation on term / user schema writes (HIGH, fixed)

`Post_Meta_Repository::update_schemas()` called `\RankMath\Rest\Shared::update_schemas()` directly while
asserting per-object rights only for `object_type === 'post'`. The input enum accepted `post|term|user`.

Rank Math's handler carries no capability logic of its own — all per-object authorisation lives in its
REST route's `permission_callback` (`Rest_Helper::get_schema_permissions_check()`), which enforces
`current_user_can( 'edit_user', $id )` for users and the taxonomy's `edit_terms` for terms, plus a
`rich-snippet` module gate. Invoking the handler directly dropped all of it.

Reachability came from the capability floor: Rank Math grants `rank_math_onpage_snippet` to the **author
and editor** roles by default (`class-capability-manager.php:143-166`, called unconditionally at install),
and `update-post-schemas` used an `edit_posts` floor — so an Author passed the `permission_callback`. WP
core's abilities run controller performs no additional capability check.

Compounding it, a `schema-<meta_id>` key routes to `update_metadata_by_mid()`, which locates the row by
meta id **alone** (`wp-includes/meta.php:967`), derives the object id from the row, and **rewrites the
row's `meta_key`**. The submitted `object_id` was therefore decorative even on the `post` branch, so any
row in `postmeta` / `termmeta` / `usermeta` was reachable by enumerating small integers.

**Impact:** integrity destruction and administrator lockout — e.g. renaming an admin's `wp_capabilities`
row, stripping their roles. **Not** privilege gain: the written `meta_key` is always
`'rank_math_schema_' . $type` with `$type` sanitised to alphanumerics, so a forged `wp_capabilities`
*value* was never possible. An initial draft of the finding claimed self-escalation; that was corrected
during validation.

**Fix:**
- Every `permission_floor()` override removed; the base method is `final` and returns `manage_options`.
  This alone removes the lower-privileged path.
- `assert_object_editable()` covers post, term and user with an explicit reject for anything else.
- `assert_meta_row_belongs_to()` verifies via `get_metadata_by_mid()` that the row belongs to the named
  object and is genuinely a `rank_math_schema_*` row before any write. This is the check that actually
  contains the `update_metadata_by_mid()` primitive, and it closes the same gap on the `post` branch.
- Both schema abilities now gate on the `rich-snippet` module, matching Rank Math's own route.
- Pinned by `Test_Rank_Math_Suite_Contract::test_no_ability_lowers_the_capability_floor()`,
  `::test_base_floor_is_manage_options_and_final()` and
  `::test_schema_writes_authorise_every_object_type()`.

**Verified live:** administrator 61/61; editor, author, contributor, subscriber 0/61 even when granted
`rank_math_onpage_snippet`, `onpage_general` and `general` explicitly. Cross-object write refused.

## Dismissed — `DENIED_KEYS` not applied on the import path (false positive, 2/10)

`Status_Tools_Repository::import_settings()` passes unfiltered `$data` to `do_import_data()`, bypassing
`Settings_Writer`'s deny list. Not a vulnerability:

- `maybe_update_htaccess()` is `private static`, called only from `save_settings()`, and reads only the
  **caller-submitted** array. No reader of the *stored* `htaccess_content` exists anywhere in Rank Math,
  so an imported value is inert and never reaches disk. `Helper::is_edit_allowed()` also blocks the direct
  path under `DISALLOW_FILE_EDIT` / `DISALLOW_FILE_MODS`.
- `set_capabilities()` touches only registered `rank_math_*` capabilities.
- The ability requires `manage_options` + `rank_math_general` + `confirm: true`, whereas Rank Math's own
  `/rankmath/v1/updateSettings` route gates on `rank_math_$type` alone with no `manage_options` and no
  confirmation — so the ability is strictly *more* restrictive than the vendor endpoint already reaching
  these effects. No boundary is crossed.

**Maintainer nits (non-security):** `panels_imported` is derived from submitted keys rather than what
`set_options()` wrote, so it omits the `modules` key present in every export and can over-report;
intersecting `$data` down to `PANELS + 'modules'` before dispatch would align the accepted surface with
the documented one.

## Areas verified and found safe

`Rank_Math_Guard::can()` strict AND with no OR path · `Get_Settings` runtime capability re-check
(enum-constrained, resolved through `Settings_Registry::panel()` before `has_cap()`; all 20 panel caps
match their writer's) · `Settings_Writer` deny-key enforcement (rejected twice; nested group rows cannot
inject a top-level key; the `instant_indexing` branch runs after both strips) · post/score/primary-term
writes (per-object `edit_post` per row, whitelisted fields, enum-checked robots, `esc_url_raw` canonical)
· redirection serializers (the Apache branch omits the newline strip, but every write path runs
`wp_strip_all_tags( $pattern, true )` first, collapsing `[\r\n\t ]+`; a defence-in-depth strip on the
Apache branch is still recommended since the safety rests on third-party code) · SSRF (fixed literal
paths on `home_url()`; `rendered_head()` passes the caller URL only as a `rawurlencode`d query arg gated
by `wp_http_validate_url()`, and Rank Math derives a local `REQUEST_URI` rather than fetching it — host
and scheme never attacker-controlled, so `sslverify => false` affects loopback only) · dynamic dispatch in
`Maintenance_Tools` and `Entitlement_Repository` (method and class names from private constant maps after
`isset()` / `in_array(…, true)`) · `maybe_unserialize()` call sites (all read the `sources` column written
only by Rank Math's model; abilities pass arrays, never serialized strings — no object-injection path) ·
no `$wpdb`, raw SQL, `eval`, `extract`, `unserialize`, shell function or file write in the 79 new files ·
analytics parameters land in Rank Math's `orderby`/`order` allowlists, `search` never interpolated into
SQL · `Content_Audit_Repository` author-scopes its query when the caller lacks `edit_others_posts` and
filters each row through `edit_post` · data exposure (`Status_Repository::google()` returns booleans only,
never tokens; `Export_Settings` does not include the OAuth token option) · XSS (abilities return data
structures, no HTML sink, all labels are static literals) · registration wiring (`class_exists` gate,
`mcp.public => false` on every ability).

## Residual risk accepted

- The Apache serializer relies on Rank Math's `wp_strip_all_tags( $pattern, true )` at every write path to
  keep newlines out of `sources[].pattern`. If Rank Math changes that, newline injection into generated
  `.htaccess` text becomes possible. Low risk today; a local strip on the Apache branch would remove the
  dependency.
- `bulk_update_meta` with `object_type=term` performs no per-term capability check. This matches Rank
  Math's own `updateBulkMeta` route exactly, and the ability now requires `manage_options`, so no boundary
  is crossed. A defensive `edit_term` check remains worth adding.
