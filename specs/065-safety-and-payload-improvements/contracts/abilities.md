# Ability Contracts — modified abilities

Every ability listed below **retains** its existing name, category, and `permission_callback`. Only the input/output schemas and `execute()` behaviour change.

---

## `plugins/deactivate-plugin`

**Input schema (unchanged)** — takes `plugin: string`.

**Output schema (additive)** — new field `blocked_reason: string` when refused. Values: `"protected_plugin"` (new), plus any existing refusal values.

## `media/delete-media`

**Input schema (breaking)**:
```json
{
  "type": "object",
  "properties": {
    "id":      { "type": "integer", "minimum": 1 },
    "confirm": { "type": "boolean", "description": "Must be true to proceed" },
    "force":   { "type": "boolean", "default": false, "description": "Skip trash even when MEDIA_TRASH is defined" }
  },
  "required": ["id", "confirm"],
  "additionalProperties": false
}
```

**Output schema (breaking on `deleted` type)**:
```json
{
  "success": { "type": "boolean" },
  "deleted": { "type": "string", "enum": ["deleted", "trashed"] },
  "media":   { "type": "object" },
  "message": { "type": "string" },
  "blocked_reason": { "type": "string" }
}
```

`blocked_reason` values on refusal: `"confirmation_required"`, existing not-found reasons.

## `media/update-media`

**Input schema (unchanged)**.

**Output schema (additive)**:
```json
{
  "success": bool,
  "updated": { "type": "array", "items": { "type": "string" } },
  "media":   object,
  "message": string
}
```

`updated` values are field names from `["title", "caption", "description", "alt_text"]` in the order processed. Empty array when no update field was passed.

## `media/list-media`

**Input schema (unchanged)**.

**Output schema (unchanged)**. Behaviour change: `search` now also matches `_wp_attachment_image_alt` postmeta.

## `content/get-post`

**Input schema (unchanged)**.

**Output schema (additive)**:
```json
{
  "success":  bool,
  "post":     object,  // unchanged — raw get_post(ARRAY_A)
  "terms":    { "type": "object", "additionalProperties": { "type": "array" } },
  "meta":     { "type": "object" },
  "featured_image": { "type": ["object", "null"] },
  "permalink": string,
  "edit_link": string,
  "author":   { "type": "object", "properties": { "id": integer, "name": string } },
  "message":  string
}
```

`meta` skips protected keys (`_`-prefix or `is_protected_meta`) unless allow-listed by the `acrossai_allowed_protected_meta` filter.

## `content/update-post`

**Input schema (unchanged)**.

**Output schema (additive)**:
```json
{
  "success": bool,
  "id":      integer,
  "post":    object,
  "dropped_meta_keys": { "type": "array", "items": { "type": "string" } },
  "blocked_reason":    string,
  "message": string
}
```

`blocked_reason` values on refusal: `"non_writable_post_type"`, `"publish_cap_required"`, `"edit_others_posts_required"`, existing not-found value.

## `content/delete-post`

**Input schema (unchanged)**.

**Output schema (additive)**:
```json
{
  "success": bool,
  "id":      integer,
  "force":   bool,
  "suggested_redirect": {
    "type": "object",
    "properties": {
      "from": string,
      "to":   string
    }
  },
  "message": string
}
```

`suggested_redirect` present only when the deleted post was `publish` AND `force: true`.

## `file-manager/read-file`

**Input schema (unchanged)**.

**Output schema (additive/breaking)**:
```json
{
  "success":  bool,
  "content":  string,   // OMITTED when binary or file too large
  "path":     string,
  "size":     integer,
  "binary":   bool,     // NEW — true when non-UTF-8 detected
  "blocked_reason": string,
  "message":  string
}
```

`blocked_reason` values on refusal: `"protected_read"`, `"file_too_large"`, existing not-found / invalid-path.

## `file-manager/delete-file`

**Input schema (breaking)**:
```json
{
  "type": "object",
  "properties": {
    "path":    { "type": "string" },
    "confirm": { "type": "boolean" }
  },
  "required": ["path", "confirm"],
  "additionalProperties": false
}
```

**Output schema (additive)**:
```json
{
  "success":  bool,
  "backup":   { "type": ["string", "null"] },
  "message":  string,
  "blocked_reason": string
}
```

`blocked_reason` values on refusal: `"confirmation_required"`, `"protected_write"`, existing not-found / invalid-path.
