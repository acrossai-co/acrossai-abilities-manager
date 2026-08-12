# Research: Block Tree Mutation & Nested Editing

**Feature**: 066-block-tree-mutation-and-nested-editing
**Date**: 2026-08-12

Phase 0 resolves technical unknowns raised by the spec's Assumptions and by the "Constraints" line in Technical Context. Every decision is captured with a Decision / Rationale / Alternatives block.

---

## R1. Canonical block-path addressing scheme

**Decision**: Ordered array of non-negative integers (`int[]`). `[]` denotes root; `[i]` denotes the i-th top-level block; `[i, j]` denotes the j-th child of the i-th top-level block; and so on recursively.

**Rationale**:
- Unambiguous — `[0, 10]` vs `[0, 1, 0]` cannot be confused with each other, unlike dot-string notation ("0.10" vs "0.1.0").
- Trivial to validate — `is_array($path) && every(fn($x) => is_int($x) && $x >= 0)`.
- Native to JSON — clients pass the array as-is; no parsing.
- Native to PHP — `count($path)` gives depth; recursion by `$rest = array_slice($path, 1)` gives child-tree traversal.
- Matches how JSON tools reason about nested trees.

**Alternatives considered**:
- **Dot-string ("0.2.1")**: user-friendlier at low sibling counts but breaks past 10 siblings (indexes are visually ambiguous).
- **Name + occurrence + parent-path (hybrid)**: extends the existing `block_name + occurrence` scheme with a parent-path prefix. Backward compatibility gained is illusory — clients still learn a new input shape — and complexity is higher.

**Impact on API**: All tree-mutation abilities accept a `path` input of type `array<int>`. `insert` / `insert-pattern` / `move` additionally split `path` into `parent_path` + `index` for clarity ("into this parent, at this index").

---

## R2. Atomicity of `move`

**Decision**: Move is implemented as `remove-then-insert` on an in-memory tree copy. The `wp_update_post` write is a single atomic DB call, so callers never observe an intermediate state.

**Rationale**:
- WordPress `wp_update_post()` performs a single `UPDATE wp_posts SET post_content = ...` — either the whole new tree is persisted or nothing changes.
- Doing remove-then-insert on a PHP array copy in-memory means the source and destination paths are computed against a consistent snapshot (before applying either mutation to the DB).
- FR-013 requires atomicity of observable state, not atomicity of the internal PHP steps.

**Alternatives considered**:
- **Custom SQL transaction**: unnecessary — single-row `UPDATE` is already atomic at the row level; adding a transaction would only matter if we also updated a second table, which we do not.

**Impact on API**: Client sees either the new tree or the pre-move tree; no interleaved state is observable.

---

## R3. Move-to-descendant refusal

**Decision**: Refuse if the destination `parent_path` starts with the source `path`. Detect via `array_slice($destination_parent_path, 0, count($source_path)) === $source_path`.

**Rationale**:
- If a block at `[0, 1]` were moved into `[0, 1, 0]`, the moved block would become its own ancestor — a cycle that `serialize_blocks()` cannot represent and that would corrupt round-trip fidelity.
- The check is O(depth), cheap.

**Alternatives considered**:
- **Allow and let serializer fail**: leaks a low-level parser error to callers; violates FR-005 ("clear failure messages").

**Impact on API**: `move-block` fails with a clear "destination lies within source subtree" message before touching the DB.

---

## R4. Attribute-schema validation strategy

**Decision**: For each mutating write, look up the block type via `WP_Block_Type_Registry::get_instance()->get_registered($name)` (through the existing `Block_Info::get_block()` helper). If registered and its `attributes` schema is available, validate the incoming attributes against that schema. If not registered, log at `wp_debug_log` level and proceed (soft-fail).

**Rationale**:
- Matches Constitution §V "graceful degradation" — custom or plugin-supplied blocks may not be registered on this specific site (e.g., pattern imports from other sites); refusing them all would break real workflows.
- Matches the spec's FR-020 requirement ("degrade gracefully when the block type is not registered").
- Rejecting on schema mismatch prevents storing invalid attribute payloads that the block editor cannot render.

