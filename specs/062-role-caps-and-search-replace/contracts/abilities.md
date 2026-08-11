# Ability Contracts: Role & capability CRUD + site-wide DB search-replace

Each contract below describes the public interface the AI client / REST caller sees. Interface = `input_schema` + `output_schema` + `permission_callback` + destructive/idempotent annotations. These are the values that will populate `wp_register_ability()` when the classes ship.

Every ability uses the same permission callback verbatim:

```php
'permission_callback' => static function (): bool {
    return current_user_can( 'manage_options' );
},
```

Every ability uses the same category-agnostic `meta` prefix:

```php
'meta' => array(
    'acrossai'     => array(
        'tab_group'       => 'core',
        'sub_group'       => '<users|db>',
        'sub_group_label' => __( '<Users|Database>', 'acrossai-abilities-manager' ),
    ),
    'show_in_rest' => true,
    'mcp'          => array(
        'public' => false,
        'type'   => 'tool',
    ),
    'annotations'  => array( /* per-ability, see below */ ),
),
```

---

## 1. `acrossai/add-role-capability`

**Category**: `acrossai-abilities-manager-users`
**Annotations**: `readonly: false, destructive: true, idempotent: true`

**Input schema**:
```json
{
  "type": "object",
  "properties": {
    "role":       { "type": "string", "minLength": 1 },
    "capability": { "type": "string", "minLength": 1 },
    "grant":      { "type": "boolean", "default": true }
  },
  "required": ["role", "capability"],
  "additionalProperties": false
}
```

**Output schema**:
```json
{
  "type": "object",
  "properties": {
    "success": { "type": "boolean" },
    "role":    { "type": "string" },
    "capability": { "type": "string" },
    "message": { "type": "string" }
  },
  "required": ["success"],
  "additionalProperties": false
}
```

---

## 2. `acrossai/remove-role-capability`

**Category**: `acrossai-abilities-manager-users`
**Annotations**: `readonly: false, destructive: true, idempotent: true`

**Input schema**:
```json
{
  "type": "object",
  "properties": {
    "role":       { "type": "string", "minLength": 1 },
    "capability": { "type": "string", "minLength": 1 }
  },
  "required": ["role", "capability"],
  "additionalProperties": false
}
```

**Output schema**: identical shape to (1) plus optional `"blocked_reason"` field naming the guard that refused (e.g. `"core_admin_cap"`).

---

## 3. `acrossai/create-role`

**Category**: `acrossai-abilities-manager-users`
**Annotations**: `readonly: false, destructive: true, idempotent: false`

**Input schema**:
```json
{
  "type": "object",
  "properties": {
    "role":         { "type": "string", "minLength": 1 },
    "display_name": { "type": "string", "minLength": 1 },
    "clone_from":   { "type": "string" }
  },
  "required": ["role", "display_name"],
  "additionalProperties": false
}
```

**Output schema**:
```json
{
  "type": "object",
  "properties": {
    "success":       { "type": "boolean" },
    "role":          { "type": "string" },
    "capabilities":  { "type": "object", "additionalProperties": { "type": "boolean" } },
    "message":       { "type": "string" }
  },
  "required": ["success"],
  "additionalProperties": false
}
```

---

## 4. `acrossai/delete-role`

**Category**: `acrossai-abilities-manager-users`
**Annotations**: `readonly: false, destructive: true, idempotent: false`

**Input schema**:
```json
{
  "type": "object",
  "properties": {
    "role": { "type": "string", "minLength": 1 }
  },
  "required": ["role"],
  "additionalProperties": false
}
```

**Output schema**:
```json
{
  "type": "object",
  "properties": {
    "success":         { "type": "boolean" },
    "role":            { "type": "string" },
    "user_count":      { "type": "integer" },
    "blocked_reason":  { "type": "string" },
    "message":         { "type": "string" }
  },
  "required": ["success"],
  "additionalProperties": false
}
```

`blocked_reason` populated when refusing: values are `"default_role"` or `"role_has_users"`.

---

## 5. `acrossai/reset-role`

**Category**: `acrossai-abilities-manager-users`
**Annotations**: `readonly: false, destructive: true, idempotent: false`

