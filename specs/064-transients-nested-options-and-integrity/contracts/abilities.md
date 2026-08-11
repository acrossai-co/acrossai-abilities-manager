# Ability Contracts: Transient CRUD, nested option access, plugin lifecycle & checksum integrity

Every ability uses the same permission callback verbatim:

```php
'permission_callback' => static function (): bool {
    return current_user_can( 'manage_options' );
},
```

Meta shape: `show_in_rest: true, mcp: { public: false, type: 'tool' }`, plus a per-ability `sub_group` (`cache | options | posts | plugins | core`).

---

## Cache (4)

### 1. `acrossai/get-transient`

`readonly: true, idempotent: true, destructive: false`

**Input**:
```json
{
  "type": "object",
  "properties": { "key": { "type": "string", "minLength": 1 }, "site": { "type": "boolean", "default": false } },
  "required": ["key"],
  "additionalProperties": false
}
```

**Output**:
```json
{ "success": bool, "exists": bool, "value": any, "expires_at": integer|null, "message": string }
```

### 2. `acrossai/list-transients`

`readonly: true, idempotent: true, destructive: false`

**Input**:
```json
{
  "type": "object",
  "properties": {
    "search":          { "type": "string" },
    "limit":           { "type": "integer", "minimum": 1, "maximum": 500, "default": 100 },
    "offset":          { "type": "integer", "minimum": 0, "default": 0 },
    "site_only":       { "type": "boolean", "default": false },
    "include_expired": { "type": "boolean", "default": true }
  },
  "additionalProperties": false
}
```

**Output**:
```json
{
  "success": bool,
  "transients": [
    { "name": string, "expires_at": integer|null, "is_site": bool, "is_expired": bool }
  ],
  "count": integer,
  "total": integer
}
```

### 3. `acrossai/delete-transient`

`readonly: false, idempotent: true, destructive: true`

**Input**:
```json
{
  "type": "object",
  "properties": { "key": { "type": "string", "minLength": 1 }, "site": { "type": "boolean", "default": false } },
  "required": ["key"],
  "additionalProperties": false
}
```

**Output**: `{ success, deleted: bool, message }`

### 4. `acrossai/delete-expired-transients`

`readonly: false, idempotent: true, destructive: true`

**Input**: `{}`.
**Output**: `{ success, deleted: integer, message }`

---

## Options (2)

### 5. `acrossai/get-nested-option-value`

`readonly: true, idempotent: true, destructive: false`

**Input**:
```json
{
  "type": "object",
  "properties": {
    "option": { "type": "string", "minLength": 1 },
    "path":   { "type": "array", "items": { "type": "string" }, "minItems": 1 }
  },
  "required": ["option", "path"],
  "additionalProperties": false
}
```

**Output**: `{ success, exists: bool, value: any, message }`

### 6. `acrossai/patch-option-value`

`readonly: false, idempotent: false, destructive: true`

**Input**:
```json
{
  "type": "object",
  "properties": {
    "option":    { "type": "string", "minLength": 1 },
    "operation": { "type": "string", "enum": ["insert", "update", "delete"] },
    "path":      { "type": "array", "items": { "type": "string" }, "minItems": 1 },
    "value":     {}
  },
  "required": ["option", "operation", "path"],
  "additionalProperties": false
}
```

**Output**: `{ success, message, blocked_reason: string }`

`blocked_reason` values: `"blocked_option"`, `"non_traversable_intermediate"`.

---

## Content (1)

### 7. `acrossai/add-post-meta`

`readonly: false, idempotent: false, destructive: false` (append is additive, not destructive)

**Input** (mirrors `Update_Post_Meta.php` with an added `unique` flag):
```json
{
  "type": "object",
  "properties": {
    "post_id":    { "type": "integer", "minimum": 1 },
    "key":        { "type": "string" },
    "meta_key":   { "type": "string" },
    "value":      {},
    "meta_value": {},
    "unique":     { "type": "boolean", "default": false }
  },
  "allOf": [
    { "required": ["post_id"] },
    { "anyOf": [ { "required": ["key"] }, { "required": ["meta_key"] } ] }
  ],
  "additionalProperties": false
}
```

**Output**: `{ success, meta_id: integer|false, message }`

---

## Plugins (3)

### 8. `acrossai/search-wp-plugin-directory`

`readonly: true, idempotent: true, destructive: false`

**Input**:
```json
{
  "type": "object",
  "properties": {
    "query":    { "type": "string", "minLength": 1 },
    "per_page": { "type": "integer", "minimum": 1, "maximum": 100, "default": 10 },
    "page":     { "type": "integer", "minimum": 1, "default": 1 }
  },
  "required": ["query"],
  "additionalProperties": false
}
```

**Output**:
```json
{
  "success": bool,
  "plugins": [
    {
      "slug": string, "name": string, "short_description": string,
      "rating": integer, "active_installs": integer,
      "homepage": string, "download_link": string
    }
  ],
  "info": { "page": integer, "pages": integer, "results": integer },
  "message": string
}
```

### 9. `acrossai/uninstall-plugin`

`readonly: false, idempotent: true, destructive: true`

**Input**:
```json
{
  "type": "object",
  "properties": {
    "plugin":      { "type": "string", "minLength": 1 },
    "delete_data": { "type": "boolean", "default": true }
  },
  "required": ["plugin"],
  "additionalProperties": false
}
```

**Output**: `{ success, uninstalled: bool, message, blocked_reason: string }`

`blocked_reason` values: `"plugin_active"`, `"file_mods_disallowed"`, `"plugin_not_found"`.

### 10. `acrossai/verify-plugin-checksums`

`readonly: true, idempotent: true, destructive: false`

**Input**:
```json
{
  "type": "object",
  "properties": {
    "plugin": { "type": "string", "minLength": 1 },
    "strict": { "type": "boolean", "default": false }
  },
  "required": ["plugin"],
  "additionalProperties": false
}
```

**Output**:
```json
{
  "success": bool,
  "plugin": string, "version": string,
  "results": [ { "file": string, "expected": string, "actual": string, "status": string } ],
  "summary": { "total": integer, "ok": integer, "modified": integer, "missing": integer, "added": integer },
  "message": string
}
```

`status` enum: `"ok" | "modified" | "missing" | "added"`.

---

## Core (1)

### 11. `acrossai/verify-core-checksums`

`readonly: true, idempotent: true, destructive: false`

**Input**:
```json
{
  "type": "object",
  "properties": {
    "version":      { "type": "string" },
    "locale":       { "type": "string" },
    "include_root": { "type": "boolean", "default": false },
    "exclude":      { "type": "array", "items": { "type": "string" } }
  },
  "additionalProperties": false
}
```

Defaults: `version` = currently-installed core version; `locale` = `"en_US"`.

**Output**: identical shape to `verify-plugin-checksums` (with `plugin` field replaced by nothing — just `version` reported).

## Contract test hooks

Each contract is validated at test time by calling `wp_get_ability( 'acrossai/<slug>' )->execute( $input )` with golden-path and guardrail-tripping inputs and asserting shape + `blocked_reason` values. HTTP-facing abilities mock `pre_http_request` per feature 062/063 pattern.