**Alternatives considered**:
- **Hard-fail on unregistered blocks**: too restrictive; would refuse legitimate content.
- **Validate only in `add-block` / `insert-pattern`**: creates inconsistent validation coverage between insert and update. Applying the check uniformly to every write is simpler and safer.

**Impact on API**: Client sees a clear validation error for attribute schema mismatches; unregistered blocks pass through unchanged.

---

## R5. Pattern-source resolution reuse

**Decision**: `Insert_Pattern` delegates slug-to-pattern resolution to whatever helper `includes/Abilities/Block/Read_Block_Pattern.php` uses today. If that helper is inline (not extracted), extract it into `includes/Abilities/Utilities/Pattern_Source_Resolver.php` as part of this feature (single-use extraction is fine when a second use materialises — matches Constitution §VI).

**Rationale**:
- Zero duplication of source-scanning logic (db `wp_block` CPT + theme `/patterns` + plugin `/patterns`).
- The existing helper already handles the "multiple sources" ambiguity case that FR-017 requires; reusing avoids re-implementing the ambiguity detection.

**Alternatives considered**:
- **Reimplement source scan in `Insert_Pattern`**: violates DRY; risks divergence in "multiple locations" detection.

**Impact on scope**: If extraction is needed, one additional utility class file. Detection happens during implementation, not now.

---

## R6. Round-trip fidelity of `post_content`

**Decision**: All writes go through `parse_blocks(get_post()->post_content)` → mutate → `serialize_blocks($blocks)` → `wp_update_post([post_content])`. No custom serialization.

**Rationale**:
- SC-004 targets 95% byte-identical round-trip; core `parse_blocks`/`serialize_blocks` achieves this except for known parser-normalisation edge cases (whitespace inside HTML comments, empty inner content collapsing) — all outside our control.
- Using core functions means we inherit their bug fixes and format changes for free.

**Alternatives considered**:
- **Custom serializer preserving exact byte layout**: overkill for the target; would fork WordPress core behaviour we don't own.

**Impact on tests**: One integration test creates a post from a canonical fixture, reads via `Get_Post_Blocks`, writes back with `Update_Post_Block` supplying identical inputs, and asserts `post_content` is unchanged.

---

## R7. Backward compatibility of `Update_Post_Block`

**Decision**: Add an optional `path` input to the ability's input schema. In `execute()`, resolve target block in this priority order:

1. If `path` present and non-empty → use `Block_Tree::replace_at_path`.
2. Else if `block_index` present → use existing top-level index logic (unchanged).
3. Else if `block_name` + optional `occurrence` present → use existing name-occurrence logic (unchanged).
4. Else → validation error identical to today's.

**Rationale**:
- Any existing consumer passing only `block_index` or `block_name` sees zero behaviour change (path is null → branch 2 or 3 runs).
- The `path` input is optional, so consumers don't need to know it exists.
- Extraction of the top-level index / name-occurrence resolution into `Block_Tree::resolve_top_level_index()` is optional — it can stay inline in `Update_Post_Block` if extraction adds no new caller.

**Alternatives considered**:
- **Deprecate `block_index` / `block_name` inputs**: would break SC-003. Rejected.
- **New ability `update-block-at-path` instead of extending `update-post-block`**: proliferates near-duplicate abilities. Rejected.

**Impact on tests**: New test file `Test_Update_Post_Block_Nested.php` covers the `path` branch; existing `Test_Update_Post_Block.php` (if present) continues to cover the legacy branches.

---

## R8. Post-type whitelist

**Decision**: Reuse the exact whitelist logic from `Update_Post_Block::execute` lines 127-135. Extract into `Block_Tree::assert_post_type_editable(int $post_id): true|WP_Error` so all seven abilities share one implementation.

**Rationale**:
- Constitution §VI mandates extraction on second use — this is the seventh use.
- Single source of truth for the internal-CPT reject list means adding a new internal CPT to the deny list touches one place, not seven.

**Alternatives considered**:
- **Inline the check in each ability**: 7× duplication. Rejected outright.