**Input schema**:
```json
{
  "type": "object",
  "properties": {
    "role": { "type": "string", "enum": ["administrator", "editor", "author", "contributor", "subscriber"] }
  },
  "required": ["role"],
  "additionalProperties": false
}
```

**Output schema**:
```json
{
  "type": "object",
  "properties": {
    "success":                 { "type": "boolean" },
    "role":                    { "type": "string" },
    "restored_capabilities":   { "type": "object", "additionalProperties": { "type": "boolean" } },
    "message":                 { "type": "string" }
  },
  "required": ["success"],
  "additionalProperties": false
}
```

---

## 6. `acrossai/add-user-capability`

**Category**: `acrossai-abilities-manager-users`
**Annotations**: `readonly: false, destructive: true, idempotent: true`

**Input schema**:
```json
{
  "type": "object",
  "properties": {
    "user_id":    { "type": "integer", "minimum": 1 },
    "capability": { "type": "string", "minLength": 1 },
    "grant":      { "type": "boolean", "default": true }
  },
  "required": ["user_id", "capability"],
  "additionalProperties": false
}
```

**Output schema**:
```json
{
  "type": "object",
  "properties": {
    "success":    { "type": "boolean" },
    "user_id":    { "type": "integer" },
    "capability": { "type": "string" },
    "message":    { "type": "string" }
  },
  "required": ["success"],
  "additionalProperties": false
}
```

---

## 7. `acrossai/remove-user-capability`

**Category**: `acrossai-abilities-manager-users`
**Annotations**: `readonly: false, destructive: true, idempotent: true`

**Input schema**: identical to (6) minus the `grant` field.

**Output schema**: identical to (6) plus optional `"blocked_reason"` field. Value on refusal: `"last_admin_core_cap"`.

---

## 8. `acrossai/search-replace`

**Category**: `acrossai-abilities-manager-db`
**Annotations**: `readonly: false, destructive: true, idempotent: false` — but idempotent effectively becomes `true` when `dry_run: true` because the write path is not taken.

**Input schema**:
```json
{
  "type": "object",
  "properties": {
    "old":            { "type": "string", "minLength": 1 },
    "new":            { "type": "string" },
    "tables":         { "type": "array", "items": { "type": "string" } },
    "skip_tables":    { "type": "array", "items": { "type": "string" } },
    "skip_columns":   { "type": "array", "items": { "type": "string" } },
    "include_guids":  { "type": "boolean", "default": false },
    "all_tables":     { "type": "boolean", "default": false },
    "dry_run":        { "type": "boolean", "default": true }
  },
  "required": ["old", "new"],
  "additionalProperties": false
}
```

**Output schema**:
```json
{
  "type": "object",
  "properties": {
    "success":  { "type": "boolean" },
    "dry_run":  { "type": "boolean" },
    "results":  {
      "type": "array",
      "items": {
        "type": "object",
        "properties": {
          "table":    { "type": "string" },
          "column":   { "type": "string" },
          "matches":  { "type": "integer" },
          "replaced": { "type": "integer" }
        },
        "required": ["table", "column", "matches", "replaced"],
        "additionalProperties": false
      }
    },
    "summary": {
      "type": "object",
      "properties": {
        "tables_scanned": { "type": "integer" },
        "rows_matched":   { "type": "integer" },
        "rows_replaced":  { "type": "integer" }
      },
      "required": ["tables_scanned", "rows_matched", "rows_replaced"],
      "additionalProperties": false
    },
    "message":         { "type": "string" },
    "blocked_reason":  { "type": "string" }
  },
  "required": ["success"],
  "additionalProperties": false
}
```

`blocked_reason` populated when refusing: values are `"unknown_table"`, `"empty_old"`.

## Contract test hooks

Every one of the 8 contracts above is validated at test time by:

1. Calling `wp_get_ability( 'acrossai/<slug>' )->execute( $input )` with a golden-path payload and asserting the returned array validates against the output schema above (using a shared JSON-schema-validate helper if one exists in `tests/phpunit/`, or a hand-rolled assertion if not — decision deferred to `/speckit-tasks`).
2. Calling with an input that trips each documented guardrail and asserting `success: false` + the correct `blocked_reason` string.
