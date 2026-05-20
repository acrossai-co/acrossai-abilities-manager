# Security Constraints: Sitewide Ability Management

**Feature**: 001-sitewide-ability-management
**Review Date**: 2026-05-17
**Artifacts Reviewed**:
- `specs/001-sitewide-ability-management/spec.md`
- `specs/001-sitewide-ability-management/plan.md`
- `specs/001-sitewide-ability-management/memory-synthesis.md`
- `specs/001-sitewide-ability-management/contracts/rest-api.md`
- `docs/memory/DECISIONS.md`
- `.specify/memory/CONSTITUTION.md`

**Finding Summary**: 0 HIGH · 3 MEDIUM · 4 LOW · 3 INFO

---

## Trust Boundaries

| ID | Boundary | Direction | Control |
|---|---|---|---|
| TB-01 | Browser → WordPress REST API | Inbound | Nonce (`X-WP-Nonce`) + `manage_options` in `check_permission()` |
| TB-02 | REST API → BerlinDB / MySQL | Outbound | BerlinDB wraps `$wpdb->prepare()`; no raw query interpolation |
| TB-03 | REST API → WordPress Abilities Registry | Read-only | Plugin never writes to the registry; `wp_get_ability()` / `wp_get_abilities()` are read-only calls |
| TB-04 | REST API → MCP Adapter (`WP\MCP\Core\McpAdapter`) | Optional outbound | `class_exists()` guard; absent = empty server list, no error surfaced to client |
| TB-05 | `after_save` hook → third-party consumers | Outbound | `$fields` passed to hook MUST be the post-sanitization value only (SEC-02) |
| TB-06 | Admin page → `localStorage` | Client-side | Column visibility preferences only; no capability tokens, nonces, or override data stored |
| TB-07 | `AcrossAI_Ability_Override_Processor` → WordPress Abilities API | Conditional | Wires `wp_register_ability_args` / `wp_abilities_api_init` only on PATH B (non-Manager REST); guarded by `is_manager_rest_request()` (ARCH-ADV-001) |

---

## Authorization Constraints

All 7 REST endpoints share a single `check_permission()` in the orchestrator
(`AcrossAI_Sitewide_Rest_Controller`). Sub-controllers reference it as
`array( AcrossAI_Sitewide_Rest_Controller::instance(), 'check_permission' )` — they MUST NOT
define their own permission callbacks.

**Shared gate** (all endpoints):
1. WordPress REST nonce verified automatically via `X-WP-Nonce` header and the WP REST API nonce cookie mechanism.
2. `current_user_can( 'manage_options' )` evaluated inside `check_permission()`.
3. Any failure returns HTTP `403 Forbidden`.

| Endpoint | Method | Additional constraint |
|---|---|---|
| `/sitewide/abilities` | GET | None beyond shared gate |
| `/sitewide/abilities/{slug}` | GET | Slug sanitized before registry look-up; 404 if not registered |
| `/sitewide/abilities/{slug}` | POST | Partial-field save: only `has_param()` fields are written |
| `/sitewide/abilities/{slug}` | DELETE | Idempotent; 200 with `deleted: false` if no row exists — no 404 leak |
| `/sitewide/abilities/{slug}/toggle` | POST | Only `site_allowed` field modified; no other fields touched |
| `/sitewide/abilities/bulk` | POST | Max 100 slugs enforced server-side; `action` validated against allowlist |
| `/sitewide/mcp-servers` | GET | None beyond shared gate; returns `[]` when adapter absent |

**Nonce key**: `'wp_rest'` — set via `wp_create_nonce('wp_rest')` in the inline script at page render. Nonces are user-scoped and expire in 24 hours per WordPress default.

**Admin page gate**: `admin.php?page=acrossai-abilities-manager` must be registered with a
`manage_options` capability argument in `add_menu_page()` so WordPress prevents direct page
access by lower-privilege users before any React code loads.

---

## Input Validation Requirements

### Slug Parameters (all endpoints with `{slug}`)

- **SEC-01**: `AcrossAI_Sanitizer::sanitize_ability_slug()` MUST be called on every URL slug
  parameter before the value is used in a registry look-up, query filter, or hook argument.
- Slugs use `/` as a namespace separator (e.g., `my-plugin/read-posts`). The sanitizer MUST
  preserve the forward slash — `sanitize_key()` strips it and is NOT acceptable on its own.
- After sanitization, the slug MUST be validated against the live registry
  (`wp_get_ability( $slug )` → non-null). Unrecognised slugs return 404.
- Maximum slug length must be enforced to prevent excessive memory pressure in bulk operations
  (see Q-04 in Open Questions).

