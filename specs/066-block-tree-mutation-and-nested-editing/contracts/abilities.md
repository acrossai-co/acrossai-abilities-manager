# Ability Contracts

**Feature**: 066-block-tree-mutation-and-nested-editing
**Date**: 2026-08-12

Every ability in this feature registers via `wp_register_ability( 'acrossai/<slug>', [...] )` and lives under the existing `acrossai-abilities-manager-content` category. All abilities share the response envelope defined in [data-model.md](../data-model.md#entity-response-envelope).

**Shared cross-cutting behaviour** (documented once here, applied by every ability):

- **Capability model**: every write ability requires `manage_options` AND `edit_posts` globally, plus `edit_post($post_id)` per-post. `Get_Post_Blocks` swaps `edit_post` for `read_post`.
- **Post-type gate**: writes refuse on `revision`, `nav_menu_item`, `custom_css`, `customize_changeset`, `oembed_cache`, `user_request`; also refuse any post type whose registration excludes editor UI, REST exposure, AND public access.
- **Round-trip**: writes flow through `parse_blocks( get_post()->post_content )` → mutate in memory → `serialize_blocks()` → `wp_update_post( [ 'ID' => $post_id, 'post_content' => $new_content ] )`.
- **Attribute validation**: soft — validates against the registered block type when known; permits unknown types.
- **On error**: returns `{ success: false, post_id, message, error_code }` with no payload keys.

---

## 1. `acrossai/get-post-blocks`

**Purpose**: Return the parsed block tree of a post with each block annotated with its canonical path.

**Type**: read · idempotent · non-destructive

### Input schema

```json
{
  "type": "object",
  "required": ["post_id"],
  "properties": {
    "post_id": { "type": "integer", "minimum": 1 }
  }
}
```

### Output schema (success)

```json
{
  "type": "object",
  "required": ["success", "post_id", "blocks", "message"],
  "properties": {
    "success":  { "type": "boolean", "const": true },
    "post_id":  { "type": "integer" },
    "blocks":   { "type": "array", "items": { "$ref": "#/definitions/Block" } },
    "total":    { "type": "integer", "description": "Total blocks across all nesting levels" },
    "message":  { "type": "string" }
  },
  "definitions": {
    "Block": {
      "type": "object",
      "required": ["name", "attrs", "innerBlocks", "innerHTML", "path"],
      "properties": {
        "name":         { "type": "string", "pattern": "^[A-Za-z0-9_-]+/[A-Za-z0-9_-]+$" },
        "attrs":        { "type": "object" },
        "innerBlocks":  { "type": "array", "items": { "$ref": "#/definitions/Block" } },
        "innerHTML":    { "type": "string" },
        "innerContent": { "type": "array" },
        "path":         { "type": "array", "items": { "type": "integer", "minimum": 0 } }
      }
    }
  }
}
```

### Error cases

| `error_code` | Cause |
|---|---|
| `post_not_found` | `post_id` does not resolve |
| `post_type_forbidden` | Post type is internal or non-editor-compatible |
| `insufficient_capability` | Caller lacks `manage_options` / `edit_posts` / `read_post($post_id)` |

---

## 2. `acrossai/add-block`

**Purpose**: Insert a new block at a parent path + sibling index (append if index ≥ current sibling count).

**Type**: write · non-idempotent · destructive

### Input schema

```json
{
  "type": "object",
  "required": ["post_id", "parent_path", "index", "block"],
  "properties": {
    "post_id":     { "type": "integer", "minimum": 1 },
    "parent_path": { "type": "array", "items": { "type": "integer", "minimum": 0 } },
    "index":       { "type": "integer", "minimum": 0 },
    "block": {
      "type": "object",
      "required": ["name"],
      "properties": {
        "name":         { "type": "string", "pattern": "^[A-Za-z0-9_-]+/[A-Za-z0-9_-]+$" },
        "attrs":        { "type": "object" },
        "innerHTML":    { "type": "string" },
        "innerBlocks":  { "type": "array" }
      }
    }
  }
}
```

### Output schema (success)

```json
{
  "type": "object",
  "required": ["success", "post_id", "block", "message"],
  "properties": {
    "success": { "const": true },
    "post_id": { "type": "integer" },
    "block":   { "type": "object", "description": "The inserted block with its new path" },
    "message": { "type": "string" }
  }
}
```

### Error cases

| `error_code` | Cause |
|---|---|
| `post_not_found` / `post_type_forbidden` / `insufficient_capability` | Standard |
| `invalid_block_name` | `name` fails the regex |
| `invalid_path` | `parent_path` does not resolve to an existing block (or root) |
| `invalid_attributes` | `attrs` fail the registered block type's schema |

---

## 3. `acrossai/remove-block`

**Purpose**: Remove the block at a specified path.

**Type**: write · non-idempotent · destructive

### Input schema

```json
{
  "type": "object",
  "required": ["post_id", "path"],
  "properties": {
    "post_id": { "type": "integer", "minimum": 1 },
    "path":    { "type": "array", "items": { "type": "integer", "minimum": 0 }, "minItems": 1 }
  }
}
```

### Output schema (success)

```json
{
  "type": "object",
  "required": ["success", "post_id", "removed", "message"],
  "properties": {
    "success": { "const": true },
    "post_id": { "type": "integer" },
    "removed": { "type": "object", "description": "The removed block (for undo/logging)" },
    "message": { "type": "string" }
  }
}
```

### Error cases

| `error_code` | Cause |
|---|---|
| `post_not_found` / `post_type_forbidden` / `insufficient_capability` | Standard |
| `invalid_path` | `path` empty or does not resolve |

---

## 4. `acrossai/update-post-block` (MODIFIED — extends existing ability)

**Purpose**: Update block attributes and/or inner HTML at any nesting depth. Backward compatible with existing input shapes.

**Type**: write · idempotent · destructive

### Input schema (additions in **bold**)

```json
{
  "type": "object",
  "required": ["post_id"],
  "properties": {
    "post_id":     { "type": "integer", "minimum": 1 },

    "path":        { "type": "array", "items": { "type": "integer", "minimum": 0 } },

    "block_index": { "type": "integer", "minimum": 0 },
    "block_name":  { "type": "string" },
    "occurrence":  { "type": "integer", "minimum": 0 },

    "attributes":  { "type": "object" },
    "inner_html":  { "type": "string" }
  }
}
```

### Target resolution priority

1. `path` present and non-empty → use `Block_Tree::replace_at_path`.
2. `block_index` present → existing top-level index logic (unchanged behaviour).
3. `block_name` (+ optional `occurrence`) → existing name-occurrence logic (unchanged).
4. None of the above → `invalid_input` error.

### Output schema (unchanged from existing)

```json
{
  "type": "object",
  "required": ["success", "post_id", "block", "message"],
  "properties": {
    "success": { "type": "boolean" },
    "post_id": { "type": "integer" },
    "block":   { "type": "object" },
    "message": { "type": "string" }
  }
}
```

### Error cases (additions)

| `error_code` | Cause |
|---|---|
| Existing codes | Unchanged |
| `invalid_path` | `path` does not resolve (new) |
| `invalid_attributes` | Attributes fail registered schema |

### Backward-compatibility guarantee

Any request that omits `path` behaves identically to the pre-066 version of this ability. No behaviour change for existing consumers.

---

## 5. `acrossai/move-block`

**Purpose**: Atomically move a block from a source path to a destination (parent path + sibling index). Refuses moves into the source's own subtree.

**Type**: write · non-idempotent · destructive

### Input schema

```json
{
  "type": "object",
  "required": ["post_id", "from_path", "to_parent_path", "to_index"],
  "properties": {
    "post_id":        { "type": "integer", "minimum": 1 },
    "from_path":      { "type": "array", "items": { "type": "integer", "minimum": 0 }, "minItems": 1 },
    "to_parent_path": { "type": "array", "items": { "type": "integer", "minimum": 0 } },
    "to_index":       { "type": "integer", "minimum": 0 }
  }
}
```

### Output schema (success)

```json
{
  "type": "object",
  "required": ["success", "post_id", "block", "previous_path", "message"],
  "properties": {
    "success":       { "const": true },
    "post_id":       { "type": "integer" },
    "block":         { "type": "object", "description": "The moved block at its new path" },
    "previous_path": { "type": "array", "items": { "type": "integer" } },
    "message":       { "type": "string" }
  }
}
```

### Error cases

| `error_code` | Cause |
|---|---|
| `post_not_found` / `post_type_forbidden` / `insufficient_capability` | Standard |
| `invalid_path` | `from_path` empty or does not resolve |
| `invalid_destination` | `to_parent_path` does not resolve |
| `descendant_destination` | `to_parent_path` starts with `from_path` (would create a cycle) |

---

## 6. `acrossai/duplicate-block`

**Purpose**: Deep-clone the block at a specified path (including all inner blocks) and insert the clone as the next sibling.

**Type**: write · non-idempotent · destructive

### Input schema

```json
{
  "type": "object",
  "required": ["post_id", "path"],
  "properties": {
    "post_id": { "type": "integer", "minimum": 1 },
    "path":    { "type": "array", "items": { "type": "integer", "minimum": 0 }, "minItems": 1 }
  }
}
```

### Output schema (success)

```json
{
  "type": "object",
  "required": ["success", "post_id", "block", "message"],
  "properties": {
    "success": { "const": true },
    "post_id": { "type": "integer" },
    "block":   { "type": "object", "description": "The cloned block with its new path" },
    "message": { "type": "string" }
  }
}
```

### Error cases

Standard set + `invalid_path`.

---

## 7. `acrossai/insert-pattern`

**Purpose**: Resolve a saved pattern by slug (across db / theme / plugin sources) and insert its constituent blocks at a specified parent path and sibling index.

**Type**: write · non-idempotent · destructive

### Input schema

```json
{
  "type": "object",
  "required": ["post_id", "parent_path", "index", "slug"],
  "properties": {
    "post_id":     { "type": "integer", "minimum": 1 },
    "parent_path": { "type": "array", "items": { "type": "integer", "minimum": 0 } },
    "index":       { "type": "integer", "minimum": 0 },
    "slug":        { "type": "string", "minLength": 1 },
    "source":      { "type": "string", "enum": ["db", "theme", "plugin"], "description": "Optional disambiguation when slug resolves in multiple sources" },
    "theme_type":  { "type": "string", "enum": ["parent", "child"], "description": "Optional — only relevant when source=theme" },
    "plugin_slug": { "type": "string", "description": "Optional — only relevant when source=plugin" }
  }
}
```

### Output schema (success)

```json
{
  "type": "object",
  "required": ["success", "post_id", "inserted_paths", "message"],
  "properties": {
    "success":        { "const": true },
    "post_id":        { "type": "integer" },
    "inserted_paths": {
      "type": "array",
      "items": { "type": "array", "items": { "type": "integer", "minimum": 0 } },
      "description": "Paths of every inserted block, in insertion order"
    },
    "count":          { "type": "integer", "description": "Number of blocks inserted" },
    "message":        { "type": "string" }
  }
}
```

### Error cases

| `error_code` | Cause |
|---|---|
| Standard set | — |
| `pattern_not_found` | Slug does not resolve in any source |
| `multiple_locations` | Slug resolves in more than one source and no `source` was specified |
| `invalid_path` | `parent_path` does not resolve |

---

## Contract summary

| Ability | Read/Write | Input requires | Output payload |
|---|---|---|---|
| `get-post-blocks` | R | `post_id` | `blocks` (annotated tree), `total` |
| `add-block` | W | `post_id`, `parent_path`, `index`, `block` | `block` (with new path) |
| `remove-block` | W | `post_id`, `path` | `removed` |
| `update-post-block` | W | `post_id` + one of {`path`, `block_index`, `block_name`} | `block` |
| `move-block` | W | `post_id`, `from_path`, `to_parent_path`, `to_index` | `block`, `previous_path` |
| `duplicate-block` | W | `post_id`, `path` | `block` |
| `insert-pattern` | W | `post_id`, `parent_path`, `index`, `slug` | `inserted_paths`, `count` |