**Impact on `Block_Tree`**: One additional method. No new dependencies.

---

## R9. Capability model uniformity

**Decision**: Every write ability performs the same three checks in this order:

1. Global: `current_user_can('manage_options')` AND `current_user_can('edit_posts')` (matches existing `Update_Post_Block::execute`).
2. Post-type gate: `Block_Tree::assert_post_type_editable($post_id)`.
3. Per-post: `current_user_can('edit_post', $post_id)`.

The read ability (`Get_Post_Blocks`) performs 1 + 2, plus `current_user_can('read_post', $post_id)` in place of `edit_post`.

**Rationale**:
- FR-018 requires parity with existing `Update_Post_Block` for writes.
- The read gate prevents leaking private-post content to callers who can `manage_options` at site level but shouldn't see all posts.

**Alternatives considered**:
- **Skip per-post cap and rely on global**: violates least privilege.

**Impact on tests**: Each ability's test file asserts all three checks are present via source inspection (matching existing convention in `Test_Add_Post_Meta.php`).

---

## R10. Response envelope shape

**Decision**: Every ability returns:

```json
{
  "success": true,
  "post_id": 123,
  "<payload_key>": ...,
  "message": "Human-readable summary"
}
```

For `Get_Post_Blocks`: payload key is `blocks`, value is the annotated tree.
For every write ability: payload keys include `block` (the affected block with its new path) and, where relevant, `previous_path` (e.g. for `move-block`).

**Rationale**:
- Matches existing convention in `List_Blocks` (`{ success, blocks, total, filters, message }`) and `Update_Post_Block` (`{ success, post_id, block, message }`).
- `success: false` responses omit the payload keys and include `message` + an optional `error_code` field for machine-readable error routing.

**Alternatives considered**:
- **Throw exceptions on failure**: violates existing convention. Rejected.
- **Envelope with `data` wrapper**: adds one level of nesting without new information. Rejected.

**Impact on contracts**: `contracts/abilities.md` documents this envelope once and each ability's schema references it.

---

## R11. Deep-clone strategy for `duplicate-block`

**Decision**: Use PHP's native array-copy semantics. Since PHP arrays are copy-on-write value types (not references), `$clone = $original` on a nested `parse_blocks` structure gives a deep copy without special handling. Regenerate any `clientId` fields if present (the block parser does not persist `clientId`, so typically none exist in the parsed tree).

**Rationale**:
- `parse_blocks()` returns arrays of arrays (with no object references) — PHP's assignment operator produces an independent copy.
- No third-party deep-clone library needed.

**Alternatives considered**:
- **`json_decode(json_encode($block))`**: works but ~10× slower and loses `NAN`/`INF` if they appear. Rejected — plain assignment is fine.

**Impact on `Block_Tree`**: `insert_at_path` accepts the clone by value; no reference concerns.

---

## Summary of resolved unknowns

| # | Unknown | Resolution |
|---|---|---|
| R1 | Path scheme | `int[]` (ordered array of non-negative integers) |
| R2 | Move atomicity | In-memory `remove-then-insert` + single `wp_update_post` |
| R3 | Move-to-descendant guard | Prefix-match check on `parent_path` vs `source path` |
| R4 | Attribute validation | Registry lookup; soft-fail on unregistered blocks |
| R5 | Pattern source reuse | Delegate to existing `Read_Block_Pattern` helper; extract if needed |
| R6 | Round-trip fidelity | Core `parse_blocks` + `serialize_blocks` — no custom serializer |
| R7 | Backward compat | Optional `path` input; falls through to existing index/name logic |
| R8 | Post-type whitelist | Extract from `Update_Post_Block:127-135` into `Block_Tree` |
| R9 | Capability model | Global + post-type gate + per-post cap (edit or read) |
| R10 | Response envelope | `{ success, post_id, <payload>, message }` — matches existing |
| R11 | Deep clone | PHP native value-copy semantics on parsed arrays |

**All NEEDS CLARIFICATION resolved. Ready for Phase 1 design.**