### Tri-State Boolean Fields (`site_allowed`, `readonly`, `destructive`, `idempotent`, `show_in_rest`, `show_in_mcp`)

- Accept `true`, `false`, or `null` (JSON null = "clear override / use registry default").
- Sanitize with `rest_sanitize_boolean()` for non-null values.
- **PHP bool→int cast REQUIRED** before BerlinDB: `true → 1`, `false → 0`; leave `null`
  unchanged. Failure to cast causes `$wpdb` to assign `%s` format, producing `''` on
  `sprintf()`, which MySQL 8+ strict mode silently rejects on `tinyint NOT NULL` columns (D2).

### Enum Fields

| Field | Allowed values | Validator |
|---|---|---|
| `mcp_type` | `"tool"`, `"resource"`, `"prompt"`, `null` | Allowlist check; reject all other strings |
| `action` (bulk) | `"allow"`, `"disallow"`, `"reset"` | Allowlist check; 400 on invalid value |
| `orderby` | `"slug"`, `"provider"`, `"source"`, `"status"` | Allowlist check; default `"slug"` |
| `order` | `"asc"`, `"desc"` | Allowlist check; default `"asc"` |
| `source` (filter) | `"plugin"`, `"theme"`, `"core"`, `"db"`, `""` | Allowlist check |

### Array Fields

- `mcp_servers`: each element MUST be validated as a non-empty string; array length MUST NOT
  exceed 100 items; the full array is stored serialized — READ-time deserialization MUST verify
  the result is an array before returning it to the client (see MEDIUM-01).
- `slugs` (bulk): maximum 100 items enforced; each item sanitized via
  `AcrossAI_Sanitizer::sanitize_ability_slug()` individually.

### Pagination / Query Parameters

- `page`: `absint()`, minimum 1.
- `per_page`: `absint()`, range 1–100; default 20.
- `search`: `sanitize_text_field()`.

### Partial-Field Save Guard

- REST handlers MUST use `$request->has_param( $field )` — not `get_param()` — to determine
  whether a field was sent (D3). `has_param()` returns `true` even when the field is
  explicitly set to `null` (user intends to clear the override), preventing silent overwrite
  of other-tab DB values with `null`.
- `is_all_default()` MUST be gated on `!$existing` (D4): if a DB row exists, always write
  so that explicit `null` values reach the database and clear stored overrides.

---

## Data Isolation Risks

| ID | Risk | Severity | Mitigation |
|---|---|---|---|
| DI-01 | **Multisite table scope** | MEDIUM (see MEDIUM-02 note) | The `{prefix}acrossai_abilities_overwrite` table uses the per-blog prefix, making overrides site-specific in multisite. Confirm `$wpdb->prefix` (not `$wpdb->base_prefix`) is used in `AcrossAI_Sitewide_Table` to prevent network-wide bleed. |
| DI-02 | **Orphan override rows** | LOW | When an ability is deregistered the override row persists. The registry is the source of truth for the visible list; orphan rows are not shown. No data-leak risk, but rows accumulate silently. Address in a future maintenance task. |
| DI-03 | **`mcp_servers` deserialization** | MEDIUM (MEDIUM-01) | Stored as a serialized array. READ path must deserialize and type-check before including in REST response. |
| DI-04 | **User ID fields in REST response** | LOW | `created_by` / `updated_by` expose WP user IDs to `manage_options` users only (already within trust boundary). Acceptable. |
| DI-05 | **`localStorage` key includes user ID** | INFO | `acrossai_ability_table_view_{userId}` leaks user ID to any JS executing in the same origin. No sensitive data is stored in that key; risk is negligible for an admin-only page. |

---

## Hook Security

### `acrossai_abilities_sitewide_after_save( $slug, $fields )`

- **SEC-02**: Hook fires ONLY with sanitized `$fields`. Raw request data MUST NOT reach this hook.
- Third-party consumers MUST treat `$slug` and `$fields` as already-sanitized values and MUST
  NOT re-sanitize with lossy functions.
- Consumers MUST NOT cast or re-encode the `$fields` values, as they are already cast to
  their final DB types at this point.

### `acrossai_abilities_sitewide_before_save( $slug, $fields )`

- Fires before the DB write. If any hooked callback modifies `$fields`, the implementation
  MUST re-apply type validation before passing values to BerlinDB. The plan does not currently
  document this re-validation requirement.
- Consumers MUST NOT inject new keys into `$fields` — only the 8 documented fields are safe
  to pass to BerlinDB columns.

### `acrossai_abilities_sitewide_rest_response`

- Filter on the REST response array. Consumers can modify the payload seen by the JS client.
- MUST NOT remove `slug`, `has_override`, or alter field types. Arbitrary removal or type
  change will break client-side Redux store deserialization.
