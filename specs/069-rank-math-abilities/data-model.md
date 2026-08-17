# Data Model: Rank Math Ability Suite

**Feature**: 069-rank-math-abilities

Four entities. None is persisted by this feature — `Field Spec`, `Settings Panel` and
`Tool Descriptor` are compile-time tables in the helper layer, and `Response Envelope` is the
runtime contract.

---

## Response Envelope

Returned by every ability, on both paths. Assembled only by
`Rank_Math_Guard::ok()` / `::fail()` / `::error()`.

**Success**

| Field | Type | Notes |
|---|---|---|
| `success` | boolean | Always `true` |
| *(payload)* | mixed | Ability-specific keys, declared in that ability's `output_schema` |
| `message` | string | Human-readable summary. Translated |

**Failure**

| Field | Type | Notes |
|---|---|---|
| `success` | boolean | Always `false` |
| `message` | string | Human-readable. Names the flag, setting, module or field that must change |
| `error_code` | string | Machine-readable, from the table below |
| *(context)* | mixed | Optional echo of the identifying input (e.g. `post_id`, `tool`, `panel`) |

Constraints:
- `output_schema` always declares `'required' => array( 'success' )` and
  `'additionalProperties' => false`.
- A `WP_Error` from a helper is unwrapped into `error_code` + `message`. A raw `WP_Error` is never
  returned from `execute()`.
- The eight keys permitted by `AcrossAI_Ability_Library_Registry::ALLOWED_ARGS_FIELDS` are the only
  keys `ability()` may emit: `label`, `description`, `category`, `execute_callback`,
  `permission_callback`, `input_schema`, `output_schema`, `meta`. Anything else is silently stripped.

### Standard error codes

| Code | Meaning |
|---|---|
| `rank_math_missing` | Rank Math is not active |
| `rank_math_module_inactive` | The required Rank Math module is disabled |
| `rank_math_pro_required` | Requires the Rank Math PRO plugin |
| `rank_math_account_required` | Requires a connected Rank Math cloud account |
| `content_ai_no_credits` | Credit balance is insufficient; no remote call was made |
| `google_console_not_connected` | Search Console is not connected |
| `confirmation_required` | Destructive operation called without `confirm: true` |
| `insufficient_capability` | Caller lacks the capability for the requested panel or object |
| `unknown_field` | Field does not belong to the requested settings panel |
| `protected_field` | Field is on the deny list |
| `invalid_input` | A value failed type, enum, range or pattern validation |
| `not_found` | The requested object does not exist |
| `tool_unavailable` | Maintenance tool's module or precondition is missing |
| `inspections_table_missing` | URL Inspection storage absent; run `recreate_tables` |
| `headless_support_disabled` | `general.headless_support` is off |
| `infinite_loop_new` | Redirection created but auto-deactivated — source resolves to destination |
| `infinite_loop_update` | Redirection update refused — would create a loop |
| `no_valid_source` | Redirection has no usable source rule |
| `indexnow_400` / `_403_invalid_key` / `_422` / `_429_rate_limited` / `_500` | IndexNow service response |

---

## Field Spec

One settings field. Lives in `Settings_Registry::panels()`.

| Field | Type | Notes |
|---|---|---|
| `type` | string | The LEGACY CMB2 type, verbatim from the Rank Math source. Translated to a `TYPE_*` value by `emitted_type()` |
| `enum` | string[]\|null | Allowed values for `select` / `checkboxlist` |
| `min` / `max` | int\|null | Bounds for `number` |
| `pattern` | string\|null | Regex for constrained text (e.g. `HH:MM-HH:MM`) |
| `group_schema` | array\|null | Field Specs for each row, when `type` is `group` |
| `label` | string | Human-readable, translated |
| `default` | mixed | Rank Math's default |
| `readonly` | bool | Derived values, e.g. `indexnow_api_key_location` |

### Types and their sanitizer consequence

| Constant | Value passed to Rank Math | Effect |
|---|---|---|
| `TYPE_TEXT` | `text` | `sanitize_textfield()` — **collapses newlines** |
| `TYPE_TEXTAREA` | `textarea` | Preserves newlines |
| `TYPE_TOGGLE` | `toggle` | Normalized to `'on'` / `'off'` |
| `TYPE_NUMBER` | `number` | Integer, clamped to `min`/`max` |
| `TYPE_SELECT` | `select` | Must be in `enum` |
| `TYPE_CHECKBOXLIST` | `checkboxlist` | List of `enum` members |
| `TYPE_GROUP` | `group` | Repeatable; **must** be `array_values()`-reindexed |
| `TYPE_FILE` | `file` | `esc_url_raw` |

`DENIED_KEYS`: `htaccess_allow_editing`, `htaccess_content`, `searchConsole`, `analyticsData`,
`analytics`, `usage_tracking`.

---

## Settings Panel

A named group of Field Specs. 20 panels.

| Field | Type | Notes |
|---|---|---|
| `slug` | string | e.g. `general-webmaster`, `titles-post-type` |
| `option_type` | string | `general` \| `titles` \| `sitemap` \| `instant_indexing` |
| `cap` | string | Rank Math capability suffix: `general` \| `titles` \| `sitemap` |
| `dynamic` | string\|null | `post_type` or `taxonomy` when field ids are patterned (`pt_{$object}_*`) |
| `fields` | array | field id or pattern → Field Spec |
| `source` | string | The Rank Math `file:line` this table mirrors — required, for re-diffing on upgrade |

Panels: `general-{links,breadcrumbs,webmaster,image-seo,404-monitor,redirections,instant-indexing,robots-txt,others}`,
`titles-{post-type,taxonomy,homepage,author,misc,global,social,local-seo}`,
`sitemap-{general,post-type,taxonomy}`.

`general-robots-txt` reads carry an extra `state` object: `editable`, `robots_locked`,
`site_not_public`, `physical_file_exists`, `physical_file_path` — a physical `robots.txt` on disk
makes the editor inert.

---

## Tool Descriptor

One maintenance tool. Lives in `Maintenance_Tools`.

| Field | Type | Notes |
|---|---|---|
| `id` | string | Input enum member, e.g. `clear_transients` |
| `target` | callable | Concrete `[class, method]` pair — never a filter name (research F3) |
| `module` | string\|null | Module that must be active, else `tool_unavailable` |
| `async` | bool | Work continues after the response |

| `id` | Module | Async |
|---|---|---|
| `clear_transients` | — | no |
| `clear_seo_analysis` | `seo-analysis` | no |
| `delete_links` | — | no |
| `delete_log` | `404-monitor` | no |
| `delete_redirections` | — | no |
| `recreate_tables` | — | **yes** |
| `recreate_actionscheduler_tables` | — | no |
| `yoast_blocks` | — | **yes** |
| `aioseo_blocks` | — | **yes** |
| `analytics_clear_caches` | `analytics` | no |
| `analytics_reindex_posts` | `analytics` | **yes** |
| `analytics_fix_collations` | `analytics` | no |

Normalized result: `{ success, tool, completed, async, tool_message, message }`, plus `poll_hint`
naming the Action Scheduler group `rank-math` when `async` is true. Rank Math's handlers return either
a plain string or `['status' => 'error', 'message' => …]`; both are normalized here.
