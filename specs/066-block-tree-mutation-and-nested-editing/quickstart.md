# Quickstart: Block Tree Mutation & Nested Editing

**Feature**: 066-block-tree-mutation-and-nested-editing
**Date**: 2026-08-12

End-to-end walkthrough for a client using the seven abilities together. This is the reference flow verification in [plan.md § Verification](./plan.md) is derived from.

---

## Scenario

Build the following block tree inside an empty post using nothing but the new/enhanced abilities:

```
core/columns
├─ core/column
│  ├─ core/heading  ("Left column heading")
│  └─ core/paragraph ("Left body copy")
└─ core/column
   └─ core/paragraph ("Right body copy")
```

Then verify the tree, duplicate a column, remove the duplicate, move a paragraph across columns, and read the final state.

---

## Setup

Create the target post via WP-CLI (or the admin) — post ID `42` used below.

```
wp post create --post_type=post --post_status=draft --post_title="Block tree demo" --porcelain
# → 42
```

At the start, `post_content` is empty, so `get-post-blocks` returns `blocks: []`.

---

## Step 1 — Insert the columns container at root

```json
Ability: blocks/add-block
Input:  { "post_id": 42, "parent_path": [], "index": 0,
          "block": { "name": "core/columns" } }
```

**Expected**: `success: true`, inserted `block.path = [0]`.

---

## Step 2 — Insert the two columns as children of `[0]`

```json
Ability: blocks/add-block
Input:  { "post_id": 42, "parent_path": [0], "index": 0,
          "block": { "name": "core/column" } }
```
→ `block.path = [0, 0]`

```json
Ability: blocks/add-block
Input:  { "post_id": 42, "parent_path": [0], "index": 1,
          "block": { "name": "core/column" } }
```
→ `block.path = [0, 1]`

---

## Step 3 — Populate the left column

```json
Ability: blocks/add-block
Input:  { "post_id": 42, "parent_path": [0, 0], "index": 0,
          "block": { "name": "core/heading",
                     "attrs": { "level": 2, "content": "Left column heading" } } }
```
→ `block.path = [0, 0, 0]`

```json
Ability: blocks/add-block
Input:  { "post_id": 42, "parent_path": [0, 0], "index": 1,
          "block": { "name": "core/paragraph",
                     "attrs": { "content": "Left body copy" } } }
```
→ `block.path = [0, 0, 1]`

---

## Step 4 — Populate the right column

```json
Ability: blocks/add-block
Input:  { "post_id": 42, "parent_path": [0, 1], "index": 0,
          "block": { "name": "core/paragraph",
                     "attrs": { "content": "Right body copy" } } }
```
→ `block.path = [0, 1, 0]`

---

## Step 5 — Read the tree to verify

```json
Ability: blocks/get-post-blocks
Input:  { "post_id": 42 }
```

**Expected** (elided innerHTML for readability):

```json
{
  "success": true,
  "post_id": 42,
  "total": 6,
  "blocks": [
    {
      "name": "core/columns", "path": [0],
      "innerBlocks": [
        {
          "name": "core/column", "path": [0, 0],
          "innerBlocks": [
            { "name": "core/heading",   "path": [0, 0, 0], "attrs": { "level": 2, "content": "Left column heading" } },
            { "name": "core/paragraph", "path": [0, 0, 1], "attrs": { "content": "Left body copy" } }
          ]
        },
        {
          "name": "core/column", "path": [0, 1],
          "innerBlocks": [
            { "name": "core/paragraph", "path": [0, 1, 0], "attrs": { "content": "Right body copy" } }
          ]
        }
      ]
    }
  ]
}
```

**SC-006 checkpoint**: two-column layout with 4 nested blocks built with 6 write calls + 1 read = 7 total. ✅

---

## Step 6 — Update a nested paragraph (nested `update-post-block`)

```json
Ability: blocks/update-post-block
Input:  { "post_id": 42, "path": [0, 0, 1],
          "attributes": { "content": "Left body copy (revised)" } }
```

**Expected**: `success: true`, `block.path = [0, 0, 1]`, `block.attrs.content = "Left body copy (revised)"`. Every other block untouched (verify with a second `get-post-blocks`).

---

## Step 7 — Duplicate the left column

```json
Ability: blocks/duplicate-block
Input:  { "post_id": 42, "path": [0, 0] }
```

**Expected**: clone of the left column (including its heading + paragraph) appears at `[0, 1]`; the former right column shifts to `[0, 2]`.

---

## Step 8 — Remove the duplicate

```json
Ability: blocks/remove-block
Input:  { "post_id": 42, "path": [0, 1] }
```

**Expected**: the tree returns to its pre-duplicate shape — the right column is back at `[0, 1]`.

---

## Step 9 — Move the right column's paragraph into the left column

```json
Ability: blocks/move-block
Input:  { "post_id": 42,
          "from_path": [0, 1, 0],
          "to_parent_path": [0, 0], "to_index": 2 }
```

**Expected**: the paragraph appears at `[0, 0, 2]` (after the heading and the original left paragraph); the right column at `[0, 1]` is now empty.

---

## Step 10 — Insert a pattern into the (now empty) right column

Assume a theme pattern with slug `my-theme/promo` exists.

```json
Ability: blocks/insert-pattern
Input:  { "post_id": 42,
          "parent_path": [0, 1], "index": 0,
          "slug": "my-theme/promo" }
```

**Expected**: `success: true`, `inserted_paths: [[0,1,0], [0,1,1], ...]` (one entry per block the pattern contains); `count` matches the pattern's block count.

---

## Step 11 — Backward-compat check on `update-post-block`

Verify a pre-066 consumer call still works — pass ONLY `block_index`, no `path`:

```json
Ability: blocks/update-post-block
Input:  { "post_id": 42, "block_index": 0,
          "attributes": { "align": "wide" } }
```

**Expected**: the top-level `core/columns` at `[0]` gets `attrs.align = "wide"`. Every other block untouched. This exact request shape worked before 066 and MUST continue to work identically (SC-003).

---

## Round-trip fidelity check (SC-004)

For a canonical post (e.g. the one built above at step 5):

1. `get-post-blocks` → capture tree.
2. `update-post-block` with `path: [0]` and empty `attributes: {}` — this is a "no-op" write that goes through the full parse-serialize round-trip.
3. Compare stored `post_content` before/after. Byte-identical for 95%+ of realistic content (known parser-normalisation edge cases excluded).

---

## Failure paths worth exercising manually

| Scenario | Expected error_code |
|---|---|
| `get-post-blocks` with `post_id = 999999` | `post_not_found` |
| `add-block` with invalid `name: "notaslug"` | `invalid_block_name` |
| `remove-block` with `path: [0, 5, 3]` when only 2 top-level blocks exist | `invalid_path` |
| `move-block` from `[0]` to inside `[0, 0]` (destination inside source) | `descendant_destination` |
| `insert-pattern` with a slug that exists in both db and active theme | `multiple_locations` |
| Any write ability called by a subscriber user | `insufficient_capability` |
| `update-post-block` against a `revision` post ID | `post_type_forbidden` |