- No type contract is currently documented for consumers of this filter (see Q-03).

### `wp_register_ability_args` / `wp_abilities_api_init` (Override Processor)

- Registered via direct WP API in `AcrossAI_Ability_Override_Processor::boot()` (ARCH-ADV-001),
  not through the Loader. These hooks MUST NOT fire on PATH A (Manager REST requests) — guarded
  by `is_manager_rest_request()`.
- **Fail-open design** (DEC-PERM-CB): When `AccessControlManager::get_manager()` returns null
  (library absent), `inject_override_args()` returns `true` — all access permitted. Deployers
  who rely on AC rules MUST ensure `wpb-access-control` library is present and active.

---

## Findings

### MEDIUM-01 — `mcp_servers` deserialization not explicitly guarded on read

**Severity**: MEDIUM
**Location**: `AcrossAI_Sitewide_Query::get_override()` (read path) → REST response
**Description**: `mcp_servers` is stored as a serialized value in a DB column. The REST contract
specifies it is returned as `string[]|null`. If the stored value is corrupted or manually edited
to a non-array type, the read path may return an unexpected type (scalar string or object) to
the JS client, causing Redux store deserialization failures or unexpected client-side behavior.
**Required constraint**: After deserializing `mcp_servers` from the DB on every read, the
implementation MUST assert `is_array( $value ) || is_null( $value )`. If the assertion fails,
return `null` and log a warning. Do NOT pass a malformed value to the REST response.

---

### MEDIUM-02 — Multisite table prefix not explicitly verified as per-blog

