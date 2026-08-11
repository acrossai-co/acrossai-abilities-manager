# Ability Contracts: Site introspection read endpoints

Every ability uses the same permission callback verbatim:

```php
'permission_callback' => static function (): bool {
    return current_user_can( 'manage_options' );
},
```

Every ability declares `readonly: true, idempotent: true, destructive: false` in annotations, and `show_in_rest: true, mcp: { public: false, type: 'tool' }` in meta.

---

## 1. `acrossai/get-wp-version`

**Category**: `acrossai-abilities-manager-core`
**Input**: `{}` (no properties, `additionalProperties: false`).
**Output**:
```json
{ "success": bool, "version": string, "is_multisite": bool, "message": string }
```

## 2. `acrossai/get-db-prefix`

**Category**: `acrossai-abilities-manager-db`
**Input**: `{}`.
**Output**:
```json
{ "success": bool, "prefix": string, "base_prefix": string, "message": string }
```

## 3. `acrossai/get-wp-config-constant`

**Category**: `acrossai-abilities-manager-files`
**Input**:
```json
{
  "type": "object",
  "properties": { "constant": { "type": "string", "minLength": 1 } },
  "required": ["constant"],
  "additionalProperties": false
}
```
**Output**:
```json
{
  "type": "object",
  "properties": {
    "success":  { "type": "boolean" },
    "constant": { "type": "string" },
    "defined":  { "type": "boolean" },
    "value":    { "type": ["string","integer","number","boolean","null"] },
    "blocked_reason": { "type": "string" },
    "message":  { "type": "string" }
  },
  "required": ["success"],
  "additionalProperties": false
}
```
`blocked_reason = "sensitive_constant"` when the caller requests any name in the block-list.

## 4. `acrossai/list-theme-mods`

**Category**: `acrossai-abilities-manager-themes`
**Input**: `{}`.
**Output**:
```json
{ "success": bool, "theme": string, "mods": object, "message": string }
```

## 5. `acrossai/list-rewrite-rules`

**Category**: `acrossai-abilities-manager-settings`
**Input**: `{}`.
**Output**:
```json
{ "success": bool, "rules": object, "count": integer, "message": string }
```

## 6. `acrossai/list-widgets`

**Category**: `acrossai-abilities-manager-widgets`
**Input**: `{}`.
**Output**:
```json
{
  "success":   bool,
  "sidebars":  { "<sidebar_id>": [ "<widget_instance_id>", ... ] },
  "widgets":   { "<widget_instance_id>": { "name": string, "classname": string, ... } },
  "message":   string
}
```

## 7. `acrossai/list-sidebars`

**Category**: `acrossai-abilities-manager-widgets`
**Input**: `{}`.
**Output**:
```json
{
  "success":  bool,
  "sidebars": [
    {
      "id":            string,
      "name":          string,
      "description":   string,
      "before_widget": string,
      "after_widget":  string,
      "before_title":  string,
      "after_title":   string
    }
  ],
  "message": string
}
```

## 8. `acrossai/list-image-sizes`

**Category**: `acrossai-abilities-manager-media`
**Input**: `{}`.
**Output**:
```json
{
  "success": bool,
  "sizes":   [
    { "name": string, "width": integer, "height": integer, "crop": bool }
  ],
  "message": string
}
```

## 9. `acrossai/get-comment-count`

**Category**: `acrossai-abilities-manager-comments`
**Input**:
```json
{
  "type": "object",
  "properties": { "post_id": { "type": "integer", "minimum": 0 } },
  "additionalProperties": false
}
```
(default `post_id = 0` means site-wide.)

**Output**:
```json
{
  "success": bool,
  "counts": {
    "approved":     integer,
    "moderated":    integer,
    "spam":         integer,
    "trash":        integer,
    "post-trashed": integer,
    "total_comments": integer
  },
  "message": string
}
```

## 10. `acrossai/get-maintenance-mode-status`

**Category**: `acrossai-abilities-manager-health`
**Input**: `{}`.
**Output**:
```json
{
  "success":   bool,
  "active":    bool,
  "since":     integer,
  "is_stale":  bool,
  "message":   string
}
```
`since` and `is_stale` present only when `active: true`.

## 11. `acrossai/test-wp-cron`

**Category**: `acrossai-abilities-manager-cron`
**Input**: `{}`.
**Output**:
```json
{
  "success":         bool,
  "reachable":       bool,
  "disable_wp_cron": bool,
  "message":         string
}
```

## Contract test hooks

Each contract is validated at test time by calling `wp_get_ability( 'acrossai/<slug>' )->execute( $input )` and asserting the returned array's shape matches the output schema. Guardrail tests: blocked-constant lookup (returns `blocked_reason: "sensitive_constant"`), stale-maintenance detection (returns `is_stale: true` when the marker timestamp is old), and cron-test HTTP failure (returns `reachable: false` when the `pre_http_request` filter forces a `WP_Error`).
