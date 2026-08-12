# Data Model: Block Tree Mutation & Nested Editing

**Feature**: 066-block-tree-mutation-and-nested-editing
**Date**: 2026-08-12

This feature introduces **no new persistent storage**. All data lives in existing WordPress storage (`wp_posts.post_content`) and is manipulated in-memory as parsed block trees. This document describes the **logical entities** that appear in ability inputs and outputs.

---

## Entity: `Post`

The WordPress post whose content is being read or mutated.

| Field | Type | Notes |
|---|---|---|
| `post_id` | `int` (≥ 1) | Primary identifier — matches `wp_posts.ID` |
| `post_content` | `string` | The raw block-comment-annotated HTML stored in the database (read/write side effect only — never returned to callers directly) |
| `post_type` | `string` | Used for the editability whitelist (`Block_Tree::assert_post_type_editable`) |

**Source of truth**: `get_post( $post_id )` (WordPress core).

**Validation**:
- `$post_id` must resolve to an existing post via `get_post()`.
- `$post_type` must NOT be one of the internal types: `revision`, `nav_menu_item`, `custom_css`, `customize_changeset`, `oembed_cache`, `user_request`.
- `$post_type` must have `show_in_rest = true` OR `show_ui = true` OR `public = true` (matches existing `Update_Post_Block:127-135`).

---

## Entity: `Block`

A single node in a Post's parsed block tree. Corresponds exactly to one element in the array returned by `parse_blocks()`.

| Field | Type | Notes |
|---|---|---|
| `name` | `string` | Block namespace/name — e.g. `core/paragraph`. Must match `^[A-Za-z0-9_-]+\/[A-Za-z0-9_-]+$` (extracted regex from `Update_Post_Block:160`) |
| `attrs` | `array<string, mixed>` | Block attributes — validated against the registered block type's `attributes` schema when the type is registered (soft-fail if not) |
| `innerBlocks` | `Block[]` | Ordered list of child blocks — recursive |
| `innerHTML` | `string` | Rendered HTML between the opening and closing block comment |
| `innerContent` | `array<string\|null>` | Interleaved HTML fragments and inner-block placeholders (`null` marks a child-block position). Matches core `parse_blocks` shape. |

**Additional field appended by `Get_Post_Blocks` output** (not part of stored data):

| Field | Type | Notes |
|---|---|---|
| `path` | `int[]` | Canonical path of this block within its Post's tree — pre-computed by `Block_Tree::annotate_with_paths()` |

**Validation**:
- On input to write abilities: `name` must pass the regex; `attrs` must satisfy the registered schema when known.
- `innerContent` is preserved verbatim on writes that only modify `attrs` (matches `Update_Post_Block:186` behaviour).

**State transitions**:
- Created by `Add_Block` / `Insert_Pattern`.
- Modified in place by `Update_Post_Block` (with `path`).
- Moved by `Move_Block`.
- Deep-cloned by `Duplicate_Block`.
- Destroyed by `Remove_Block`.

---

## Entity: `Block path`

A canonical, unambiguous address for one `Block` within a `Post`'s tree.

| Property | Value |
|---|---|
| **Type** | `int[]` (ordered list of non-negative integers) |
| **Root** | `[]` — refers to the top-level list; not a block itself |
| **Semantics** | `[i]` = i-th top-level block. `[i, j]` = j-th child of the i-th top-level block. Depth is `count($path)`. |
| **Validation** | Every element `>= 0`; every element must resolve to an existing index at that level (except where an "insert-at-one-past-the-end" append is explicitly allowed) |

**Operations** (all pure, from `Block_Tree`):
- `get_at_path( array $blocks, array $path ): ?array` — return the Block at path or `null`.
- `insert_at_path( array &$blocks, array $parent_path, int $index, array $new_block ): bool` — insert as child of `parent_path` at `$index`; append if `$index >= count(children)`.
- `remove_at_path( array &$blocks, array $path ): ?array` — remove and return the removed Block or `null`.
- `replace_at_path( array &$blocks, array $path, array $new_block ): bool` — swap in place.
- `move( array &$blocks, array $from, array $to_parent, int $to_index ): true|WP_Error` — atomic move; returns error if `$to_parent` starts with `$from` (descendant guard).
- `annotate_with_paths( array $blocks, array $prefix = [] ): array` — recursive; returns a copy with `path` added to every node.

**Edge cases**:
- Path `[]` cannot be passed to `get_at_path` — it identifies the root array itself, not a Block.
- `insert_at_path` accepts `[]` as `parent_path` — insert into the top-level list.
- `remove_at_path([])` is undefined behaviour and MUST be rejected.

---

## Entity: `Block pattern`

A named collection of blocks resolvable by slug from one of three sources.

| Field | Type | Notes |
|---|---|---|
| `slug` | `string` | Pattern identifier |
| `source` | `string` (`db` \| `theme` \| `plugin`) | Which registry to resolve against |
| `theme_type` | `string?` | If `source=theme`, distinguishes parent/child theme |
| `plugin_slug` | `string?` | If `source=plugin`, identifies which plugin owns the pattern |
| `blocks` | `Block[]` | The parsed pattern content — inserted as children of the target parent path when the pattern is applied |

**Resolution**:
- If `slug` is unambiguous across sources → auto-resolve.
- If `slug` resolves in multiple sources without an explicit `source` → return error with `error_code=multiple_locations` (matches existing `Read_Block_Pattern` behaviour per feature spec assumption 4).
- If `slug` resolves nowhere → return error with a "unknown pattern slug" message.

---

## Entity: `Block type` (external / read-only)

The runtime registration for a given `Block.name` in the site's `WP_Block_Type_Registry`. Used for attribute-schema validation only.

| Field | Type | Notes |
|---|---|---|
| `name` | `string` | Namespace/name |
| `attributes` | `array` | Schema keyed by attribute name; each has `type`, optional `default`, optional `source` |

**Access**: `Block_Info::get_block( $name )` (existing utility at `includes/Abilities/Utilities/Block_Info.php:56`).

**Validation policy**: Soft — if not registered on this site, block writes proceed without attribute validation (see research R4). Never mutated by this feature.

---

## Entity: `Response envelope`

The shape returned by every ability in this feature.

| Field | Type | When present | Notes |
|---|---|---|---|
| `success` | `boolean` | always | `true` on completion, `false` on error |
| `post_id` | `int` | always | Echoes the input `post_id` for correlation |
| `message` | `string` | always | Human-readable summary |
| `error_code` | `string?` | on `success=false` | Machine-readable code — e.g. `invalid_path`, `multiple_locations`, `insufficient_capability` |
| `blocks` | `Block[]` | `Get_Post_Blocks` success only | Annotated tree |
| `block` | `Block` | write abilities success only | The affected block, including its `path` after mutation |
| `previous_path` | `int[]` | `Move_Block` success only | Where the block used to live |
| `inserted_paths` | `int[][]` | `Insert_Pattern` success only | Paths of every inserted block, in insertion order |

**Rule**: When `success=false`, none of the payload keys (`blocks`, `block`, `previous_path`, `inserted_paths`) are present.

---

## Relationships

```
Post 1 ── 1 tree of Blocks
Block 1 ── 0..N innerBlocks (Blocks)  [recursive]
Block 1 ── 1 Block path                [computed per read]
Block 1 ── 0..1 Block type             [registry lookup for validation]
Block pattern 1 ── N Blocks            [expanded on insert]
```

No relational storage is introduced. `Post` and `Block type` are external entities WordPress core owns. `Block`, `Block path`, `Block pattern`, and `Response envelope` are all in-memory shapes derived from or destined for `Post.post_content`.