**Severity**: MEDIUM
**Location**: `AcrossAI_Sitewide_Table` (BerlinDB Table class)
**Description**: The plan states the plugin is multisite-compatible but does not explicitly
confirm that the table is created with `$wpdb->prefix` (per-blog) rather than
`$wpdb->base_prefix` (network-wide). If `base_prefix` is used, override records from all
sites share one table, mixing data across trust boundaries and allowing a blog-level admin
of Site A to read or influence Site B's overrides.
**Required constraint**: `AcrossAI_Sitewide_Table` MUST use `$wpdb->prefix` (the current
blog's prefix). The `$db_version_key` option MUST also be stored per-blog via `update_option()`
without `blog_id` override.

---

### MEDIUM-03 — Fail-open AC rule enforcement must be communicated to deployers

**Severity**: MEDIUM
**Location**: `AcrossAI_Ability_Override_Processor::inject_override_args()` (DEC-PERM-CB)
**Description**: When the `wpb-access-control` library is absent, `inject_override_args()`
returns `true` unconditionally — all ability access is permitted regardless of any AC rules
that may appear to be configured. An administrator who activates the plugin expecting AC rules
to be enforced but has not installed `wpb-access-control` receives no warning and no indication
that enforcement is silently inactive.
**Required constraint**: The admin UI MUST surface a notice (or the About/Settings page MUST
document) that AC rule enforcement requires `wpb-access-control` to be active. This notice
MUST NOT be a PHP error — it MUST be a WP admin notice displayed to `manage_options` users.
The fail-open behavior itself is an accepted architectural decision (DEC-PERM-CB) and MUST NOT
be changed to deny-by-default without a new governed decision.

---

### LOW-01 — User ID exposure in REST responses

**Severity**: LOW
**Location**: All endpoints returning `created_by` / `updated_by`
**Description**: WP user IDs are included in override records returned to the client. Since
all read endpoints are gated behind `manage_options`, this is acceptable within the trust
boundary. Record for awareness.
**Constraint**: `created_by` and `updated_by` MUST NOT be included in any endpoint that does
not require `manage_options`.

---

### LOW-02 — Orphan override rows accumulate silently

**Severity**: LOW
**Location**: `{prefix}acrossai_abilities_overwrite` table
**Description**: When a plugin deregisters an ability, its override row persists. The row is
not visible in the table UI (registry is source of truth), does not affect access control,
and contains no credentials. However, rows for removed abilities could confuse future
administrators if the ability is re-registered with a different provider.
**Constraint**: Document the orphan-row behavior in admin UI inline help or tooltip. Schedule
a cleanup task in a future maintenance spec.

---

### LOW-03 — `before_save` hook consumers may corrupt field types

**Severity**: LOW
**Location**: `acrossai_abilities_sitewide_before_save` hook
**Description**: The plan confirms `after_save` receives sanitized fields (SEC-02), but
`before_save` runs before the DB write and after sanitization. A third-party filter on
`before_save` that modifies `$fields` could introduce wrong types (e.g., string `"true"`
instead of int `1`), bypassing the PHP bool→int cast and causing BerlinDB to receive
format-mismatched values (D2 risk).
**Constraint**: Immediately before the BerlinDB call, re-apply PHP bool→int cast and
allowlist check on all 8 fields regardless of whether `before_save` is hooked. This must be
the final transformation before the DB call.

---

### LOW-04 — No explicit maximum slug length enforcement

**Severity**: LOW
**Location**: `AcrossAI_Sanitizer::sanitize_ability_slug()` + bulk endpoint
**Description**: Ability slugs are URL parameters and bulk array elements. No maximum length
constraint is specified in the contract or plan. In a bulk request with 100 slugs, extremely
long slug strings could produce excess memory consumption during sanitization or DB operations.
**Constraint**: `sanitize_ability_slug()` MUST enforce a maximum slug length (recommended
≤ 255 characters, matching the DB column size). Slugs exceeding this limit MUST be rejected
with a `400 Bad Request` response, not silently truncated.
**Current behavior (2026-05-17)**: `sanitize_ability_slug()` calls `substr( $slug, 0, 255 )` —
slugs > 255 chars are silently truncated, not rejected. A `validate_callback` returning
`WP_Error( 'rest_invalid_param', ..., 400 )` is required on each `{slug}` URL parameter in
all sub-controllers. **Remediation tracked as ST-01 in tasks.md (MEDIUM — must fix before merge)**.

---

### INFO-01 — `GET /sitewide/mcp-servers` correctly gated behind `manage_options`

**Severity**: INFO
**Description**: MCP server IDs could reveal infrastructure topology. The endpoint is correctly
protected by the shared `check_permission()` gate. No action required.

---

### INFO-02 — BerlinDB unlimited query uses `number => 0` (not `-1`)

**Severity**: INFO
**Description**: `absint(-1) = 1` in BerlinDB, so "select all" queries MUST pass `number => 0`.
Already documented as BUG-BERLINDB-UNLIMITED (B1 in memory-synthesis.md). No security
implication; recorded here for implementation cross-reference.

---

### INFO-03 — Registry is read-only; plugin owns only override data

**Severity**: INFO
**Description**: The plugin never writes to the WordPress Abilities Registry. Override data
lives exclusively in `{prefix}acrossai_abilities_overwrite`. This clean separation limits the
blast radius of any implementation defect to override data only — no registry corruption is
possible via this feature's code paths.

---

## Open Questions

| ID | Question | Impact | Status |
|---|---|---|---|
| Q-01 | Is `{prefix}acrossai_abilities_overwrite` created with `$wpdb->prefix` (per-blog) or `$wpdb->base_prefix` (network)? Drives MEDIUM-02. | Data isolation across multisite blogs | **RESOLVED (2026-05-17)** — `AcrossAI_Sitewide_Table` uses `$wpdb->prefix` (per-blog); `$global = false` explicit; option stored per-blog via `update_option()`. |
| Q-02 | Does `AcrossAI_Sanitizer::sanitize_ability_slug()` preserve the `/` namespace separator in slugs like `my-plugin/read-posts`? `sanitize_key()` strips slashes and is not usable alone. | Slug look-up correctness + SEC-01 coverage | **RESOLVED (2026-05-17)** — Regex `[^a-zA-Z0-9\-_\/]` preserves `/`; `sanitize_key()` not used alone. |
| Q-03 | Is `acrossai_abilities_sitewide_rest_response` documented with a type contract for third-party filter consumers? | Client-side deserialization safety | **RESOLVED (2026-05-17)** — Inline `@param` PHPDoc exists in both applying controllers (Override and Abilities); `(array)` cast enforced on return. No separate contract doc; acceptable given `manage_options` gate. |
| Q-04 | What is the maximum length enforced by `sanitize_ability_slug()`? Should match the DB column definition. | LOW-04 / bulk DoS surface | **RESOLVED (2026-05-17)** — `AcrossAI_Sanitizer::validate_ability_slug()` added (ST-01); returns `WP_Error('rest_invalid_param', ..., ['status'=>400])` when `mb_strlen($slug) > 255`. Wired as `validate_callback` on all `{slug}` URL params in Abilities, Override, and Bulk controllers. Bulk `slugs` items also enforce `'maxLength' => 255` in route schema. |
| Q-05 | Is an admin notice shown when `wpb-access-control` is absent and AC rules are therefore not enforced? | MEDIUM-03 deployer awareness | **RESOLVED (2026-05-17)** — `AcrossAI_Sitewide_Access_Control::maybe_show_library_notice()` implemented and wired to `admin_notices` via `Main.php:288`. Uses `wp_admin_notice()`; gated by `current_user_can('manage_options')` and `$this->is_available()`. |
